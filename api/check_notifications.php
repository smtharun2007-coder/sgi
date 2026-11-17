<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$fcmToken = trim($_GET['fcm_token'] ?? '');
$fallbackRoll = trim($_GET['roll'] ?? $_GET['identifier'] ?? '');
$fallbackType = trim($_GET['type'] ?? 'student');
$identifier = null;
$userType = null;

if (!empty($_SESSION['user']['roll'])) {
    $identifier = (string)$_SESSION['user']['roll'];
    $userType = 'student';
} elseif (!empty($_SESSION['roll'])) {
    $identifier = (string)$_SESSION['roll'];
    $userType = 'student';
} elseif (!empty($_SESSION['mentor']['mentor_id'])) {
    $identifier = (string)$_SESSION['mentor']['mentor_id'];
    $userType = 'mentor';
} elseif (!empty($_SESSION['mentor_id'])) {
    $identifier = (string)$_SESSION['mentor_id'];
    $userType = 'mentor';
} elseif (!empty($fallbackRoll)) {
    $identifier = $fallbackRoll;
    $userType = $fallbackType;
} elseif (!empty($fcmToken)) {
    $tokenDoc = $db->fcm_tokens->findOne(['fcm_token' => $fcmToken]);
    if ($tokenDoc && !empty($tokenDoc['identifier'])) {
        $identifier = (string)$tokenDoc['identifier'];
        $userType = $tokenDoc['user_type'] ?? 'student';
    }
}

if (empty($identifier)) {
    echo json_encode(['success' => true, 'notifications' => []]);
    exit;
}

try {
    $query = ['read' => false];
    if ($userType === 'mentor') {
        $query['mentor_id'] = $identifier;
    } else {
        $query['$or'] = [
            ['roll' => $identifier],
            ['student_id' => $identifier],
            ['user_id' => $identifier]
        ];
    }

    $cursor = $db->notifications->find($query, [
        'sort' => ['created_at' => -1],
        'limit' => 10
    ]);

    $notifs = [];
    foreach ($cursor as $doc) {
        $notifs[] = [
            'id'      => (string)$doc['_id'],
            'title'   => 'SGI Alert',
            'message' => (string)($doc['message'] ?? ''),
            'link'    => (string)($doc['link'] ?? '')
        ];
    }

    echo json_encode(['success' => true, 'notifications' => $notifs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'notifications' => []]);
}
