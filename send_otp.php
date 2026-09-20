<?php
// send_otp.php — always included after config.php
include_once __DIR__ . '/mail_helper.php';

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function sendOTPEmail($to, $name, $otp, $type = 'student') {
    $accentColor = ($type === 'mentor') ? '#8e44ad' : '#e94560';
    $subject     = "SGI - Password Reset OTP Verification";
    $html        = "
    <!DOCTYPE html><html><head><meta charset='UTF-8'>
    <style>
        body{font-family:'Segoe UI',Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;}
        .header{background:linear-gradient(135deg,#1a1a2e,{$accentColor});color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;}
        .content{background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;}
        .otp-box{background:white;border:2px dashed {$accentColor};padding:20px;text-align:center;margin:20px 0;border-radius:10px;}
        .otp-code{font-size:36px;font-weight:bold;color:{$accentColor};letter-spacing:8px;}
        .warning{background:#fff3cd;border-left:4px solid #f5a623;padding:15px;margin:20px 0;border-radius:5px;}
    </style></head>
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
    </body></html>";

    $sent = sendResendEmail(
        $to,
        $subject,
        "Hi $name,\n\nYour SGI OTP is: $otp. Valid for 10 minutes. Do not share with anyone.",
        $html,
        null,
        'SGI - Student Growth Index'
    );
    if ($sent) error_log("SGI Email: OTP sent to $to via Resend");
    return $sent;
}

function sendOTPViaSMS($phone, $otp) {
    return true;
}
?>
