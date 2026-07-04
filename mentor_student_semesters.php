<?php
include 'config.php';

if (!isset($_SESSION['mentor'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$mentor = $_SESSION['mentor'];
$roll = trim($_GET['roll'] ?? '');

if ($roll === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing roll']);
    exit;
}

// Verify the student belongs to this mentor
$student = $users->findOne(['roll' => $roll, 'mentor_id' => $mentor['mentor_id']]);
if (!$student) {
    echo json_encode(['status' => 'error', 'message' => 'Student not found']);
    exit;
}

$semCursor = $semesters->find(
    ['roll' => $roll, 'sgi' => ['$exists' => true]],
    ['sort' => ['sem' => -1]]
);

$result = [];
foreach ($semCursor as $s) {
    $result[] = [
        'sem' => $s['sem'] ?? null,
        'sgi' => isset($s['sgi']) ? (float)$s['sgi'] : null,
        'academic_score' => isset($s['academic_score']) ? (float)$s['academic_score'] : null,
        'skills_score' => isset($s['skills_score']) ? (float)$s['skills_score'] : null,
        'projects_score' => isset($s['projects_score']) ? (float)$s['projects_score'] : null,
        'activities_score' => isset($s['activities_score']) ? (float)$s['activities_score'] : null,
        'discipline_score' => isset($s['discipline_score']) ? (float)$s['discipline_score'] : null,
        'attendance' => isset($s['attendance']) ? (float)$s['attendance'] : null,
        'prev_gpa' => isset($s['prev_gpa']) ? (float)$s['prev_gpa'] : null,
        'gpa' => isset($s['gpa']) ? (float)$s['gpa'] : null,
        'cgpa' => isset($s['cgpa']) ? (float)$s['cgpa'] : null,
        'result_photo' => $s['result_photo'] ?? null,
        'ca_photo' => $s['ca_photo'] ?? null
    ];
}

echo json_encode(['status' => 'success', 'student' => ['name' => $student['name'] ?? '', 'roll' => $roll], 'semesters' => $result]);

