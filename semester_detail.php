<?php
include 'config.php';
requireLogin();

if (empty($_GET['id'])) { header("Location: dashboard.php"); exit; }

$id   = $_GET['id'];
$roll = $_SESSION['user']['roll'];
$s    = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);
if (!$s) { header("Location: dashboard.php"); exit; }

$subCursor    = $subjects->find(['sem_id' => $id, 'roll' => $roll]);
$subList      = iterator_to_array($subCursor);
$internalSubs = array_filter($subList, fn($sub) => $sub['internal'] === 'yes');

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

$verified  = !empty($s['verified']);
$sgiDone   = !empty($s['sgi']);

// Handle document upload for completed semesters
$docError = '';
if ($sgiDone && isset($_POST['upload_docs'])) {
    $updateDocs = [];
    if (!empty($_FILES['result_photo']['name'])) {
        if ($_FILES['result_photo']['size'] > 5 * 1024 * 1024) { $docError = "Semester result must be ≤ 5 MB."; }
        else { $updateDocs['result_photo'] = uploadToCloudinary($_FILES['result_photo']['tmp_name'], 'sgi/results', 'image'); }
    }
    if (!$docError && !empty($_FILES['ca_photo']['name'])) {
        if ($_FILES['ca_photo']['size'] > 5 * 1024 * 1024) { $docError = "CA mark sheet photo must be ≤ 5 MB."; }
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
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">SGI</a>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="update_profile.php">Update Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="form-box" style="padding-bottom:80px;max-width:1200px;">
        <h2>Semester <?= $s['sem'] ?> – Details</h2>
        <hr style="margin:16px 0;">

        <h3>CAT Marks <?php if (!$cat1Submitted): ?><a href="edit_subjects.php?sem_id=<?= $id ?>" class="btn-calc" style="font-size:12px;padding:5px 12px;margin-left:10px;">✏ Edit Subjects</a><?php endif; ?></h3>
        <?php if (!empty($subList)): ?>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subList as $sub): ?>
                    <tr>
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
                    </tr>
                    <?php endforeach; ?>
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
            <div class="detail-item"><label>GPA</label><span><?= $s['gpa'] ?? '—' ?></span></div>
            <div class="detail-item"><label>CGPA</label><span><?= $s['cgpa'] ?? '—' ?></span></div>
        </div>
        <?php
        $gpa      = (float)($s['gpa'] ?? 0);
        $prev_gpa = (float)($s['prev_gpa'] ?? 0);
        if ($gpa > $prev_gpa)      { $gpaStatus = 'Improved'; $gpaColor = 'linear-gradient(135deg,#27ae60,#2ecc71)'; $gpaIcon = '↑'; }
        elseif ($gpa == $prev_gpa) { $gpaStatus = 'No Change'; $gpaColor = 'linear-gradient(135deg,#f5a623,#e67e22)'; $gpaIcon = '→'; }
        else                       { $gpaStatus = 'Decreased'; $gpaColor = 'linear-gradient(135deg,#e94560,#c0392b)'; $gpaIcon = '↓'; }
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
                <span class="attendance-card-status"><?= ($s['attendance'] >= 80) ? '✓ Good Standing' : '⚠ Below Required' ?></span>
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
                        <a href="<?= htmlspecialchars(imgUrl($s['result_photo'])) ?>" target="_blank" class="btn-calc" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;font-size:14px;">🖼 View Semester Result</a>
                    <?php else: ?>
                        <label>Upload Semester Result</label>
                        <input type="file" name="result_photo" accept="image/*">
                    <?php endif; ?>
                </div>
                <!-- CA Mark Sheet -->
                <div>
                    <?php if (!empty($s['ca_photo'])): ?>
                        <a href="<?= htmlspecialchars(imgUrl($s['ca_photo'])) ?>" target="_blank" class="btn-calc" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;font-size:14px;">📄 View CA Mark Sheet</a>
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

        <div style="text-align:center;margin-top:30px;">
            <a href="dashboard.php" class="btn-home">🏠 Home</a>
            <?php if ($sgiDone): ?>
            <a href="print_report.php?id=<?= $id ?>" class="btn-print" target="_blank">🖨 Print Report</a>
            <?php else: ?>
            <span class="btn-print btn-disabled">🖨 Print Report</span>
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
    <?php if (!$verified): ?>
        <a href="cat1_marks.php?sem_id=<?= $id ?>" class="btn-calc">CAT 1 Marks</a>
        <a href="cat2_marks.php?sem_id=<?= $id ?>" class="btn-calc">CAT 2 Marks</a>
        <a href="cat3_marks.php?sem_id=<?= $id ?>" class="btn-calc">CAT 3 Marks</a>
    <?php else: ?>
        <span class="btn-calc btn-disabled">CAT 1 Marks</span>
        <span class="btn-calc btn-disabled">CAT 2 Marks</span>
        <span class="btn-calc btn-disabled">CAT 3 Marks</span>
    <?php endif; ?>

    <?php if ($allCatsFilled && !$verified): ?>
        <a href="verify_marks.php?sem_id=<?= $id ?>" class="btn-verify">Confirmation</a>
    <?php elseif (!$allCatsFilled): ?>
        <span class="btn-verify btn-disabled">Confirmation</span>
    <?php else: ?>
        <span class="btn-verify btn-disabled">Confirmed ✓</span>
    <?php endif; ?>

    <?php if ($verified): ?>
        <?php if (empty($s['ca_done'])): ?>
            <a href="final_ca_marks.php?sem_id=<?= $id ?>" class="btn-verify" style="background:#2980b9;">Final CA Marks</a>
            <span class="btn-verify btn-disabled">Calculate SGI</span>
        <?php else: ?>
            <span class="btn-verify btn-disabled" style="background:#2980b9;">CA Done ✓</span>
            <a href="calculate_sgi.php?id=<?= $id ?>" class="btn-verify">Calculate SGI</a>
        <?php endif; ?>
    <?php else: ?>
        <span class="btn-verify btn-disabled" style="background:#2980b9;">Final CA Marks</span>
        <span class="btn-verify btn-disabled">Calculate SGI</span>
    <?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>


