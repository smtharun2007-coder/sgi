<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';

$u = $_SESSION['user'];

// Check existing application / membership
$membership = $leap_memberships->findOne(['student_id' => $u['roll'], 'status' => 'ACTIVE']);
if ($membership) {
    header('Location: leap.php');
    exit;
}

$application = $leap_applications->findOne(
    ['student_id' => $u['roll']],
    ['sort' => ['submitted_at' => -1]]
);

// ── AJAX: verify mentor ──────────────────────────────────────────────────────
if (isset($_GET['verify_mentor'])) {
    header('Content-Type: application/json');
    $mid = trim($_GET['mentor_id'] ?? '');
    if (!$mid) { echo json_encode(['ok' => false, 'msg' => 'Enter a Mentor ID.']); exit; }
    $mentor = $mentors->findOne(['mentor_id' => $mid]);
    if (!$mentor) { echo json_encode(['ok' => false, 'msg' => 'Mentor ID not found.']); exit; }
    echo json_encode([
        'ok'   => true,
        'name' => htmlspecialchars($mentor['name']),
        'id'   => htmlspecialchars($mentor['mentor_id']),
        'dept' => htmlspecialchars($mentor['dept'] ?? ''),
    ]);
    exit;
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function validateProfileUrl(string $url, string $domain): bool {
    if ($url === '') return true; // optional
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = ltrim($host, 'www.');
    return ($host === $domain || str_ends_with($host, '.' . $domain));
}

// ── POST: submit application ─────────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leap'])) {
    $willing   = $_POST['willing'] ?? '';
    $mentor_id = trim($_POST['mentor_id'] ?? '');
    $leetcode  = trim($_POST['leetcode_profile']  ?? '');
    $hackerrank= trim($_POST['hackerrank_profile'] ?? '');

    // Server-side domain validation
    if ($leetcode   && !validateProfileUrl($leetcode,   'leetcode.com'))   { $error = 'LeetCode URL must be a valid leetcode.com link.'; }
    if (!$error && $hackerrank && !validateProfileUrl($hackerrank, 'hackerrank.com')) { $error = 'HackerRank URL must be a valid hackerrank.com link.'; }

    if (!$error && $willing === 'NO') {
        $leap_applications->insertOne([
            'student_id'       => $u['roll'],
            'name'             => $u['name'],
            'roll_no'          => $u['roll'],
            'integrated_no'    => $u['reg'],
            'batch_no'         => $u['batch_no'] ?? '',
            'department'       => $u['dept'],
            'gmail'            => $u['email'],
            'leetcode_profile' => '',
            'hackerrank_profile' => '',
            'willing'          => false,
            'mentor_id'        => '',
            'mentor_name'      => '',
            'status'           => 'NOT_WILLING',
            'submitted_at'     => new MongoDB\BSON\UTCDateTime(),
            'reviewed_at'      => null,
            'rejection_reason' => '',
        ]);
        header('Location: leap_apply.php?not_willing=1');
        exit;
    }

    if (!$error && $willing === 'YES') {
        if (!$mentor_id) { $error = 'Please enter and verify a Mentor ID.'; }
        else {
            $mentor = $mentors->findOne(['mentor_id' => $mentor_id]);
            if (!$mentor) { $error = 'Invalid Mentor ID. Please verify first.'; }
            else {
                $leap_applications->insertOne([
                    'student_id'         => $u['roll'],
                    'name'               => $u['name'],
                    'roll_no'            => $u['roll'],
                    'integrated_no'      => $u['reg'],
                    'batch_no'           => $u['batch_no'] ?? '',
                    'department'         => $u['dept'],
                    'gmail'              => $u['email'],
                    'leetcode_profile'   => $leetcode,
                    'hackerrank_profile' => $hackerrank,
                    'willing'            => true,
                    'mentor_id'          => $mentor_id,
                    'mentor_name'        => $mentor['name'],
                    'status'             => 'PENDING',
                    'submitted_at'       => new MongoDB\BSON\UTCDateTime(),
                    'reviewed_at'        => null,
                    'rejection_reason'   => '',
                ]);
                // Notify mentor
                leapNotifyMentor($mentor_id,
                    "🎓 New LEAP application from {$u['name']} ({$u['roll']})",
                    'mentor_leap_applications.php'
                );
                header('Location: leap_apply.php?submitted=1');
                exit;
            }
        }
    }
}

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
$status = $application['status'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Application</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        .leap-hero{background:linear-gradient(135deg,#1a1a2e,#f5a623);border-radius:16px;padding:32px;text-align:center;color:#fff;margin-bottom:28px;}
        .leap-hero h1{font-size:32px;font-weight:800;letter-spacing:2px;margin-bottom:6px;}
        .leap-hero p{opacity:.8;font-size:15px;}
        .status-card{border-radius:14px;padding:28px;text-align:center;margin-bottom:24px;}
        .step-label{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;}
        .verified-box{background:#eaffea;border:1px solid #28a745;border-radius:10px;padding:14px 18px;margin-top:10px;display:none;}
    </style>
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">

    <div class="leap-hero">
        <img src="/LEAP.png" alt="LEAP" style="width:72px;height:72px;object-fit:contain;border-radius:14px;margin-bottom:12px;box-shadow:0 4px 16px rgba(0,0,0,0.2);">
        <h1>LEAP</h1>
        <p>The Placement Series — Your gateway to placement readiness</p>
    </div>

    <?php if (isset($_GET['not_willing'])): ?>
    <div class="status-card" style="background:#fff3cd;border:1px solid #f5a623;">
        <div style="font-size:48px;margin-bottom:12px;">🙅</div>
        <h2 style="color:#856404;">Not Enrolled</h2>
        <p style="color:#856404;margin-top:8px;">You chose not to participate in LEAP. You can apply again anytime.</p>
        <a href="leap_apply.php" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:16px;display:inline-block;">Apply Again</a>
        <a href="dashboard.php" class="btn-secondary" style="width:auto;padding:10px 28px;margin-top:8px;display:inline-block;">Back to SGI</a>
    </div>

    <?php elseif (isset($_GET['submitted'])): ?>
    <div class="status-card" style="background:#eaffea;border:1px solid #28a745;">
        <div style="font-size:48px;margin-bottom:12px;">✅</div>
        <h2 style="color:#28a745;">Application Submitted!</h2>
        <p style="color:#555;margin-top:8px;">Your LEAP application has been sent to your mentor for review. You will be notified once it is reviewed.</p>
        <a href="dashboard.php" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:16px;display:inline-block;">Back to SGI</a>
    </div>

    <?php elseif ($status === 'PENDING'): ?>
    <div class="status-card" style="background:#fff;box-shadow:0 4px 14px rgba(0,0,0,0.08);">
        <div style="font-size:48px;margin-bottom:12px;">⏳</div>
        <h2 style="color:#1a1a2e;">Application Pending</h2>
        <p style="color:#666;margin-top:8px;">Your LEAP application is awaiting mentor review. Please check back later.</p>
        <div style="margin-top:16px;background:#f8f9fa;border-radius:10px;padding:14px;display:inline-block;text-align:left;min-width:260px;">
            <div style="font-size:12px;color:#888;">Submitted to</div>
            <div style="font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($application['mentor_name'] ?? '') ?> (<?= htmlspecialchars($application['mentor_id'] ?? '') ?>)</div>
            <div style="font-size:12px;color:#888;margin-top:8px;">Submitted on</div>
            <div style="font-weight:600;color:#555;"><?= date('d M Y, h:i A', $application['submitted_at']->toDateTime()->getTimestamp()) ?></div>
        </div>
        <br><a href="dashboard.php" class="btn-secondary" style="width:auto;padding:10px 28px;margin-top:16px;display:inline-block;">Back to SGI</a>
    </div>

    <?php elseif ($status === 'REJECTED'): ?>
    <div class="status-card" style="background:#fff;box-shadow:0 4px 14px rgba(0,0,0,0.08);">
        <div style="font-size:48px;margin-bottom:12px;">❌</div>
        <h2 style="color:#e94560;">Application Rejected</h2>
        <?php if (!empty($application['rejection_reason'])): ?>
        <p style="color:#666;margin-top:8px;"><strong>Reason:</strong> <?= htmlspecialchars($application['rejection_reason']) ?></p>
        <?php endif; ?>
        <p style="color:#888;margin-top:8px;font-size:13px;">You can apply again with a new application.</p>
        <a href="leap_apply.php?reapply=1" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:16px;display:inline-block;">Apply Again</a>
    </div>

    <?php elseif ($status === 'NOT_WILLING'): ?>
    <div class="status-card" style="background:#fff3cd;border:1px solid #f5a623;">
        <div style="font-size:48px;margin-bottom:12px;">🙅</div>
        <h2 style="color:#856404;">Previously Declined</h2>
        <p style="color:#856404;margin-top:8px;">You previously chose not to participate. You can apply now if you've changed your mind.</p>
        <a href="leap_apply.php?reapply=1" class="btn-primary" style="width:auto;padding:10px 28px;margin-top:16px;display:inline-block;">Apply Now</a>
    </div>

    <?php else: ?>
    <!-- APPLICATION FORM (new or reapply) -->
    <div class="form-box" style="padding:32px;max-width:700px;margin:0 auto;">
        <h2 style="color:#1a1a2e;margin-bottom:6px;">LEAP Application</h2>
        <p style="color:#888;font-size:13px;margin-bottom:24px;">Your SGI details are pre-filled and verified. Review them below.</p>

        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="POST" id="leapForm">
            <!-- Read-only SGI details -->
            <div class="detail-grid" style="grid-template-columns:1fr 1fr;">
                <div class="detail-item">
                    <label>Name</label>
                    <span><?= htmlspecialchars($u['name']) ?></span>
                </div>
                <div class="detail-item">
                    <label>Roll Number</label>
                    <span><?= htmlspecialchars($u['roll']) ?></span>
                </div>
                <div class="detail-item">
                    <label>Integrated No</label>
                    <span><?= htmlspecialchars($u['reg']) ?></span>
                </div>
                <div class="detail-item">
                    <label>Batch No</label>
                    <span><?= htmlspecialchars($u['batch_no'] ?? '—') ?></span>
                </div>
                <div class="detail-item">
                    <label>Department</label>
                    <span><?= htmlspecialchars($u['dept']) ?></span>
                </div>
                <div class="detail-item">
                    <label>Email (Gmail)</label>
                    <span><?= htmlspecialchars($u['email']) ?></span>
                </div>
            </div>

            <!-- Coding Profiles -->
            <div style="margin:20px 0 0;">
                <label style="font-size:15px;font-weight:600;color:#1a1a2e;display:block;margin-bottom:12px;">💻 Coding Profiles <span style="font-size:12px;font-weight:400;color:#aaa;">(optional)</span></label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-size:13px;color:#555;display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <img src="https://leetcode.com/favicon.ico" style="width:14px;height:14px;"> LeetCode Profile
                        </label>
                        <input type="url" name="leetcode_profile" id="leetcodeInput"
                               placeholder="https://leetcode.com/u/yourname/"
                               value="<?= htmlspecialchars($_POST['leetcode_profile'] ?? '') ?>"
                               style="margin:0;">
                        <div id="leetcodeErr" style="color:#e94560;font-size:12px;margin-top:3px;display:none;"></div>
                    </div>
                    <div>
                        <label style="font-size:13px;color:#555;display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <img src="https://www.hackerrank.com/favicon.ico" style="width:14px;height:14px;"> HackerRank Profile
                        </label>
                        <input type="url" name="hackerrank_profile" id="hackerrankInput"
                               placeholder="https://www.hackerrank.com/profile/yourname"
                               value="<?= htmlspecialchars($_POST['hackerrank_profile'] ?? '') ?>"
                               style="margin:0;">
                        <div id="hackerrankErr" style="color:#e94560;font-size:12px;margin-top:3px;display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Willingness -->
            <div style="margin:24px 0 16px;">
                <label style="font-size:15px;font-weight:600;color:#1a1a2e;">Are you willing to participate in LEAP?</label>
                <div style="display:flex;gap:16px;margin-top:12px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:14px 24px;border:2px solid #e0e0e0;border-radius:10px;flex:1;justify-content:center;transition:all .2s;" id="yesLabel">
                        <input type="radio" name="willing" value="YES" id="willingYes" style="width:auto;" onchange="toggleWilling('YES')"> ✅ Yes, I'm willing
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:14px 24px;border:2px solid #e0e0e0;border-radius:10px;flex:1;justify-content:center;transition:all .2s;" id="noLabel">
                        <input type="radio" name="willing" value="NO" id="willingNo" style="width:auto;" onchange="toggleWilling('NO')"> ❌ No, not interested
                    </label>
                </div>
            </div>

            <!-- Mentor section (shown only if YES) -->
            <div id="mentorSection" style="display:none;margin-top:16px;">
                <label style="font-size:14px;font-weight:600;color:#1a1a2e;">Mentor ID</label>
                <div style="display:flex;gap:10px;margin-top:6px;">
                    <input type="text" id="mentorIdInput" name="mentor_id" placeholder="Enter Mentor ID" style="flex:1;margin:0;">
                    <button type="button" onclick="verifyMentor()" style="padding:12px 20px;background:#1a1a2e;color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:600;white-space:nowrap;">Verify</button>
                </div>
                <div class="verified-box" id="verifiedBox">
                    <div style="font-size:12px;color:#28a745;font-weight:700;margin-bottom:6px;">✅ Mentor Verified</div>
                    <div id="verifiedName" style="font-weight:700;color:#1a1a2e;"></div>
                    <div id="verifiedDept" style="font-size:13px;color:#666;"></div>
                </div>
                <div id="mentorError" style="color:#e94560;font-size:13px;margin-top:6px;display:none;"></div>
            </div>

            <!-- No confirmation -->
            <div id="noConfirmSection" style="display:none;margin-top:16px;background:#fff3cd;border:1px solid #f5a623;border-radius:10px;padding:16px;">
                <p style="color:#856404;font-size:14px;">⚠️ Are you sure you don't want to join LEAP? You can always apply again later.</p>
                <label style="display:flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer;font-size:13px;color:#856404;">
                    <input type="checkbox" id="confirmNo" style="width:auto;"> I confirm I do not wish to participate in LEAP at this time.
                </label>
            </div>

            <button type="submit" name="submit_leap" id="submitBtn" class="btn-primary" style="margin-top:24px;display:none;">Submit Application</button>
        </form>
    </div>
    <?php endif; ?>

</div>
<?php leapNotifJS(); leapFooter(); ?>
<script>
let mentorVerified = false;

function toggleWilling(val) {
    const mentorSec = document.getElementById('mentorSection');
    const noSec = document.getElementById('noConfirmSection');
    const submitBtn = document.getElementById('submitBtn');
    const yesLabel = document.getElementById('yesLabel');
    const noLabel = document.getElementById('noLabel');

    yesLabel.style.borderColor = val === 'YES' ? '#28a745' : '#e0e0e0';
    noLabel.style.borderColor  = val === 'NO'  ? '#e94560' : '#e0e0e0';

    if (val === 'YES') {
        mentorSec.style.display = 'block';
        noSec.style.display = 'none';
        submitBtn.style.display = mentorVerified ? 'block' : 'none';
    } else {
        mentorSec.style.display = 'none';
        noSec.style.display = 'block';
        submitBtn.style.display = 'block';
        submitBtn.textContent = 'Confirm — I am not willing';
        submitBtn.style.background = 'linear-gradient(135deg,#e94560,#c73652)';
    }
}

function verifyMentor() {
    const mid = document.getElementById('mentorIdInput').value.trim();
    const errEl = document.getElementById('mentorError');
    const verBox = document.getElementById('verifiedBox');
    errEl.style.display = 'none';
    verBox.style.display = 'none';
    mentorVerified = false;
    document.getElementById('submitBtn').style.display = 'none';

    if (!mid) { errEl.textContent = 'Enter a Mentor ID first.'; errEl.style.display = 'block'; return; }

    fetch('leap_apply.php?verify_mentor=1&mentor_id=' + encodeURIComponent(mid))
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                errEl.textContent = data.msg;
                errEl.style.display = 'block';
            } else {
                document.getElementById('verifiedName').textContent = data.name + ' (' + data.id + ')';
                document.getElementById('verifiedDept').textContent = data.dept;
                verBox.style.display = 'block';
                mentorVerified = true;
                document.getElementById('submitBtn').style.display = 'block';
                document.getElementById('submitBtn').textContent = 'Send LEAP Application →';
                document.getElementById('submitBtn').style.background = '';
            }
        });
}

document.getElementById('confirmNo')?.addEventListener('change', function() {
    document.getElementById('submitBtn').disabled = !this.checked;
});

// Client-side URL domain validation
function checkDomain(inputId, errId, domain) {
    const val = document.getElementById(inputId).value.trim();
    const err = document.getElementById(errId);
    if (!val) { err.style.display = 'none'; return true; }
    try {
        const host = new URL(val).hostname.replace(/^www\./, '');
        if (host !== domain && !host.endsWith('.' + domain)) {
            err.textContent = 'URL must be from ' + domain;
            err.style.display = 'block';
            return false;
        }
    } catch(e) {
        err.textContent = 'Enter a valid URL.';
        err.style.display = 'block';
        return false;
    }
    err.style.display = 'none';
    return true;
}
document.getElementById('leetcodeInput')?.addEventListener('blur', () => checkDomain('leetcodeInput','leetcodeErr','leetcode.com'));
document.getElementById('hackerrankInput')?.addEventListener('blur', () => checkDomain('hackerrankInput','hackerrankErr','hackerrank.com'));

document.getElementById('leapForm')?.addEventListener('submit', function(e) {
    const ok1 = checkDomain('leetcodeInput','leetcodeErr','leetcode.com');
    const ok2 = checkDomain('hackerrankInput','hackerrankErr','hackerrank.com');
    if (!ok1 || !ok2) e.preventDefault();
});
</script>
</body>
</html>
