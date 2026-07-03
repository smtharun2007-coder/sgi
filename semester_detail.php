<?php
include 'config.php';
requireLogin();

if (empty($_GET['id'])) { header("Location: dashboard.php"); exit; }

$id   = $_GET['id'];
$roll = $_SESSION['user']['roll'];
$u    = $_SESSION['user'];
$unreadCount = $notifications->countDocuments(['roll'=>$u['roll'],'read'=>false]);
$s    = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);
if (!$s) { header("Location: dashboard.php"); exit; }

$subCursor    = $subjects->find(['sem_id' => $id, 'roll' => $roll]);
$subList      = iterator_to_array($subCursor);
$internalSubs = array_filter($subList, fn($sub) => $sub['internal'] === 'yes');

// Fetch pending credit subject approval to show pending changes
$pendingCreditApproval = $approvals->findOne([
    'student_roll' => $roll,
    'semester' => (int)$s['sem'],
    'type' => 'Credit Subjects',
    'status' => 'pending'
]);
$pendingAdditions = $pendingCreditApproval['credit_subjects'] ?? [];
$pendingDeletions = $pendingCreditApproval['credit_deletions'] ?? [];
// Convert BSONArray to PHP array if needed
if ($pendingDeletions instanceof \MongoDB\Model\BSONArray) {
    $pendingDeletions = $pendingDeletions->getArrayCopy();
}
$pendingDeletionIds = array_column($pendingDeletions, 'subject_id');

$cat1Total = 0; $cat1Max = 0;
$cat2Total = 0; $cat2Max = 0;
$cat3Total = 0; $cat3Max = 0;
foreach ($internalSubs as $sub) {
    if ((int)($sub['credits'] ?? 0) === 0) continue;
    if (($sub['cat1'] ?? null) !== 'nil') { $cat1Max += 100; $cat1Total += (float)($sub['cat1'] ?? 0); }
    if (($sub['cat2'] ?? null) !== 'nil') { $cat2Max += 100; $cat2Total += (float)($sub['cat2'] ?? 0); }
    if (($sub['cat3'] ?? null) !== 'nil') { $cat3Max += 100; $cat3Total += (float)($sub['cat3'] ?? 0); }
}

// Check if all 3 CATs are filled
$allCatsFilled = true;
foreach ($internalSubs as $sub) {
    if (!isset($sub['cat1']) || !isset($sub['cat2']) || !isset($sub['cat3'])) {
        $allCatsFilled = false;
        break;
    }
}

// Check if CAT 1 is submitted
$cat1Submitted = false;
foreach ($internalSubs as $sub) {
    if (isset($sub['cat1'])) { $cat1Submitted = true; break; }
}

$creditsDone = !empty($s['credits_done']);
$verified     = !empty($s['verified']);
$sgiDone      = !empty($s['sgi']);

// Handle document upload for completed semesters
$docError = '';
if ($sgiDone && isset($_POST['upload_docs'])) {
    $updateDocs = [];
    if (!empty($_FILES['result_photo']['name'])) {
        if ($_FILES['result_photo']['size'] > 5 * 1024 * 1024) { $docError = "Semester result must be = 5 MB."; }
        else { $updateDocs['result_photo'] = uploadToCloudinary($_FILES['result_photo']['tmp_name'], 'sgi/results', 'image'); }
    }
    if (!$docError && !empty($_FILES['ca_photo']['name'])) {
        if ($_FILES['ca_photo']['size'] > 5 * 1024 * 1024) { $docError = "CA mark sheet photo must be = 5 MB."; }
        else { $updateDocs['ca_photo'] = uploadToCloudinary($_FILES['ca_photo']['tmp_name'], 'sgi/ca_marks'); }
    }
    if (!$docError && !empty($updateDocs)) {
        $semesters->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => $updateDocs]);
        $s = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);
    }
}

