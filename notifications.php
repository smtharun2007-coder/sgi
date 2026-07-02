<?php
include 'config.php';

$isMentor = isset($_SESSION['mentor']);
$isStudent = isset($_SESSION['user']);

if (!$isMentor && !$isStudent) {
    header('Content-Type: application/json');
    echo json_encode([]); exit;
}

// Mark all read — always return JSON, never plain text
if (isset($_GET['mark_all'])) {
    if ($isMentor) {
        $notifications->updateMany(['mentor_id'=>$_SESSION['mentor']['mentor_id'],'read'=>false],['$set'=>['read'=>true]]);
    } else {
        $notifications->updateMany(['roll'=>$_SESSION['user']['roll'],'read'=>false],['$set'=>['read'=>true]]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']); exit;
}

// Delete all notifications
if (isset($_GET['delete_all'])) {
    if ($isMentor) {
        $notifications->deleteMany(['mentor_id'=>$_SESSION['mentor']['mentor_id']]);
    } else {
        $notifications->deleteMany(['roll'=>$_SESSION['user']['roll']]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']); exit;
}

// Fetch notifications as JSON
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    if ($isMentor) {
        $cur = $notifications->find(['mentor_id'=>$_SESSION['mentor']['mentor_id']],['sort'=>['created_at'=>-1],'limit'=>20]);
    } else {
        $cur = $notifications->find(['roll'=>$_SESSION['user']['roll']],['sort'=>['created_at'=>-1],'limit'=>20]);
    }
    $result = [];
    foreach ($cur as $n) {
        $result[] = [
            'message' => htmlspecialchars($n['message']),
            'read'    => (bool)$n['read'],
            'time'    => date('d M, h:i A', $n['created_at']->toDateTime()->getTimestamp())
        ];
    }
    echo json_encode($result);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status'=>'ok']);
