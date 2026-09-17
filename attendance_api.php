<?php
include 'config.php';

header('Content-Type: application/json');

$isMentor  = isset($_SESSION['mentor']);
$isStudent = isset($_SESSION['user']);

if (!$isMentor && !$isStudent) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$HOURS = [
    1 => ['start' => '08:45', 'end' => '09:35'],
    2 => ['start' => '09:35', 'end' => '10:25'],
    3 => ['start' => '10:45', 'end' => '11:35'],
    4 => ['start' => '11:35', 'end' => '12:25'],
    5 => ['start' => '13:25', 'end' => '14:15'],
    6 => ['start' => '14:15', 'end' => '15:05'],
    7 => ['start' => '15:25', 'end' => '16:15'],
];

$DAY_NAMES = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

function jsonOut($data) {
    echo json_encode($data);
    exit;
}

function genSessionId() {
    return 'AS' . date('Ymd') . '_' . substr(uniqid(), -8);
}

function notifyStudents($users, $notifications, $studentRolls, $message, $type = 'attendance', $extra = []) {
    foreach ($studentRolls as $roll) {
        $doc = array_merge([
            'roll'       => $roll,
            'message'    => $message,
            'type'       => $type,
            'read'       => false,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ], $extra);
        $notifications->insertOne($doc);
    }
}

function notifyMentor($notifications, $mentorId, $message, $type = 'attendance', $extra = []) {
    $doc = array_merge([
        'mentor_id'  => $mentorId,
        'message'   => $message,
        'type'      => $type,
        'read'      => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ], $extra);
    $notifications->insertOne($doc);
}