function grade($sgi) {
    if ($sgi >= 9) return 'O (Excellent)';
    if ($sgi >= 8) return 'A (Very Good)';
    if ($sgi >= 7) return 'B (Good)';
    if ($sgi >= 6) return 'C (Average)';
    return 'D (Needs Improvement)';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Semester <?= $s['sem'] ?> Details</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" alt="SGI Logo" class="nav-logo"> SGI
    </a>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="update_profile.php">Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <div class="notif-bell-wrap">
            <button class="notif-bell-btn" onclick="toggleNotif()" id="bellBtn">
                &#128276;<?php if($unreadCount>0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDrop">
                <div class="notif-dropdown-header">Notifications <span style="display:flex;gap:10px;"><a href="#" onclick="markAll(event)">Mark read</a><a href="#" onclick="clearAll(event)">Clear all</a></span></div>
                <div class="notif-list-scroll" id="notifList"><div class="notif-empty">Loading&hellip;</div></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="form-box" style="padding-bottom:80px;max-width:1200px;">
        <h2>Semester <?= $s['sem'] ?> – Details</h2>
        <hr style="margin:16px 0;">

        <h3>Subjects <?php if (!$cat1Submitted): ?><a href="edit_subjects.php?sem_id=<?= $id ?>" class="btn-calc" style="font-size:12px;padding:5px 12px;margin-left:10px;">✏️ Edit Subjects</a><?php endif; ?></h3>
        
        <?php if (!empty($pendingCreditApproval)): ?>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
            <strong style="color:#856404;">⏳ Pending Approval:</strong> 
            <span style="color:#856404;">Credit subject changes are pending mentor approval.</span>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($subList) || !empty($pendingAdditions)): ?>
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Credits</th>
                        <th>Internal</th>
                        <th>CAT 1</th>
                        <th>CAT 2</th>
                        <th>CAT 3</th>
                        <th>Total</th>
                        <th>%</th>
                        <?php if (!empty($pendingCreditApproval)): ?><th>Status</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subList as $sub): 
                        $isPendingDeletion = in_array((string)$sub['_id'], $pendingDeletionIds);
                    ?>
                    <tr style="<?= $isPendingDeletion ? 'background:#fff3f3;text-decoration:line-through;opacity:0.7;' : '' ?>">
                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                        <td><?= $sub['credits'] ?></td>
                        <td><?= ucfirst($sub['internal']) ?></td>
                        <?php if ($sub['internal'] === 'yes'): ?>
                            <td><?= ($sub['cat1'] ?? null) === 'nil' ? '<span style="color:#aaa;">NIL</span>' : ($sub['cat1'] ?? '—') ?></td>
                            <td><?= ($sub['cat2'] ?? null) === 'nil' ? '<span style="color:#aaa;">NIL</span>' : ($sub['cat2'] ?? '—') ?></td>
                            <td><?= ($sub['cat3'] ?? null) === 'nil' ? '<span style="color:#aaa;">NIL</span>' : ($sub['cat3'] ?? '—') ?></td>
                            <td><?= $sub['total'] ?? '—' ?></td>
                            <td><?= !empty($sub['percentage']) ? $sub['percentage'].'%' : '—' ?></td>
                        <?php else: ?>
                            <td colspan="5" style="text-align:center;color:#aaa;">No Internal</td>
                        <?php endif; ?>
                        <?php if (!empty($pendingCreditApproval)): ?>
                            <td style="text-align:center;">
                                <?php if ($isPendingDeletion): ?>
                                    <span style="color:#dc3545;font-weight:600;font-size:12px;">⚠ Pending Deletion</span>
                                <?php else: ?>
                                    <span style="color:#28a745;font-size:12px;">✓</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($pendingAdditions)): ?>
                        <?php foreach ($pendingAdditions as $pendingSub): 
                            $name = $pendingSub['subject_name'] ?? $pendingSub['name'] ?? '';
                            $code = $pendingSub['subject_code'] ?? $pendingSub['code'] ?? '';
                            $credits = $pendingSub['credits'] ?? 0;
                        ?>
                        <tr style="background:#f3fff3;">
                            <td><?= htmlspecialchars($name) ?> <span style="color:#28a745;font-size:11px;">(New)</span></td>
                            <td><?= htmlspecialchars($code) ?></td>
                            <td><?= $credits ?></td>
                            <td>No</td>
                            <td colspan="5" style="text-align:center;color:#aaa;">Pending Approval</td>
                            <?php if (!empty($pendingCreditApproval)): ?>
                                <td style="text-align:center;">
                                    <span style="color:#ffc107;font-weight:600;font-size:12px;">⏳ Pending</span>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($cat1Max > 0 || $cat2Max > 0 || $cat3Max > 0): ?>
        <div class="cat-summary-row" style="margin-top:16px;">
            <div class="cat-summary-item">
                <span class="cat-summary-label">CAT 1 Total</span>
                <span class="cat-summary-value"><?= $cat1Total ?> / <?= $cat1Max ?></span>
                <span class="cat-summary-percent"><?= $cat1Max > 0 ? round(($cat1Total/$cat1Max)*100,2) : 0 ?>%</span>
            </div>
            <div class="cat-summary-item">
                <span class="cat-summary-label">CAT 2 Total</span>
                <span class="cat-summary-value"><?= $cat2Total ?> / <?= $cat2Max ?></span>
                <span class="cat-summary-percent"><?= $cat2Max > 0 ? round(($cat2Total/$cat2Max)*100,2) : 0 ?>%</span>
            </div>
            <div class="cat-summary-item">
                <span class="cat-summary-label">CAT 3 Total</span>
                <span class="cat-summary-value"><?= $cat3Total ?> / <?= $cat3Max ?></span>
                <span class="cat-summary-percent"><?= $cat3Max > 0 ? round(($cat3Total/$cat3Max)*100,2) : 0 ?>%</span>
            </div>
        </div>
        <hr style="margin:24px 0;">

        <!-- LINE AREA CHART -->
        <div class="chart-box" style="margin-top:24px;">
            <h3>Subject CAT Marks</h3>
            <canvas id="catLineChart" style="max-height:500px;"></canvas>
        </div>
        <hr style="margin:24px 0;">

        <!-- PIE CHARTS -->
        <div class="cat-pie-row">
            <div class="chart-box">
                <h3>CAT 1</h3>
                <canvas id="cat1Pie"></canvas>
            </div>
            <div class="chart-box">
                <h3>CAT 2</h3>
                <canvas id="cat2Pie"></canvas>
            </div>
            <div class="chart-box">
                <h3>CAT 3</h3>
                <canvas id="cat3Pie"></canvas>
            </div>
        </div>
        <div id="pie-legend" class="pie-legend-shared"></div>
        <?php endif; ?>
        <?php else: ?>
            <p class="no-data">No subjects added for this semester.</p>
        <?php endif; ?>

        <hr style="margin:24px 0;">

        <!-- GPA / CGPA / ATTENDANCE -->
        <?php if (!empty($s['gpa']) || !empty($s['cgpa']) || !empty($s['attendance'])): ?>
        <h3>Academic Details</h3>
        <div class="detail-grid">
            <div class="detail-item"><label>Previous GPA</label><span><?= $s['prev_gpa'] ?? '—' ?></span></div>
            <div class="detail-item"><label>GPA</label><span><?= $s['gpa'] ?? '—' ?></span></div>
            <div class="detail-item"><label>CGPA</label><span><?= $s['cgpa'] ?? '—' ?></span></div>
            <div class="detail-item"><label>Attendance</label><span><?= $s['attendance'] ?? '—' ?>%</span></div>
        </div>
        <?php
        $gpa      = (float)($s['gpa'] ?? 0);
        $prev_gpa = (float)($s['prev_gpa'] ?? 0);
        if ($gpa > $prev_gpa)      { $gpaStatus = 'Improved'; $gpaColor = 'linear-gradient(135deg,#27ae60,#2ecc71)'; $gpaIcon = '📈'; }
        elseif ($gpa == $prev_gpa) { $gpaStatus = 'No Change'; $gpaColor = 'linear-gradient(135deg,#f5a623,#e67e22)'; $gpaIcon = '➡️'; }
        else                       { $gpaStatus = 'Decreased'; $gpaColor = 'linear-gradient(135deg,#e94560,#c0392b)'; $gpaIcon = '📉'; }
        ?>
        <div class="attendance-card" style="background:<?= $gpaColor ?>;margin-top:16px;">
            <div class="attendance-card-left">
                <span class="attendance-card-label">GPA Progress</span>
                <span class="attendance-card-value"><?= $gpaIcon ?> <?= $gpa ?></span>
                <span style="font-size:13px;color:rgba(255,255,255,0.8);margin-top:4px;">Previous: <?= $prev_gpa ?></span>
            </div>
            <div class="attendance-card-right">
                <span class="attendance-card-status"><?= $gpaStatus ?></span>
            </div>
        </div>
        <?php endif; ?>
        <hr style="margin:24px 0;">

        <!-- FINAL CA MARKS -->
        <?php
        $hasCa = false;
        foreach ($subList as $sub) { if (isset($sub['ca_scored'])) { $hasCa = true; break; } }
        ?>
        <?php if ($hasCa): ?>
        <h3>Final CA Marks</h3>
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Credits</th>
                        <th>Internal</th>
                        <th>Scored</th>
                        <th>Max</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subList as $sub): ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                        <td><?= $sub['credits'] ?></td>
                        <td><?= ucfirst($sub['internal']) ?></td>
                        <td><?= $sub['ca_scored'] ?? '—' ?></td>
                        <td><?= $sub['ca_max'] ?? '—' ?></td>
                        <td><?= !empty($sub['ca_percent']) ? $sub['ca_percent'].'%' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <hr style="margin:24px 0;">
        <?php endif; ?>

        <!-- SGI SCORES -->
        <?php if ($sgiDone): ?>
        <h3 style="margin-top:20px;">SGI Scores</h3>
        <div class="detail-grid">
            <div class="detail-item"><label>Academic Score</label><span><?= round($s['academic_score'], 2) ?></span></div>
            <div class="detail-item"><label>Skills Score</label><span><?= round($s['skills_score'], 2) ?></span></div>
            <div class="detail-item"><label>Projects Score</label><span><?= round($s['projects_score'], 2) ?></span></div>
            <div class="detail-item"><label>Activities Score</label><span><?= round($s['activities_score'], 2) ?></span></div>
            <div class="detail-item"><label>Discipline Score</label><span><?= round($s['discipline_score'], 2) ?></span></div>
        </div>
        <?php if (!empty($s['attendance'])): ?>
        <div class="attendance-card" style="background:<?= ($s['attendance'] >= 80) ? 'linear-gradient(135deg,#27ae60,#2ecc71)' : 'linear-gradient(135deg,#e94560,#c0392b)' ?>;margin-top:16px;">
            <div class="attendance-card-left">
                <span class="attendance-card-label">Attendance</span>
                <span class="attendance-card-value"><?= $s['attendance'] ?>%</span>
            </div>
            <div class="attendance-card-right">
                <span class="attendance-card-status"><?= ($s['attendance'] >= 80) ? '✅ Good Standing' : '⚠️ Below Required' ?></span>
            </div>
        </div>
        <?php endif; ?>
        <hr style="margin:24px 0;">
        <div class="sgi-card">
            <div class="sgi-card-left">
                <span class="sgi-card-label">Student Growth Index</span>
                <span class="sgi-card-value"><?= round($s['sgi'], 2) ?></span>
                <span class="sgi-card-max">out of 10</span>
            </div>
            <div class="sgi-card-right">
                <span class="sgi-card-grade"><?= grade($s['sgi']) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <hr style="margin:24px 0;">

        <!-- UPLOADED DOCUMENTS -->
        <?php if ($sgiDone): ?>
        <h3>Documents</h3>
        <?php if (!empty($docError)): ?><p class="error"><?= $docError ?></p><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" style="margin-top:12px;">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <!-- Semester Result -->
                <div>
                    <?php if (!empty($s['result_photo'])): ?>
                        <a href="#" onclick="showDoc('<?= htmlspecialchars(imgUrl($s['result_photo'])) ?>','Semester Result');return false;" class="btn-calc" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;font-size:14px;">📄 View Semester Result</a>
                    <?php else: ?>
                        <label>Upload Semester Result</label>
                        <input type="file" name="result_photo" accept="image/*">
                    <?php endif; ?>
                </div>
                <!-- CA Mark Sheet -->
                <div>
                    <?php if (!empty($s['ca_photo'])): ?>
                        <a href="#" onclick="showDoc('<?= htmlspecialchars(imgUrl($s['ca_photo'])) ?>','CA Mark Sheet');return false;" class="btn-calc" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;font-size:14px;">📋 View CA Mark Sheet</a>
                    <?php else: ?>
                        <label>Upload CA Mark Sheet Photo</label>
                        <input type="file" name="ca_photo" accept="image/*">
                    <?php endif; ?>
                </div>
            </div>
            <?php if (empty($s['result_photo']) || empty($s['ca_photo'])): ?>
            <button type="submit" name="upload_docs" class="btn-primary" style="margin-top:16px;">Save Documents</button>
            <?php endif; ?>
        </form>
        <hr style="margin:24px 0;">
        <?php endif; ?>

        <!-- APPROVAL HISTORY SECTION -->
        <hr style="margin:24px 0;">
        <h3>📋 Approval History</h3>
        <div id="approvalHistory">
            <p style="color:#888;text-align:center;padding:20px;">Loading approval history...</p>
        </div>

        <div style="text-align:center;margin-top:30px;">
            <a href="academics.php" class="btn-home">&#8592; Back</a>
            <?php if ($sgiDone): ?>
            <a href="print_report.php?id=<?= $id ?>" class="btn-print" target="_blank">🖨️ Print Report</a>
            <?php else: ?>
            <span class="btn-print btn-disabled">🖨️ Print Report</span>
            <?php endif; ?>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if (!empty($internalSubs)): ?>
