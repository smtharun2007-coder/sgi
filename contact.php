<?php
include 'config.php';
requireLogin();
$success = '';
if (isset($_POST['send'])) {
    $success = "Your message has been sent. We will get back to you soon.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – Contact</title>
    <link rel="stylesheet" href="style.css">
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


