<?php
// Simple email test script
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
echo "<p>Testing PHPMailer with SMTP configuration...</p>";
echo "<pre>";
echo "<strong>Current Configuration from .env file:</strong>\n";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'Not set') . "\n";
echo "MAIL_USERNAME: " . (getenv('MAIL_USERNAME') ?: 'Not set (still placeholder)') . "\n";
echo "MAIL_PASSWORD: " . (getenv('MAIL_PASSWORD') ? '***hidden***' : 'Not set (still placeholder)') . "\n";
echo "\n";

// Check if using placeholder values
if (getenv('MAIL_USERNAME') === 'your-email@gmail.com') {
    echo "⚠️  WARNING: You are using placeholder values!\n";
    echo "Please edit the .env file and replace:\n";
    echo "  - your-email@gmail.com with your actual Gmail address\n";
    echo "  - your-app-password with your actual Gmail App Password\n";
    echo "\n";
    echo "To get a Gmail App Password:\n";
    echo "1. Go to https://myaccount.google.com/security\n";
    echo "2. Enable 2-Step Verification\n";
    echo "3. Go to https://myaccount.google.com/apppasswords\n";
    echo "4. Create a new app password for 'Mail'\n";
    echo "5. Copy the 16-character password to .env file\n";
    exit;
}

try {
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('MAIL_USERNAME');
    $mail->Password   = getenv('MAIL_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Recipients
    $mail->setFrom(getenv('MAIL_USERNAME') ?: 'noreply@sgi.edu', 'SGI Test');
    $mail->addAddress($testEmail, $testName);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'SGI Email Test - Success!';
    $mail->Body    = '
    <html>
    <body style="font-family: Arial, sans-serif;">
        <h2 style="color: #27ae60;">✅ Email Test Successful!</h2>
        <p>Your SGI application email configuration is working correctly.</p>
        <p>You can now use the forgot password feature to send OTP emails.</p>
        <hr>
        <p><strong>Configuration Details:</strong></p>
        <ul>
            <li>SMTP Host: ' . getenv('SMTP_HOST') . '</li>
            <li>From Email: ' . getenv('MAIL_USERNAME') . '</li>
            <li>To Email: ' . $testEmail . '</li>
        </ul>
    </body>
    </html>
    ';
    $mail->AltBody = 'SGI Email Test - Success! Your email configuration is working.';
    
    if ($mail->send()) {
        echo "✅ SUCCESS! Email sent to: $testEmail\n\n";
        echo "Configuration used:\n";
        echo "SMTP Host: " . getenv('SMTP_HOST') . "\n";
        echo "From Email: " . getenv('MAIL_USERNAME') . "\n";
        echo "To Email: $testEmail\n";
    } else {
        echo "❌ FAILED: Could not send email.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $mail->ErrorInfo . "\n\n";
    echo "Troubleshooting:\n";
    echo "1. Check if .env file exists and has correct credentials\n";
    echo "2. Make sure you're using Gmail App Password (not regular password)\n";
    echo "3. Verify that 2-Factor Authentication is enabled on Gmail\n";
    echo "4. Check if SMTP_HOST is correct (smtp.gmail.com)\n";
    echo "5. Ensure port 587 is not blocked by firewall\n";
}

echo "</pre>";
echo "<p><a href='forgot_password.php'>← Back to Forgot Password</a></p>";
?>