<?php
// send_otp.php is always included after config.php, which already loaded .env

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendOTPEmail($to, $name, $otp, $type = 'student') {
    $username = getenv('MAIL_USERNAME');
    $password = getenv('MAIL_PASSWORD');

    if (empty($username) || empty($password)) {
        error_log("SGI Email Error: MAIL_USERNAME or MAIL_PASSWORD not configured");
        return false;
    }

    $subject = "SGI - Password Reset OTP Verification";
    $accentColor = ($type === 'mentor') ? '#8e44ad' : '#e94560';

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a1a2e, {$accentColor}); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .otp-box { background: white; border: 2px dashed {$accentColor}; padding: 20px; text-align: center; margin: 20px 0; border-radius: 10px; }
            .otp-code { font-size: 36px; font-weight: bold; color: {$accentColor}; letter-spacing: 8px; }
            .warning { background: #fff3cd; border-left: 4px solid #f5a623; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='header'><h1>🔐 SGI Password Reset</h1><p>Student Growth Index - " . ucfirst($type) . " Portal</p></div>
        <div class='content'>
            <p>Hi <strong>$name</strong>,</p>
            <p>Use the following OTP to verify your identity:</p>
            <div class='otp-box'>
                <p style='margin:0 0 10px;color:#666;font-size:14px;'>Your Verification Code:</p>
                <div class='otp-code'>$otp</div>
            </div>
            <div class='warning'>
                <strong>⚠️ Important:</strong>
                <ul style='margin:10px 0;padding-left:20px;'>
                    <li>Valid for <strong>10 minutes</strong> only</li>
                    <li>Do not share this code with anyone</li>
                </ul>
            </div>
            <p>If you didn't request a password reset, ignore this email.</p>
        </div>
        <p style='text-align:center;color:#888;font-size:12px;'>© " . date('Y') . " Student Growth Index (SGI). All rights reserved.</p>
    </body>
    </html>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // SSL cert for XAMPP local
        $localCert = __DIR__ . '/cacert.pem';
        if (file_exists($localCert)) {
            $mail->SMTPOptions = ['ssl' => ['cafile' => $localCert, 'verify_peer' => true, 'verify_peer_name' => true]];
        }

        $mail->setFrom($username, 'SGI - Student Growth Index');
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = "Your SGI OTP is: $otp (valid for 10 minutes)";

        $mail->send();
        error_log("SGI Email: OTP sent to $to");
        return true;
    } catch (Exception $e) {
        error_log("SGI Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendOTPViaSMS($phone, $otp) {
    return true;
}
?>
