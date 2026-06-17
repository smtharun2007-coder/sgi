<?php
include 'config.php';
requireLogin();

if (empty($_GET['id'])) { header("Location: dashboard.php"); exit; }

$id   = $_GET['id'];
$roll = $_SESSION['user']['roll'];
$sem  = $semesters->findOne(['_id' => new MongoDB\BSON\ObjectId($id), 'roll' => $roll]);

if (!$sem) { header("Location: dashboard.php"); exit; }
if (!empty($sem['sgi'])) { header("Location: dashboard.php"); exit; }

// Fetch CAT totals from subjects
$subCursor    = $subjects->find(['sem_id' => $id, 'roll' => $roll, 'internal' => 'yes']);
$subList      = iterator_to_array($subCursor);
$maxTotal     = count(array_filter($subList, fn($s) => (int)($s['credits'] ?? 0) > 0)) * 100;

$cat1Total = 0; $cat2Total = 0; $cat3Total = 0;
foreach ($subList as $sub) {
    if ((int)($sub['credits'] ?? 0) === 0) continue;
    $cat1Total += (float)($sub['cat1'] ?? 0);
    $cat2Total += (float)($sub['cat2'] ?? 0);
    $cat3Total += (float)($sub['cat3'] ?? 0);
}

// Convert CAT % to out of 10
$cat1_10 = $maxTotal > 0 ? round(($cat1Total / $maxTotal) * 10, 2) : 0;
$cat2_10 = $maxTotal > 0 ? round(($cat2Total / $maxTotal) * 10, 2) : 0;
$cat3_10 = $maxTotal > 0 ? round(($cat3Total / $maxTotal) * 10, 2) : 0;

// Auto-fetched from verify
$gpa        = (float)($sem['gpa'] ?? 0);
$cgpa       = (float)($sem['cgpa'] ?? 0);
$attendance = (float)($sem['attendance'] ?? 0);
$prev_gpa   = (float)($sem['prev_gpa'] ?? 0);

$error = '';

