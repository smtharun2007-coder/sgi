<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Save / publish results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $test_id = trim($_POST['test_id'] ?? '');
    $publish = isset($_POST['publish']);
    $results = $_POST['results'] ?? [];

    $test = $leap_tests->findOne(['_id' => new MongoDB\BSON\ObjectId($test_id), 'mentor_id' => $m['mentor_id']]);
    if ($test) {
        foreach ($results as $roll => $data) {
            $marks   = isset($data['marks']) && $data['marks'] !== '' ? (int)$data['marks'] : null;
            $remarks = trim($data['remarks'] ?? '');
            if ($marks === null) continue;

            $status = $publish ? 'PUBLISHED' : 'DRAFT';
            $leap_test_results->updateOne(
                ['test_id' => $test_id, 'student_id' => $roll],
                ['$set' => [
                    'test_id'        => $test_id,
                    'student_id'     => $roll,
                    'marks_obtained' => $marks,
                    'maximum_marks'  => (int)$test['maximum_marks'],
                    'remarks'        => $remarks,
                    'published_by'   => $m['mentor_id'],
                    'published_at'   => $publish ? new MongoDB\BSON\UTCDateTime() : null,
                    'status'         => $status,
                ]],
                ['upsert' => true]
            );
            if ($publish) {
                leapNotifyStudent($roll,
                    "🏆 Your LEAP result for \"{$test['title']}\" has been published. Score: {$marks}/{$test['maximum_marks']}",
                    'leap_results.php'
                );
            }
        }
        if ($publish) {
            $leap_tests->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($test_id)],
                ['$set' => ['status' => 'PUBLISHED']]
            );
        }
        header('Location: mentor_leap_results.php?test=' . $test_id . '&saved=1' . ($publish ? '&published=1' : ''));
        exit;
    }
}

$tests = iterator_to_array($leap_tests->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['created_at' => -1]]
));

$activeTestId = $_GET['test'] ?? (count($tests) > 0 ? (string)$tests[0]['_id'] : null);
$activeTest   = null;
$students     = [];
$existingRes  = [];

