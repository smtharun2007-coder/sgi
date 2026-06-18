<?php
include 'config.php';
requireLogin();
$u = $_SESSION['user'];

// Fetch semesters
$semCursor = $semesters->find(['roll' => $u['roll']], ['sort' => ['sem' => 1]]);
$semList   = iterator_to_array($semCursor);

// Best / Worst semester by SGI
$bestSem = null; $worstSem = null;
foreach ($semList as $s) {
    if (empty($s['sgi'])) continue;
    if (!$bestSem  || $s['sgi'] > $bestSem['sgi'])  $bestSem  = $s;
    if (!$worstSem || $s['sgi'] < $worstSem['sgi']) $worstSem = $s;
}

// Subject stats — best/worst by 40% avgCAT + 60% CA%
$subjectStats  = [];
foreach ($semList as $s) {
    $subCursor = $subjects->find(['sem_id' => (string)$s['_id'], 'roll' => $u['roll'], 'internal' => 'yes']);
    foreach (iterator_to_array($subCursor) as $sub) {
        $name = $sub['subject_name'];
        if (!isset($subjectStats[$name])) {
            $subjectStats[$name] = ['sem' => $s['sem'], 'cat_sum' => 0, 'cat_count' => 0, 'ca_scored' => null, 'ca_max' => null];
        }
        foreach (['cat1','cat2','cat3'] as $cat) {
            if (isset($sub[$cat]) && $sub[$cat] !== 'nil') {
                $subjectStats[$name]['cat_sum']   += (float)$sub[$cat];
                $subjectStats[$name]['cat_count'] ++;
            }
        }
        if (!empty($sub['ca_max']) && $sub['ca_max'] > 0) {
            $subjectStats[$name]['ca_scored'] = $sub['ca_scored'];
            $subjectStats[$name]['ca_max']    = $sub['ca_max'];
        }
    }
}

// Calculate combined score
$subjectScores = [];
foreach ($subjectStats as $name => $d) {
    $avgCat = $d['cat_count'] > 0 ? round($d['cat_sum'] / $d['cat_count'], 2) : 0;
    $subjectStats[$name]['avg_cat'] = $avgCat;
    if ($d['ca_scored'] !== null && $d['ca_max'] > 0) {
        $ca100 = round(($d['ca_scored'] / $d['ca_max']) * 100, 2);
        $subjectStats[$name]['ca_pct'] = $ca100;
        $subjectScores[$name] = round(0.4 * $avgCat + 0.6 * $ca100, 2);
    } else {
        $subjectStats[$name]['ca_pct'] = null;
        // Only include in scoring if CA exists
    }
}
arsort($subjectScores);
$bestSubject  = !empty($subjectScores) ? array_key_first($subjectScores) : null;
$worstSubject = !empty($subjectScores) ? array_key_last($subjectScores)  : null;

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
    <title>SGI – About</title>
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

    <!-- ABOUT SGI -->
    <div class="section">
        <h2>About SGI</h2>
        <p>The <strong>Student Growth Index (SGI)</strong> is a comprehensive scoring system designed to evaluate a student's overall development beyond just academics.</p>
        <br>
        <ul class="about-list">
            <li><strong>Academic (40%)</strong> – CAT scores, GPA, and CGPA performance.</li>
            <li><strong>Skills (20%)</strong> – Credit courses, coding platforms, and normal courses.</li>
            <li><strong>Projects (10%)</strong> – Mini projects, main projects, and other contributions.</li>
            <li><strong>Activities (20%)</strong> – Hackathons, competitions, workshops, and participations.</li>
            <li><strong>Discipline (10%)</strong> – Attendance record and GPA improvement trend.</li>
        </ul>
        <br>
        <a href="SGI.pdf" target="_blank" class="btn-primary" style="width:auto;display:inline-block;padding:10px 24px;">📄 View SGI Documentation (PDF)</a>
    </div>

    <!-- ALL SEMESTER ANALYSIS -->
    <?php if ($bestSem || $worstSem || $bestSubject): ?>
    <div class="section" style="margin-top:24px;">
        <h2>All Semester Analysis</h2>
        <p style="color:#888;font-size:13px;margin-top:4px;margin-bottom:20px;">
            Best &amp; Worst Semester based on <strong>SGI Score</strong> &nbsp;|&nbsp;
            Best &amp; Worst Subject based on <strong>40% Avg CAT + 60% CA Marks</strong>
        </p>
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

            <?php if ($bestSubject): ?>
            <div class="analytics-card best">
                <span class="analytics-card-icon">⭐</span>
                <span class="analytics-card-label">Best Subject</span>
                <span class="analytics-card-sem"><?= htmlspecialchars($bestSubject) ?></span>
                <span class="analytics-card-value">Score: <?= $subjectScores[$bestSubject] ?> / 100</span>
                <span class="analytics-card-grade">
                    Avg CAT: <?= $subjectStats[$bestSubject]['avg_cat'] ?> &nbsp;|&nbsp;
                    CA: <?= $subjectStats[$bestSubject]['ca_pct'] !== null ? $subjectStats[$bestSubject]['ca_pct'].'%' : '—' ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($worstSubject && $worstSubject !== $bestSubject): ?>
            <div class="analytics-card worst">
                <span class="analytics-card-icon">📚</span>
                <span class="analytics-card-label">Needs Focus</span>
                <span class="analytics-card-sem"><?= htmlspecialchars($worstSubject) ?></span>
                <span class="analytics-card-value">Score: <?= $subjectScores[$worstSubject] ?> / 100</span>
                <span class="analytics-card-grade">
                    Avg CAT: <?= $subjectStats[$worstSubject]['avg_cat'] ?> &nbsp;|&nbsp;
                    CA: <?= $subjectStats[$worstSubject]['ca_pct'] !== null ? $subjectStats[$worstSubject]['ca_pct'].'%' : '—' ?>
                </span>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

    <!-- SGI GRADE SCALE -->
    <div class="section" style="margin-top:24px;">
        <h2>SGI Grade Scale</h2>
        <table class="grade-table">
            <tr><th>SGI Score</th><th>Grade</th></tr>
            <tr><td>9.0 – 10.0</td><td>O (Excellent)</td></tr>
            <tr><td>8.0 – 8.9</td><td>A (Very Good)</td></tr>
            <tr><td>7.0 – 7.9</td><td>B (Good)</td></tr>
            <tr><td>6.0 – 6.9</td><td>C (Average)</td></tr>
            <tr><td>Below 6.0</td><td>D (Needs Improvement)</td></tr>
        </table>
    </div>

</div>
</body>
</html>
