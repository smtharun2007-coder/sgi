<?php
include 'config.php';

header('Content-Type: application/json');

// Check authentication
$isMentor = isset($_SESSION['mentor']);
$isStudent = isset($_SESSION['user']);

if (!$isMentor && !$isStudent) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Helper function to create notification
function createNotification($db, $notifications, $recipientId, $recipientField, $message) {
    $notifications->insertOne([
        $recipientField => $recipientId,
        'message' => $message,
        'read' => false,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
}

// GET - Fetch approvals
if (isset($_GET['fetch'])) {
    if ($isStudent) {
        // Student fetches their own approvals
        $roll = $_SESSION['user']['roll'];
        $cursor = $approvals->find(
            ['student_roll' => $roll],
            ['sort' => ['created_at' => -1]]
        );
    } else {
        // Mentor fetches approvals for their students
        $mentorId = $_SESSION['mentor']['mentor_id'];
        
        // Get all students of this mentor
        $students = $users->find(['mentor_id' => $mentorId], ['projection' => ['roll' => 1]]);
        $studentRolls = [];
        foreach ($students as $student) {
            $studentRolls[] = $student['roll'];
        }
        
        if (empty($studentRolls)) {
            echo json_encode([]);
            exit;
        }
        
        $cursor = $approvals->find(
            ['student_roll' => ['$in' => $studentRolls]],
            ['sort' => ['created_at' => -1]]
        );
    }
    
    $result = [];
    foreach ($cursor as $approval) {
        $result[] = [
            '_id' => (string)$approval['_id'],
            'student_roll' => $approval['student_roll'],
            'student_name' => $approval['student_name'],
            'type' => $approval['type'],
            'semester' => $approval['semester'] ?? null,
            'reg' => $approval['reg'] ?? '',
            'subject' => $approval['subject'] ?? '',
            'subjects' => $approval['subjects'] ?? [],
            'subject_count' => $approval['subject_count'] ?? 0,
            'message' => $approval['message'],
            'status' => $approval['status'],
            'mentor_remarks' => $approval['mentor_remarks'] ?? '',
            // New approval types data
            'credit_subjects' => $approval['credit_subjects'] ?? [],
            'existing_subjects' => $approval['existing_subjects'] ?? [],
            'verification_data' => $approval['verification_data'] ?? [],
            'cat_marks' => $approval['cat_marks'] ?? [],
            'ca_data' => $approval['ca_data'] ?? [],
            'ca_subjects' => $approval['ca_subjects'] ?? [],
            'sgi_data' => $approval['sgi_data'] ?? [],
            'documents' => $approval['documents'] ?? [],
            'created_at' => date('d M, Y h:i A', $approval['created_at']->toDateTime()->getTimestamp()),
            'updated_at' => isset($approval['updated_at']) ? date('d M, Y h:i A', $approval['updated_at']->toDateTime()->getTimestamp()) : ''
        ];
    }
    
    echo json_encode($result);
    exit;
}

// GET - Fetch single approval details
if (isset($_GET['get_approval'])) {
    $approvalId = $_GET['get_approval'];
    $approval = $approvals->findOne(['_id' => new MongoDB\BSON\ObjectId($approvalId)]);
    
    if (!$approval) {
        echo json_encode(['status' => 'error', 'message' => 'Approval not found']);
        exit;
    }
    
    // Check permission
    if ($isStudent && $approval['student_roll'] !== $_SESSION['user']['roll']) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    if ($isMentor) {
        $student = $users->findOne(['roll' => $approval['student_roll']]);
        if (!$student || $student['mentor_id'] !== $_SESSION['mentor']['mentor_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
    }
    
    echo json_encode([
        '_id' => (string)$approval['_id'],
        'student_roll' => $approval['student_roll'],
        'student_name' => $approval['student_name'],
        'type' => $approval['type'],
        'subject' => $approval['subject'] ?? '',
        'message' => $approval['message'],
        'status' => $approval['status'],
        'mentor_remarks' => $approval['mentor_remarks'] ?? '',
        'created_at' => date('d M, Y h:i A', $approval['created_at']->toDateTime()->getTimestamp()),
        'updated_at' => isset($approval['updated_at']) ? date('d M, Y h:i A', $approval['updated_at']->toDateTime()->getTimestamp()) : ''
    ]);
    exit;
}

// POST - Create new approval request (Student only)
if (isset($_POST['create']) && $isStudent) {
    $type = trim($_POST['type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($type) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Type and message are required']);
        exit;
    }
    
    $student = $_SESSION['user'];
    
    $approvalData = [
        'student_roll' => $student['roll'],
        'student_name' => $student['name'],
        'type' => $type,
        'subject' => $subject,
        'message' => $message,
        'status' => 'pending',
        'mentor_id' => $student['mentor_id'] ?? '',
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $result = $approvals->insertOne($approvalData);
    
    // Create notification for mentor
    if (!empty($student['mentor_id'])) {
        createNotification(
            $db, $notifications,
            $student['mentor_id'],
            'mentor_id',
            '📝 New approval request from ' . $student['name'] . ' (' . $student['roll'] . ') - ' . $type
        );
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Approval request submitted successfully', 'id' => (string)$result->getInsertedId()]);
    exit;
}

// POST - Update approval status (Mentor only)
if (isset($_POST['update_status']) && $isMentor) {
    $approvalId = $_POST['approval_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (empty($approvalId) || empty($status)) {
        echo json_encode(['status' => 'error', 'message' => 'Approval ID and status are required']);
        exit;
    }
    
    if (!in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
        exit;
    }
    
    $approval = $approvals->findOne(['_id' => new MongoDB\BSON\ObjectId($approvalId)]);
    
    if (!$approval) {
        echo json_encode(['status' => 'error', 'message' => 'Approval not found']);
        exit;
    }
    
    // Check if already processed
    $currentStatus = $approval['status'] ?? '';
    if ($currentStatus !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'This request has already been processed (current status: ' . $currentStatus . ')']);
        exit;
    }
    
    // Check if mentor is assigned to this student
    $student = $users->findOne(['roll' => $approval['student_roll']]);
    if (!$student || $student['mentor_id'] !== $_SESSION['mentor']['mentor_id']) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Not your student']);
        exit;
    }
    
    $roll = $approval['student_roll'];
    $sem = $approval['semester'] ?? null;
    
    // Update approval status
    $approvals->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($approvalId)],
        ['$set' => [
            'status' => $status,
            'mentor_remarks' => $remarks,
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
            'processed_by' => $_SESSION['mentor']['mentor_id'],
            'processed_at' => new MongoDB\BSON\UTCDateTime()
        ]]
    );
    
    if ($status === 'approved') {
        $approvalType = $approval['type'] ?? '';
        
        // Handle different approval types
        switch ($approvalType) {
            case 'Semester Registration':
                // Save semester and subjects
                $reg = $approval['reg'] ?? $student['reg'];
                $mentorId = $approval['mentor_id'] ?? '';
                
                $result = $semesters->insertOne([
                    'roll' => $roll,
                    'reg' => $reg,
                    'sem' => (int)$sem,
                    'mentor_id' => $mentorId,
                    'approved_from_approval' => true,
                    'approval_id' => (string)$approvalId
                ]);
                $sem_id = (string)$result->getInsertedId();
                
                if (!empty($approval['subjects']) && is_array($approval['subjects'])) {
                    foreach ($approval['subjects'] as $subject) {
                        $subjects->insertOne([
                            'sem_id' => $sem_id,
                            'roll' => $roll,
                            'subject_name' => $subject['name'],
                            'subject_code' => $subject['code'],
                            'credits' => (int)$subject['credits'],
                            'internal' => $subject['internal'],
                            'approved_from_approval' => true
                        ]);
                    }
                }
                break;
                
            case 'Credit Subjects':
                // Add credit subjects to existing semester
                if (!empty($approval['credit_subjects']) && is_array($approval['credit_subjects'])) {
                    $existingSem = $semesters->findOne(['roll' => $roll, 'sem' => (int)$sem]);
                    if ($existingSem) {
                        $sem_id = (string)$existingSem['_id'];
                        foreach ($approval['credit_subjects'] as $subject) {
                            $subjects->insertOne([
                                'sem_id' => $sem_id,
                                'roll' => $roll,
                                'subject_name' => $subject['name'],
                                'subject_code' => $subject['code'],
                                'credits' => (int)$subject['credits'],
                                'internal' => 'no',
                                'approved_from_approval' => true
                            ]);
                        }
                        // Mark credits as done
                        $semesters->updateOne(['_id' => $existingSem['_id']], ['$set' => ['credits_done' => true]]);
                    }
                }
                break;
                
            case 'Verification':
                // Save verified marks
                $existingSem = $semesters->findOne(['roll' => $roll, 'sem' => (int)$sem]);
                if ($existingSem && !empty($approval['verification_data'])) {
                    $sem_id = (string)$existingSem['_id'];
                    $vData = $approval['verification_data'];
                    
                    // Update semester with verified data
                    $semesters->updateOne(['_id' => $existingSem['_id']], [
                        '$set' => [
                            'prev_gpa' => (float)($vData['prev_gpa'] ?? 0),
                            'attendance' => (float)($vData['attendance'] ?? 0),
                            'verified' => true
                        ]
                    ]);
                    
                    // Update CAT marks if provided
                    if (!empty($approval['cat_marks']) && is_array($approval['cat_marks'])) {
                        foreach ($approval['cat_marks'] as $catMark) {
                            if (!empty($catMark['subject_id'])) {
                                $subjects->updateOne(
                                    ['_id' => new MongoDB\BSON\ObjectId($catMark['subject_id'])],
                                    ['$set' => [
                                        'cat1' => isset($catMark['cat1']) ? ($catMark['cat1'] === 'nil' ? 'nil' : (float)$catMark['cat1']) : null,
                                        'cat2' => isset($catMark['cat2']) ? ($catMark['cat2'] === 'nil' ? 'nil' : (float)$catMark['cat2']) : null,
                                        'cat3' => isset($catMark['cat3']) ? ($catMark['cat3'] === 'nil' ? 'nil' : (float)$catMark['cat3']) : null
                                    ]]
                                );
                            }
                        }
                    }
                }
                break;
                
            case 'Final CA Marks':
                // Save final CA marks and documents
                $existingSem = $semesters->findOne(['roll' => $roll, 'sem' => (int)$sem]);
                if ($existingSem && !empty($approval['ca_data'])) {
                    $sem_id = (string)$existingSem['_id'];
                    $caData = $approval['ca_data'];
                    
                    // Update semester with CA data
                    $updateData = [
                        'gpa' => (float)($caData['gpa'] ?? 0),
                        'cgpa' => (float)($caData['cgpa'] ?? 0),
                        'ca_done' => true
                    ];
                    
                    // Handle document uploads (stored as base64 or URLs in approval)
                    if (!empty($caData['result_photo'])) {
                        $updateData['result_photo'] = $caData['result_photo'];
                    }
                    if (!empty($caData['ca_photo'])) {
                        $updateData['ca_photo'] = $caData['ca_photo'];
                    }
                    
                    $semesters->updateOne(['_id' => $existingSem['_id']], ['$set' => $updateData]);
                    
                    // Update CA marks for subjects
                    if (!empty($approval['ca_subjects']) && is_array($approval['ca_subjects'])) {
                        foreach ($approval['ca_subjects'] as $caSub) {
                            if (!empty($caSub['subject_id'])) {
                                $subjects->updateOne(
                                    ['_id' => new MongoDB\BSON\ObjectId($caSub['subject_id'])],
                                    ['$set' => [
                                        'ca_scored' => (float)($caSub['ca_scored'] ?? 0),
                                        'ca_max' => (float)($caSub['ca_max'] ?? 0),
                                        'ca_percent' => (float)($caSub['ca_percent'] ?? 0)
                                    ]]
                                );
                            }
                        }
                    }
                }
                break;
                
            case 'SGI Calculation':
                // Save SGI calculation results
                $existingSem = $semesters->findOne(['roll' => $roll, 'sem' => (int)$sem]);
                if ($existingSem && !empty($approval['sgi_data'])) {
                    $sgiData = $approval['sgi_data'];
                    
                    $semesters->updateOne(['_id' => $existingSem['_id']], ['$set' => [
                        'sgi' => (float)($sgiData['sgi'] ?? 0),
                        'academic_score' => (float)($sgiData['academic_score'] ?? 0),
                        'skills_score' => (float)($sgiData['skills_score'] ?? 0),
                        'projects_score' => (float)($sgiData['projects_score'] ?? 0),
                        'activities_score' => (float)($sgiData['activities_score'] ?? 0),
                        'discipline_score' => (float)($sgiData['discipline_score'] ?? 0)
                    ]]);
                }
                break;
        }
        
        // Create notification for student
        createNotification(
            $db, $notifications,
            $roll,
            'roll',
            '✅ Your ' . $approvalType . ' request for Semester ' . $sem . ' has been APPROVED!' . ($remarks ? ' Remarks: ' . $remarks : '')
        );
    } else {
        // If rejected, create notification for student
        createNotification(
            $db, $notifications,
            $roll,
            'roll',
            '❌ Your ' . ($approval['type'] ?? 'Request') . ' for Semester ' . $sem . ' has been REJECTED.' . ($remarks ? ' Reason: ' . $remarks : '')
        );
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Approval status updated successfully']);
    exit;
}

// POST - Delete approval (Student only, pending requests)
if (isset($_POST['delete']) && $isStudent) {
    $approvalId = $_POST['approval_id'] ?? '';
    
    if (empty($approvalId)) {
        echo json_encode(['status' => 'error', 'message' => 'Approval ID is required']);
        exit;
    }
    
    $approval = $approvals->findOne(['_id' => new MongoDB\BSON\ObjectId($approvalId)]);
    
    if (!$approval || $approval['student_roll'] !== $_SESSION['user']['roll']) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    // Only allow deletion of pending requests
    if ($approval['status'] !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'Only pending requests can be deleted']);
        exit;
    }
    
    $approvals->deleteOne(['_id' => new MongoDB\BSON\ObjectId($approvalId)]);
    
    echo json_encode(['status' => 'success', 'message' => 'Approval request deleted']);
    exit;
}

// GET - Get approval statistics (Mentor only)
if (isset($_GET['stats']) && $isMentor) {
    $mentorId = $_SESSION['mentor']['mentor_id'];
    
    // Get all students of this mentor
    $students = $users->find(['mentor_id' => $mentorId], ['projection' => ['roll' => 1]]);
    $studentRolls = [];
    foreach ($students as $student) {
        $studentRolls[] = $student['roll'];
    }
    
    if (empty($studentRolls)) {
        echo json_encode(['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0]);
        exit;
    }
    
    // Count by status
    $pending = $approvals->countDocuments(['student_roll' => ['$in' => $studentRolls], 'status' => 'pending']);
    $approved = $approvals->countDocuments(['student_roll' => ['$in' => $studentRolls], 'status' => 'approved']);
    $rejected = $approvals->countDocuments(['student_roll' => ['$in' => $studentRolls], 'status' => 'rejected']);
    
    echo json_encode([
        'pending' => $pending,
        'approved' => $approved,
        'rejected' => $rejected,
        'total' => $pending + $approved + $rejected
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);