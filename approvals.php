d <?php
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
    if ($approval['status'] !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'This request has already been processed']);
        exit;
    }
    
    // Check if mentor is assigned to this student
    $student = $users->findOne(['roll' => $approval['student_roll']]);
    if (!$student || $student['mentor_id'] !== $_SESSION['mentor']['mentor_id']) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Not your student']);
        exit;
    }
    
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
        // If approved, save the semester and subjects to database
        $roll = $approval['student_roll'];
        $reg = $approval['reg'] ?? $student['reg'];
        $sem = $approval['semester'];
        $mentorId = $approval['mentor_id'] ?? '';
        
        // Insert semester record
        $result = $semesters->insertOne([
            'roll' => $roll,
            'reg' => $reg,
            'sem' => (int)$sem,
            'mentor_id' => $mentorId,
            'approved_from_approval' => true,
            'approval_id' => (string)$approvalId
        ]);
        $sem_id = (string)$result->getInsertedId();
        
        // Insert subjects if available
        if (!empty($approval['subjects']) && is_array($approval['subjects'])) {
            foreach ($approval['subjects'] as $subject) {
                $subjects->insertOne([
                    'sem_id'       => $sem_id,
                    'roll'         => $roll,
                    'subject_name' => $subject['name'],
                    'subject_code' => $subject['code'],
                    'credits'      => (int)$subject['credits'],
                    'internal'     => $subject['internal'],
                    'approved_from_approval' => true
                ]);
            }
        }
        
        // Create notification for student
        createNotification(
            $db, $notifications,
            $approval['student_roll'],
            'roll',
            '✅ Your semester ' . $sem . ' registration has been APPROVED by your mentor!' . ($remarks ? ' Remarks: ' . $remarks : '')
        );
    } else {
        // If rejected, create notification for student
        createNotification(
            $db, $notifications,
            $approval['student_roll'],
            'roll',
            '❌ Your semester ' . ($approval['semester'] ?? '') . ' registration has been REJECTED by your mentor.' . ($remarks ? ' Reason: ' . $remarks : '')
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