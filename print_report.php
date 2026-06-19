<?php
include 'config.php';
requireLogin();

if (empty($_GET['id'])) { header("Location: dashboard.php"); exit; }

$id   = $_GET['id'];
$roll = $_SESSION['user']['roll'];
$u    = $_SESSION['user'];
$s    = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);
if (!$s) { header("Location: dashboard.php"); exit; }

$subCursor    = $subjects->find(['sem_id' => $id, 'roll' => $roll]);
$subList      = iterator_to_array($subCursor);
$internalSubs = array_filter($subList, fn($sub) => $sub['internal'] === 'yes');

$maxTotal  = count(array_filter($internalSubs, fn($s) => (int)($s['credits'] ?? 0) > 0)) * 100;
$cat1Total = 0; $cat2Total = 0; $cat3Total = 0;
foreach ($internalSubs as $sub) {
    if ((int)($sub['credits'] ?? 0) === 0) continue;
    $cat1Total += (float)($sub['cat1'] ?? 0);
    $cat2Total += (float)($sub['cat2'] ?? 0);
    $cat3Total += (float)($sub['cat3'] ?? 0);
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
    <title>SGI Report - Semester <?= $s['sem'] ?></title>
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; color: #333; font-size: 13px; }
        .page { max-width: 900px; margin: 30px auto; padding: 30px; border: 1px solid #ddd; }

        /* HEADER */
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a2e; padding-bottom: 16px; margin-bottom: 20px; }
        .report-title h1 { font-size: 22px; color: #1a1a2e; }
        .report-title h2 { font-size: 15px; color: #e94560; margin-top: 4px; }
        .report-profile { display: flex; gap: 20px; align-items: flex-start; }
        .report-photo img { width: 130px; height: 130px; object-fit: cover; border: 2px solid #1a1a2e; border-radius: 6px; }
        .report-info { text-align: left; }
        .report-info .student-name { font-size: 22px; font-weight: 700; color: #1a1a2e; }
        .report-info .student-roll { font-size: 17px; color: #e94560; font-weight: 600; margin: 4px 0; }
        .report-info p { font-size: 13px; color: #555; margin: 3px 0; }

        /* SEM BADGE */
        .sem-badge { display: inline-block; background: #1a1a2e; color: #fff; padding: 6px 20px; border-radius: 20px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }

        /* TABLE */
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px; }
        th { background: #1a1a2e; color: #fff; padding: 8px 10px; text-align: left; }
        td { padding: 7px 10px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) td { background: #f9f9f9; }
        tfoot td { background: #1a1a2e !important; color: #fff; font-weight: 700; padding: 8px 10px; }
        tfoot small { color: #f5a623; font-size: 11px; }

        /* SECTION TITLE */
        .section-title { font-size: 13px; font-weight: 700; color: #e94560; text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 8px; border-left: 4px solid #e94560; padding-left: 8px; }

        /* SUMMARY CARDS */
        .summary-row { display: flex; gap: 12px; margin-bottom: 16px; }
        .summary-card { flex: 1; border: 2px solid #1a1a2e; border-radius: 8px; padding: 12px; text-align: center; }
        .summary-card label { font-size: 11px; color: #888; display: block; margin-bottom: 4px; text-transform: uppercase; }
        .summary-card span { font-size: 22px; font-weight: 700; color: #1a1a2e; }

        /* CAT SUMMARY CARDS */
        .cat-summary-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin: 12px 0 16px; }
        .cat-summary-item { border: 2px solid #1a1a2e; border-radius: 10px; padding: 14px; text-align: center; }
        .cat-summary-label { display: block; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .cat-summary-value { display: block; font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
        .cat-summary-percent { display: block; font-size: 14px; font-weight: 600; color: #e94560; }

        /* SGI CARD */
        .sgi-print-card { display: flex; justify-content: space-between; align-items: center; background: #1a1a2e; color: #fff; border-radius: 10px; padding: 20px 28px; margin: 16px 0; }
        .sgi-print-card .sgi-val { font-size: 48px; font-weight: 700; }
        .sgi-print-card .sgi-label { font-size: 11px; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 1px; }
        .sgi-print-card .sgi-grade { font-size: 18px; font-weight: 700; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 8px; }

        /* CHART */
        .chart-wrap { margin: 16px 0; }
        canvas { max-height: 280px; }

        /* DECLARATION */
        .declaration { margin-top: 30px; border: 1px solid #ddd; border-radius: 8px; padding: 16px 20px; }
        .declaration p { font-size: 12px; color: #444; line-height: 1.8; }
        .sign-row { display: flex; justify-content: space-between; margin-top: 40px; gap: 20px; align-items: flex-end; }
        .sign-box { flex: 1; text-align: center; }
        .sign-box .sign-line { border-top: 1px solid #333; margin-bottom: 6px; }
        .sign-box p { font-size: 11px; color: #555; }
        .sign-img img { height: 50px; object-fit: contain; margin-bottom: 4px; }
        .remark-box { border: 1px solid #ddd; border-radius: 6px; padding: 10px; min-height: 120px; margin-top: 8px; }
        .remark-label { font-size: 11px; color: #888; margin-bottom: 4px; }

        /* PRINT BUTTON */
        .print-btn-row { text-align: center; margin-bottom: 20px; }
        .print-btn { padding: 10px 30px; background: linear-gradient(135deg, #1a1a2e, #f5a623); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .back-btn { padding: 10px 30px; background: #eee; color: #333; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; margin-right: 10px; text-decoration: none; display: inline-block; }

        @media print {
            .print-btn-row { display: none; }
            body { margin: 0; }
            .page { border: none; margin: 0; padding: 20px; max-width: 100%; }
            canvas { max-height: 220px; }
            .sgi-print-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tfoot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-row { page-break-inside: avoid; break-inside: avoid; }
            .cat-summary-row { page-break-inside: avoid; break-inside: avoid; }
            .declaration { page-break-inside: avoid; break-inside: avoid; }
            .sgi-print-card { page-break-inside: avoid; break-inside: avoid; }
            .chart-wrap { page-break-inside: avoid; break-inside: avoid; }
            .section-title { page-break-after: avoid; }
            tr { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="print-btn-row">
    <a href="semester_detail.php?id=<?= $id ?>" class="back-btn">← Back</a>
    <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
</div>

<div class="page">
    <!-- SEMESTER BADGE - CENTERED -->
    <div style="text-align:center;margin-bottom:16px;">
        <div class="sem-badge" style="font-size:16px;padding:8px 28px;">Semester <?= $s['sem'] ?></div>
    </div>
    
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
                <p><?= $u['year_from'] ?> - <?= $u['year_to'] ?></p>
            </div>
        </div>
        <div class="report-title">
            <img src="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png" style="height:55px;display:block;margin-left:auto;margin-bottom:8px;">
            <h1>Student Growth Index</h1>
            <h2>Academic Performance Report</h2>
            <div class="sem-badge" style="margin-top:10px;display:none;">Semester <?= $s['sem'] ?></div>
            <?php if (!empty($s['mentor_id'])): ?>
            <p style="font-size:12px;color:#555;margin-top:6px;text-align:right;">Mentor ID: <strong><?= htmlspecialchars($s['mentor_id']) ?></strong></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUBJECT TABLE -->
    <div class="section-title">Subject Marks</div>
    <table>
        <thead>
            <tr>
                <th>Subject Name</th>
                <th>Code</th>
                <th>Credits</th>
                <th>CAT 1</th>
                <th>CAT 2</th>
                <th>CAT 3</th>
                <th>Total</th>
                <th>%</th>
                <th>CA Scored</th>
                <th>CA Max</th>
                <th>CA %</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subList as $sub): ?>
            <tr>
                <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                <td><?= htmlspecialchars($sub['subject_code']) ?></td>
                <td><?= $sub['credits'] ?></td>
                <?php if ($sub['internal'] === 'yes'): ?>
                    <td><?= $sub['cat1'] ?? '—' ?></td>
                    <td><?= $sub['cat2'] ?? '—' ?></td>
                    <td><?= $sub['cat3'] ?? '—' ?></td>
                    <td><?= $sub['total'] ?? '—' ?></td>
                    <td><?= !empty($sub['percentage']) ? $sub['percentage'].'%' : '—' ?></td>
                <?php else: ?>
                    <td colspan="5" style="text-align:center;color:#aaa;">No Internal</td>
                <?php endif; ?>
                <td><?= $sub['ca_scored'] ?? '—' ?></td>
                <td><?= $sub['ca_max'] ?? '—' ?></td>
                <td><?= !empty($sub['ca_percent']) ? $sub['ca_percent'].'%' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
        <?php if ($maxTotal > 0): ?>
        </table>
        <div class="cat-summary-row">
            <div class="cat-summary-item">
                <span class="cat-summary-label">CAT 1 Total</span>
                <span class="cat-summary-value"><?= $cat1Total ?> / <?= $maxTotal ?></span>
                <span class="cat-summary-percent"><?= round(($cat1Total/$maxTotal)*100,2) ?>%</span>
            </div>
            <div class="cat-summary-item">
                <span class="cat-summary-label">CAT 2 Total</span>
                <span class="cat-summary-value"><?= $cat2Total ?> / <?= $maxTotal ?></span>
                <span class="cat-summary-percent"><?= round(($cat2Total/$maxTotal)*100,2) ?>%</span>
            </div>
            <div class="cat-summary-item">
                <span class="cat-summary-label">CAT 3 Total</span>
                <span class="cat-summary-value"><?= $cat3Total ?> / <?= $maxTotal ?></span>
                <span class="cat-summary-percent"><?= round(($cat3Total/$maxTotal)*100,2) ?>%</span>
            </div>
        </div>
        <?php else: ?>
        </table>
        <?php endif; ?>

    <!-- LINE CHART -->
    <?php if (!empty($internalSubs)): ?>
    <div class="section-title">CAT Performance Chart</div>
    <div class="chart-wrap">
        <canvas id="catChart"></canvas>
    </div>
    <?php endif; ?>

    <!-- ACADEMIC SUMMARY -->
    <div class="section-title">Academic Summary</div>
    <div class="summary-row">
        <div class="summary-card"><label>GPA</label><span><?= $s['gpa'] ?? '—' ?></span></div>
        <div class="summary-card"><label>CGPA</label><span><?= $s['cgpa'] ?? '—' ?></span></div>
        <div class="summary-card"><label>Attendance</label><span><?= !empty($s['attendance']) ? $s['attendance'].'%' : '—' ?></span></div>
        <div class="summary-card"><label>Previous GPA</label><span><?= $s['prev_gpa'] ?? '—' ?></span></div>
    </div>

    <!-- SGI SCORES -->
    <?php if (!empty($s['sgi'])): ?>
    <div class="section-title">SGI Component Scores</div>
    <div class="summary-row">
        <div class="summary-card"><label>Academic</label><span><?= round($s['academic_score'], 2) ?></span></div>
        <div class="summary-card"><label>Skills</label><span><?= round($s['skills_score'], 2) ?></span></div>
        <div class="summary-card"><label>Projects</label><span><?= round($s['projects_score'], 2) ?></span></div>
        <div class="summary-card"><label>Activities</label><span><?= round($s['activities_score'], 2) ?></span></div>
        <div class="summary-card"><label>Discipline</label><span><?= round($s['discipline_score'], 2) ?></span></div>
    </div>

    <div class="sgi-print-card">
        <div>
            <div class="sgi-label">Student Growth Index</div>
            <div class="sgi-val"><?= round($s['sgi'], 2) ?></div>
            <div style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:4px;">out of 10</div>
        </div>
        <div class="sgi-grade"><?= grade($s['sgi']) ?></div>
    </div>
    <?php endif; ?>

    <!-- DECLARATION -->
    <div class="declaration">
        <p>I hereby declare that all the information provided in this report including CAT marks, CA marks, GPA, CGPA, attendance, and SGI scores are true and correct to the best of my knowledge.</p>

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

<?php if (!empty($internalSubs)): ?>
<script>
const subLabels = [<?= implode(',', array_map(fn($s) => '"'.addslashes($s['subject_name']).'"', $internalSubs)) ?>];
const cat1Data  = [<?= implode(',', array_map(fn($s) => $s['cat1'] ?? 0, $internalSubs)) ?>];
const cat2Data  = [<?= implode(',', array_map(fn($s) => $s['cat2'] ?? 0, $internalSubs)) ?>];
const cat3Data  = [<?= implode(',', array_map(fn($s) => $s['cat3'] ?? 0, $internalSubs)) ?>];

new Chart(document.getElementById('catChart'), {
    type: 'line',
    data: {
        labels: subLabels,
        datasets: [
            { label: 'CAT 1', data: cat1Data, borderColor: '#e94560', backgroundColor: 'rgba(233,69,96,0.1)', fill: true, tension: 0.4, pointRadius: 5 },
            { label: 'CAT 2', data: cat2Data, borderColor: '#1a1a2e', backgroundColor: 'rgba(26,26,46,0.1)',  fill: true, tension: 0.4, pointRadius: 5 },
            { label: 'CAT 3', data: cat3Data, borderColor: '#f5a623', backgroundColor: 'rgba(245,166,35,0.1)',fill: true, tension: 0.4, pointRadius: 5 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { min: 0, max: 100, ticks: { stepSize: 10 } } }
    }
});
</script>
<?php endif; ?>
<div class="copyright-footer" style="text-align:center;padding:20px;margin-top:40px;color:#888;font-size:12px;border-top:1px solid #ddd;">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>