// ─── GET endpoints ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // ── Student: overview ──
    if ($action === 'student_overview' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $batchNo = $_SESSION['user']['batch_no'] ?? '';

        $sessions = $attendance_sessions->find([
            'rolls' => $roll,
            'status' => 'CONDUCTED'
        ]);
        $allSessions = iterator_to_array($sessions);

        $attCursor = $student_attendance->find(['student_roll' => $roll]);
        $allAtt = iterator_to_array($attCursor);
        $attBySession = [];
        foreach ($allAtt as $a) {
            $attBySession[$a['attendance_session_id']] = $a;
        }

        $present = 0; $absent = 0; $od = 0; $leave = 0; $medical = 0;
        $attended = 0; $validConducted = 0;
        $subjectStats = [];

        foreach ($allSessions as $sess) {
            $validConducted++;
            $att = $attBySession[$sess['attendance_session_id']] ?? null;
            if (!$att) continue;

            $eff = $att['effective_status'] ?? $att['status'] ?? 'ABSENT';
            $orig = $att['original_status'] ?? $att['status'] ?? 'ABSENT';

            if ($orig === 'PRESENT') $present++;
            else if ($orig === 'ABSENT') $absent++;
            else if ($orig === 'OD') $od++;
            else if ($orig === 'LEAVE') $leave++;
            else if ($orig === 'MEDICAL') $medical++;

            if ($eff === 'PRESENT' || $eff === 'OD') $attended++;

            $subjKey = $sess['subject'] ?? 'Unknown';
            if (!isset($subjectStats[$subjKey])) {
                $subjectStats[$subjKey] = ['subject' => $subjKey, 'code' => $sess['subject_code'] ?? '', 'attended' => 0, 'total' => 0];
            }
            $subjectStats[$subjKey]['total']++;
            if ($eff === 'PRESENT' || $eff === 'OD') $subjectStats[$subjKey]['attended']++;
        }

        $overall = $validConducted > 0 ? round(($attended / $validConducted) * 100, 2) : 0;

        jsonOut([
            'status' => 'success',
            'overall' => $overall,
            'present' => $present,
            'absent' => $absent,
            'od' => $od,
            'leave' => $leave,
            'medical' => $medical,
            'total_sessions' => $validConducted,
            'attended_sessions' => $attended,
            'subjects' => array_values($subjectStats),
        ]);
    }

    // ── Student: calendar ──
    if ($action === 'student_calendar' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $month = (int)($_GET['month'] ?? date('n'));
        $year  = (int)($_GET['year']  ?? date('Y'));

        $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,$month,1,$year)*1000);
        $daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
        $to = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,$month,$daysInMonth,$year)*1000);

        $sessions = $attendance_sessions->find([
            'rolls' => $roll,
            'date' => ['$gte' => $from, '$lte' => $to]
        ]);
        $allSessions = iterator_to_array($sessions);

        $attCursor = $student_attendance->find(['student_roll' => $roll]);
        $allAtt = iterator_to_array($attCursor);
        $attBySession = [];
        foreach ($allAtt as $a) {
            $attBySession[$a['attendance_session_id']] = $a;
        }

        $dayMap = [];
        foreach ($allSessions as $sess) {
            $day = (int)date('j', $sess['date']->toDateTime()->getTimestamp());
            $att = $attBySession[$sess['attendance_session_id']] ?? null;
            $effStatus = $att ? ($att['effective_status'] ?? $att['status']) : null;
            $dayMap[$day][] = [
                'hour' => $sess['hour'],
                'subject' => $sess['subject'] ?? '',
                'subject_code' => $sess['subject_code'] ?? '',
                'faculty' => $sess['actual_faculty'] ?? $sess['original_faculty'] ?? '',
                'session_status' => $sess['status'],
                'attendance' => $effStatus,
                'start_time' => $sess['start_time'] ?? '',
                'end_time' => $sess['end_time'] ?? '',
                'session_type' => $sess['session_type'] ?? 'REGULAR',
            ];
        }

        // Check for holidays
        $exceptions = $attendance_exceptions->find([
            'date' => ['$gte' => $from, '$lte' => $to]
        ]);
        $holidayMap = [];
        foreach ($exceptions as $ex) {
            if ($ex['type'] === 'HOLIDAY') {
                $day = (int)date('j', $ex['date']->toDateTime()->getTimestamp());
                $holidayMap[$day] = $ex['description'] ?? 'Holiday';
            }
        }

        jsonOut([
            'status' => 'success',
            'days' => $dayMap,
            'holidays' => $holidayMap,
        ]);
    }

    // ── Student: OD history ──
    if ($action === 'student_od_list' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $cursor = $od_requests->find(['student_roll' => $roll], ['sort' => ['created_at' => -1]]);
        $list = [];
        foreach ($cursor as $r) {
            $list[] = [
                '_id' => (string)$r['_id'],
                'type' => $r['od_type'] ?? '',
                'duration' => $r['duration'] ?? '',
                'date_from' => isset($r['date_from']) ? date('d M Y', $r['date_from']->toDateTime()->getTimestamp()) : '',
                'date_to' => isset($r['date_to']) ? date('d M Y', $r['date_to']->toDateTime()->getTimestamp()) : '',
                'hours' => $r['hours'] ?? [],
                'reason' => $r['reason'] ?? '',
                'status' => $r['status'] ?? 'pending',
                'mentor_remarks' => $r['mentor_remarks'] ?? '',
                'created_at' => isset($r['created_at']) ? date('d M Y, h:i A', $r['created_at']->toDateTime()->getTimestamp()) : '',
            ];
        }
        jsonOut(['status' => 'success', 'requests' => $list]);
    }

    // ── Mentor: timetable ──
    if ($action === 'timetable' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $batch = $_GET['batch'] ?? '';
        $semester = (int)($_GET['semester'] ?? 0);

        if (!$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'Batch and semester required']);
        }

        $cursor = $timetables->find([
            'mentor_id' => $mentorId,
            'batch' => $batch,
            'semester' => $semester
        ], ['sort' => ['day' => 1, 'hour' => 1]]);
        $entries = [];
        foreach ($cursor as $t) {
            $entries[] = [
                '_id' => (string)$t['_id'],
                'day' => $t['day'],
                'day_name' => $DAY_NAMES[$t['day']] ?? '',
                'hour' => $t['hour'],
                'start_time' => $t['start_time'],
                'end_time' => $t['end_time'],
                'subject' => $t['subject'] ?? '',
                'subject_code' => $t['subject_code'] ?? '',
                'faculty' => $t['faculty'] ?? '',
            ];
        }
        jsonOut(['status' => 'success', 'timetable' => $entries]);
    }

    // ── Mentor: students by batch ──
    if ($action === 'batch_students' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $batch = $_GET['batch'] ?? '';
        if (!$batch) jsonOut(['status' => 'error', 'message' => 'Batch required']);

        $cursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $students = [];
        foreach ($cursor as $s) {
            $students[] = [
                'roll' => $s['roll'],
                'name' => $s['name'],
                'reg' => $s['reg'] ?? '',
            ];
        }
        jsonOut(['status' => 'success', 'students' => $students]);
    }

    // ── Mentor: sessions for date ──
    if ($action === 'sessions_for_date' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $dateStr = $_GET['date'] ?? '';
        if (!$dateStr) jsonOut(['status' => 'error', 'message' => 'Date required']);

        $ts = strtotime($dateStr);
        $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);
        $to = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);

        $sessions = $attendance_sessions->find([
            'mentor_id' => $mentorId,
            'date' => ['$gte' => $from, '$lte' => $to]
        ], ['sort' => ['hour' => 1]]);
        $result = [];
        foreach ($sessions as $sess) {
            $result[] = [
                'attendance_session_id' => $sess['attendance_session_id'],
                'hour' => $sess['hour'],
                'start_time' => $sess['start_time'] ?? '',
                'end_time' => $sess['end_time'] ?? '',
                'subject' => $sess['subject'] ?? '',
                'subject_code' => $sess['subject_code'] ?? '',
                'original_faculty' => $sess['original_faculty'] ?? '',
                'actual_faculty' => $sess['actual_faculty'] ?? '',
                'session_type' => $sess['session_type'] ?? 'REGULAR',
                'status' => $sess['status'] ?? 'SCHEDULED',
                'batch' => $sess['batch'] ?? '',
                'semester' => $sess['semester'] ?? 0,
            ];
        }

        // Check holiday
        $holiday = $attendance_exceptions->findOne([
            'type' => 'HOLIDAY',
            'date' => ['$gte' => $from, '$lte' => $to]
        ]);

        jsonOut([
            'status' => 'success',
            'sessions' => $result,
            'holiday' => $holiday ? ['description' => $holiday['description'] ?? 'Holiday'] : null,
        ]);
    }

    // ── Mentor: session attendance (for marking) ──
    if ($action === 'session_attendance' && $isMentor) {
        $sessionId = $_GET['session_id'] ?? '';
        if (!$sessionId) jsonOut(['status' => 'error', 'message' => 'Session ID required']);

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);

        $mentorId = $_SESSION['mentor']['mentor_id'];
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $rolls = $sess['rolls'] ?? [];
        $attCursor = $student_attendance->find(['attendance_session_id' => $sessionId]);
        $attMap = [];
        foreach ($attCursor as $a) {
            $attMap[$a['student_roll']] = $a;
        }

        $students = [];
        foreach ($rolls as $r) {
            $stu = $users->findOne(['roll' => $r]);
            $att = $attMap[$r] ?? null;
            $students[] = [
                'roll' => $r,
                'name' => $stu['name'] ?? $r,
                'status' => $att['status'] ?? '',
                'effective_status' => $att['effective_status'] ?? '',
                'original_status' => $att['original_status'] ?? '',
            ];
        }

        jsonOut([
            'status' => 'success',
            'session' => [
                'attendance_session_id' => $sess['attendance_session_id'],
                'hour' => $sess['hour'],
                'subject' => $sess['subject'] ?? '',
                'subject_code' => $sess['subject_code'] ?? '',
                'status' => $sess['status'],
                'batch' => $sess['batch'] ?? '',
                'semester' => $sess['semester'] ?? 0,
                'date' => date('d M Y', $sess['date']->toDateTime()->getTimestamp()),
            ],
            'students' => $students,
        ]);
    }

    // ── Mentor: OD requests list ──
    if ($action === 'od_requests' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $statusFilter = $_GET['filter'] ?? 'all';
        $query = ['mentor_id' => $mentorId];
        if ($statusFilter !== 'all') $query['status'] = $statusFilter;

        $cursor = $od_requests->find($query, ['sort' => ['created_at' => -1]]);
        $list = [];
        foreach ($cursor as $r) {
            $stu = $users->findOne(['roll' => $r['student_roll'] ?? '']);
            $list[] = [
                '_id' => (string)$r['_id'],
                'student_roll' => $r['student_roll'] ?? '',
                'student_name' => $stu['name'] ?? $r['student_roll'] ?? '',
                'od_type' => $r['od_type'] ?? '',
                'duration' => $r['duration'] ?? '',
                'date_from' => isset($r['date_from']) ? date('d M Y', $r['date_from']->toDateTime()->getTimestamp()) : '',
                'date_to' => isset($r['date_to']) ? date('d M Y', $r['date_to']->toDateTime()->getTimestamp()) : '',
                'hours' => $r['hours'] ?? [],
                'reason' => $r['reason'] ?? '',
                'status' => $r['status'] ?? 'pending',
                'mentor_remarks' => $r['mentor_remarks'] ?? '',
                'created_at' => isset($r['created_at']) ? date('d M Y, h:i A', $r['created_at']->toDateTime()->getTimestamp()) : '',
            ];
        }
        jsonOut(['status' => 'success', 'requests' => $list]);
    }

    // ── Mentor: attendance report for batch ──
    if ($action === 'batch_report' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $batch = $_GET['batch'] ?? '';
        $semester = (int)($_GET['semester'] ?? 0);
        if (!$batch) jsonOut(['status' => 'error', 'message' => 'Batch required']);

        $students = iterator_to_array($users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]));
        $report = [];
        foreach ($students as $stu) {
            $roll = $stu['roll'];
            $sessions = $attendance_sessions->find([
                'rolls' => $roll,
                'batch' => $batch,
                'status' => 'CONDUCTED'
            ]);
            $allSessions = iterator_to_array($sessions);
            $attCursor = $student_attendance->find(['student_roll' => $roll]);
            $attMap = [];
            foreach ($attCursor as $a) {
                $attMap[$a['attendance_session_id']] = $a;
            }

            $attended = 0; $total = 0;
            foreach ($allSessions as $sess) {
                $total++;
                $att = $attMap[$sess['attendance_session_id']] ?? null;
                if ($att) {
                    $eff = $att['effective_status'] ?? $att['status'] ?? 'ABSENT';
                    if ($eff === 'PRESENT' || $eff === 'OD') $attended++;
                }
            }
            $pct = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
            $report[] = [
                'roll' => $roll,
                'name' => $stu['name'],
                'attended' => $attended,
                'total' => $total,
                'percentage' => $pct,
            ];
        }
        jsonOut(['status' => 'success', 'report' => $report]);
    }

    jsonOut(['status' => 'error', 'message' => 'Unknown action']);
}

