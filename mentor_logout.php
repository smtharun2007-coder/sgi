<?php
include 'config.php';
unset($_SESSION['mentor']);
header("Location: mentor_login.php");
exit;
?>
