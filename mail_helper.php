<?php
// mail_helper.php — shared Resend HTTP mailer

function sendResendEmail($toEmail, $subject, $bodyText, $html = null, $replyTo = null, $fromLabel = 'SGI') {
    $apiKey = getenv('RESEND_API_KEY');
    $senderEmail = trim((string)getenv('RESEND_FROM_EMAIL')) ?: 'onboarding@resend.dev';

    if (empty($apiKey)) {
        error_log("SGI Email Error: RESEND_API_KEY not configured");
        return false;
    }
    if (empty($toEmail)) {
        error_log("SGI Email Error: email destination is not configured");
        return false;
    }

    $data = [
        'to'      => [$toEmail],
        'subject' => $subject,
        'text'    => $bodyText,
    ];
    if ($html !== null) $data['html'] = $html;
    if (!empty($replyTo)) $data['reply_to'] = $replyTo;

    $data['from'] = $fromLabel . ' <' . $senderEmail . '>';
    $payload = json_encode($data);
    if ($payload === false) {
        error_log("SGI Resend Error: failed to encode email payload");
        return false;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $localCert = __DIR__ . '/cacert.pem';
    if (file_exists($localCert)) curl_setopt($ch, CURLOPT_CAINFO, $localCert);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        error_log("SGI Resend: email accepted using configured sender");
        return true;
    }

    error_log("SGI Resend Error: HTTP $httpCode using sender - " . ($curlErr ?: 'API rejected request') . " - $response");
    return false;
}

function sendContactEmail($toEmail, $toName, $fromEmail, $fromName, $replyTo, $subject, $bodyText) {
    return sendResendEmail(
        $toEmail,
        $subject,
        $bodyText,
        null,
        $replyTo,
        'SGI Contact Form'
    );
}
?>
