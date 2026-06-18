<?php
// Simple test script to debug OTP email sending
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>OTP Email Test</h2>";

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        if (!getenv(trim($key))) {
            putenv(trim($key) . '=' . trim($value));
        }
    }
    echo "<p>✅ .env file loaded</p>";
} else {
    echo "<p>❌ .env file not found</p>";
    exit;
}

// Check API key
$apiKey = getenv('RESEND_API_KEY');
if (empty($apiKey)) {
    echo "<p>❌ RESEND_API_KEY is empty</p>";
    exit;
} else {
    echo "<p>✅ RESEND_API_KEY is set (first 10 chars: " . substr($apiKey, 0, 10) . "...)</p>";
}

// Test email parameters
$to = "support.tgsgi@gmail.com"; // Your email
$name = "Test User";
$otp = "123456";
$subject = "SGI Test OTP";

$message = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .otp-box { background: #f0f0f0; padding: 20px; text-align: center; margin: 20px 0; }
        .otp-code { font-size: 36px; font-weight: bold; color: #e94560; letter-spacing: 8px; }
    </style>
</head>
<body>
    <h1>Test OTP Email</h1>
    <p>Hi $name,</p>
    <p>Your test OTP is:</p>
    <div class='otp-box'>
        <div class='otp-code'>$otp</div>
    </div>
    <p>This is a test email from SGI.</p>
</body>
</html>
";

echo "<p>📧 Sending test email to: <strong>$to</strong></p>";

// Send via Resend API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'from' => 'SGI <onboarding@resend.dev>',
    'to' => $to,
    'subject' => $subject,
    'html' => $message,
    'text' => "Your test OTP is: $otp"
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Results:</h3>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";

if ($httpCode == 200) {
    echo "<p style='color: green; font-size: 20px;'>✅ Email sent successfully!</p>";
} else {
    echo "<p style='color: red; font-size: 20px;'>❌ Failed to send email</p>";
    if ($error) {
        echo "<p><strong>cURL Error:</strong> $error</p>";
    }
}

echo "<hr>";
echo "<p><em>Note: Check your spam folder if you don't see the email in your inbox.</em></p>";
?>