<?php
// Email OTP sending utility
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendOTPEmail($to, $name, $otp, $type = 'student') {
    $subject = "SGI - Password Reset OTP Verification";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a1a2e, #e94560); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { margin: 10px 0 0; opacity: 0.9; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .otp-box { background: white; border: 2px dashed #e94560; padding: 20px; text-align: center; margin: 20px 0; border-radius: 10px; }
            .otp-code { font-size: 36px; font-weight: bold; color: #e94560; letter-spacing: 8px; }
            .warning { background: #fff3cd; border-left: 4px solid #f5a623; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .footer { text-align: center; margin-top: 30px; color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🔐 SGI Password Reset</h1>
            <p>Student Growth Index - " . ucfirst($type) . " Portal</p>
        </div>
        <div class='content'>
            <p>Hi <strong>$name</strong>,</p>
            <p>We received a request to reset your password. Please use the following One-Time Password (OTP) to verify your identity:</p>
            
            <div class='otp-box'>
                <p style='margin: 0 0 10px; color: #666; font-size: 14px;'>Your Verification Code:</p>
                <div class='otp-code'>$otp</div>
            </div>
            
            <div class='warning'>
                <strong>⚠️ Important:</strong>
                <ul style='margin: 10px 0; padding-left: 20px;'>
                    <li>This OTP is valid for <strong>10 minutes</strong> only</li>
                    <li>Do not share this code with anyone</li>
                    <li>Our team will never ask for this code</li>
                </ul>
            </div>
            
            <p>If you didn't request a password reset, you can safely ignore this email. Your account remains secure.</p>
            
            <p>Need help? Contact our support team.</p>
        </div>
        <div class='footer'>
            <p>© " . date('Y') . " Student Growth Index (SGI). All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </body>
    </html>
    ";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: SGI Password Reset <noreply@sgi.edu>" . "\r\n";
    $headers .= "Reply-To: support@sgi.edu" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email using PHP mail() function
    // Note: For production, consider using PHPMailer or SwiftMailer with SMTP
    return mail($to, $subject, $message, $headers);
}

function sendOTPViaSMS($phone, $otp) {
    // SMS sending can be implemented using services like Twilio, MSG91, etc.
    // This is a placeholder for SMS functionality
    $message = "SGI OTP: $otp. Valid for 10 minutes. Do not share with anyone.";
    
    // Example using a hypothetical SMS API
    // $ch = curl_init();
    // curl_setopt($ch, CURLOPT_URL, "https://api.sms-service.com/send");
    // curl_setopt($ch, CURLOPT_POST, true);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    //     'to' => $phone,
    //     'message' => $message,
    //     'sender_id' => 'SGIOTP'
    // ]));
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // $result = curl_exec($ch);
    // curl_close($ch);
    
    // For now, return true as SMS is optional
    return true;
}
?>