if (isset($_POST['calculate'])) {
    if ($_POST['reg'] !== $sem['reg']) {
        $error = "Integrated Number does not match. Please try again.";
    } else {
        // Handle file uploads
        $result_photo = '';
        if (!empty($_FILES['result_photo']['name'])) {
            $ext = pathinfo($_FILES['result_photo']['name'], PATHINFO_EXTENSION);
            $result_photo = 'result_' . $id . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['result_photo']['tmp_name'], 'uploads/' . $result_photo);
        }
        $ca_photo = '';
        if (!empty($_FILES['ca_photo']['name'])) {
            $ext = pathinfo($_FILES['ca_photo']['name'], PATHINFO_EXTENSION);
            $ca_photo = 'ca_' . $id . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['ca_photo']['tmp_name'], 'uploads/' . $ca_photo);
        }
        // ACADEMIC from auto-fetched values
        $academic = 0.15*$cat1_10 + 0.15*$cat2_10 + 0.2*$cat3_10
                  + 0.25*$gpa + 0.25*$cgpa;

        // SKILLS
        $skills = 3*(float)$_POST['credit'] + 3*(float)$_POST['coding'] + 1*(float)$_POST['normal'];
        $skills = min($skills, 10);

        // PROJECTS
        $projects = 2*(float)$_POST['mini'] + 3*(float)$_POST['main'];
        if (!empty($_POST['other_check']) && !empty($_POST['other_name'])) {
            foreach ($_POST['other_name'] as $i => $name) {
                if (empty(trim($name))) continue;
                $count  = (float)($_POST['other_count'][$i] ?? 0);
                $points = (float)($_POST['other_points'][$i] ?? 0);
                $projects += $count * $points;
            }
        }
        $projects = min($projects, 10);

        // ACTIVITIES
        $activities = 3*(float)$_POST['leader_win']
                    + 2.5*(float)$_POST['member_win']
                    + 2.5*(float)$_POST['leader_place']
                    + 2.0*(float)$_POST['member_place']
                    + 1*(float)$_POST['participation']
                    + 1*(float)$_POST['workshop'];
        $activities = min($activities, 10);

        // DISCIPLINE
        $att_score = $attendance / 20;
        if ($gpa > $prev_gpa)      $gpa_score = 5;
        elseif ($gpa == $prev_gpa) $gpa_score = 3;
        else                       $gpa_score = 1;
        $discipline = $att_score + $gpa_score;

        // FINAL SGI
        $sgi = (($academic * 4) + ($skills * 2) + ($projects * 1) + ($activities * 2) + ($discipline * 1)) / 10;

        $updateData = [
                'sgi'              => round($sgi, 2),
                'academic_score'   => round($academic, 2),
                'skills_score'     => round($skills, 2),
                'projects_score'   => round($projects, 2),
                'activities_score' => round($activities, 2),
                'discipline_score' => round($discipline, 2),
            ];
        if ($result_photo) $updateData['result_photo'] = $result_photo;
        if ($ca_photo)     $updateData['ca_photo']     = $ca_photo;

        $semesters->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => $updateData]
        );

        header("Location: semester_detail.php?id=$id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Calculate</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="logo1.jpeg">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand"><img src="logo1.jpeg" alt="SGI">SGI</a>
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
<div class="form-box">
    <h2>Calculate SGI – Semester <?= $sem['sem'] ?></h2>
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <form method="POST">

        <h3>Verification</h3>
        <label>Integrated Number</label>
        <input type="text" name="reg" required>

        <h3>Academic</h3>
        <div class="readonly-grid">
            <div><label>CAT 1 (10)</label><input type="text" value="<?= $cat1_10 ?>" readonly></div>
            <div><label>CAT 2 (10)</label><input type="text" value="<?= $cat2_10 ?>" readonly></div>
            <div><label>CAT 3 (10)</label><input type="text" value="<?= $cat3_10 ?>" readonly></div>
            <div><label>GPA</label><input type="text" value="<?= $gpa ?>" readonly></div>
            <div><label>CGPA</label><input type="text" value="<?= $cgpa ?>" readonly></div>
        </div>

        <h3>Skills</h3>
        <label>Credit Courses Completed</label>
        <input type="number" name="credit" min="0" value="0" required>
        <label>Coding Platforms Used</label>
        <input type="number" name="coding" min="0" value="0" required>
        <label>Normal Courses Completed</label>
        <input type="number" name="normal" min="0" value="0" required>

        <h3>Projects</h3>
        <label>Mini Projects Count</label>
        <input type="number" name="mini" min="0" value="0" required>
        <label>Main Projects Count</label>
        <input type="number" name="main" min="0" value="0" required>
        <label class="checkbox-label">
            <input type="checkbox" name="other_check" id="otherCheck" onchange="toggleOther()">
            Include Other Projects
        </label>
        <div id="otherFields" style="display:none;">
            <div class="cat-table-wrap" style="margin-top:10px;">
                <table class="cat-table" id="other-project-table">
                    <thead>
                        <tr>
                            <th style="width:35%;">Project Name</th>
                            <th style="width:15%;">No. of Projects</th>
                            <th style="width:15%;">Points / Project</th>
                            <th style="width:25%;">Evaluator ID</th>
                            <th style="width:10%;"></th>
                        </tr>
                    </thead>
                    <tbody id="other-project-list">
                        <tr>
                            <td><input type="text"   name="other_name[]"    placeholder="Project Name" style="width:100%;margin:0;"></td>
                            <td><input type="number" name="other_count[]"   placeholder="Count" min="0" value="1" style="width:100%;margin:0;"></td>
                            <td><input type="number" step="0.01" name="other_points[]" placeholder="Points" min="0" value="0" style="width:100%;margin:0;"></td>
                            <td><input type="text"   name="other_eval_id[]" placeholder="Evaluator ID" style="width:100%;margin:0;"></td>
                            <td style="text-align:center;"><button type="button" class="btn-remove" onclick="removeOtherRow(this)">✕</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn-add-subject" onclick="addOtherRow()" style="margin-top:8px;">+ Add Project</button>
        </div>

        <h3>Activities / Hackathons</h3>
        <label>Hackathon Wins (as Leader)</label>
        <input type="number" name="leader_win" min="0" value="0" required>
        <label>Hackathon Wins (as Member)</label>
        <input type="number" name="member_win" min="0" value="0" required>
        <label>2nd / 3rd Place (as Leader)</label>
        <input type="number" name="leader_place" min="0" value="0" required>
        <label>2nd / 3rd Place (as Member)</label>
        <input type="number" name="member_place" min="0" value="0" required>
        <label>Participations</label>
        <input type="number" name="participation" min="0" value="0" required>
        <label>Workshops Attended</label>
        <input type="number" name="workshop" min="0" value="0" required>
        <h3>Discipline</h3>
        <div><label>Attendance</label><input type="text" value="<?= $attendance ?>%" readonly></div>
        <div><label>Previous GPA</label><input type="text" value="<?= $prev_gpa ?>" readonly></div>

        <button type="submit" name="calculate" class="btn-primary">Calculate SGI</button>
        <a href="semester_detail.php?id=<?= $id ?>" class="btn-secondary">Cancel</a>
    </form>
</div>
</div>
<script>
function toggleOther() {
    document.getElementById('otherFields').style.display =
        document.getElementById('otherCheck').checked ? 'block' : 'none';
}
function addOtherRow() {
    const list = document.getElementById('other-project-list');
    const row  = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text"   name="other_name[]"    placeholder="Project Name" style="width:100%;margin:0;"></td>
        <td><input type="number" name="other_count[]"   placeholder="Count" min="0" value="1" style="width:100%;margin:0;"></td>
        <td><input type="number" step="0.01" name="other_points[]" placeholder="Points" min="0" value="0" style="width:100%;margin:0;"></td>
        <td><input type="text"   name="other_eval_id[]" placeholder="Evaluator ID" style="width:100%;margin:0;"></td>
        <td style="text-align:center;"><button type="button" class="btn-remove" onclick="removeOtherRow(this)">✕</button></td>
    `;
    list.appendChild(row);
}
function removeOtherRow(btn) {
    const list = document.getElementById('other-project-list');
    if (list.children.length > 1) btn.closest('tr').remove();
}
</script>
</body>
</html>


