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

    <!-- ABOUT SGI -->
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
        <a href="SGI.pdf" target="_blank" class="btn-primary" style="width:auto;display:inline-block;padding:10px 24px;">📄 View SGI Documentation (PDF)</a>
    </div>

    <!-- ALL SEMESTER ANALYSIS GUIDELINES -->
    <div class="section" style="margin-top:24px;">
        <h2>All Semester Analysis</h2>
        <p>The All Semester Analysis evaluates your overall performance across semesters using the following criteria:</p>
        <br>
        <ul class="about-list">
            <li><strong>Best & Worst Semester</strong> – Determined by your <strong>SGI Score</strong>. The semester with the highest SGI is your best and the lowest is your worst.</li>
            <li><strong>Best & Worst Subject</strong> – Determined by a combined score based on CAT and CA performance. Both must be available for a subject to be ranked.</li>
            <li><strong>Avg CAT Marks</strong> – Average of CAT 1, CAT 2, and CAT 3 scores (each out of 100) for that subject.</li>
            <li><strong>CA Marks</strong> – Final Continuous Assessment mark converted to a percentage out of 100.</li>
        </ul>
        <br>
    </div>

    <!-- SGI GRADE SCALE -->
    <div class="section" style="margin-top:24px;">
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
