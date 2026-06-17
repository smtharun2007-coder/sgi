<?php
include 'config.php';
requireLogin();

$u = $_SESSION['user'];
$semCursor = $semesters->find(['roll' => $u['roll']], ['sort' => ['sem' => 1]]);
$semList   = iterator_to_array($semCursor);

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
}

$avg_gpa  = $gpa_count  ? round($total_gpa  / $gpa_count,  2) : '—';
$avg_cgpa = $cgpa_count ? round($total_cgpa / $cgpa_count, 2) : '—';
$avg_sgi  = $sgi_count  ? round($total_sgi  / $sgi_count,  2) : '—';

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
    <title>SGI – Student Report</title>
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; color: #333; font-size: 13px; }
        .page { max-width: 900px; margin: 30px auto; padding: 30px; border: 1px solid #ddd; }

        .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a2e; padding-bottom: 16px; margin-bottom: 24px; }
        .report-profile { display: flex; gap: 20px; align-items: flex-start; }
        .report-photo img { width: 130px; height: 130px; object-fit: cover; border: 2px solid #1a1a2e; border-radius: 6px; }
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

        /* TABLE */
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 12px; }
        th { background: #1a1a2e; color: #fff; padding: 9px 12px; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; text-align: center; }
        tr:nth-child(even) td { background: #f9f9f9; }

        /* CHART */
        .chart-wrap { margin: 10px 0 20px; }
        canvas { max-height: 250px; }

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
            .summary-row, .declaration { page-break-inside: avoid; break-inside: avoid; }
            .section-title { page-break-after: avoid; }
            tr { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="print-btn-row">
    <a href="dashboard.php" class="back-btn">← Back</a>
    <button class="print-btn" onclick="window.print()">🖨 Print Report</button>
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
                <p><?= $u['year_from'] ?> – <?= $u['year_to'] ?></p>
            </div>
        </div>
        <div class="report-title">
            <img src="logo.png" style="height:55px;display:block;margin-left:auto;margin-bottom:8px;">
            <h1>Student Growth Index</h1>
            <h2>Overall Academic Report</h2>
            <?php if (!empty($u['mentor_id'])): ?>
            <p style="font-size:12px;color:#555;margin-top:6px;text-align:right;">Mentor ID: <strong><?= htmlspecialchars($u['mentor_id']) ?></strong></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="section-title">Overall Summary</div>
    <div class="summary-row">
        <div class="summary-card"><label>Average GPA</label><span><?= $avg_gpa ?></span></div>
        <div class="summary-card"><label>Average CGPA</label><span><?= $avg_cgpa ?></span></div>
        <div class="summary-card"><label>Average SGI</label><span><?= $avg_sgi ?></span></div>
        <div class="summary-card"><label>Total Semesters</label><span><?= count($semList) ?></span></div>
        <div class="summary-card"><label>Total Credits</label><span><?= $total_credits ?></span></div>
    </div>

    <!-- SEMESTER TABLE -->
    <div class="section-title">Semester-wise Performance</div>
    <table>
        <thead>
            <tr>
                <th>Semester</th>
                <th>GPA</th>
                <th>CGPA</th>
                <th>Attendance</th>
                <th>SGI Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($semList as $s): ?>
            <tr>
                <td>Semester <?= $s['sem'] ?></td>
                <td><?= $s['gpa'] ?? '—' ?></td>
                <td><?= $s['cgpa'] ?? '—' ?></td>
                <td><?= !empty($s['attendance']) ? $s['attendance'].'%' : '—' ?></td>
                <td><?= !empty($s['sgi']) ? round($s['sgi'], 2) : '—' ?></td>
                <td><?= !empty($s['sgi']) ? grade($s['sgi']) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- LINE CHART -->
    <?php if (!empty($semList)): ?>
    <div class="section-title">Performance Chart</div>
    <div class="chart-wrap">
        <canvas id="semChart"></canvas>
    </div>
    <?php endif; ?>

    <!-- DECLARATION -->
    <div class="declaration">
        <p>I hereby declare that all the information provided in this report including GPA, CGPA, attendance, and SGI scores are true and correct to the best of my knowledge.</p>
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

<?php if (!empty($semList)): ?>
<script>
const labels   = [<?= implode(',', array_map(fn($s) => '"Sem '.$s['sem'].'"', $semList)) ?>];
const gpaData  = [<?= implode(',', array_map(fn($s) => $s['gpa']  ?? 'null', $semList)) ?>];
const cgpaData = [<?= implode(',', array_map(fn($s) => $s['cgpa'] ?? 'null', $semList)) ?>];
const sgiData  = [<?= implode(',', array_map(fn($s) => !empty($s['sgi']) ? round($s['sgi'],2) : 'null', $semList)) ?>];
new Chart(document.getElementById('semChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            { label: 'GPA',  data: gpaData,  borderColor: '#e94560', backgroundColor: 'rgba(233,69,96,0.1)',  fill: true, tension: 0.4, pointRadius: 5 },
            { label: 'CGPA', data: cgpaData, borderColor: '#1a1a2e', backgroundColor: 'rgba(26,26,46,0.1)',   fill: true, tension: 0.4, pointRadius: 5 },
            { label: 'SGI',  data: sgiData,  borderColor: '#f5a623', backgroundColor: 'rgba(245,166,35,0.1)', fill: true, tension: 0.4, pointRadius: 5, spanGaps: true }
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