// ─── POST endpoints ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mentorId = $isMentor ? $_SESSION['mentor']['mentor_id'] : '';

    // ── Save timetable entry ──
    if ($action === 'save_timetable' && $isMentor) {
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $day = (int)($_POST['day'] ?? -1);
        $hour = (int)($_POST['hour'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');

        if (!$batch || !$semester || $day < 0 || $day > 6 || $hour < 1 || $hour > 7 || !$subject) {
            jsonOut(['status' => 'error', 'message' => 'All fields are required']);
        }

        $existing = $timetables->findOne([
            'mentor_id' => $mentorId,
            'batch' => $batch,
            'semester' => $semester,
            'day' => $day,
            'hour' => $hour,
        ]);

        $entry = [
            'mentor_id' => $mentorId,
            'batch' => $batch,
            'semester' => $semester,
            'day' => $day,
            'hour' => $hour,
            'start_time' => $HOURS[$hour]['start'],
            'end_time' => $HOURS[$hour]['end'],
            'subject' => $subject,
            'subject_code' => $subjectCode,
            'faculty' => $faculty,
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ];

        if ($existing) {
            $timetables->updateOne(
                ['_id' => $existing['_id']],
                ['$set' => $entry]
            );
        } else {
            $entry['created_at'] = new MongoDB\BSON\UTCDateTime();
            $timetables->insertOne($entry);
        }

        jsonOut(['status' => 'success', 'message' => 'Timetable entry saved']);
    }

    // ── Delete timetable entry ──
    if ($action === 'delete_timetable' && $isMentor) {
        $id = $_POST['id'] ?? '';
        if (!$id) jsonOut(['status' => 'error', 'message' => 'ID required']);
        $timetables->deleteOne([
            '_id' => new MongoDB\BSON\ObjectId($id),
            'mentor_id' => $mentorId,
        ]);
        jsonOut(['status' => 'success', 'message' => 'Timetable entry deleted']);
    }

    // ── Generate sessions for date ──
    if ($action === 'generate_sessions' && $isMentor) {
        $dateStr = $_POST['date'] ?? '';
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);

        if (!$dateStr || !$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'Date, batch and semester required']);
        }

        $ts = strtotime($dateStr);
        $dayOfWeek = (int)date('w', $ts);
        $dateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);

        // Check holiday
        $holiday = $attendance_exceptions->findOne([
            'type' => 'HOLIDAY',
            'date' => $dateObj,
            'batch' => ['$in' => [$batch, '*']],
        ]);
        if ($holiday) {
            jsonOut(['status' => 'error', 'message' => 'This date is a holiday: ' . ($holiday['description'] ?? '')]);
        }

        // Load timetable for this day
        $ttCursor = $timetables->find([
            'mentor_id' => $mentorId,
            'batch' => $batch,
            'semester' => $semester,
            'day' => $dayOfWeek,
        ], ['sort' => ['hour' => 1]]);
        $ttEntries = iterator_to_array($ttCursor);

        if (empty($ttEntries)) {
            jsonOut(['status' => 'error', 'message' => 'No timetable found for ' . $DAY_NAMES[$dayOfWeek] . '. Please set up the timetable first.']);
        }

        // Get students for this batch
        $studentCursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $studentRolls = [];
        foreach ($studentCursor as $s) {
            $studentRolls[] = $s['roll'];
        }

        if (empty($studentRolls)) {
            jsonOut(['status' => 'error', 'message' => 'No students found for this batch']);
        }

        $created = 0;
        foreach ($ttEntries as $tt) {
            // Check if session already exists
            $existing = $attendance_sessions->findOne([
                'date' => $dateObj,
                'batch' => $batch,
                'hour' => $tt['hour'],
            ]);

            if ($existing) continue;

            $sessionId = genSessionId();
            $attendance_sessions->insertOne([
                'attendance_session_id' => $sessionId,
                'date' => $dateObj,
                'mentor_id' => $mentorId,
                'batch' => $batch,
                'semester' => $semester,
                'hour' => $tt['hour'],
                'start_time' => $tt['start_time'],
                'end_time' => $tt['end_time'],
                'subject' => $tt['subject'],
                'subject_code' => $tt['subject_code'] ?? '',
                'original_faculty' => $tt['faculty'] ?? '',
                'actual_faculty' => $tt['faculty'] ?? '',
                'session_type' => 'REGULAR',
                'status' => 'SCHEDULED',
                'rolls' => $studentRolls,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]);
            $created++;
        }

        jsonOut(['status' => 'success', 'message' => "$created attendance sessions created for " . date('d M Y', $ts)]);
    }

    // ── Mark attendance ──
    if ($action === 'mark_attendance' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $statusesJson = $_POST['statuses'] ?? '[]';
        $statuses = json_decode($statusesJson, true);

        if (!$sessionId || !$statuses) {
            jsonOut(['status' => 'error', 'message' => 'Session ID and statuses required']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $now = new MongoDB\BSON\UTCDateTime();
        $absentRolls = [];

        foreach ($statuses as $s) {
            $roll = $s['roll'];
            $status = $s['status'];

            $existing = $student_attendance->findOne([
                'student_roll' => $roll,
                'attendance_session_id' => $sessionId,
            ]);

            $attData = [
                'student_roll' => $roll,
                'attendance_session_id' => $sessionId,
                'status' => $status,
                'original_status' => $status,
                'effective_status' => $status,
                'marked_by' => $mentorId,
                'marked_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing) {
                // Preserve original_status if it was set
                $origStatus = $existing['original_status'] ?? $status;
                $attData['original_status'] = $origStatus;
                // If effective_status was changed by OD approval, preserve it
                $effStatus = $existing['effective_status'] ?? $status;
                if ($existing['od_approved'] ?? false) {
                    $attData['effective_status'] = $effStatus;
                }
                $student_attendance->updateOne(
                    ['_id' => $existing['_id']],
                    ['$set' => $attData]
                );
            } else {
                $student_attendance->insertOne($attData);
            }

            if ($status === 'ABSENT') {
                $absentRolls[] = $roll;
            }
        }

        // Update session status to CONDUCTED
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status' => 'CONDUCTED',
                'updated_at' => $now,
            ]]
        );

        // Notify absent students
        if (!empty($absentRolls)) {
            $subject = $sess['subject'] ?? '';
            $hour = $sess['hour'] ?? '';
            $dateStr = date('d M Y', $sess['date']->toDateTime()->getTimestamp());
            notifyStudents($users, $notifications, $absentRolls,
                "You were marked ABSENT for $subject (H$hour) on $dateStr",
                'ABSENT',
                ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour]
            );
        }

        jsonOut(['status' => 'success', 'message' => 'Attendance saved successfully']);
    }

    // ── Mark all present ──
    if ($action === 'mark_all_present' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        if (!$sessionId) jsonOut(['status' => 'error', 'message' => 'Session ID required']);

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $rolls = $sess['rolls'] ?? [];
        $now = new MongoDB\BSON\UTCDateTime();

        foreach ($rolls as $roll) {
            $existing = $student_attendance->findOne([
                'student_roll' => $roll,
                'attendance_session_id' => $sessionId,
            ]);

            if ($existing && ($existing['od_approved'] ?? false)) {
                continue;
            }

            $attData = [
                'student_roll' => $roll,
                'attendance_session_id' => $sessionId,
                'status' => 'PRESENT',
                'original_status' => $existing['original_status'] ?? 'PRESENT',
                'effective_status' => 'PRESENT',
                'marked_by' => $mentorId,
                'marked_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing) {
                $student_attendance->updateOne(
                    ['_id' => $existing['_id']],
                    ['$set' => $attData]
                );
            } else {
                $student_attendance->insertOne($attData);
            }
        }

        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => ['status' => 'CONDUCTED', 'updated_at' => $now]]
        );

        jsonOut(['status' => 'success', 'message' => 'All students marked present']);
    }

    // ── Declare holiday ──
    if ($action === 'declare_holiday' && $isMentor) {
        $dateStr = $_POST['date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $batch = trim($_POST['batch'] ?? '*');

        if (!$dateStr || !$description) {
            jsonOut(['status' => 'error', 'message' => 'Date and description required']);
        }

        $ts = strtotime($dateStr);
        $dateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);

        $existing = $attendance_exceptions->findOne([
            'type' => 'HOLIDAY',
            'date' => $dateObj,
            'batch' => $batch,
        ]);
        if ($existing) {
            jsonOut(['status' => 'error', 'message' => 'Holiday already declared for this date']);
        }

        $attendance_exceptions->insertOne([
            'type' => 'HOLIDAY',
            'date' => $dateObj,
            'description' => $description,
            'batch' => $batch,
            'mentor_id' => $mentorId,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ]);

        // Cancel all sessions for this date+batch
        $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);
        $to = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);

        $sessionQuery = ['date' => ['$gte' => $from, '$lte' => $to]];
        if ($batch !== '*') $sessionQuery['batch'] = $batch;

        $attendance_sessions->updateMany(
            $sessionQuery,
            ['$set' => ['status' => 'CANCELLED', 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
        );

        // Notify students
        $studentQuery = ['mentor_id' => $mentorId];
        if ($batch !== '*') $studentQuery['batch_no'] = $batch;
        $students = iterator_to_array($users->find($studentQuery));
        $rolls = array_map(fn($s) => $s['roll'], $students);
        notifyStudents($users, $notifications, $rolls,
            "Holiday declared for " . date('d M Y', $ts) . ": $description",
            'HOLIDAY',
            ['date' => $dateStr]
        );

        jsonOut(['status' => 'success', 'message' => 'Holiday declared successfully']);
    }

    // ── Suspend period ──
    if ($action === 'suspend_session' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (!$sessionId || !$reason) {
            jsonOut(['status' => 'error', 'message' => 'Session ID and reason required']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status' => 'SUSPENDED',
                'suspension_reason' => $reason,
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        // Notify affected students
        $rolls = $sess['rolls'] ?? [];
        $subject = $sess['subject'] ?? '';
        $hour = $sess['hour'] ?? '';
        $dateStr = date('d M Y', $sess['date']->toDateTime()->getTimestamp());
        notifyStudents($users, $notifications, $rolls,
            "H$hour $subject on $dateStr has been SUSPENDED: $reason",
            'SUSPENDED',
            ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour]
        );

        jsonOut(['status' => 'success', 'message' => 'Session suspended successfully']);
    }

    // ── Substitution ──
    if ($action === 'substitute' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $subFaculty = trim($_POST['substitute_faculty'] ?? '');

        if (!$sessionId || !$subFaculty) {
            jsonOut(['status' => 'error', 'message' => 'Session ID and substitute faculty required']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $originalFaculty = $sess['original_faculty'] ?? '';
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'actual_faculty' => $subFaculty,
                'status' => 'SUBSTITUTION',
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        $rolls = $sess['rolls'] ?? [];
        $subject = $sess['subject'] ?? '';
        $hour = $sess['hour'] ?? '';
        $dateStr = date('d M Y', $sess['date']->toDateTime()->getTimestamp());
        notifyStudents($users, $notifications, $rolls,
            "Substitution: H$hour $subject on $dateStr - $originalFaculty replaced by $subFaculty",
            'SUBSTITUTION',
            ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour]
        );

        jsonOut(['status' => 'success', 'message' => 'Substitution saved successfully']);
    }

    // ── Reschedule session ──
    if ($action === 'reschedule_session' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $newDate = $_POST['new_date'] ?? '';
        $newHour = (int)($_POST['new_hour'] ?? 0);

        if (!$sessionId || !$newDate || $newHour < 1 || $newHour > 7) {
            jsonOut(['status' => 'error', 'message' => 'Session ID, new date and hour required']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // Mark original as RESCHEDULED
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status' => 'RESCHEDULED',
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        // Create new session
        $ts = strtotime($newDate);
        $newDateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);
        $newSessionId = genSessionId();

        $attendance_sessions->insertOne([
            'attendance_session_id' => $newSessionId,
            'date' => $newDateObj,
            'mentor_id' => $mentorId,
            'batch' => $sess['batch'],
            'semester' => $sess['semester'],
            'hour' => $newHour,
            'start_time' => $HOURS[$newHour]['start'],
            'end_time' => $HOURS[$newHour]['end'],
            'subject' => $sess['subject'],
            'subject_code' => $sess['subject_code'] ?? '',
            'original_faculty' => $sess['original_faculty'] ?? '',
            'actual_faculty' => $sess['original_faculty'] ?? '',
            'session_type' => 'RESCHEDULED',
            'status' => 'SCHEDULED',
            'rolls' => $sess['rolls'] ?? [],
            'rescheduled_from' => $sessionId,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ]);

        $rolls = $sess['rolls'] ?? [];
        $subject = $sess['subject'] ?? '';
        $oldDate = date('d M Y', $sess['date']->toDateTime()->getTimestamp());
        notifyStudents($users, $notifications, $rolls,
            "Rescheduled: H" . $sess['hour'] . " $subject from $oldDate to " . date('d M Y', $ts) . " (H$newHour)",
            'RESCHEDULED',
            ['attendance_session_id' => $newSessionId, 'subject' => $subject, 'hour' => $newHour]
        );

        jsonOut(['status' => 'success', 'message' => 'Session rescheduled successfully']);
    }

    // ── Create special class ──
    if ($action === 'create_special_class' && $isMentor) {
        $dateStr = $_POST['date'] ?? '';
        $hour = (int)($_POST['hour'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $classType = $_POST['class_type'] ?? 'SPECIAL';

        if (!$dateStr || $hour < 1 || $hour > 7 || !$subject || !$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'All fields are required']);
        }

        $ts = strtotime($dateStr);
        $dateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$ts),date('j',$ts),date('Y',$ts))*1000);
        $dayOfWeek = (int)date('w', $ts);

        $validTypes = ['SPECIAL', 'EXTRA', 'SATURDAY', 'SUNDAY'];
        if (!in_array($classType, $validTypes)) $classType = 'SPECIAL';
        if ($dayOfWeek === 6) $classType = 'SATURDAY';
        if ($dayOfWeek === 0) $classType = 'SUNDAY';

        $studentCursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $studentRolls = [];
        foreach ($studentCursor as $s) {
            $studentRolls[] = $s['roll'];
        }

        if (empty($studentRolls)) {
            jsonOut(['status' => 'error', 'message' => 'No students found for this batch']);
        }

        $sessionId = genSessionId();
        $attendance_sessions->insertOne([
            'attendance_session_id' => $sessionId,
            'date' => $dateObj,
            'mentor_id' => $mentorId,
            'batch' => $batch,
            'semester' => $semester,
            'hour' => $hour,
            'start_time' => $HOURS[$hour]['start'],
            'end_time' => $HOURS[$hour]['end'],
            'subject' => $subject,
            'subject_code' => $subjectCode,
            'original_faculty' => $faculty,
            'actual_faculty' => $faculty,
            'session_type' => $classType,
            'status' => 'SCHEDULED',
            'rolls' => $studentRolls,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ]);

        $typeLabel = strtolower($classType);
        notifyStudents($users, $notifications, $studentRolls,
            "New $typeLabel class: $subject (H$hour) on " . date('d M Y', $ts),
            'SPECIAL',
            ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour]
        );

        jsonOut(['status' => 'success', 'message' => ucfirst(strtolower($classType)) . ' class created successfully']);
    }

    // ── Submit OD request (student) ──
    if ($action === 'submit_od' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $mentorId = $_SESSION['user']['mentor_id'] ?? '';
        $odType = trim($_POST['od_type'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $dateFrom = $_POST['date_from'] ?? '';
        $dateTo = $_POST['date_to'] ?? '';
        $hours = $_POST['hours'] ?? [];
        $reason = trim($_POST['reason'] ?? '');

        if (!$odType || !$duration || !$reason) {
            jsonOut(['status' => 'error', 'message' => 'Type, duration and reason are required']);
        }

        if ($duration === 'select_hours' && empty($hours)) {
            jsonOut(['status' => 'error', 'message' => 'Please select at least one hour']);
        }

        $fromTs = $dateFrom ? strtotime($dateFrom) : null;
        $toTs = $dateTo ? strtotime($dateTo) : null;

        $odData = [
            'student_roll' => $roll,
            'student_name' => $_SESSION['user']['name'],
            'mentor_id' => $mentorId,
            'od_type' => $odType,
            'duration' => $duration,
            'date_from' => $fromTs ? new MongoDB\BSON\UTCDateTime($fromTs * 1000) : null,
            'date_to' => $toTs ? new MongoDB\BSON\UTCDateTime($toTs * 1000) : null,
            'hours' => $hours,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ];

        $od_requests->insertOne($odData);

        // Notify mentor
        if ($mentorId) {
            notifyMentor($notifications, $mentorId,
                "New OD/Leave request from " . $_SESSION['user']['name'] . " ($roll) - Type: $odType",
                'OD_REQUEST',
                ['student_roll' => $roll]
            );
        }

        jsonOut(['status' => 'success', 'message' => 'OD/Leave request submitted successfully']);
    }

    // ── Approve/Reject OD (mentor) ──
    if ($action === 'process_od' && $isMentor) {
        $odId = $_POST['od_id'] ?? '';
        $decision = $_POST['decision'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$odId || !in_array($decision, ['approved', 'rejected'])) {
            jsonOut(['status' => 'error', 'message' => 'Invalid request']);
        }

        $od = $od_requests->findOne(['_id' => new MongoDB\BSON\ObjectId($odId)]);
        if (!$od) jsonOut(['status' => 'error', 'message' => 'Request not found']);
        if ($od['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $od_requests->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($odId)],
            ['$set' => [
                'status' => $decision,
                'mentor_remarks' => $remarks,
                'processed_at' => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        $roll = $od['student_roll'] ?? '';
        $odType = $od['od_type'] ?? '';

        if ($decision === 'approved') {
            // Update attendance records - set effective_status to OD
            $hours = $od['hours'] ?? [];
            $fromTs = $od['date_from'] ? $od['date_from']->toDateTime()->getTimestamp() : null;
            $toTs = $od['date_to'] ? $od['date_to']->toDateTime()->getTimestamp() : null;

            $query = ['student_roll' => $roll];
            if (!empty($hours)) {
                // Find sessions matching the selected hours
                $sessions = $attendance_sessions->find([
                    'rolls' => $roll,
                    'hour' => ['$in' => array_map('intval', $hours)],
                ]);
            } else if ($fromTs && $toTs) {
                $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,date('n',$fromTs),date('j',$fromTs),date('Y',$fromTs))*1000);
                $to = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,date('n',$toTs),date('j',$toTs),date('Y',$toTs))*1000);
                $sessions = $attendance_sessions->find([
                    'rolls' => $roll,
                    'date' => ['$gte' => $from, '$lte' => $to],
                    'status' => 'CONDUCTED',
                ]);
            } else {
                $sessions = [];
            }

            if (is_iterable($sessions)) {
                foreach ($sessions as $sess) {
                    $existing = $student_attendance->findOne([
                        'student_roll' => $roll,
                        'attendance_session_id' => $sess['attendance_session_id'],
                    ]);

                    if ($existing) {
                        $student_attendance->updateOne(
                            ['_id' => $existing['_id']],
                            ['$set' => [
                                'effective_status' => 'OD',
                                'od_approved' => true,
                                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                            ]]
                        );
                    } else {
                        $student_attendance->insertOne([
                            'student_roll' => $roll,
                            'attendance_session_id' => $sess['attendance_session_id'],
                            'status' => 'ABSENT',
                            'original_status' => 'ABSENT',
                            'effective_status' => 'OD',
                            'od_approved' => true,
                            'marked_by' => $mentorId,
                            'marked_at' => new MongoDB\BSON\UTCDateTime(),
                            'updated_at' => new MongoDB\BSON\UTCDateTime(),
                        ]);
                    }
                }
            }

            // Notify student
            $notifications->insertOne([
                'roll' => $roll,
                'message' => "Your OD/Leave request ($odType) has been APPROVED.",
                'type' => 'OD_APPROVED',
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
            ]);
        } else {
            // Rejected - attendance remains as-is
            $notifications->insertOne([
                'roll' => $roll,
                'message' => "Your OD/Leave request ($odType) has been REJECTED." . ($remarks ? " Reason: $remarks" : ''),
                'type' => 'OD_REJECTED',
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
            ]);
        }

        jsonOut(['status' => 'success', 'message' => 'OD/Leave request ' . $decision . ' successfully']);
    }

    // ── Get attendance percentage (for SGI integration) ──
    if ($action === 'attendance_percentage' && $isStudent) {
        $roll = $_SESSION['user']['roll'];

        $sessions = $attendance_sessions->find([
            'rolls' => $roll,
            'status' => 'CONDUCTED'
        ]);
        $allSessions = iterator_to_array($sessions);

        $attCursor = $student_attendance->find(['student_roll' => $roll]);
        $attMap = [];
        foreach ($attCursor as $a) {
            $attMap[$a['attendance_session_id']] = $a;
        }

        $attended = 0; $total = 0;
        foreach ($allSessions as $sess) {
            $total++;
            $att = $attMap[$sess['attendance_session_id']] ?? null;
            if ($att) {
                $eff = $att['effective_status'] ?? $att['status'] ?? 'ABSENT';
                if ($eff === 'PRESENT' || $eff === 'OD') $attended++;
            }
        }

        $pct = $total > 0 ? round(($attended / $total) * 100, 2) : 0;
        jsonOut(['status' => 'success', 'percentage' => $pct, 'total' => $total, 'attended' => $attended]);
    }

    jsonOut(['status' => 'error', 'message' => 'Unknown action']);
}

jsonOut(['status' => 'error', 'message' => 'Method not allowed']);
