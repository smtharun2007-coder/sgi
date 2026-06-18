<?php
// Test script to check user lookup in MongoDB
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>User Lookup Test</h2>";

// Include config to get MongoDB connection
include 'config.php';

echo "<p>✅ MongoDB connected</p>";

// Test: Try to find a user
echo "<h3>Testing User Lookup:</h3>";
echo "<form method='GET'>
    <label>Roll Number: <input type='text' name='roll' value=''></label>
    <label>Email: <input type='email' name='email' value=''></label>
    <button type='submit'>Search</button>
</form>";

if (isset($_GET['roll']) && isset($_GET['email'])) {
    $roll = trim($_GET['roll']);
    $email = trim($_GET['email']);
    
    echo "<p>Searching for: Roll = <strong>$roll</strong>, Email = <strong>$email</strong></p>";
    
    $user = $users->findOne(['roll' => $roll, 'email' => $email]);
    
    if ($user) {
        echo "<p style='color: green; font-size: 20px;'>✅ User found!</p>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
        
        // Now test sending OTP
        include 'send_otp.php';
        $otp = generateOTP(6);
        echo "<p>Generated OTP: <strong>$otp</strong></p>";
        
        $emailSent = sendOTPEmail($email, $user['name'], $otp, 'student');
        if ($emailSent) {
            echo "<p style='color: green; font-size: 20px;'>✅ OTP email sent successfully!</p>";
        } else {
            echo "<p style='color: red; font-size: 20px;'>❌ Failed to send OTP email</p>";
        }
    } else {
        echo "<p style='color: red; font-size: 20px;'>❌ User not found with those credentials</p>";
        
        // Try to find by roll only
        $userByRoll = $users->findOne(['roll' => $roll]);
        if ($userByRoll) {
            echo "<p>⚠️ User found by roll number, but email doesn't match:</p>";
            echo "<p>Stored email: <strong>" . ($userByRoll['email'] ?? 'N/A') . "</strong></p>";
        } else {
            echo "<p>User with roll number '$roll' also not found.</p>";
        }
    }
}

echo "<hr>";
echo "<h3>All Users in Database:</h3>";
$allUsers = $users->find([], ['limit' => 10]);
foreach ($allUsers as $u) {
    echo "<p>Roll: <strong>" . ($u['roll'] ?? 'N/A') . "</strong> | Email: <strong>" . ($u['email'] ?? 'N/A') . "</strong> | Name: " . ($u['name'] ?? 'N/A') . "</p>";
}
?>