<?php
// mail_helper.php — shared Resend mailer via official SDK (Render-safe)

require_once __DIR__ . '/vendor/autoload.php';

if (!function_exists('sgi_env')) {
    function sgi_env($key, $default = '') {
        // Render/Docker inject real environment variables. getenv() can be
        // disabled or empty under some PHP/Apache configs, so fall back to
        // $_SERVER / $_ENV. .env file is gitignored and NOT present on Render.
        $val = getenv($key);
        if ($val === false || $val === '') {
            if (isset($_ENV[$key]) && $_ENV[$key] !== '') $val = $_ENV[$key];
            elseif (isset($_SERVER[$key]) && $_SERVER[$key] !== '') $val = $_SERVER[$key];
            else $val = $default;
        }
        return is_string($val) ? trim($val) : $val;
    }
}

function sendResendEmail($toEmail, $subject, $bodyText, $html = null, $replyTo = null, $fromLabel = 'SGI') {
    $apiKey = sgi_env('RESEND_API_KEY');
    $senderEmail = sgi_env('RESEND_FROM_EMAIL');

    if (empty($apiKey)) {
        error_log("SGI Email Error: RESEND_API_KEY not configured (Render Dashboard > Environment, then redeploy)");
        return false;
    }
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("SGI Email Error: invalid destination <$toEmail>");
        return false;
    }
    if (empty($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("SGI Email Error: RESEND_FROM_EMAIL missing/invalid <$senderEmail>. Set a verified domain sender in Render env vars.");
        return false;
    }
    $domain = strtolower(substr(strrchr($senderEmail, '@'), 1));
    if (in_array($domain, ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'], true)) {
        error_log("SGI Email Error: RESEND_FROM_EMAIL <$senderEmail> is a freemail domain — Resend will reject it (403). Use noreply@yourdomain.com from a verified domain.");
        return false;
    }
    if ($senderEmail === 'onboarding@resend.dev') {
        error_log("SGI Email Warning: using onboarding@resend.dev test sender — delivers ONLY to your own Resend account email (use delivered@resend.dev for tests). Verify a domain for real OTP.");
    }

    $params = [
        'from'    => $fromLabel . ' <' . $senderEmail . '>',
        'to'      => [$toEmail],
        'subject' => $subject,
    ];
    // SDK equivalent of your snippet: html preferred, text as fallback
    if ($html !== null && $html !== '') {
        $params['html'] = $html;
        $params['text'] = $bodyText;
    } else {
        $params['text'] = $bodyText;
    }
    if (!empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $params['reply_to'] = $replyTo;

    try {
        $resend = \Resend::client($apiKey);
        $result = $resend->emails->send($params);
        // SDK v1.x returns ['id' => ...]; v2 returns object with ->id
        $id = is_array($result) ? ($result['id'] ?? '') : ($result->id ?? '');
        error_log("SGI Resend: email accepted to <$toEmail> from <$senderEmail> id=$id");
        return true;
    } catch (\Exception $e) {
        // Map SDK message to actionable fix for Render Logs
        $msg = $e->getMessage();
        $hint = '';
        if (stripos($msg, '401') !== false || stripos($msg, 'unauthorized') !== false || stripos($msg, 'api key') !== false)
            $hint = 'Invalid/revoked RESEND_API_KEY. Regenerate in Resend > API Keys, update Render env, redeploy.';
        elseif (stripos($msg, '403') !== false || stripos($msg, 'domain') !== false || stripos($msg, 'not verified') !== false || stripos($msg, 'onboarding') !== false)
            $hint = 'Sender rejected. Verify domain in Resend > Domains (SPF/DKIM) and set RESEND_FROM_EMAIL to it. onboarding@resend.dev only sends to yourself/delivered@resend.dev.';
        elseif (stripos($msg, '422') !== false || stripos($msg, 'validation') !== false)
            $hint = 'Payload rejected. Check from/to/subject format.';
        elseif (stripos($msg, '429') !== false || stripos($msg, 'rate') !== false)
            $hint = 'Rate limited (free: 100/day, 3000/mo). Retry later.';
        error_log("SGI Resend Error: from <$senderEmail> to <$toEmail> - $msg" . ($hint ? " | Fix: $hint" : ''));
        return false;
    }
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
