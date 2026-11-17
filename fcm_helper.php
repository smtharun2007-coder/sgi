<?php
// fcm_helper.php — Firebase Cloud Messaging HTTP v1 API Push Notification Dispatcher

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
 * Base64Url encode helper for JWT
 */
function sgi_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Get Google OAuth 2.0 Access Token using Service Account credentials
 */
function getGoogleOAuth2AccessToken() {
    static $cachedToken = null;
    static $tokenExpiry = 0;

    if ($cachedToken && time() < ($tokenExpiry - 300)) {
        return $cachedToken;
    }

    $saData = null;
    $saPath = __DIR__ . '/firebase_service_account.json';
    if (file_exists($saPath)) {
        $saData = json_decode(file_get_contents($saPath), true);
    } elseif ($envJson = sgi_env('FIREBASE_SERVICE_ACCOUNT_JSON')) {
        $saData = json_decode($envJson, true);
    }

    if (!$saData || empty($saData['private_key']) || empty($saData['client_email'])) {
        return null;
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $saData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600
    ];

    $jwtHeader = sgi_base64url_encode(json_encode($header));
    $jwtClaims = sgi_base64url_encode(json_encode($claims));
    $signingInput = $jwtHeader . '.' . $jwtClaims;

    $signature = '';
    $privateKey = openssl_pkey_get_private($saData['private_key']);
    if (!$privateKey) {
        error_log("FCM Error: Failed to load service account private key");
        return null;
    }

    if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log("FCM Error: Failed to sign JWT");
        return null;
    }

    $jwt = $signingInput . '.' . sgi_base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $tokenData = json_decode($response, true);
        if (!empty($tokenData['access_token'])) {
            $cachedToken = $tokenData['access_token'];
            $tokenExpiry = $now + (int)($tokenData['expires_in'] ?? 3600);
            return $cachedToken;
        }
    }

    error_log("FCM Error: OAuth token exchange failed with code $httpCode: $response");
    return null;
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
 * Send notification to one or more FCM tokens using Google HTTP v1 API (or legacy fallback)
 */
function sendFcmNotificationRaw($tokens, $title, $body, $targetUrl = '') {
    if (empty($tokens)) return false;
    if (is_string($tokens)) $tokens = [$tokens];
    $tokens = array_values(array_unique(array_filter($tokens)));

    $projectId = 'tgsgi-2f41f';
    $accessToken = getGoogleOAuth2AccessToken();

    // 1. Preferred Modern Route: Firebase HTTP v1 API
    if ($accessToken) {
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $successCount = 0;

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body
                    ],
                    'data' => [
                        'title'   => (string)$title,
                        'body'    => (string)$body,
                        'message' => (string)$body,
                        'url'     => (string)$targetUrl
                    ],
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id'    => 'sgi_system_notifications',
                            'default_sound' => true,
                            'click_action'  => 'OPEN_ACTIVITY'
                        ]
                    ]
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; UTF-8'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $successCount++;
            } else {
                error_log("FCM HTTP v1 Error ($httpCode): " . $result);
            }
        }

        return ($successCount > 0);
    }

    // 2. Fallback Route: Legacy FCM Key (if server key configured)
    $serverKey = sgi_env('FCM_SERVER_KEY');
    if (!empty($serverKey)) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $payload = [
            'registration_ids' => $tokens,
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

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . $serverKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200);
    }

    return false;
}
