<?php
include 'config.php';
requireLogin();

$success = ''; $error = '';

if (isset($_POST['send'])) {
    $apiKey = getenv('RESEND_API_KEY');
    $payload = json_encode([
        'from'     => 'SGI Support <onboarding@resend.dev>',
        'to'       => [getenv('MAIL_USERNAME')],
        'reply_to' => $_POST['email'],
        'subject'  => '[SGI] ' . $_POST['subject'],
        'text'     =>
            "Name:    " . $_POST['name']    . "\n" .
            "Email:   " . $_POST['email']   . "\n" .
            "Roll No: " . $_SESSION['user']['roll'] . "\n\n" .
            "Message:\n" . $_POST['message']
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $success = "Your message has been sent. We will get back to you soon.";
    } else {
        $error = "Message could not be sent. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Contact</title>
    <link rel="stylesheet" href="/css/style.css?v=2">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
    <span class="nav-brand">SGI</span>
    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="update_profile.php">Update Profile</a>
        <a href="about.php">About</a>
        <a href="contact.php">Contact</a>
        <a href="print_select.php">Print</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="container">
    <div class="section">
        <h2>Contact Us</h2>
        <p>For support or queries, fill in the form below or reach out to your department coordinator.</p>
    </div>
    <div class="form-box" style="margin-top:24px;">
        <h2>Send a Message</h2>
        <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
        <form method="POST">
            <input type="text"  name="name"    placeholder="Your Name"    value="<?= htmlspecialchars($_SESSION['user']['name']) ?>" required>
            <input type="email" name="email"   placeholder="Your Email"   value="<?= htmlspecialchars($_SESSION['user']['email']) ?>" required>
            <input type="text"  name="subject" placeholder="Subject" required>
            <textarea name="message" placeholder="Your message..." rows="5" required></textarea>
            <button type="submit" name="send" class="btn-primary">Send Message</button>
        </form>
    </div>
</div>
<div class="copyright-footer">
    &copy; <?= date('Y') ?> Student Growth Index (SGI), All rights reserved by TG.
</div>
</body>
</html>


