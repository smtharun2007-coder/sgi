<?php
include 'config.php';
requireLogin();

$u = $_SESSION['user'];
$semCursor = $semesters->find(['roll' => $u['roll']], ['sort' => ['sem' => 1]]);
$semList   = iterator_to_array($semCursor);

$bestSem = null; $worstSem = null;
foreach ($semList as $s) {
    if (empty($s['sgi'])) continue;
    if (!$bestSem  || $s['sgi'] > $bestSem['sgi'])  $bestSem  = $s;
    if (!$worstSem || $s['sgi'] < $worstSem['sgi']) $worstSem = $s;
}

$subjectStats = [];
$caStats = [];
foreach ($semList as $s) {
    $subCursor = $subjects->find(['sem_id' => (string)$s['_id'], 'roll' => $u['roll'], 'internal' => 'yes']);
    foreach (iterator_to_array($subCursor) as $sub) {
        $name = $sub['subject_name'];
        $sem  = $s['sem'];
        if (!isset($subjectStats[$name])) $subjectStats[$name] = ['total' => 0, 'count' => 0, 'sem' => $sem, 'ca_scored' => null, 'ca_max' => null];
        $conducted = 0; $catSum = 0;
        foreach (['cat1','cat2','cat3'] as $cat) {
            $v = $sub[$cat] ?? null;
            if ($v !== null && $v !== 'nil') { $catSum += (float)$v; $conducted++; }
        }
        $avg = $conducted > 0 ? $catSum / $conducted : 0;
        $subjectStats[$name]['total'] += $avg;
        $subjectStats[$name]['count']++;
        if (isset($sub['ca_scored']) && isset($sub['ca_max']) && $sub['ca_max'] > 0) {
            $subjectStats[$name]['ca_scored'] = $sub['ca_scored'];
            $subjectStats[$name]['ca_max']    = $sub['ca_max'];
            $caPercent = round(($sub['ca_scored'] / $sub['ca_max']) * 100, 2);
            $caStats[] = ['name' => $name, 'sem' => $sem, 'percent' => $caPercent];
        }
    }
}
$subjectAvgs = [];
$subjectScores = [];
foreach ($subjectStats as $name => $data) {
    $catAvg = round($data['total'] / $data['count'], 2);
    $subjectAvgs[$name] = $catAvg;
    $caS = $data['ca_scored']; $caM = $data['ca_max'];
    if ($caS !== null && $caM > 0) {
        $ca100 = round(($caS / $caM) * 100, 2);
        $subjectScores[$name] = round(0.4 * $catAvg + 0.6 * $ca100, 2);
    } else {
        $subjectScores[$name] = $catAvg;
    }
}
arsort($subjectScores);
$bestSubject  = array_key_first($subjectScores);
$worstSubject = array_key_last($subjectScores);

