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
$allSubjectsCursor = $subjects->find(['roll' => $roll]);
$allSubjectsList = iterator_to_array($allSubjectsCursor);

foreach ($semCursor as $s) {
    $semSubjects = array_filter($allSubjectsList, function($sub) use ($s) {
        return $sub['sem_id'] == (string)$s['_id'];
    });
    
    // Build subjects array with CAT marks and details
    $subjectsData = [];
    $internalSubs = array_filter($semSubjects, fn($sub) => $sub['internal'] === 'yes');
    
    foreach ($semSubjects as $sub) {
        $subjectEntry = [
            'subject_name' => $sub['subject_name'] ?? '',
            'subject_code' => $sub['subject_code'] ?? '',
            'credits' => $sub['credits'] ?? 0,
            'internal' => $sub['internal'] ?? 'no'
        ];
        
        // Add CAT marks for internal subjects
        if ($sub['internal'] === 'yes') {
            $subjectEntry['cat1'] = $sub['cat1'] ?? null;
            $subjectEntry['cat2'] = $sub['cat2'] ?? null;
            $subjectEntry['cat3'] = $sub['cat3'] ?? null;
            
            // Calculate total and percentage
            $catTotal = 0;
            $catMax = 0;
            if (($sub['cat1'] ?? null) !== 'nil' && ($sub['cat1'] ?? null) !== null) {
                $catTotal += (float)($sub['cat1'] ?? 0);
                $catMax += 100;
            }
            if (($sub['cat2'] ?? null) !== 'nil' && ($sub['cat2'] ?? null) !== null) {
                $catTotal += (float)($sub['cat2'] ?? 0);
                $catMax += 100;
            }
            if (($sub['cat3'] ?? null) !== 'nil' && ($sub['cat3'] ?? null) !== null) {
                $catTotal += (float)($sub['cat3'] ?? 0);
                $catMax += 100;
            }
            $subjectEntry['cat_total'] = $catTotal;
            $subjectEntry['cat_max'] = $catMax;
            $subjectEntry['cat_percentage'] = $catMax > 0 ? round(($catTotal / $catMax) * 100, 2) : 0;
        }
        
        // Add Final CA marks if available - convert to scale of 100
        if (isset($sub['ca_scored']) && $sub['ca_max'] > 0) {
            $subjectEntry['ca_scored'] = $sub['ca_scored'] ?? null;
            $subjectEntry['ca_max'] = $sub['ca_max'] ?? null;
            $subjectEntry['ca_percent'] = $sub['ca_percent'] ?? null;
            // Convert CA marks to scale of 100
            $subjectEntry['ca_out_of_100'] = round(($sub['ca_scored'] / $sub['ca_max']) * 100, 2);
        } else if (isset($sub['ca_scored'])) {
            $subjectEntry['ca_scored'] = $sub['ca_scored'] ?? null;
            $subjectEntry['ca_max'] = $sub['ca_max'] ?? null;
            $subjectEntry['ca_percent'] = $sub['ca_percent'] ?? null;
            $subjectEntry['ca_out_of_100'] = 0;
        }
        
        $subjectsData[] = $subjectEntry;
    }
    
    // Calculate CAT totals for the semester
    $cat1Total = 0; $cat1Max = 0;
    $cat2Total = 0; $cat2Max = 0;
    $cat3Total = 0; $cat3Max = 0;
    
    foreach ($internalSubs as $sub) {
        if ((int)($sub['credits'] ?? 0) === 0) continue;
        if (($sub['cat1'] ?? null) !== 'nil') { $cat1Max += 100; $cat1Total += (float)($sub['cat1'] ?? 0); }
        if (($sub['cat2'] ?? null) !== 'nil') { $cat2Max += 100; $cat2Total += (float)($sub['cat2'] ?? 0); }
        if (($sub['cat3'] ?? null) !== 'nil') { $cat3Max += 100; $cat3Total += (float)($sub['cat3'] ?? 0); }
    }
    
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
        'sem_id' => (string)$s['_id'] ?? null,
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
        'credits_done' => $s['credits_done'] ?? false,
        'subjects' => $subjectsData,
        'cat1_total' => $cat1Total,
        'cat1_max' => $cat1Max,
        'cat1_percent' => $cat1Max > 0 ? round(($cat1Total / $cat1Max) * 100, 2) : 0,
        'cat2_total' => $cat2Total,
        'cat2_max' => $cat2Max,
        'cat2_percent' => $cat2Max > 0 ? round(($cat2Total / $cat2Max) * 100, 2) : 0,
        'cat3_total' => $cat3Total,
        'cat3_max' => $cat3Max,
        'cat3_percent' => $cat3Max > 0 ? round(($cat3Total / $cat3Max) * 100, 2) : 0
    ];
}

echo json_encode([
    'status' => 'success', 
    'student' => $studentData, 
    'semesters' => $semesters
]);

