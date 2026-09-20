<?php
// mail_helper.php — shared Brevo HTTP mailer

function sendContactEmail($toEmail, $toName, $fromEmail, $fromName, $replyTo, $subject, $bodyText) {
    $apiKey    = getenv('BREVO_API_KEY');
    $senderEmail = getenv('MAIL_USERNAME') ?: 'support.tgsgi@gmail.com';

    if (empty($apiKey)) {
        error_log("SGI Email Error: BREVO_API_KEY not configured");
        return false;
    }

    $data = [
        'sender'      => ['name' => 'SGI Contact Form', 'email' => $senderEmail],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'replyTo'     => ['email' => $replyTo, 'name' => $fromName],
        'subject'     => $subject,
        'textContent' => $bodyText,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $apiKey,
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

    if ($httpCode === 201) return true;
    error_log("SGI Brevo Contact Error: HTTP $httpCode - $curlErr - $response");
    return false;
}
?>