if ($activeTestId) {
    $activeTest = $leap_tests->findOne(['_id' => new MongoDB\BSON\ObjectId($activeTestId), 'mentor_id' => $m['mentor_id']]);
    if ($activeTest) {
        $mems = iterator_to_array($leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']));
        foreach ($mems as $mem) {
            $st = $users->findOne(['roll' => $mem['student_id']]);
            if ($st) $students[] = $st;
        }
        $resCursor = $leap_test_results->find(['test_id' => $activeTestId]);
        foreach ($resCursor as $r) {
            $existingRes[$r['student_id']] = $r;
        }
    }
}

$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Results (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .test-tab{padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;border:2px solid #e0e0e0;background:#fff;color:#555;cursor:pointer;transition:all .2s;display:block;margin-bottom:6px;text-decoration:none;}
        .test-tab.active{background:linear-gradient(135deg,#1a1a2e,#f39c12);color:#fff;border-color:transparent;}
        .res-row{display:grid;grid-template-columns:1fr 110px 1fr;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #f0f2f5;}
        .res-row:last-child{border-bottom:none;}
    </style>
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">🏆 LEAP Results</h2>

    <?php if (isset($_GET['saved'])): ?>
    <p class="success" style="margin-bottom:16px;">
        <?= isset($_GET['published']) ? '✅ Results published and students notified.' : '✅ Results saved as draft.' ?>
    </p>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:flex-start;">

        <!-- Test selector -->
        <div>
            <div style="font-size:12px;text-transform:uppercase;color:#888;letter-spacing:1px;margin-bottom:8px;">Tests</div>
            <?php if (empty($tests)): ?>
                <p style="font-size:13px;color:#aaa;">No tests yet.</p>
            <?php endif; ?>
            <?php foreach ($tests as $test): ?>
            <a href="mentor_leap_results.php?test=<?= (string)$test['_id'] ?>"
               class="test-tab <?= $activeTestId === (string)$test['_id'] ? 'active' : '' ?>">
                <?= htmlspecialchars($test['title']) ?>
                <span style="font-size:11px;opacity:.7;display:block;"><?= htmlspecialchars($test['date'] ?? '') ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Results entry -->
        <div>
            <?php if ($activeTest): ?>
            <div class="form-box" style="padding:24px;">
                <div style="margin-bottom:16px;">
                    <h3 style="color:#1a1a2e;margin-bottom:4px;"><?= htmlspecialchars($activeTest['title']) ?></h3>
                    <div style="font-size:13px;color:#888;">Max marks: <?= $activeTest['maximum_marks'] ?></div>
                </div>

                <?php if (empty($students)): ?>
                <p class="no-data">No active LEAP students found.</p>
                <?php else: ?>
                <form method="POST" id="resultsForm">
                    <input type="hidden" name="test_id" value="<?= $activeTestId ?>">
                    <div style="display:grid;grid-template-columns:1fr 110px 1fr;gap:10px;padding:10px 0;border-bottom:2px solid #f0f2f5;margin-bottom:4px;">
                        <span style="font-size:12px;color:#888;text-transform:uppercase;font-weight:700;">Student</span>
                        <span style="font-size:12px;color:#888;text-transform:uppercase;font-weight:700;">Marks /<?= $activeTest['maximum_marks'] ?></span>
                        <span style="font-size:12px;color:#888;text-transform:uppercase;font-weight:700;">Remarks</span>
                    </div>
                    <?php foreach ($students as $st):
                        $existing = $existingRes[$st['roll']] ?? null;
                        $isPub    = ($existing['status'] ?? '') === 'PUBLISHED';
                    ?>
                    <div class="res-row">
                        <div>
                            <div style="font-weight:600;color:#1a1a2e;font-size:14px;"><?= htmlspecialchars($st['name']) ?></div>
                            <div style="font-size:12px;color:#888;"><?= htmlspecialchars($st['roll']) ?></div>
                            <?php if ($isPub): ?>
                            <span style="font-size:11px;background:#28a745;color:#fff;padding:2px 8px;border-radius:10px;">Published</span>
                            <?php elseif ($existing): ?>
                            <span style="font-size:11px;background:#6c757d;color:#fff;padding:2px 8px;border-radius:10px;">Draft</span>
                            <?php endif; ?>
                        </div>
                        <input type="number"
                               name="results[<?= htmlspecialchars($st['roll']) ?>][marks]"
                               placeholder="—" min="0" max="<?= $activeTest['maximum_marks'] ?>"
                               value="<?= $existing ? htmlspecialchars($existing['marks_obtained']) : '' ?>"
                               style="margin:0;text-align:center;font-weight:700;font-size:16px;">
                        <input type="text"
                               name="results[<?= htmlspecialchars($st['roll']) ?>][remarks]"
                               placeholder="Remarks (optional)"
                               value="<?= $existing ? htmlspecialchars($existing['remarks'] ?? '') : '' ?>"
                               style="margin:0;font-size:13px;">
                    </div>
                    <?php endforeach; ?>

                    <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                        <button type="submit" name="save_results"
                                class="btn-primary"
                                style="width:auto;padding:10px 28px;background:linear-gradient(135deg,#6c757d,#495057);">
                            Save as Draft
                        </button>
                        <button type="button"
                                onclick="submitPublish()"
                                class="btn-primary"
                                style="width:auto;padding:10px 28px;">
                            Publish Results
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="background:#fff;border-radius:14px;padding:40px;text-align:center;color:#aaa;box-shadow:0 2px 10px rgba(0,0,0,0.07);">
                Select a test to enter results.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php leapMentorNotifJS(); leapFooter(); ?>
<script>
function submitPublish() {
    const form = document.getElementById('resultsForm');
    let inp = form.querySelector('input[name="publish"]');
    if (!inp) {
        inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'publish';
        form.appendChild(inp);
    }
    inp.value = '1';
    form.submit();
}
</script>
</body>
</html>
