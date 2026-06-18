<?php include 'config.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SGI – About</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/jpeg" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
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
        <h2>About SGI</h2>
        <p>The <strong>Student Growth Index (SGI)</strong> is a comprehensive scoring system designed to evaluate a student's overall development beyond just academics.</p>
        <br>
        <ul class="about-list">
            <li><strong>Academic (40%)</strong> – CAT scores, GPA, and CGPA performance.</li>
            <li><strong>Skills (20%)</strong> – Credit courses, coding platforms, and normal courses.</li>
            <li><strong>Projects (10%)</strong> – Mini projects, main projects, and other contributions.</li>
            <li><strong>Activities (20%)</strong> – Hackathons, competitions, workshops, and participations.</li>
            <li><strong>Discipline (10%)</strong> – Attendance record and GPA improvement trend.</li>
        </ul>
        <br>
        <h3 style="color:#1a1a2e;margin-bottom:10px;">How Subject Performance is Measured</h3>
        <p style="margin-bottom:10px;">Each subject with internal assessment is evaluated through <strong>3 CAT (Continuous Assessment Tests)</strong>, each scored out of <strong>100 marks</strong>.</p>
        <ul class="about-list">
            <li><strong>CAT 1, CAT 2, CAT 3</strong> – Each test is out of 100. The total is 300 marks per subject.</li>
            <li><strong>Subject Total</strong> – Sum of CAT 1 + CAT 2 + CAT 3 (max 300).</li>
            <li><strong>Subject Percentage</strong> – (Total / 300) × 100.</li>
            <li><strong>CAT Score for SGI</strong> – All subjects' CAT totals are combined and converted to a score out of 10 for each CAT exam.</li>
            <li><strong>Final CA Marks</strong> – The final Continuous Assessment mark sheet is also recorded per subject (scored vs max).</li>
        </ul>
        <br>
        <h3 style="color:#1a1a2e;margin-bottom:10px;">Academic Score Formula</h3>
        <ul class="about-list">
            <li><strong>CAT 1 (15%)</strong> – (Total CAT 1 marks / Max) × 10 × 0.15</li>
            <li><strong>CAT 2 (15%)</strong> – (Total CAT 2 marks / Max) × 10 × 0.15</li>
            <li><strong>CAT 3 (20%)</strong> – (Total CAT 3 marks / Max) × 10 × 0.20</li>
            <li><strong>GPA (25%)</strong> – Current semester GPA × 0.25</li>
            <li><strong>CGPA (25%)</strong> – Cumulative GPA × 0.25</li>
        </ul>
        <br>
        <a href="SGI.pdf" target="_blank" class="btn-primary" style="width:auto;display:inline-block;padding:10px 24px;">📄 View SGI Documentation (PDF)</a>
    </div>
    <div class="section">
        <h2>SGI Grade Scale</h2>
        <table class="grade-table">
            <tr><th>SGI Score</th><th>Grade</th></tr>
            <tr><td>9.0 – 10.0</td><td>O (Excellent)</td></tr>
            <tr><td>8.0 – 8.9</td><td>A (Very Good)</td></tr>
            <tr><td>7.0 – 7.9</td><td>B (Good)</td></tr>
            <tr><td>6.0 – 6.9</td><td>C (Average)</td></tr>
            <tr><td>Below 6.0</td><td>D (Needs Improvement)</td></tr>
        </table>
    </div>
</div>
</body>
</html>


