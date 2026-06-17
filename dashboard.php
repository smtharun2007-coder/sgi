<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];

$semCursor = $semesters->find(['roll' => $u['roll']]);
$semList   = iterator_to_array($semCursor);

$total_gpa = 0; $gpa_count = 0;
$total_sgi = 0; $sgi_count = 0;
$total_credits = 0;
foreach ($semList as $s) {
    if (!empty($s['gpa']))  { $total_gpa += $s['gpa'];  $gpa_count++; }
    if (!empty($s['sgi']))  { $total_sgi += $s['sgi'];  $sgi_count++; }
}
$creditCursor = $subjects->find(['roll' => $u['roll']]);
foreach (iterator_to_array($creditCursor) as $sub) {
    $total_credits += (int)($sub['credits'] ?? 0);
}
$avg_gpa = $gpa_count ? round($total_gpa / $gpa_count, 2) : '—';
$avg_sgi = $sgi_count ? round($total_sgi / $sgi_count, 2) : '—';

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
    <title>SGI – Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
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
    <div class="profile-card">
        <div class="profile-info">
            <h2><?= htmlspecialchars($u['name']) ?></h2>
            <p class="profile-roll"><?= htmlspecialchars($u['roll']) ?></p>
            <p><strong>Integrated No:</strong> <?= htmlspecialchars($u['reg']) ?></p>
            <p><strong>DOB:</strong> <?= htmlspecialchars($u['dob']) ?></p>
            <p><strong>Father:</strong> <?= htmlspecialchars($u['father']) ?></p>
            <p><strong>Mother:</strong> <?= htmlspecialchars($u['mother']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($u['dept']) ?></p>
            <p><strong>Class:</strong> <?= htmlspecialchars($u['class']) ?></p>
            <p><strong>Year:</strong> <?= $u['year_from'] ?> – <?= $u['year_to'] ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($u['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($u['phone']) ?></p>
            <?php if (!empty($u['mentor_id'])): ?>
            <div style="margin-top:14px;background:linear-gradient(135deg,#1a1a2e,#e94560);border-radius:12px;padding:20px 28px;display:inline-block;box-shadow:0 4px 14px rgba(233,69,96,0.3);">
                <div style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Mentor ID</div>
                <div style="font-size:28px;font-weight:700;color:#fff;"><?= htmlspecialchars($u['mentor_id']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="profile-photo">
            <?php if (!empty($u['photo'])): ?>
                <img src="<?= htmlspecialchars(imgUrl($u['photo'])) ?>" alt="Photo">
            <?php else: ?>
                <div class="no-photo">No Photo</div>
            <?php endif; ?>
            <?php if (!empty($u['signature'])): ?>
                <div class="signature-box" style="margin-top:30px;">
                    <img src="<?= htmlspecialchars(imgUrl($u['signature'])) ?>" alt="Signature">
                    <label>Student Signature</label>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="summary-row">
        <div class="summary-card"><h3>Average GPA</h3><p><?= $avg_gpa ?></p></div>
        <div class="summary-card"><h3>Average SGI</h3><p><?= $avg_sgi ?></p></div>
        <div class="summary-card"><h3>Total Semesters</h3><p><?= count($semList) ?></p></div>
        <div class="summary-card"><h3>Total Credits</h3><p><?= $total_credits ?></p></div>
    </div>

    <div class="add-btn-row">
        <a href="add_semester.php" class="btn-primary">+ Add Semester</a>
    </div>

    <?php if (!empty($semList)): ?>
    <div class="chart-box">
        <h3>Semester Performance Overview</h3>
        <canvas id="semChart"></canvas>
    </div>
    <?php endif; ?>

    <div class="sem-cards">
        <?php foreach ($semList as $s):
            $semCredits = 0;
            $semSubCursor = $subjects->find(['sem_id' => (string)$s['_id'], 'roll' => $u['roll']]);
            $semSubList = iterator_to_array($semSubCursor);
            foreach ($semSubList as $sub) $semCredits += (int)($sub['credits'] ?? 0);
            $cat1Done = false;
            foreach ($semSubList as $sub) { if ($sub['internal'] === 'yes' && isset($sub['cat1'])) { $cat1Done = true; break; } }
        ?>
        <div class="sem-card">
            <div class="sem-card-header">
                <h3>Semester <?= $s['sem'] ?></h3>
                <?php if (!empty($s['mentor_id'])): ?>
                <p style="color:#aaa;font-size:11px;margin-top:4px;">Mentor: <?= htmlspecialchars($s['mentor_id']) ?></p>
                <?php endif; ?>
            </div>
            <div class="sem-card-body">
                <div class="sem-stat-grid">
                    <div class="sem-stat"><span class="sem-stat-label">GPA</span><span class="sem-stat-value"><?= $s['gpa'] ?? '—' ?></span></div>
                    <div class="sem-stat"><span class="sem-stat-label">CGPA</span><span class="sem-stat-value"><?= $s['cgpa'] ?? '—' ?></span></div>
                    <div class="sem-stat"><span class="sem-stat-label">SGI</span><span class="sem-stat-value"><?= !empty($s['sgi']) ? round($s['sgi'], 2) : '—' ?></span></div>
                    <div class="sem-stat"><span class="sem-stat-label">Credits</span><span class="sem-stat-value"><?= $semCredits ?></span></div>
                </div>
                <?php
                    $allCats = true;
                    foreach ($semSubList as $sub) { if ($sub['internal']==='yes' && (!isset($sub['cat1'])||!isset($sub['cat2'])||!isset($sub['cat3']))) { $allCats=false; break; } }
                    if (!empty($s['sgi']))           $status = ['SGI Calculated', '#27ae60'];
                    elseif (!empty($s['ca_done']))   $status = ['CA Done', '#2980b9'];
                    elseif (!empty($s['verified']))  $status = ['Confirmed', '#8e44ad'];
                    elseif ($allCats && $cat1Done)   $status = ['CATs Done', '#f5a623'];
                    elseif ($cat1Done)               $status = ['In Progress', '#e67e22'];
                    else                             $status = ['Subjects Added', '#888'];
                ?>
                <div style="text-align:center;margin-top:8px;">
                    <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;color:#fff;background:<?= $status[1] ?>;"><?= $status[0] ?></span>
                </div>
                <a href="semester_detail.php?id=<?= (string)$s['_id'] ?>" class="btn-calc" style="margin-top:12px;display:block;text-align:center;">View Details</a>
                <?php if (!$cat1Done && empty($s['sgi'])): ?>
                <a href="delete_semester.php?id=<?= (string)$s['_id'] ?>" class="btn-secondary" style="margin-top:6px;display:block;text-align:center;color:#e94560;">Delete</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($semList)): ?>
            <p class="no-data">No semesters added yet. Click "+ Add Semester" to begin.</p>
        <?php endif; ?>
    </div>

    <!-- ANALYTICS CARD -->
    <?php if (!empty($semList)): ?>
    <div class="sem-cards" style="margin-top:20px;">
        <div class="sem-card">
            <div class="sem-card-header" style="background:linear-gradient(135deg,#1a1a2e,#8e44ad);"><h3>📊 All Sem Analytics</h3></div>
            <div class="sem-card-body" style="text-align:center;padding:24px 20px;">
                <a href="analytics.php" class="btn-primary" style="width:auto;padding:10px 24px;">View Analytics</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if (!empty($semList)): ?>
<script>
    const labels  = [<?= implode(',', array_map(fn($s) => '"Sem '.$s['sem'].'"', $semList)) ?>];
    const gpaData  = [<?= implode(',', array_map(fn($s) => $s['gpa'] ?? 'null', $semList)) ?>];
    const cgpaData = [<?= implode(',', array_map(fn($s) => $s['cgpa'] ?? 'null', $semList)) ?>];
    const sgiData  = [<?= implode(',', array_map(fn($s) => !empty($s['sgi']) ? round($s['sgi'],2) : 'null', $semList)) ?>];
    new Chart(document.getElementById('semChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: 'GPA',  data: gpaData,  borderColor: '#e94560', backgroundColor: 'rgba(233,69,96,0.1)',  fill: true, tension: 0.4, pointBackgroundColor: '#e94560', pointRadius: 5 },
                { label: 'CGPA', data: cgpaData, borderColor: '#1a1a2e', backgroundColor: 'rgba(26,26,46,0.1)',   fill: true, tension: 0.4, pointBackgroundColor: '#1a1a2e', pointRadius: 5 },
                { label: 'SGI',  data: sgiData,  borderColor: '#f5a623', backgroundColor: 'rgba(245,166,35,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#f5a623', pointRadius: 5, spanGaps: true }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { min: 0, max: 10, ticks: { stepSize: 1 } } }
        }
    });
</script>
<?php endif; ?>
</body>
</html>
