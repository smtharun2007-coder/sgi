<?php
include 'config.php';

$isMentor = isset($_SESSION['mentor']);
$isStudent = isset($_SESSION['user']);

if (!$isMentor && !$isStudent) {
    echo json_encode([]); exit;
}

// Mark all read
if (isset($_GET['mark_all'])) {
    if ($isMentor) {
        $notifications->updateMany(['mentor_id'=>$_SESSION['mentor']['mentor_id'],'read'=>false],['$set'=>['read'=>true]]);
    } else {
        $notifications->updateMany(['roll'=>$_SESSION['user']['roll'],'read'=>false],['$set'=>['read'=>true]]);
    }
    if (!isset($_GET['fetch'])) { echo 'ok'; exit; }
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
            'message' => $n['message'],
            'read'    => (bool)$n['read'],
            'time'    => date('d M, h:i A', $n['created_at']->toDateTime()->getTimestamp())
        ];
    }
    // Mark all as read after fetching
    if ($isMentor) {
        $notifications->updateMany(['mentor_id'=>$_SESSION['mentor']['mentor_id'],'read'=>false],['$set'=>['read'=>true]]);
    } else {
        $notifications->updateMany(['roll'=>$_SESSION['user']['roll'],'read'=>false],['$set'=>['read'=>true]]);
    }
    echo json_encode($result);
    exit;
}

echo 'ok';
