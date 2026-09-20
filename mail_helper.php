<?php
// mail_helper.php — shared Resend HTTP mailer

function sendContactEmail($toEmail, $toName, $fromEmail, $fromName, $replyTo, $subject, $bodyText) {
    $apiKey      = getenv('RESEND_API_KEY');
    $senderEmail = getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev';

    if (empty($apiKey)) {
        error_log("SGI Email Error: RESEND_API_KEY not configured");
        return false;
    }
    if (empty($toEmail)) {
        error_log("SGI Email Error: contact destination is not configured");
        return false;
    }

    $data = [
        'from'     => 'SGI Contact Form <' . $senderEmail . '>',
        'to'       => [$toEmail],
        'reply_to' => $replyTo,
        'subject'  => $subject,
        'text'     => $bodyText,
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
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

    if ($httpCode === 200 || $httpCode === 201) return true;
    error_log("SGI Resend Contact Error: HTTP $httpCode - $curlErr - $response");
    return false;
}
?>
