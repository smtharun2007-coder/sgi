<?php
include 'config.php';
requireLogin();

$u = $_SESSION['user'];
$semCursor = $semesters->find(['roll' => $u['roll']], ['sort' => ['sem' => 1]]);
$semList   = iterator_to_array($semCursor);

$bestSem = null; $worstSem = null;
$total_gpa = 0; $gpa_count = 0;
$total_cgpa = 0; $cgpa_count = 0;
$total_sgi = 0; $sgi_count = 0;
$total_credits = 0;

foreach ($semList as $s) {
    if (!empty($s['gpa']))  { $total_gpa  += $s['gpa'];  $gpa_count++; }
    if (!empty($s['cgpa'])) { $total_cgpa += $s['cgpa']; $cgpa_count++; }
    if (!empty($s['sgi']))  { $total_sgi  += $s['sgi'];  $sgi_count++; }
    $subCur = $subjects->find(['sem_id' => (string)$s['_id'], 'roll' => $u['roll']]);
    foreach (iterator_to_array($subCur) as $sub) {
        $total_credits += (int)($sub['credits'] ?? 0);
    }
    if (empty($s['sgi'])) continue;
    if (!$bestSem  || $s['sgi'] > $bestSem['sgi'])  $bestSem  = $s;
    if (!$worstSem || $s['sgi'] < $worstSem['sgi']) $worstSem = $s;
}

$avg_gpa  = $gpa_count  ? round($total_gpa  / $gpa_count,  2) : '—';
$avg_cgpa = $cgpa_count ? round($total_cgpa / $cgpa_count, 2) : '—';
$avg_sgi  = $sgi_count  ? round($total_sgi  / $sgi_count,  2) : '—';

