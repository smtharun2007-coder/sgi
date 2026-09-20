<?php
include 'config.php';
header('Content-Type: application/json');
$isMentor = isset($_SESSION['mentor']);
$isStudent = isset($_SESSION['user']);
if (!$isMentor && !$isStudent) { echo json_encode(['error'=>'Unauthorized']); exit; }
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$timetables = $db->timetables;
$attendance_sessions = $db->attendance_sessions;
$student_attendance = $db->student_attendance;
$od_requests = $db->od_requests;
$calendar_overrides = $db->calendar_overrides;
$HOURS = ['H1'=>['start'=>'08:45','end'=>'09:35'],'H2'=>['start'=>'09:35','end'=>'10:25'],'H3'=>['start'=>'10:45','end'=>'11:35'],'H4'=>['start'=>'11:35','end'=>'12:25'],'H5'=>['start'=>'13:25','end'=>'14:15'],'H6'=>['start'=>'14:15','end'=>'15:05'],'H7'=>['start'=>'15:25','end'=>'16:15']];
