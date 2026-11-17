<?php
// fcm_helper.php — Firebase Cloud Messaging push notification dispatcher

if (!function_exists('sgi_env')) {
    function sgi_env($key, $default = '') {
        $val = getenv($key);
        if ($val === false || $val === '') {
            if (isset($_ENV[$key]) && $_ENV[$key] !== '') $val = $_ENV[$key];
            elseif (isset($_SERVER[$key]) && $_SERVER[$key] !== '') $val = $_SERVER[$key];
            else $val = $default;
        }
        return is_string($val) ? trim($val) : $val;
    }
}

/**
 * Dispatch an FCM notification to a specific user (by roll number, student_id, or mentor_id).
 */
function sendFcmNotificationToUser($identifier, $title, $body, $targetUrl = '') {
    global $db;
    if (empty($identifier) || empty($db)) return false;

    try {
        $cursor = $db->fcm_tokens->find([
            'identifier' => (string)$identifier,
            'fcm_token'  => ['$exists' => true, '$ne' => '']
        ]);

        $tokens = [];
        foreach ($cursor as $doc) {
            if (!empty($doc['fcm_token'])) {
                $tokens[] = (string)$doc['fcm_token'];
            }
        }

        if (empty($tokens)) return false;

        return sendFcmNotificationRaw($tokens, $title, $body, $targetUrl);
    } catch (Exception $e) {
        error_log("FCM error finding tokens: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification directly to one or more FCM tokens
 */
function sendFcmNotificationRaw($tokens, $title, $body, $targetUrl = '') {
    if (empty($tokens)) return false;
    if (is_string($tokens)) $tokens = [$tokens];

    $serverKey = sgi_env('FCM_SERVER_KEY');
    if (empty($serverKey)) {
        // If server key is not configured in environment yet, tokens are still recorded
        return false;
    }

    $url = 'https://fcm.googleapis.com/fcm/send';

    $payload = [
        'registration_ids' => array_values(array_unique($tokens)),
        'notification' => [
            'title'        => $title,
            'body'         => $body,
            'sound'        => 'default',
            'click_action' => 'OPEN_ACTIVITY'
        ],
        'data' => [
            'title'   => $title,
            'body'    => $body,
            'message' => $body,
            'url'     => $targetUrl
        ],
        'priority' => 'high'
    ];

    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}