<script>
const subLabels = [<?= implode(',', array_map(fn($s) => '"'.addslashes($s['subject_name']).'"', $internalSubs)) ?>];
const cat1Data  = [<?= implode(',', array_map(fn($s) => ($s['cat1'] ?? null) === 'nil' ? 'null' : ($s['cat1'] ?? 0), $internalSubs)) ?>];
const cat2Data  = [<?= implode(',', array_map(fn($s) => ($s['cat2'] ?? null) === 'nil' ? 'null' : ($s['cat2'] ?? 0), $internalSubs)) ?>];
const cat3Data  = [<?= implode(',', array_map(fn($s) => ($s['cat3'] ?? null) === 'nil' ? 'null' : ($s['cat3'] ?? 0), $internalSubs)) ?>];

const colors = ['#e94560','#1a1a2e','#f5a623','#27ae60','#8e44ad','#2980b9','#e67e22','#16a085'];

// LINE AREA CHART
new Chart(document.getElementById('catLineChart'), {
    type: 'line',
    data: {
        labels: subLabels,
        datasets: [
            { label: 'CAT 1', data: cat1Data, borderColor: '#e94560', backgroundColor: 'rgba(233,69,96,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#e94560', pointRadius: 6, pointHoverRadius: 8, spanGaps: true },
            { label: 'CAT 2', data: cat2Data, borderColor: '#1a1a2e', backgroundColor: 'rgba(26,26,46,0.1)',  fill: true, tension: 0.4, pointBackgroundColor: '#1a1a2e', pointRadius: 6, pointHoverRadius: 8, spanGaps: true },
            { label: 'CAT 3', data: cat3Data, borderColor: '#f5a623', backgroundColor: 'rgba(245,166,35,0.1)',fill: true, tension: 0.4, pointBackgroundColor: '#f5a623', pointRadius: 6, pointHoverRadius: 8, spanGaps: true }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { font: { size: 13 } } } },
        scales: { y: { min: 0, max: 100, ticks: { stepSize: 10 } } }
    }
});

