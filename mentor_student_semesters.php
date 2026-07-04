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

// Get student details
$studentData = [
    'name' => $student['name'] ?? '',
    'roll' => $roll,
    'reg' => $student['reg'] ?? '',
    'email' => $student['email'] ?? '',
    'phone' => $student['phone'] ?? '',
    'dept' => $student['dept'] ?? '',
    'class' => $student['class'] ?? '',
    'batch_no' => $student['batch_no'] ?? '',
    'year' => $student['year'] ?? '',
    'mentor_id' => $student['mentor_id'] ?? ''
];

// Get all semesters for this student
$semCursor = $semesters->find(
    ['roll' => $roll],
    ['sort' => ['sem' => -1]]
);

$semesters = [];
$subjectsCursor = $subjects->find(['roll' => $roll]);
$subjectsList = iterator_to_array($subjectsCursor);

foreach ($semCursor as $s) {
    $semSubjects = array_filter($subjectsList, function($sub) use ($s) {
        return $sub['sem_id'] == (string)$s['_id'];
    });
    
    // Determine semester status
    $status = 'Not Started';
    $statusColor = '#6c757d';
    
    if (isset($s['sgi']) && $s['sgi'] !== null) {
        $status = 'SGI Calculated';
        $statusColor = '#28a745';
    } elseif (isset($s['ca_done']) && $s['ca_done']) {
        $status = 'Final CA Done';
        $statusColor = '#17a2b8';
    } elseif (isset($s['verified']) && $s['verified']) {
        $status = 'Verified';
        $statusColor = '#ffc107';
    } elseif (isset($s['_id'])) {
        $status = 'Registered';
        $statusColor = '#007bff';
    }
    
    $semesters[] = [
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
        'ca_photo' => $s['ca_photo'] ?? null,
        'ca_done' => $s['ca_done'] ?? false,
        'verified' => $s['verified'] ?? false,
        'status' => $status,
        'status_color' => $statusColor,
        'subject_count' => count($semSubjects),
        'credits_done' => $s['credits_done'] ?? false
    ];
}

echo json_encode([
    'status' => 'success', 
    'student' => $studentData, 
    'semesters' => $semesters
]);

