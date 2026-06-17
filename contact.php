<?php
include 'config.php';
requireLogin();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$success = ''; $error = '';

if (isset($_POST['send'])) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USERNAME');
        $mail->Password   = getenv('MAIL_PASSWORD');
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom(getenv('MAIL_USERNAME'), 'SGI Support');
        $mail->addAddress(getenv('MAIL_USERNAME'), 'SGI Admin');
        $mail->addReplyTo($_POST['email'], $_POST['name']);

        $mail->Subject = '[SGI] ' . htmlspecialchars($_POST['subject']);
        $mail->Body    =
            "Name:    " . htmlspecialchars($_POST['name'])    . "\n" .
            "Email:   " . htmlspecialchars($_POST['email'])   . "\n" .
            "Roll No: " . htmlspecialchars($_SESSION['user']['roll']) . "\n\n" .
            "Message:\n" . htmlspecialchars($_POST['message']);

        $mail->send();
        $success = "Your message has been sent. We will get back to you soon.";
    } catch (Exception $e) {
        $error = "Message could not be sent. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Contact</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">SGI</a>
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
</body>
</html>


