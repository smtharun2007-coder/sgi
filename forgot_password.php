<?php
// Redirect to student forgot password by default or based on query parameter
$portal = isset($_GET['portal']) ? $_GET['portal'] : 'student';

if ($portal === 'mentor') {
    header("Location: forgot_password_mentor.php");
} else {
    header("Location: forgot_password_student.php");
}
exit;
?>