$lowAttendance = [];
foreach ($semList as $s) {
    if (!empty($s['attendance']) && $s['attendance'] < 80) $lowAttendance[] = $s;
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
    <title>SGI – Analytics</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    <!-- ATTENDANCE WARNING -->
    <?php if (!empty($lowAttendance)): ?>
    <div class="alert-warning">
        ⚠ Low Attendance Warning: Semester <?= implode(', ', array_map(fn($s) => $s['sem'], $lowAttendance)) ?> — attendance below 80%
    </div>
    <?php endif; ?>

    <!-- HIGHLIGHT CARDS -->
    <h3 class="analytics-title">Performance Highlights</h3>
    <div class="analytics-grid">
        <?php if ($bestSem): ?>
        <div class="analytics-card best">
            <span class="analytics-card-icon">🏆</span>
            <span class="analytics-card-label">Best Semester</span>
            <span class="analytics-card-sem">Semester <?= $bestSem['sem'] ?></span>
            <span class="analytics-card-value">SGI <?= round($bestSem['sgi'], 2) ?></span>
            <span class="analytics-card-grade"><?= grade($bestSem['sgi']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($worstSem): ?>
        <div class="analytics-card worst">
            <span class="analytics-card-icon">📉</span>
            <span class="analytics-card-label">Worst Semester</span>
            <span class="analytics-card-sem">Semester <?= $worstSem['sem'] ?></span>
            <span class="analytics-card-value">SGI <?= round($worstSem['sgi'], 2) ?></span>
            <span class="analytics-card-grade"><?= grade($worstSem['sgi']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($subjectAvgs)): ?>
        <div class="analytics-card best">
            <span class="analytics-card-icon">⭐</span>
            <span class="analytics-card-label">Best Subject</span>
            <span class="analytics-card-sem"><?= htmlspecialchars($bestSubject) ?></span>
            <span class="analytics-card-value"><?= $subjectScores[$bestSubject] ?> / 100</span>
        </div>
        <div class="analytics-card worst">
            <span class="analytics-card-icon">📚</span>
            <span class="analytics-card-label">Needs Focus</span>
            <span class="analytics-card-sem"><?= htmlspecialchars($worstSubject) ?></span>
            <span class="analytics-card-value"><?= $subjectScores[$worstSubject] ?> / 100</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- SUBJECT CAT BAR CHART -->
    <?php if (!empty($subjectAvgs)): ?>
    <div class="chart-box" style="margin-top:20px;">
        <h3>Subject CAT Average</h3>
        <canvas id="subjectChart" style="max-height:300px;"></canvas>
    </div>
    <?php endif; ?>

    <!-- FINAL CA MARKS BAR CHART -->
    <?php if (!empty($caStats)): ?>
    <div class="chart-box" style="margin-top:20px;">
        <h3>Final CA Marks (% out of 100)</h3>
        <canvas id="caChart" style="max-height:300px;"></canvas>
    </div>
    <?php endif; ?>

    <!-- SGI + GPA TREND CHART -->
    <?php if (!empty($semList)): ?>
    <div class="chart-box" style="margin-top:20px;">
        <h3>SGI &amp; GPA Trend Across Semesters</h3>
        <canvas id="sgiChart" style="max-height:300px;"></canvas>
    </div>
    <?php endif; ?>

    <!-- SUBJECT TABLE -->
    <?php if (!empty($subjectAvgs)): ?>
    <h3 class="analytics-title">All Subject Averages</h3>
    <div class="form-box" style="padding:24px;margin-top:10px;">
        <div class="cat-table-wrap">
            <table class="cat-table">
                <thead>
                    <tr>
                        <th style="text-align:center;">S.No</th>
                        <th>Subject</th>
                        <th style="text-align:center;">Semester</th>
                        <th style="text-align:center;">Avg CAT Marks (100)</th>
                        <th style="text-align:center;">CA Mark (100)</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach ($subjectScores as $name => $score): ?>
                    <?php
                        $avg  = $subjectAvgs[$name];
                        $caS  = $subjectStats[$name]['ca_scored'];
                        $caM  = $subjectStats[$name]['ca_max'];
                        $ca100 = ($caS !== null && $caM > 0) ? round(($caS / $caM) * 100, 2) : null;
                        $color = $score >= 75 ? '#27ae60' : ($score >= 50 ? '#f5a623' : '#e94560');
                    ?>
                    <tr>
                        <td style="text-align:center;"><?= $rank++ ?></td>
                        <td><?= htmlspecialchars($name) ?></td>
                        <td style="text-align:center;">Sem <?= $subjectStats[$name]['sem'] ?></td>
                        <td style="text-align:center;"><span style="font-weight:700;color:<?= $avg >= 75 ? '#27ae60' : ($avg >= 50 ? '#f5a623' : '#e94560') ?>;"><?= $avg ?></span></td>
                        <td style="text-align:center;"><?php if ($ca100 !== null): ?><span style="font-weight:700;color:<?= $ca100 >= 75 ? '#27ae60' : ($ca100 >= 50 ? '#f5a623' : '#e94560') ?>;"><?= $ca100 ?></span><?php else: ?><span style="color:#aaa;">—</span><?php endif; ?></td>
                        <td>
                            <div style="background:#eee;border-radius:10px;height:10px;width:100%;cursor:pointer;position:relative;" title="Score: <?= $score ?> / 100">
                                <div style="background:<?= $color ?>;width:<?= $score ?>%;height:10px;border-radius:10px;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align:center;margin-top:30px;">
        <a href="dashboard.php" class="btn-home">🏠 Home</a>
    </div>

</div>

<script>
<?php if (!empty($subjectAvgs)): ?>
new Chart(document.getElementById('subjectChart'), {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(fn($n) => '"'.addslashes($n).'"', array_keys($subjectAvgs))) ?>],
        datasets: [{
            label: 'Average CAT Marks',
            data: [<?= implode(',', array_values($subjectAvgs)) ?>],
            backgroundColor: [<?= implode(',', array_map(fn($v) => $v >= 75 ? '"rgba(39,174,96,0.7)"' : ($v >= 50 ? '"rgba(245,166,35,0.7)"' : '"rgba(233,69,96,0.7)"'), array_values($subjectAvgs))) ?>],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, ticks: { stepSize: 10 } } }
    }
});
<?php endif; ?>

<?php if (!empty($caStats)): ?>
new Chart(document.getElementById('caChart'), {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(fn($c) => '"'.addslashes($c['name']).' (Sem '.$c['sem'].')"', $caStats)) ?>],
        datasets: [{
            label: 'CA Marks %',
            data: [<?= implode(',', array_map(fn($c) => $c['percent'], $caStats)) ?>],
            backgroundColor: [<?= implode(',', array_map(fn($c) => $c['percent'] >= 75 ? '"rgba(39,174,96,0.7)"' : ($c['percent'] >= 50 ? '"rgba(245,166,35,0.7)"' : '"rgba(233,69,96,0.7)"'), $caStats)) ?>],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { min: 0, max: 100, ticks: { stepSize: 10 } } }
    }
});
<?php endif; ?>

<?php if (!empty($semList)): ?>
new Chart(document.getElementById('sgiChart'), {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(fn($s) => '"Sem '.$s['sem'].'"', $semList)) ?>],
        datasets: [
            {
                label: 'SGI',
                data: [<?= implode(',', array_map(fn($s) => !empty($s['sgi']) ? round($s['sgi'],2) : 'null', $semList)) ?>],
                borderColor: '#e94560',
                backgroundColor: 'rgba(233,69,96,0.1)',
                fill: true, tension: 0.4, pointBackgroundColor: '#e94560', pointRadius: 5, spanGaps: true
            },
            {
                label: 'GPA',
                data: [<?= implode(',', array_map(fn($s) => $s['gpa'] ?? 'null', $semList)) ?>],
                borderColor: '#1a1a2e',
                backgroundColor: 'rgba(26,26,46,0.1)',
                fill: true, tension: 0.4, pointBackgroundColor: '#1a1a2e', pointRadius: 5, spanGaps: true
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { min: 0, max: 10, ticks: { stepSize: 1 } } }
    }
});
<?php endif; ?>
</script>
</body>
</html>
