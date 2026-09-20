<?php
// Simple Resend email test script
require __DIR__ . '/mail_helper.php';

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv("$key=$value");
    }
}

// Test configuration
$testEmail = 'support.tgsgi@gmail.com'; // Change this to your test email
$testName = 'SGI Test';

echo "<h2>SGI Email Test</h2>";
echo "<p>Testing the Resend HTTP API configuration...</p>";
echo "<pre>";
echo "<strong>Current Configuration from .env file:</strong>\n";
echo "RESEND_API_KEY: " . (getenv('RESEND_API_KEY') ? '***configured***' : 'Not set') . "\n";
echo "RESEND_FROM_EMAIL: " . (getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev (fallback)') . "\n";
echo "\n";

if (empty(getenv('RESEND_API_KEY'))) {
    echo "ERROR: RESEND_API_KEY is not configured.\n";
    exit;
}

if (sendContactEmail($testEmail, $testName, $testEmail, $testName, $testEmail, 'SGI Email Test - Success!', 'Your SGI Resend email configuration is working correctly.')) {
    echo "SUCCESS! Email sent to: $testEmail\n";
} else {
    echo "FAILED: Could not send email. Check the PHP error log.\n";
}

echo "</pre>";
echo "<p><a href='forgot_password.php'>← Back to Forgot Password</a></p>";
?>
