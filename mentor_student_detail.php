<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

header('Content-Type: application/json');
$roll = trim($_GET['roll'] ?? '');
if ($roll === '') { echo json_encode(['status'=>'error','message'=>'Missing roll']); exit; }

$mem = $leap_memberships->findOne(['student_id'=>$roll,'mentor_id'=>$m['mentor_id'],'status'=>'ACTIVE']);
if (!$mem) { echo json_encode(['status'=>'error','message'=>'Student not in your LEAP']); exit; }
$st = $users->findOne(['roll'=>$roll]);
if (!$st) { echo json_encode(['status'=>'error','message'=>'Student not found']); exit; }

// Attendance summary (suspended excluded)
$sessAll = iterator_to_array($leap_training_sessions->find(['mentor_id'=>$m['mentor_id']]));
$conducted = array_values(array_filter($sessAll, fn($s)=>($s['status']??'')==='CONDUCTED'));
$suspended = array_values(array_filter($sessAll, fn($s)=>($s['status']??'')==='SUSPENDED'));
$cIds = array_map(fn($s)=>(string)$s['_id'], $conducted);
$present = 0;
if (!empty($cIds)) {
  $present = $leap_attendance->countDocuments(['student_id'=>$roll,'session_id'=>['$in'=>$cIds],'status'=>'PRESENT']);
}
$cCnt = count($conducted);
$pct = $cCnt>0 ? round(($present/$cCnt)*100) : null;

// Recent session-wise attendance (latest 6 conducted)
usort($conducted, fn($a,$b)=>strcmp((string)($b['_id']),(string)($a['_id'])));
$recent = array_slice($conducted, 0, 6);
$sessList = [];
foreach ($recent as $s) {
  $rec = $leap_attendance->findOne(['session_id'=>(string)$s['_id'],'student_id'=>$roll]);
  $sessList[] = ['title'=>$s['title'] ?? 'Session', 'att'=>$rec['status'] ?? '—'];
}

// Published results
$res = iterator_to_array($leap_test_results->find(['student_id'=>$roll,'status'=>'PUBLISHED'],['sort'=>['published_at'=>-1],'limit'=>10]));
$items = []; $tm=0; $tx=0;
foreach ($res as $r) {
  $t = null;
  try { $t = $leap_tests->findOne(['_id'=>new MongoDB\BSON\ObjectId($r['test_id'])]); } catch (Exception $e) {}
  $items[] = ['title'=>htmlspecialchars($t['title'] ?? 'Test'), 'marks'=>(int)($r['marks_obtained']??0), 'max'=>(int)($r['maximum_marks']??0)];
  $tm += (int)($r['marks_obtained']??0); $tx += (int)($r['maximum_marks']??0);
}
$avg = $tx>0 ? round(($tm/$tx)*100) : null;

// Coding profiles
$lc=''; $hr='';
try {
  if (!empty($mem['application_id'])) {
    $app = $leap_applications->findOne(['_id'=>new MongoDB\BSON\ObjectId($mem['application_id'])]);
    if ($app) { $lc=$app['leetcode_profile']??''; $hr=$app['hackerrank_profile']??''; }
  }
} catch (Exception $e) {}

echo json_encode(['status'=>'success','student'=>[
  'name'=>htmlspecialchars($st['name'] ?? $roll),
  'roll'=>htmlspecialchars($st['roll'] ?? $roll),
  'batch'=>htmlspecialchars($st['batch_no'] ?? ''),
  'dept'=>htmlspecialchars($st['dept'] ?? ''),
  'pacc'=>$mem['pacc_level'] ?? null,
],'attendance'=>['present'=>$present,'conducted'=>$cCnt,'suspended'=>count($suspended),'pct'=>$pct],
'results'=>['items'=>$items,'avg'=>$avg],'sessions'=>$sessList,
'profiles'=>['leetcode'=>htmlspecialchars($lc),'hackerrank'=>htmlspecialchars($hr)]]);