$subjectStats = [];
foreach ($semList as $s) {
    $subCursor = $subjects->find(['sem_id' => (string)$s['_id'], 'roll' => $u['roll'], 'internal' => 'yes']);
    foreach (iterator_to_array($subCursor) as $sub) {
        $name = $sub['subject_name'];
        $sem  = $s['sem'];
        if (!isset($subjectStats[$name])) $subjectStats[$name] = ['total' => 0, 'count' => 0, 'sem' => $sem, 'ca_scored' => null, 'ca_max' => null];
        $avg = ((float)($sub['cat1'] ?? 0) + (float)($sub['cat2'] ?? 0) + (float)($sub['cat3'] ?? 0)) / 3;
        $subjectStats[$name]['total'] += $avg;
        $subjectStats[$name]['count']++;
        if (isset($sub['ca_scored']) && isset($sub['ca_max']) && $sub['ca_max'] > 0) {
            $subjectStats[$name]['ca_scored'] = $sub['ca_scored'];
            $subjectStats[$name]['ca_max']    = $sub['ca_max'];
        }
    }
}
$subjectAvgs = [];
foreach ($subjectStats as $name => $data) {
    $subjectAvgs[$name] = round($data['total'] / $data['count'], 2);
}
arsort($subjectAvgs);
$bestSubject  = array_key_first($subjectAvgs);
$worstSubject = array_key_last($subjectAvgs);

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
    <title>SGI - Subject Report</title>
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; color: #333; font-size: 13px; }
        .page { max-width: 900px; margin: 30px auto; padding: 30px; border: 1px solid #ddd; }

        .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a2e; padding-bottom: 16px; margin-bottom: 24px; }
        .report-profile { display: flex; gap: 20px; align-items: flex-start; }
        .report-photo img { width: 150px; height: 150px; object-fit: cover; border: 2px solid #1a1a2e; border-radius: 6px; }
        .report-info .student-name { font-size: 22px; font-weight: 700; color: #1a1a2e; }
        .report-info .student-roll { font-size: 17px; color: #e94560; font-weight: 600; margin: 4px 0; }
        .report-info p { font-size: 13px; color: #555; margin: 3px 0; }
        .report-title { text-align: right; }
        .report-title h1 { font-size: 22px; color: #1a1a2e; }
        .report-title h2 { font-size: 15px; color: #e94560; margin-top: 4px; }

        .section-title { font-size: 13px; font-weight: 700; color: #e94560; text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 10px; border-left: 4px solid #e94560; padding-left: 8px; }

        /* SUMMARY CARDS */
        .summary-row { display: flex; gap: 12px; margin-bottom: 20px; }
        .summary-card { flex: 1; border: 2px solid #1a1a2e; border-radius: 10px; padding: 14px 10px; text-align: center; }
        .summary-card label { font-size: 10px; color: #888; display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-card span { font-size: 24px; font-weight: 700; color: #1a1a2e; }

        /* HIGHLIGHT CARDS */
        .highlight-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .highlight-card { border-radius: 10px; padding: 14px 18px; }
        .highlight-card.best  { background: #eafaf1; border: 2px solid #27ae60; }
        .highlight-card.worst { background: #fef9f0; border: 2px solid #f5a623; }
        .highlight-card label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
        .highlight-card.best  label { color: #27ae60; }
        .highlight-card.worst label { color: #f5a623; }
        .highlight-card span { font-size: 15px; font-weight: 700; color: #1a1a2e; }
        .highlight-card small { font-size: 12px; color: #666; display: block; margin-top: 2px; }

        /* TABLE */
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #1a1a2e; color: #fff; padding: 9px 12px; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; text-align: center; }
        td.left { text-align: left; }
        tr:nth-child(even) td { background: #f9f9f9; }

        /* PROGRESS BAR */
        .bar-wrap { background: #eee; border-radius: 10px; height: 8px; width: 100%; }
        .bar-fill  { height: 8px; border-radius: 10px; background: #1a1a2e; }

        /* DECLARATION */
        .declaration { margin-top: 30px; border: 1px solid #ddd; border-radius: 8px; padding: 16px 20px; }
        .declaration p { font-size: 12px; color: #444; line-height: 1.8; }
        .sign-row { display: flex; justify-content: space-between; margin-top: 40px; gap: 20px; align-items: flex-end; }
        .sign-box { flex: 1; text-align: center; }
        .sign-line { border-top: 1px solid #333; margin-bottom: 6px; }
        .sign-box p { font-size: 11px; color: #555; }
        .sign-img img { height: 50px; object-fit: contain; margin-bottom: 4px; }
        .remark-box { border: 1px solid #ddd; border-radius: 6px; padding: 10px; min-height: 120px; margin-top: 8px; }
        .remark-label { font-size: 11px; color: #888; margin-bottom: 4px; }

        .print-btn-row { text-align: center; margin-bottom: 20px; }
        .print-btn { padding: 10px 30px; background: linear-gradient(135deg, #1a1a2e, #f5a623); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .back-btn { padding: 10px 30px; background: #eee; color: #333; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; margin-right: 10px; text-decoration: none; display: inline-block; }

        @media print {
            .print-btn-row { display: none; }
            body { margin: 0; }
            .page { border: none; margin: 0; padding: 20px; max-width: 100%; }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-row, .highlight-row, .declaration { page-break-inside: avoid; break-inside: avoid; }
            .section-title { page-break-after: avoid; }
            tr { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="print-btn-row">
    <a href="print_select.php" class="back-btn">← Back</a>
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
</div>

<div class="page">
    <!-- HEADER -->
    <div class="report-header">
        <div class="report-profile">
            <div class="report-photo">
                <?php if (!empty($u['photo'])): ?>
                    <img src="<?= htmlspecialchars(imgUrl($u['photo'])) ?>" alt="Photo">
                <?php endif; ?>
            </div>
            <div class="report-info">
                <div class="student-name"><?= htmlspecialchars($u['name']) ?></div>
                <div class="student-roll"><?= htmlspecialchars($u['roll']) ?></div>
                <p><?= htmlspecialchars($u['class']) ?></p>
                <p><?= htmlspecialchars($u['reg']) ?></p>
                <p><?= htmlspecialchars($u['dept']) ?></p>
                <?php if (!empty($u['batch_no'])): ?>
                <p>Batch: <?= htmlspecialchars($u['batch_no']) ?></p>
                <?php endif; ?>
                <p><?= $u['year_from'] ?> - <?= $u['year_to'] ?></p>
            </div>
        </div>
        <div class="report-title">
            <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" style="height:55px;display:block;margin-left:auto;margin-bottom:8px;">
            <h1>Student Growth Index</h1>
            <h2>All Subject Report</h2>
            <?php if (!empty($u['batch_no'])): ?>
            <div style="margin-top:8px;background:linear-gradient(135deg,#1a1a2e,#2980b9);border-radius:10px;padding:12px 18px;display:inline-block;box-shadow:0 3px 10px rgba(41,128,185,0.3);">
                <div style="font-size:11px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Batch</div>
                <div style="font-size:20px;font-weight:700;color:#fff;"><?= htmlspecialchars($u['batch_no']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($u['mentor_id'])): ?>
            <div style="margin-top:10px;background:linear-gradient(135deg,#1a1a2e,#e94560);border-radius:12px;padding:14px 22px;display:inline-block;box-shadow:0 3px 12px rgba(233,69,96,0.3);">
                <div style="font-size:11px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Mentor ID</div>
                <div style="font-size:22px;font-weight:700;color:#fff;"><?= htmlspecialchars($u['mentor_id']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- OVERALL SUMMARY -->
    <div class="section-title">Overall Summary</div>
    <div class="summary-row">
        <div class="summary-card"><label>Average GPA</label><span><?= $avg_gpa ?></span></div>
        <div class="summary-card"><label>Average CGPA</label><span><?= $avg_cgpa ?></span></div>
        <div class="summary-card"><label>Average SGI</label><span><?= $avg_sgi ?></span></div>
        <div class="summary-card"><label>Total Semesters</label><span><?= count($semList) ?></span></div>
        <div class="summary-card"><label>Total Credits</label><span><?= $total_credits ?></span></div>
    </div>

    <!-- BEST & WORST SEM + SUBJECT -->
    <?php if ($bestSem || $worstSem || !empty($subjectAvgs)): ?>
    <div class="section-title">Performance Highlights</div>
    <div class="highlight-row">
        <?php if ($bestSem): ?>
        <div class="highlight-card best">
            <label>🏆 Best Semester</label>
            <span>Semester <?= $bestSem['sem'] ?></span>
            <small>SGI: <?= round($bestSem['sgi'], 2) ?> — <?= grade($bestSem['sgi']) ?></small>
        </div>
        <?php endif; ?>
        <?php if ($worstSem): ?>
        <div class="highlight-card worst">
            <label>📉 Needs Improvement</label>
            <span>Semester <?= $worstSem['sem'] ?></span>
            <small>SGI: <?= round($worstSem['sgi'], 2) ?> — <?= grade($worstSem['sgi']) ?></small>
        </div>
        <?php endif; ?>
        <?php if (!empty($subjectAvgs)): ?>
        <div class="highlight-card best">
            <label>⭐ Best Subject</label>
            <span><?= htmlspecialchars($bestSubject) ?></span>
            <small>Avg CAT: <?= $subjectAvgs[$bestSubject] ?></small>
        </div>
        <div class="highlight-card worst">
            <label>🎯 Needs Focus</label>
            <span><?= htmlspecialchars($worstSubject) ?></span>
            <small>Avg CAT: <?= $subjectAvgs[$worstSubject] ?></small>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- SUBJECT TABLE -->
    <?php if (!empty($subjectAvgs)): ?>
    <div class="section-title">All Subject Averages</div>
    <table>
        <thead>
            <tr>
                <th>S.No</th>
                <th style="text-align:left;">Subject</th>
                <th>Semester</th>
                <th>Avg CAT Marks (100)</th>
                <th>CA Mark (100)</th>
                <th>Performance</th>
            </tr>
        </thead>
        <tbody>
            <?php $rank = 1; foreach ($subjectAvgs as $name => $avg): ?>
            <tr>
                <td><?= $rank++ ?></td>
                <td class="left"><?= htmlspecialchars($name) ?></td>
                <td>Sem <?= $subjectStats[$name]['sem'] ?></td>
                <td><?= $avg ?></td>
                <td><?php
                    $caS = $subjectStats[$name]['ca_scored'];
                    $caM = $subjectStats[$name]['ca_max'];
                    if ($caS !== null && $caM > 0):
                        echo round(($caS / $caM) * 100, 2);
                    else: ?>—<?php endif; ?></td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-fill" style="width:<?= $avg ?>%;background:<?= $avg >= 75 ? '#27ae60' : ($avg >= 50 ? '#f5a623' : '#e94560') ?>;"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- DECLARATION -->
    <div class="declaration">
        <p>I hereby declare that all the information provided in this report including subject details, GPA, CGPA, and SGI scores are true and correct to the best of my knowledge.</p>
        <div class="sign-row">
            <div class="sign-box" style="flex:0.7;">
                <?php if (!empty($u['signature'])): ?>
                <div class="sign-img"><img src="<?= htmlspecialchars(imgUrl($u['signature'])) ?>" alt="Signature"></div>
                <?php else: ?>
                <div style="height:50px;"></div>
                <?php endif; ?>
                <div class="sign-line"></div>
                <p>Student Signature</p>
                <p style="font-size:11px;color:#888;"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['roll']) ?>)</p>
            </div>
            <div class="sign-box" style="flex:2;">
                <div class="remark-label">Remarks</div>
                <div class="remark-box"></div>
            </div>
            <div class="sign-box" style="flex:1.3;">
                <div style="height:50px;"></div>
                <div class="sign-line"></div>
                <p>CAO Signature</p>
                <p style="font-size:11px;color:#888;">Chief Academic Officer</p>
            </div>
        </div>
    </div>

</div>
<div class="copyright-footer" style="text-align:center;padding:20px;margin-top:40px;color:#888;font-size:12px;border-top:1px solid #ddd;">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>
