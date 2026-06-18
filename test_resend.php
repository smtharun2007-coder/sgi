<?php
// Test Resend API
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

$apiKey = getenv('RESEND_API_KEY');
$testEmail = 'support.tgsgi@gmail.com'; // Change to your test email

echo "<h2>SGI Resend API Test</h2>";
echo "<pre>";
echo "Testing Resend API configuration...\n\n";

if (empty($apiKey) || strpos($apiKey, 'your-resend-api-key') !== false) {
    echo "❌ ERROR: RESEND_API_KEY is not configured properly!\n";
    echo "Please update your .env file with a valid Resend API key.\n";
    echo "Get one from: https://resend.com/api-keys\n";
    exit;
}

echo "✅ API Key found (masked): " . substr($apiKey, 0, 10) . "...\n\n";

// Test API call
$from = 'SGI <onboarding@resend.dev>';
$subject = 'SGI Resend API Test - Success!';
$htmlMessage = '
<html>
<body style="font-family: Arial, sans-serif;">
    <h2 style="color: #27ae60;">✅ Resend API Test Successful!</h2>
    <p>Your SGI application Resend API integration is working correctly.</p>
    <p>You can now use the forgot password feature to send OTP emails.</p>
    <hr>
    <p><strong>API Details:</strong></p>
    <ul>
        <li>From: SGI <onboarding@resend.dev></li>
        <li>To: ' . $testEmail . '</li>
    </ul>
</body>
</html>
';
$textMessage = "SGI Resend API Test - Success! Your email configuration is working.";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'from' => $from,
    'to' => $testEmail,
    'subject' => $subject,
    'html' => $htmlMessage,
    'text' => $textMessage
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Response Code: $httpCode\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "\n✅ SUCCESS! Email sent to: $testEmail\n";
    echo "Email ID: " . ($data['id'] ?? 'N/A') . "\n";
    echo "\nYou should receive the test email shortly. Check your spam folder if you don't see it.\n";
} else {
    echo "\n❌ FAILED: Could not send email.\n";
    echo "Error: $error\n";
    echo "Response: $response\n";
    echo "\nTroubleshooting:\n";
    echo "1. Verify your Resend API key is correct\n";
    echo "2. Check if your API key has the right permissions\n";
    echo "3. Ensure you're using the default Resend domain (onboarding@resend.dev)\n";
    echo "4. Check if the recipient email is valid\n";
}

echo "</pre>";
echo "<p><a href='forgot_password.php'>← Back to Forgot Password</a></p>";
?>