// PIE CHARTS
const pieColors = colors.slice(0, subLabels.length);
[['cat1Pie', cat1Data], ['cat2Pie', cat2Data], ['cat3Pie', cat3Data]].forEach(([id, data]) => {
    new Chart(document.getElementById(id), {
        type: 'pie',
        data: {
            labels: subLabels,
            datasets: [{ data: data, backgroundColor: pieColors, borderWidth: 2 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            layout: { padding: { left: 10, right: 10, top: 10, bottom: 10 } },
            plugins: { legend: { display: false } }
        }
    });
});

// SHARED LEGEND
const legendDiv = document.getElementById('pie-legend');
subLabels.forEach((label, i) => {
    legendDiv.innerHTML += `<span class="pie-legend-item"><span class="pie-legend-color" style="background:${pieColors[i]}"></span>${label}</span>`;
});
</script>
<?php endif; ?>

<?php if (!$sgiDone): ?>
<div class="cat-fixed-bar">
    <?php if (!$verified && !$creditsDone): ?>
        <a href="cat1_marks.php?sem_id=<?= $id ?>" class="btn-calc">CAT 1 Marks</a>
        <a href="cat2_marks.php?sem_id=<?= $id ?>" class="btn-calc">CAT 2 Marks</a>
        <a href="cat3_marks.php?sem_id=<?= $id ?>" class="btn-calc">CAT 3 Marks</a>
    <?php elseif ($creditsDone && !$verified): ?>
        <span class="btn-calc btn-disabled">🔒 CAT 1</span>
        <span class="btn-calc btn-disabled">🔒 CAT 2</span>
        <span class="btn-calc btn-disabled">🔒 CAT 3</span>
    <?php else: ?>
        <span class="btn-calc btn-disabled">CAT 1 Marks</span>
        <span class="btn-calc btn-disabled">CAT 2 Marks</span>
        <span class="btn-calc btn-disabled">CAT 3 Marks</span>
    <?php endif; ?>

    <?php if ($allCatsFilled && !$creditsDone): ?>
        <a href="credit_subjects.php?sem_id=<?= $id ?>" class="btn-verify">Credit Subjects</a>
    <?php elseif (!$allCatsFilled): ?>
        <span class="btn-verify btn-disabled">Credit Subjects</span>
    <?php else: ?>
        <span class="btn-verify btn-disabled">Credits Done ✅</span>
    <?php endif; ?>

    <?php if ($creditsDone && !$verified): ?>
        <a href="verify_marks.php?sem_id=<?= $id ?>" class="btn-verify" style="background:#27ae60 !important;">Verify & Confirm</a>
    <?php elseif (!$creditsDone): ?>
        <span class="btn-verify btn-disabled" style="background:#27ae60 !important;">Verify & Confirm</span>
    <?php else: ?>
        <span class="btn-verify btn-disabled" style="background:#27ae60 !important;">Verified ✅</span>
    <?php endif; ?>

    <?php if ($verified): ?>
        <?php if (empty($s['ca_done'])): ?>
            <a href="final_ca_marks.php?sem_id=<?= $id ?>" class="btn-verify" style="background:#2980b9;">Final CA Marks</a>
            <span class="btn-verify btn-disabled">Calculate SGI</span>
        <?php else: ?>
            <span class="btn-verify btn-disabled" style="background:#2980b9;">CA Done ✅</span>
            <a href="calculate_sgi.php?id=<?= $id ?>" class="btn-verify">Calculate SGI</a>
        <?php endif; ?>
    <?php else: ?>
        <span class="btn-verify btn-disabled" style="background:#2980b9;">Final CA Marks</span>
        <span class="btn-verify btn-disabled">Calculate SGI</span>
    <?php endif; ?>
</div>
<?php endif; ?>
<!-- Document viewer modal -->
<div id="docModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:2001;flex-direction:column;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)closeDocModal()">
    <div style="position:absolute;top:15px;right:20px;z-index:2002;">
        <button onclick="closeDocModal()" style="background:none;border:none;color:#fff;font-size:32px;cursor:pointer;padding:0 10px;">&times;</button>
    </div>
    <div id="docTitle" style="color:#fff;font-size:16px;margin-bottom:15px;font-weight:600;text-align:center;"></div>
    <div style="max-width:90vw;max-height:80vh;display:flex;align-items:center;justify-content:center;">
        <iframe id="docViewer" src="" style="max-width:90vw;max-height:80vh;border:1px solid #444;border-radius:8px;background:#fff;"></iframe>
    </div>
    <div style="margin-top:15px;">
        <a id="docDownloadLink" href="" target="_blank" rel="noopener" style="color:#fff;text-decoration:underline;font-size:14px;">Open in new tab / Download</a>
    </div>
</div>
<script>
function showDoc(url, title) {
    document.getElementById('docTitle').textContent = title;
    document.getElementById('docViewer').src = url;
    document.getElementById('docDownloadLink').href = url;
    document.getElementById('docModal').style.display = 'flex';
}
function closeDocModal() {
    document.getElementById('docModal').style.display = 'none';
    document.getElementById('docViewer').src = '';
}
function toggleNotif() {
    const drop = document.getElementById('notifDrop');
    drop.classList.toggle('open');
    if (drop.classList.contains('open')) loadNotifs();
}
function loadNotifs() {
    fetch('notifications.php?fetch=1')
        .then(r=>r.json()).then(data=>{
            const list = document.getElementById('notifList');
            if (!data.length) { list.innerHTML='<div class="notif-empty">No notifications</div>'; return; }
            list.innerHTML = data.map(n=>`<div class="notif-item ${n.read?'':'unread'}"><div>${n.message}</div><div class="notif-time">${n.time}</div></div>`).join('');
        });
}
function markAll(e) {
    e.preventDefault();
    fetch('notifications.php?mark_all=1');
    document.querySelectorAll('.notif-item.unread').forEach(el=>el.classList.remove('unread'));
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
function clearAll(e) {
    e.preventDefault();
    fetch('notifications.php?delete_all=1');
    document.getElementById('notifList').innerHTML='<div class="notif-empty">No notifications</div>';
    const badge = document.querySelector('.notif-badge');
    if(badge) badge.remove();
}
document.addEventListener('click', e => {
    const btn = document.getElementById('bellBtn');
    const drop = document.getElementById('notifDrop');
    if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target))
        drop.classList.remove('open');
});

// Approval History Functions
const semesterId = '<?= $id ?>';
const semesterNum = <?= $s['sem'] ?>;

// Load approval history on page load
document.addEventListener('DOMContentLoaded', function() {
    loadApprovalHistory();
});

function loadApprovalHistory() {
    fetch('approvals.php?fetch=1')
        .then(r => r.json())
        .then(data => {
            const approvals = data.filter(a => a.semester == semesterNum);
            renderApprovalHistory(approvals);
        });
}

function renderApprovalHistory(approvals) {
    const container = document.getElementById('approvalHistory');
    
    if (approvals.length === 0) {
        container.innerHTML = '<p style="color:#888;text-align:center;padding:20px;">No approval history found for this semester.</p>';
        return;
    }
    
    container.innerHTML = approvals.map(a => {
        const requestName = a.type || 'Semester Registration Request';
        return `
        <div class="approval-history-item" onclick="showApprovalPopup(${JSON.stringify(a).replace(/"/g, '"')})" style="
            background: #fff;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseleave="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.06)';">
            <div>
                <div style="font-size:15px;font-weight:700;color:#1a1a2e;margin-bottom:4px;">📋 ${escapeHtml(requestName)}</div>
                <div style="font-size:12px;color:#888;">📅 Submitted: ${a.created_at}${a.subject_count ? ' · 📚 ' + a.subject_count + ' subjects' : ''}</div>
                ${a.updated_at ? `<div style="font-size:11px;color:#888;margin-top:2px;">⏰ Processed: ${a.updated_at}</div>` : ''}
            </div>
            <span class="status-badge status-${a.status}" style="
                padding: 4px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                background: ${a.status === 'pending' ? '#fff3cd' : a.status === 'approved' ? '#d4edda' : '#f8d7da'};
                color: ${a.status === 'pending' ? '#856404' : a.status === 'approved' ? '#155724' : '#721c24'};
            ">${a.status}</span>
        </div>
    `).join('');
}

function showApprovalPopup(approval) {
    const popup = document.createElement('div');
    popup.id = 'approvalPopup';
    popup.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;display:flex;align-items:center;justify-content:center;';
    popup.onclick = function(e) { if(e.target === this) closeApprovalPopup(); };
    
    const statusColor = approval.status === 'approved' ? '#28a745' : approval.status === 'rejected' ? '#dc3545' : '#ffc107';
    const statusIcon = approval.status === 'approved' ? '✅' : approval.status === 'rejected' ? '❌' : '⏳';
    
    popup.innerHTML = `
        <div style="background:#fff;border-radius:16px;max-width:450px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden;">
            <div style="background:${statusColor};padding:20px 24px;color:#fff;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:18px;">${statusIcon} Approval Details</h3>
                <button onclick="closeApprovalPopup()" style="background:none;border:none;color:#fff;font-size:24px;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding:12px;background:#f8f9fa;border-radius:10px;">
                    <span style="font-weight:600;color:#333;">Status</span>
                    <span style="padding:4px 14px;border-radius:20px;font-size:13px;font-weight:600;text-transform:uppercase;background:${statusColor};color:#fff;">${approval.status}</span>
                </div>
                <div style="margin-bottom:12px;">
                    <span style="font-size:12px;color:#888;text-transform:uppercase;">Request Type</span>
                    <div style="font-size:15px;font-weight:600;color:#1a1a2e;margin-top:2px;">${escapeHtml(approval.type || 'Semester Registration')}</div>
                </div>
                <div style="margin-bottom:12px;">
                    <span style="font-size:12px;color:#888;text-transform:uppercase;">Semester</span>
                    <div style="font-size:15px;font-weight:600;color:#1a1a2e;margin-top:2px;">Semester ${approval.semester}</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <span style="font-size:12px;color:#888;text-transform:uppercase;">Submitted On</span>
                        <div style="font-size:14px;color:#333;margin-top:2px;">${approval.created_at}</div>
                    </div>
                    <div>
                        <span style="font-size:12px;color:#888;text-transform:uppercase;">${approval.updated_at ? 'Processed On' : 'Last Updated'}</span>
                        <div style="font-size:14px;color:#333;margin-top:2px;">${approval.updated_at || '—'}</div>
                    </div>
                </div>
                ${approval.mentor_remarks ? `
                <div style="margin-top:16px;padding:12px;background:${approval.status === 'rejected' ? '#fff3f3' : '#f3fff3'};border-radius:10px;border-left:4px solid ${approval.status === 'rejected' ? '#dc3545' : '#28a745'};">
                    <span style="font-size:12px;color:#888;text-transform:uppercase;">Mentor Remarks</span>
                    <div style="font-size:14px;color:#333;margin-top:4px;">${escapeHtml(approval.mentor_remarks)}</div>
                </div>` : ''}
                ${approval.subjects && approval.subjects.length > 0 ? `
                <div style="margin-top:16px;">
                    <span style="font-size:12px;color:#888;text-transform:uppercase;">Subjects (${approval.subject_count || approval.subjects.length})</span>
                    <div style="margin-top:6px;max-height:120px;overflow-y:auto;">
                        ${approval.subjects.map(s => `
                            <div style="display:flex;justify-content:space-between;padding:6px 10px;background:#f8f9fa;border-radius:6px;margin-bottom:4px;font-size:13px;">
                                <span>${escapeHtml(s.name)} (${escapeHtml(s.code)})</span>
                                <span style="color:#888;">${s.credits} Credits</span>
                            </div>
                        `).join('')}
                    </div>
                </div>` : ''}
            </div>
        </div>
    `;
    
    document.body.appendChild(popup);
    document.body.style.overflow = 'hidden';
}

function closeApprovalPopup() {
    const popup = document.getElementById('approvalPopup');
    if (popup) {
        popup.remove();
        document.body.style.overflow = '';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close popup on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeApprovalPopup();
});
</script>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>


