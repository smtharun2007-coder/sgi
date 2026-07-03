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
            'credit_deletions' => $approval['credit_deletions'] ?? [],
            'existing_subjects' => $approval['existing_subjects'] ?? [],
            'existing_non_internal' => $approval['existing_non_internal'] ?? [],
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
                
                // Save subjects - handle both array and MongoDB BSON array
                $subjectsData = $approval['subjects'] ?? [];
                if (!empty($subjectsData)) {
                    // Convert MongoDB BSON array to PHP array if needed
                    if ($subjectsData instanceof \MongoDB\Model\BSONArray) {
                        $subjectsData = $subjectsData->getArrayCopy();
                    }
                    foreach ($subjectsData as $subject) {
                        // Convert BSONDocument to array if needed
                        if ($subject instanceof \MongoDB\Model\BSONDocument) {
                            $subject = (array)$subject;
                        }
                        $subjects->insertOne([
                            'sem_id' => $sem_id,
                            'roll' => $roll,
                            'subject_name' => $subject['name'] ?? '',
                            'subject_code' => $subject['code'] ?? '',
                            'credits' => (int)($subject['credits'] ?? 0),
                            'internal' => $subject['internal'] ?? 'no',
                            'approved_from_approval' => true
                        ]);
                    }
                }
                break;
                
            case 'Credit Subjects':
                // Handle credit subject changes (additions and deletions)
                $existingSem = $semesters->findOne(['roll' => $roll, 'sem' => (int)$sem]);
                if ($existingSem) {
                    $sem_id = (string)$existingSem['_id'];
                    
                    // Handle additions - add new credit subjects
                    $creditSubjects = $approval['credit_subjects'] ?? [];
                    if ($creditSubjects instanceof \MongoDB\Model\BSONArray) {
                        $creditSubjects = $creditSubjects->getArrayCopy();
                    }
                    if (!empty($creditSubjects) && is_array($creditSubjects)) {
                        foreach ($creditSubjects as $subject) {
                            // Handle BSONDocument to array conversion
                            if ($subject instanceof \MongoDB\Model\BSONDocument) {
                                $subject = (array)$subject;
                            }
                            // Handle both new format (with temp_id) and old format
                            $subjectName = $subject['subject_name'] ?? $subject['name'] ?? '';
                            $subjectCode = $subject['subject_code'] ?? $subject['code'] ?? '';
                            $credits = (int)($subject['credits'] ?? 0);
                            
                            $subjects->insertOne([
                                'sem_id' => $sem_id,
                                'roll' => $roll,
                                'subject_name' => $subjectName,
                                'subject_code' => $subjectCode,
                                'credits' => $credits,
                                'internal' => 'no',
                                'approved_from_approval' => true
                            ]);
                        }
                    }
                    
                    // Handle deletions - remove subjects that were marked for deletion
                    $creditDeletions = $approval['credit_deletions'] ?? [];
                    if ($creditDeletions instanceof \MongoDB\Model\BSONArray) {
                        $creditDeletions = $creditDeletions->getArrayCopy();
                    }
                    if (!empty($creditDeletions) && is_array($creditDeletions)) {
                        foreach ($creditDeletions as $deletedSubject) {
                            // Handle BSONDocument to array conversion
                            if ($deletedSubject instanceof \MongoDB\Model\BSONDocument) {
                                $deletedSubject = (array)$deletedSubject;
                            }
                            // Handle both new format (with subject_id) and old format
                            $subjectId = $deletedSubject['subject_id'] ?? $deletedSubject['id'] ?? '';
                            if (!empty($subjectId)) {
                                $subjects->deleteOne(['_id' => new MongoDB\BSON\ObjectId($subjectId)]);
                            }
                        }
                    }
                    
                    // Mark credits as done
                    $semesters->updateOne(['_id' => $existingSem['_id']], ['$set' => ['credits_done' => true]]);
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
                    
                    // Handle BSONDocument conversion for caData
                    if ($caData instanceof \MongoDB\Model\BSONDocument) {
                        $caData = (array)$caData;
                    }
                    
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
                    $caSubjects = $approval['ca_subjects'] ?? [];
                    if ($caSubjects instanceof \MongoDB\Model\BSONArray) {
                        $caSubjects = $caSubjects->getArrayCopy();
                    }
                    if (!empty($caSubjects) && is_array($caSubjects)) {
                        foreach ($caSubjects as $caSub) {
                            // Handle BSONDocument to array conversion
                            if ($caSub instanceof \MongoDB\Model\BSONDocument) {
                                $caSub = (array)$caSub;
                            }
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
                    
                    // Handle BSONDocument conversion
                    if ($sgiData instanceof \MongoDB\Model\BSONDocument) {
                        $sgiData = (array)$sgiData;
                    }
                    
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
                
            case 'Project Evaluation':
                // Handle project evaluation by evaluator
                // When evaluator approves/rejects, update the SGI approval and notify mentor/student
                if (!empty($approval['project_data'])) {
                    $projectData = $approval['project_data'];
                    if ($projectData instanceof \MongoDB\Model\BSONDocument) {
                        $projectData = (array)$projectData;
                    }
                    
                    $mentorId = $projectData['submitted_by_mentor'] ?? '';
                    $projectName = $projectData['project_name'] ?? '';
                    $studentName = $projectData['submitted_by'] ?? '';
                    $semester = $approval['semester'] ?? null;
                    
                    // Find the related SGI Calculation approval for this student and semester
                    $sgiApproval = $approvals->findOne([
                        'student_roll' => $roll,
                        'semester' => (int)$semester,
                        'type' => 'SGI Calculation',
                        'status' => 'pending'
                    ]);
                    
                    if ($sgiApproval) {
                        $sgiApprovalId = (string)$sgiApproval['_id'];
                        $sgiData = $sgiApproval['sgi_data'] ?? [];
                        if ($sgiData instanceof \MongoDB\Model\BSONDocument) {
                            $sgiData = (array)$sgiData;
                        }
                        
                        // Update the pending_evaluator_projects list
                        $pendingProjects = $sgiData['pending_evaluator_projects'] ?? [];
                        $otherProjects = $sgiData['other_projects'] ?? [];
                        
                        // Remove this project from pending list
                        $pendingProjects = array_filter($pendingProjects, function($p) use ($projectName) {
                            return ($p['project_name'] ?? '') !== $projectName;
                        });
                        $pendingProjects = array_values($pendingProjects);
                        
                        // Add to other_projects with status
                        $foundProject = null;
                        foreach ($otherProjects as &$proj) {
                            if (($proj['name'] ?? '') === $projectName) {
                                $proj['evaluator_status'] = $status;
                                $proj['evaluator_remarks'] = $remarks ?? '';
                                $foundProject = $proj;
                                break;
                            }
                        }
                        unset($proj);
                        
                        // Update the SGI approval with new status
                        $approvals->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($sgiApprovalId)],
                            ['$set' => [
                                'sgi_data.pending_evaluator_projects' => $pendingProjects,
                                'sgi_data.other_projects' => $otherProjects,
                                'updated_at' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );
                        
                        // Check if all evaluator projects are now approved
                        $allApproved = empty($pendingProjects);
                        $hasRejections = false;
                        foreach ($otherProjects as $proj) {
                            if (isset($proj['evaluator_status']) && $proj['evaluator_status'] === 'rejected') {
                                $hasRejections = true;
                                break;
                            }
                        }
                    }
                    
                    if (!empty($mentorId)) {
                        if ($status === 'approved') {
                            // Notify the student's mentor that evaluator has approved
                            $notifications->insertOne([
                                'mentor_id' => $mentorId,
                                'message' => '✅ Evaluator has approved the other project "' . $projectName . '" for student ' . $studentName . ' (' . $roll . '). You can now proceed with SGI approval if all projects are approved.',
                                'read' => false,
                                'created_at' => new MongoDB\BSON\UTCDateTime()
                            ]);
                        } else {
                            // Notify the student's mentor that evaluator has rejected
                            $notifications->insertOne([
                                'mentor_id' => $mentorId,
                                'message' => '❌ Evaluator has REJECTED the other project "' . $projectName . '" for student ' . $studentName . ' (' . $roll . '). Reason: ' . ($remarks ?: 'Not specified'). '. You cannot approve SGI until this is resolved.',
                                'read' => false,
                                'created_at' => new MongoDB\BSON\UTCDateTime()
                            ]);
                        }
                    }
                    
                    // Notify the student
                    if ($status === 'approved') {
                        $notifications->insertOne([
                            'roll' => $roll,
                            'message' => '✅ Your other project "' . $projectName . '" has been approved by the evaluator.',
                            'read' => false,
                            'created_at' => new MongoDB\BSON\UTCDateTime()
                        ]);
                    } else {
                        $notifications->insertOne([
                            'roll' => $roll,
                            'message' => '❌ Your other project "' . $projectName . '" has been REJECTED by the evaluator. Reason: ' . ($remarks ?: 'Not specified'). '. Please contact your evaluator to resolve this.',
                            'read' => false,
                            'created_at' => new MongoDB\BSON\UTCDateTime()
                        ]);
                    }
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