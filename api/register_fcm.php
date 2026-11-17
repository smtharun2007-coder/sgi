<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$fcmToken = trim($_POST['fcm_token'] ?? '');
if (empty($fcmToken) || strlen($fcmToken) < 10) {
    echo json_encode(['success' => false, 'error' => 'Invalid FCM token']);
    exit;
}

$userType = 'guest';
$identifier = null;
$email = null;

$fallbackIdentifier = trim($_POST['roll'] ?? $_POST['identifier'] ?? '');
$fallbackType = trim($_POST['type'] ?? 'student');

if (!empty($_SESSION['user']['roll'])) {
    $userType = 'student';
    $identifier = (string)$_SESSION['user']['roll'];
    $email = $_SESSION['user']['email'] ?? null;
} elseif (!empty($_SESSION['roll'])) {
    $userType = 'student';
    $identifier = (string)$_SESSION['roll'];
    $email = $_SESSION['email'] ?? null;
} elseif (!empty($_SESSION['mentor']['mentor_id'])) {
    $userType = 'mentor';
    $identifier = (string)$_SESSION['mentor']['mentor_id'];
    $email = $_SESSION['mentor']['email'] ?? null;
} elseif (!empty($_SESSION['mentor_id'])) {
    $userType = 'mentor';
    $identifier = (string)$_SESSION['mentor_id'];
    $email = $_SESSION['email'] ?? null;
} elseif (!empty($fallbackIdentifier)) {
    $userType = $fallbackType;
    $identifier = $fallbackIdentifier;
}

try {
    $fcmCollection = $db->fcm_tokens;
    $fcmCollection->updateOne(
        ['fcm_token' => $fcmToken],
        ['$set' => [
            'fcm_token'   => $fcmToken,
            'user_type'   => $userType,
            'identifier'  => $identifier,
            'email'       => $email,
            'updated_at'  => new MongoDB\BSON\UTCDateTime()
        ]],
        ['upsert' => true]
    );

    echo json_encode(['success' => true, 'registered_as' => $userType, 'identifier' => $identifier]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
