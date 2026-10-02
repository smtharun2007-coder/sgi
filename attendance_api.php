<?php
include 'config.php';

header('Content-Type: application/json');

$isMentor  = isset($_SESSION['mentor']) && !empty($_SESSION['mentor']['mentor_id']);
$isStudent = isset($_SESSION['user']) && !empty($_SESSION['user']['roll']);

if (!$isMentor && !$isStudent) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$HOURS = sgiGetStandardHours();
$DAY_NAMES = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

function jsonOut($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validObjectId($id) {
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
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
        'message'    => $message,
        'type'       => $type,
        'read'       => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ], $extra);
    $notifications->insertOne($doc);
}

// ─────────────────────────────────────────────────────────────
// GET ENDPOINTS
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // ── Student: Overview ──
    // ── Student: Overview ──
    if ($action === 'student_overview' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $stuUser = $users->findOne(['roll' => $roll]);
        $stuBatch = $stuUser['batch_no'] ?? '';
        $stuSem = (int)($stuUser['semester'] ?? 1);

        // Fetch semester statuses for all 8 semesters to find current active semester and allow switching
        $allSemStatus = [];
        $activeSem = $stuSem;
        for ($s = 1; $s <= 8; $s++) {
            $st = sgiGetSemesterAttendanceStatus($stuBatch, $s, $db);
            $allSemStatus[$s] = $st['status'];
            if ($st['is_closed'] && $activeSem <= $s && $s < 8) {
                $activeSem = $s + 1;
            }
        }

        // Selected semester from query or active semester
        $reqSem = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
        $viewSem = ($reqSem >= 1 && $reqSem <= 8) ? $reqSem : $activeSem;

        // Ensure user record in DB reflects the active semester if it advanced
        if ($stuSem < $activeSem) {
            $users->updateOne(['roll' => $roll], ['$set' => ['semester' => $activeSem, 'sem' => $activeSem, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]);
            $_SESSION['user']['semester'] = $activeSem;
            $_SESSION['user']['sem'] = $activeSem;
            $stuSem = $activeSem;
        }

        $attData = calculateStudentAttendance($roll, $db, ['semester' => $viewSem, 'batch' => $stuBatch]);
        $semStatus = ($stuBatch && $viewSem) ? sgiGetSemesterAttendanceStatus($stuBatch, $viewSem, $db) : ['status' => 'OPEN', 'can_mark' => true];

        jsonOut([
            'status'             => 'success',
            'overall'            => $attData['attendance_percentage'],
            'discipline_score'   => $attData['discipline_score'],
            'daily_percentage'   => $attData['daily_percentage'],
            'session_percentage' => $attData['session_percentage'],
            'present_days'       => $attData['total_days_present'],
            'absent_days'        => $attData['total_days_absent'],
            'total_days'         => $attData['total_days_conducted'],
            'present'            => $attData['total_attended'],
            'absent'             => $attData['total_absent'],
            'od'                 => $attData['total_od_approved'],
            'suspended'          => $attData['total_suspended'],
            'total_sessions'     => $attData['total_conducted'],
            'attended_sessions'  => $attData['total_attended'],
            'subjects'           => $attData['subjects'],
            'semester'           => $viewSem,
            'current_semester'   => $activeSem,
            'user_semester'      => $stuSem,
            'all_semesters'      => $allSemStatus,
            'batch'              => $stuBatch,
            'semester_status'    => $semStatus['status'] ?? 'OPEN',
            'is_closed'          => ($semStatus['status'] ?? '') === 'CLOSED',
            'is_locked'          => ($semStatus['status'] ?? '') === 'LOCKED',
            'closed_by'          => $semStatus['closed_by_name'] ?? $semStatus['closed_by'] ?? null,
            'closed_at'          => isset($semStatus['closed_at']) && ($semStatus['closed_at'] instanceof MongoDB\BSON\UTCDateTime) ? date('d M Y, h:i A', $semStatus['closed_at']->toDateTime()->getTimestamp()) : null,
        ]);
    }

    // ── Student: Day-wise Attendance ──
    if ($action === 'student_daywise' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $stuUser = $users->findOne(['roll' => $roll]);
        $stuBatch = $stuUser['batch_no'] ?? '';
        $stuSem = (int)($stuUser['semester'] ?? 1);
        $reqSem = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
        $viewSem = ($reqSem >= 1 && $reqSem <= 8) ? $reqSem : $stuSem;

        $attData = calculateStudentAttendance($roll, $db, ['semester' => $viewSem, 'batch' => $stuBatch]);

        jsonOut([
            'status'          => 'success',
            'semester'        => $viewSem,
            'overall'         => $attData['attendance_percentage'],
            'daily_breakdown' => $attData['daily_breakdown'],
        ]);
    }

    // ── Student: Calendar ──
    if ($action === 'student_calendar' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $month = (int)($_GET['month'] ?? date('n'));
        $year  = (int)($_GET['year']  ?? date('Y'));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $from = new MongoDB\BSON\UTCDateTime(mktime(0, 0, 0, $month, 1, $year) * 1000);
        $to   = new MongoDB\BSON\UTCDateTime(mktime(23, 59, 59, $month, $daysInMonth, $year) * 1000);

        $sessions = iterator_to_array($attendance_sessions->find([
            'rolls' => $roll,
            'date'  => ['$gte' => $from, '$lte' => $to]
        ], ['sort' => ['date' => 1, 'hour' => 1]]));

        $attRecords = iterator_to_array($student_attendance->find(['student_roll' => $roll]));
        $attBySession = [];
        foreach ($attRecords as $a) {
            $attBySession[$a['attendance_session_id']] = $a;
        }

        $stuUser = $users->findOne(['roll' => $roll]);
        $mentorId = $stuUser['mentor_id'] ?? '';
        $stuBatch = $stuUser['batch_no'] ?? '';

        // Check for holidays scoped to batch
        $exQuery = [
            'type' => 'HOLIDAY',
            'date' => ['$gte' => $from, '$lte' => $to]
        ];
        if (!empty($stuBatch)) {
            $exQuery['$or'] = [
                ['batch' => $stuBatch],
                ['batch' => '*'],
                ['batch' => ['$exists' => false]],
            ];
        }
        $exceptions = iterator_to_array($attendance_exceptions->find($exQuery));
        $holidayMap = [];
        foreach ($exceptions as $ex) {
            if (isset($ex['date']) && $ex['date'] instanceof MongoDB\BSON\UTCDateTime) {
                $day = (int)date('j', $ex['date']->toDateTime()->getTimestamp());
                $holidayMap[$day] = $ex['description'] ?? 'Holiday';
            }
        }

        // Also check mentor calendar events for holidays
        $cevQuery = [
            'type' => ['$in' => ['holiday', 'Holiday']],
            'date' => ['$gte' => $from, '$lte' => $to]
        ];
        if (!empty($mentorId)) {
            $cevQuery['mentor_id'] = $mentorId;
        }
        $calEvents = iterator_to_array($calendar_events->find($cevQuery));
        foreach ($calEvents as $cev) {
            if (isset($cev['date']) && $cev['date'] instanceof MongoDB\BSON\UTCDateTime) {
                $day = (int)date('j', $cev['date']->toDateTime()->getTimestamp());
                if (!isset($holidayMap[$day])) {
                    $holidayMap[$day] = $cev['title'] ?? 'Holiday';
                }
            }
        }

        $dayMap = [];
        foreach ($sessions as $sess) {
            $sessId = $sess['attendance_session_id'];
            $day = (int)date('j', $sess['date']->toDateTime()->getTimestamp());
            $att = $attBySession[$sessId] ?? null;

            $sessStatus = strtoupper($sess['status'] ?? 'SCHEDULED');
            $effStatus = null;
            $origStatus = null;
            $isOD = false;

            if ($att) {
                $effStatus = strtoupper($att['effective_status'] ?? $att['status'] ?? '');
                $origStatus = strtoupper($att['original_status'] ?? $att['status'] ?? '');
                $isOD = (!empty($att['od_approved']) || $effStatus === 'OD');
            }

            // Student calendar display status: Holidays are strictly excluded with zero penalty
            $isHoliday = isset($holidayMap[$day]);
            if ($isHoliday) {
                $displayAtt = 'HOLIDAY';
                $sessStatus = 'HOLIDAY';
                $reasonNote = 'Holiday: ' . $holidayMap[$day];
            } elseif ($sessStatus === 'SUSPENDED') {
                $displayAtt = 'SUSPENDED';
                $reasonNote = $sess['suspension_reason'] ?? 'Suspended';
            } elseif ($sessStatus === 'CANCELLED') {
                $displayAtt = 'CANCELLED';
                $reasonNote = $sess['cancellation_reason'] ?? 'Cancelled (Excluded)';
            } else {
                $displayAtt = $effStatus;
                $reasonNote = $sess['suspension_reason'] ?? '';
            }

            $dayMap[$day][] = [
                'attendance_session_id' => $sessId,
                'hour'            => $sess['hour'],
                'subject'         => $sess['subject'] ?? '',
                'subject_code'    => $sess['subject_code'] ?? '',
                'faculty'         => $sess['actual_faculty'] ?? $sess['original_faculty'] ?? '',
                'session_status'  => $sessStatus,
                'attendance'      => $displayAtt,
                'original_status' => $origStatus,
                'effective_status'=> $effStatus,
                'is_od'           => $isOD,
                'is_holiday'      => $isHoliday,
                'suspension_reason'=> $reasonNote,
                'start_time'      => $sess['start_time'] ?? '',
                'end_time'        => $sess['end_time'] ?? '',
                'session_type'    => $sess['session_type'] ?? 'REGULAR',
            ];
        }

        jsonOut([
            'status'         => 'success',
            'days'           => $dayMap,
            'holidays'       => $holidayMap,
            'holiday_count'  => count($holidayMap),
            'impact_penalty' => '0%',
            'impact_message' => 'Holidays are non-instructional days and are excluded from attendance calculations (0% penalty).',
        ]);
    }

    // ── Student: OD list ──
    if ($action === 'student_od_list' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $cursor = $od_requests->find(['student_roll' => $roll], ['sort' => ['created_at' => -1]]);
        $list = [];
        foreach ($cursor as $r) {
            $list[] = [
                '_id'            => (string)$r['_id'],
                'type'           => $r['od_type'] ?? '',
                'duration'       => $r['duration'] ?? '',
                'half_day_type'  => $r['half_day_type'] ?? '',
                'date_from'      => isset($r['date_from']) ? date('d M Y', $r['date_from']->toDateTime()->getTimestamp()) : '',
                'date_to'        => isset($r['date_to']) ? date('d M Y', $r['date_to']->toDateTime()->getTimestamp()) : '',
                'hours'          => $r['hours'] ?? [],
                'reason'         => $r['reason'] ?? '',
                'status'         => $r['status'] ?? 'pending',
                'mentor_remarks' => $r['mentor_remarks'] ?? '',
                'created_at'     => isset($r['created_at']) ? date('d M Y, h:i A', $r['created_at']->toDateTime()->getTimestamp()) : '',
            ];
        }
        jsonOut(['status' => 'success', 'requests' => $list]);
    }

    // ── Mentor: Timetable ──
    if ($action === 'timetable' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $batch = $_GET['batch'] ?? '';
        $semester = (int)($_GET['semester'] ?? 0);

        if (!$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'Batch and semester required']);
        }

        $cursor = $timetables->find([
            'mentor_id' => $mentorId,
            'batch'     => $batch,
            'semester'  => $semester
        ], ['sort' => ['day' => 1, 'hour' => 1]]);

        $entries = [];
        foreach ($cursor as $t) {
            $entries[] = [
                '_id'          => (string)$t['_id'],
                'day'          => $t['day'],
                'day_name'     => $DAY_NAMES[$t['day']] ?? '',
                'hour'         => $t['hour'],
                'start_time'   => $t['start_time'],
                'end_time'     => $t['end_time'],
                'subject'      => $t['subject'] ?? '',
                'subject_code' => $t['subject_code'] ?? '',
                'faculty'      => $t['faculty'] ?? '',
                'class_section'=> $t['class_section'] ?? '',
            ];
        }
        jsonOut(['status' => 'success', 'timetable' => $entries]);
    }

    // ── Mentor: Batch Students ──
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
                'reg'  => $s['reg'] ?? '',
            ];
        }
        jsonOut(['status' => 'success', 'students' => $students]);
    }

    // ── Mentor: Sessions for Date ──
    if ($action === 'sessions_for_date' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $dateStr = $_GET['date'] ?? '';
        if (!$dateStr) jsonOut(['status' => 'error', 'message' => 'Date required']);

        $ts = strtotime($dateStr);
        $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
        $to   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);

        $sessions = iterator_to_array($attendance_sessions->find([
            'mentor_id' => $mentorId,
            'date'      => ['$gte' => $from, '$lte' => $to]
        ], ['sort' => ['hour' => 1]]));

        $result = [];
        foreach ($sessions as $sess) {
            $result[] = [
                'attendance_session_id' => $sess['attendance_session_id'],
                'hour'             => $sess['hour'],
                'start_time'       => $sess['start_time'] ?? '',
                'end_time'         => $sess['end_time'] ?? '',
                'subject'          => $sess['subject'] ?? '',
                'subject_code'     => $sess['subject_code'] ?? '',
                'original_faculty' => $sess['original_faculty'] ?? '',
                'actual_faculty'   => $sess['actual_faculty'] ?? '',
                'session_type'     => $sess['session_type'] ?? 'REGULAR',
                'status'           => $sess['status'] ?? 'SCHEDULED',
                'suspension_reason'=> $sess['suspension_reason'] ?? '',
                'batch'            => $sess['batch'] ?? '',
                'semester'         => $sess['semester'] ?? 0,
                'semester_status'  => (function() use ($sess, $db) {
                    $sb = $sess['batch'] ?? '';
                    $sm = (int)($sess['semester'] ?? 0);
                    return ($sb && $sm) ? sgiGetSemesterAttendanceStatus($sb, $sm, $db)['status'] : 'OPEN';
                })(),
                'can_mark'         => (function() use ($sess, $db) {
                    $sb = $sess['batch'] ?? '';
                    $sm = (int)($sess['semester'] ?? 0);
                    return ($sb && $sm) ? sgiCanMarkAttendance($sb, $sm, $db)['allowed'] : true;
                })(),
                'lock_reason'      => (function() use ($sess, $db) {
                    $sb = $sess['batch'] ?? '';
                    $sm = (int)($sess['semester'] ?? 0);
                    return ($sb && $sm) ? (sgiGetSemesterAttendanceStatus($sb, $sm, $db)['reason'] ?? '') : '';
                })(),
            ];
        }

        // Check holiday
        $holiday = $attendance_exceptions->findOne([
            'type' => 'HOLIDAY',
            'date' => ['$gte' => $from, '$lte' => $to]
        ]);

        jsonOut([
            'status'   => 'success',
            'sessions' => $result,
            'holiday'  => $holiday ? ['description' => $holiday['description'] ?? 'Holiday', '_id' => (string)$holiday['_id']] : null,
        ]);
    }

    // ── Mentor: Month Calendar Overview (Color-coded by marked/pending/holiday) ──
    if ($action === 'mentor_month_calendar' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $month = (int)($_GET['month'] ?? date('n'));
        $year  = (int)($_GET['year']  ?? date('Y'));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $from = new MongoDB\BSON\UTCDateTime(mktime(0, 0, 0, $month, 1, $year) * 1000);
        $to   = new MongoDB\BSON\UTCDateTime(mktime(23, 59, 59, $month, $daysInMonth, $year) * 1000);

        // Fetch all sessions for this mentor in this month
        $sessions = iterator_to_array($attendance_sessions->find([
            'mentor_id' => $mentorId,
            'date'      => ['$gte' => $from, '$lte' => $to]
        ]));

        // Fetch holiday exceptions
        $exceptions = iterator_to_array($attendance_exceptions->find([
            'type' => 'HOLIDAY',
            'date' => ['$gte' => $from, '$lte' => $to]
        ]));
        $holidayMap = [];
        foreach ($exceptions as $ex) {
            if (isset($ex['date']) && $ex['date'] instanceof MongoDB\BSON\UTCDateTime) {
                $d = (int)date('j', $ex['date']->toDateTime()->getTimestamp());
                $holidayMap[$d] = [
                    'id'   => (string)$ex['_id'],
                    'desc' => $ex['description'] ?? 'Holiday',
                ];
            }
        }

        // Fetch calendar events holidays as well
        $calEvents = iterator_to_array($calendar_events->find([
            'mentor_id' => $mentorId,
            'type'      => ['$in' => ['holiday', 'Holiday']],
            'date'      => ['$gte' => $from, '$lte' => $to]
        ]));
        foreach ($calEvents as $cev) {
            if (isset($cev['date']) && $cev['date'] instanceof MongoDB\BSON\UTCDateTime) {
                $d = (int)date('j', $cev['date']->toDateTime()->getTimestamp());
                if (!isset($holidayMap[$d])) {
                    $holidayMap[$d] = [
                        'id'   => (string)$cev['_id'],
                        'desc' => $cev['title'] ?? 'Holiday',
                    ];
                }
            }
        }

        // Group sessions by day
        $days = [];
        foreach ($sessions as $s) {
            $d = (int)date('j', $s['date']->toDateTime()->getTimestamp());
            if (!isset($days[$d])) {
                $days[$d] = [
                    'total'      => 0,
                    'conducted'  => 0, // Marked
                    'scheduled'  => 0, // Unmarked / Pending
                    'suspended'  => 0,
                    'cancelled'  => 0,
                ];
            }
            $days[$d]['total']++;
            $st = strtoupper($s['status'] ?? 'SCHEDULED');
            if ($st === 'CONDUCTED') $days[$d]['conducted']++;
            elseif ($st === 'SCHEDULED') $days[$d]['scheduled']++;
            elseif ($st === 'SUSPENDED') $days[$d]['suspended']++;
            elseif ($st === 'CANCELLED') $days[$d]['cancelled']++;
        }

        // Determine status and color for each day
        $dayStatus = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $isHol = isset($holidayMap[$d]);
            $sess = $days[$d] ?? null;

            if ($isHol) {
                $dayStatus[$d] = [
                    'type'  => 'holiday',
                    'color' => '#f8fafc',
                    'label' => '🏖️ ' . $holidayMap[$d]['desc'],
                    'desc'  => $holidayMap[$d]['desc'],
                    'badge' => 'HOLIDAY',
                ];
            } elseif ($sess && $sess['total'] > 0) {
                if ($sess['conducted'] > 0 && $sess['scheduled'] === 0) {
                    // ALL MARKED -> GREEN!
                    $dayStatus[$d] = [
                        'type'  => 'marked',
                        'color' => '#d4edda',
                        'label' => "✓ {$sess['conducted']} Marked",
                        'badge' => 'MARKED',
                        'count' => $sess['conducted'],
                    ];
                } elseif ($sess['conducted'] > 0 && $sess['scheduled'] > 0) {
                    // PARTIALLY MARKED -> ORANGE / AMBER
                    $dayStatus[$d] = [
                        'type'  => 'partial',
                        'color' => '#fff3cd',
                        'label' => "⚡ {$sess['conducted']}/{$sess['total']} Marked",
                        'badge' => 'PARTIAL',
                        'count' => $sess['conducted'],
                    ];
                } elseif ($sess['scheduled'] > 0) {
                    // SCHEDULED / PENDING -> BLUE
                    $dayStatus[$d] = [
                        'type'  => 'scheduled',
                        'color' => '#e7f3ff',
                        'label' => "⏳ {$sess['scheduled']} Pending",
                        'badge' => 'PENDING',
                        'count' => $sess['scheduled'],
                    ];
                } elseif ($sess['suspended'] > 0 || $sess['cancelled'] > 0) {
                    // SUSPENDED / CANCELLED -> GRAY
                    $dayStatus[$d] = [
                        'type'  => 'suspended',
                        'color' => '#e2e3e5',
                        'label' => 'Suspended',
                        'badge' => 'SUSPENDED',
                    ];
                }
            } else {
                $dayStatus[$d] = [
                    'type'  => 'empty',
                    'label' => '',
                ];
            }
        }

        jsonOut([
            'status'     => 'success',
            'month'      => $month,
            'year'       => $year,
            'days'       => $dayStatus,
            'holidays'   => $holidayMap,
        ]);
    }

    // ── Mentor: Session Attendance (for marking / viewing) ──
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
                'roll'             => $r,
                'name'             => $stu['name'] ?? $r,
                'status'           => $att['status'] ?? '',
                'effective_status' => $att['effective_status'] ?? '',
                'original_status'  => $att['original_status'] ?? '',
                'od_approved'      => !empty($att['od_approved']),
            ];
        }

        $semStatus = sgiGetSemesterAttendanceStatus($sess['batch'], (int)$sess['semester'], $db);

        jsonOut([
            'status'               => 'success',
            'semester_status'      => $semStatus['status'],
            'can_mark'             => $semStatus['can_mark'],
            'is_closed'            => $semStatus['is_closed'],
            'is_locked'            => $semStatus['is_locked'],
            'semester_lock_reason' => $semStatus['lock_reason'],
            'closed_by'            => $semStatus['closed_by_name'] ?? $semStatus['closed_by'] ?? '',
            'closed_at'            => $semStatus['closed_at'] ?? '',
            'session' => [
                'attendance_session_id' => $sess['attendance_session_id'],
                'hour'              => $sess['hour'],
                'subject'           => $sess['subject'] ?? '',
                'subject_code'      => $sess['subject_code'] ?? '',
                'status'            => $sess['status'] ?? 'SCHEDULED',
                'suspension_reason' => $sess['suspension_reason'] ?? '',
                'batch'             => $sess['batch'] ?? '',
                'semester'          => $sess['semester'] ?? 0,
                'faculty'           => $sess['actual_faculty'] ?? $sess['original_faculty'] ?? '',
                'date'              => date('d M Y', $sess['date']->toDateTime()->getTimestamp()),
            ],
            'students' => $students,
        ]);
    }

    // ── Mentor: OD Requests List ──
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
                '_id'            => (string)$r['_id'],
                'student_roll'   => $r['student_roll'] ?? '',
                'student_name'   => $stu['name'] ?? $r['student_roll'] ?? '',
                'od_type'        => $r['od_type'] ?? '',
                'duration'       => $r['duration'] ?? '',
                'half_day_type'  => $r['half_day_type'] ?? '',
                'date_from'      => isset($r['date_from']) ? date('d M Y', $r['date_from']->toDateTime()->getTimestamp()) : '',
                'date_to'        => isset($r['date_to']) ? date('d M Y', $r['date_to']->toDateTime()->getTimestamp()) : '',
                'hours'          => $r['hours'] ?? [],
                'reason'         => $r['reason'] ?? '',
                'status'         => $r['status'] ?? 'pending',
                'mentor_remarks' => $r['mentor_remarks'] ?? '',
                'created_at'     => isset($r['created_at']) ? date('d M Y, h:i A', $r['created_at']->toDateTime()->getTimestamp()) : '',
            ];
        }
        jsonOut(['status' => 'success', 'requests' => $list]);
    }

    // ── Mentor: Batch Report ──
    if ($action === 'batch_report' && $isMentor) {
        $mentorId = $_SESSION['mentor']['mentor_id'];
        $batch = $_GET['batch'] ?? '';
        $semester = (int)($_GET['semester'] ?? 0);
        if (!$batch) jsonOut(['status' => 'error', 'message' => 'Batch required']);

        $students = iterator_to_array($users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]));
        $report = [];
        foreach ($students as $stu) {
            $r = $stu['roll'];
            $att = calculateStudentAttendance($r, $db, ['batch' => $batch, 'semester' => $semester]);
            $report[] = [
                'roll'       => $r,
                'name'       => $stu['name'],
                'attended'   => $att['total_attended'],
                'total'      => $att['total_conducted'],
                'days_present' => $att['total_days_present'],
                'days_total'   => $att['total_days_conducted'],
                'percentage' => $att['attendance_percentage'],
                'discipline_score' => $att['discipline_score'],
            ];
        }
        jsonOut(['status' => 'success', 'report' => $report]);
    }

    // ── Attendance Percentage (Universal: Student or Mentor) ──
    if ($action === 'attendance_percentage') {
        $targetRoll = '';
        if ($isStudent) {
            $targetRoll = $_SESSION['user']['roll'];
        } elseif ($isMentor) {
            $targetRoll = $_GET['roll'] ?? '';
            // Verify student belongs to this mentor
            $stu = $users->findOne(['roll' => $targetRoll, 'mentor_id' => $_SESSION['mentor']['mentor_id']]);
            if (!$stu) {
                jsonOut(['status' => 'error', 'message' => 'Unauthorized student']);
            }
        }

        if (!$targetRoll) {
            jsonOut(['status' => 'error', 'message' => 'Roll number required']);
        }

        $sem = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
        $att = calculateStudentAttendance($targetRoll, $db, $sem ? ['semester' => $sem] : []);
        jsonOut([
            'status'            => 'success',
            'percentage'        => $att['attendance_percentage'],
            'discipline_score'  => $att['discipline_score'],
            'total'             => $att['total_conducted'],
            'attended'          => $att['total_attended'],
            'days_present'      => $att['total_days_present'],
            'days_conducted'    => $att['total_days_conducted'],
        ]);
    }

    // ── Semester Attendance Status ──
    if ($action === 'semester_status') {
        $batch = trim($_GET['batch'] ?? '');
        $sem   = (int)($_GET['semester'] ?? 1);
        if (!$batch && $isMentor) {
            $batch = $_SESSION['mentor']['batch_no'] ?? $_SESSION['mentor']['batch'] ?? '';
        }
        if (!$batch && $isStudent) {
            $stu = $users->findOne(['roll' => $_SESSION['user']['roll']]);
            $batch = $stu['batch_no'] ?? '';
        }
        if (!$batch) jsonOut(['status' => 'error', 'message' => 'Batch required'], 400);

        $st = sgiGetSemesterAttendanceStatus($batch, $sem, $db);
        jsonOut(['status' => 'success', 'data' => $st]);
    }

    // ── Batch Semesters Status (All 8 Semesters) ──
    if ($action === 'batch_semesters_status') {
        $batch = trim($_GET['batch'] ?? '');
        if (!$batch && $isMentor) {
            $batch = $_SESSION['mentor']['batch_no'] ?? $_SESSION['mentor']['batch'] ?? '';
        }
        if (!$batch && $isStudent) {
            $stu = $users->findOne(['roll' => $_SESSION['user']['roll']]);
            $batch = $stu['batch_no'] ?? '';
        }
        if (!$batch) jsonOut(['status' => 'error', 'message' => 'Batch required'], 400);

        $all = sgiGetAllSemestersStatus($batch, $db);
        jsonOut(['status' => 'success', 'batch' => $batch, 'semesters' => $all]);
    }

    jsonOut(['status' => 'error', 'message' => 'Unknown action']);
}

// ─────────────────────────────────────────────────────────────
// POST ENDPOINTS
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $mentorId = $isMentor ? $_SESSION['mentor']['mentor_id'] : '';
    $isAdmin  = !empty($_SESSION['admin']) || ($isMentor && !empty($_SESSION['mentor']['is_admin']));

    // ── Close Semester Attendance (Mentor / Admin) ──
    if ($action === 'close_semester') {
        if (!$isMentor && !$isAdmin) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized: Only mentors or administrators can close semester attendance.'], 403);
        }

        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $confirm = strtolower(trim($_POST['confirm'] ?? ''));

        if (!$batch || $semester < 1 || $semester > 8) {
            jsonOut(['status' => 'error', 'message' => 'Valid batch and semester (1 to 8) are required.'], 400);
        }

        if ($confirm !== 'yes' && $confirm !== 'true' && $confirm !== '1') {
            jsonOut(['status' => 'error', 'message' => 'Explicit confirmation is required to close semester attendance.'], 400);
        }

        // Mentor authorization check: Mentor must be assigned to this batch
        if ($isMentor && !$isAdmin) {
            $mentorBatch = $_SESSION['mentor']['batch_no'] ?? $_SESSION['mentor']['batch'] ?? '';
            if ($mentorBatch && $mentorBatch !== $batch) {
                jsonOut(['status' => 'error', 'message' => 'Unauthorized: You can only close attendance for your assigned batch.'], 403);
            }
        }

        $closedBy = $isMentor ? $_SESSION['mentor']['mentor_id'] : ($_SESSION['user']['roll'] ?? 'ADMIN');
        $closedByName = $isMentor ? ($_SESSION['mentor']['name'] ?? $closedBy) : 'Administrator';

        $res = sgiCloseSemesterAttendance($batch, $semester, $closedBy, $closedByName, $db);
        if ($res['status'] !== 'success') {
            jsonOut($res, 400);
        }

        jsonOut($res);
    }

    // ── Reopen Semester Attendance (Restricted to Mentor of batch / Admin with reason) ──
    if ($action === 'reopen_semester') {
        if (!$isMentor && !$isAdmin) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized: Only authorized mentors or administrators can reopen semester attendance.'], 403);
        }

        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!$batch || $semester < 1 || $semester > 8) {
            jsonOut(['status' => 'error', 'message' => 'Valid batch and semester (1 to 8) are required.'], 400);
        }

        if (empty($reason)) {
            jsonOut(['status' => 'error', 'message' => 'A mandatory reason is required to reopen semester attendance.'], 400);
        }

        // Mentor authorization check: Mentor must be assigned to this batch
        if ($isMentor && !$isAdmin) {
            $mentorBatch = $_SESSION['mentor']['batch_no'] ?? $_SESSION['mentor']['batch'] ?? '';
            if ($mentorBatch && $mentorBatch !== $batch) {
                jsonOut(['status' => 'error', 'message' => 'Unauthorized: You can only manage your assigned batch.'], 403);
            }
        }

        $reopenedBy = $isMentor ? $_SESSION['mentor']['mentor_id'] : 'ADMIN';
        $res = sgiReopenSemesterAttendance($batch, $semester, $reopenedBy, $reason, $db);
        if ($res['status'] !== 'success') {
            jsonOut($res, 400);
        }

        jsonOut($res);
    }

    // ── Mentor: Save Timetable Slot ──
    if ($action === 'save_timetable' && $isMentor) {
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $day = (int)($_POST['day'] ?? -1);
        $hour = (int)($_POST['hour'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');
        $classSection = trim($_POST['class_section'] ?? '');

        if (!$batch || !$semester || $day < 0 || $day > 6 || $hour < 1 || $hour > 12 || !$subject) {
            jsonOut(['status' => 'error', 'message' => 'All required timetable fields must be filled (Hour 1-12)']);
        }

        $existing = $timetables->findOne([
            'mentor_id' => $mentorId,
            'batch'     => $batch,
            'semester'  => $semester,
            'day'       => $day,
            'hour'      => $hour,
        ]);

        $entry = [
            'mentor_id'    => $mentorId,
            'batch'        => $batch,
            'semester'     => $semester,
            'day'          => $day,
            'hour'         => $hour,
            'start_time'   => $HOURS[$hour]['start'] ?? '16:20',
            'end_time'     => $HOURS[$hour]['end'] ?? '17:10',
            'subject'      => $subject,
            'subject_code' => $subjectCode,
            'faculty'      => $faculty,
            'class_section'=> $classSection,
            'updated_at'   => new MongoDB\BSON\UTCDateTime(),
        ];

        if ($existing) {
            $timetables->updateOne(['_id' => $existing['_id']], ['$set' => $entry]);
        } else {
            $entry['created_at'] = new MongoDB\BSON\UTCDateTime();
            $timetables->insertOne($entry);
        }

        // Auto-generate hours for all working days of the month except holidays
        $month = (int)date('n');
        $year  = (int)date('Y');
        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $autoGenCount = 0;
        $holidaySkipCount = 0;

        $studentCursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $studentRolls = [];
        foreach ($studentCursor as $s) {
            $studentRolls[] = $s['roll'];
        }
        if (empty($studentRolls)) {
            $allStu = $users->find(['batch_no' => $batch]);
            foreach ($allStu as $s) $studentRolls[] = $s['roll'];
        }

        if (!empty($studentRolls)) {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $ts = mktime(0, 0, 0, $month, $d, $year);
                if ((int)date('w', $ts) !== $day) continue;

                // Check holiday
                if (sgiIsHoliday($db, $ts, $batch)) {
                    $holidaySkipCount++;
                    continue;
                }

                $dateObj = new MongoDB\BSON\UTCDateTime($ts * 1000);
                $existingSess = $attendance_sessions->findOne([
                    'date'  => $dateObj,
                    'batch' => $batch,
                    'hour'  => $hour,
                ]);

                if (!$existingSess) {
                    $attendance_sessions->insertOne([
                        'attendance_session_id' => genSessionId(),
                        'date'             => $dateObj,
                        'mentor_id'        => $mentorId,
                        'batch'            => $batch,
                        'semester'         => $semester,
                        'hour'             => $hour,
                        'subject'          => $subject,
                        'subject_code'     => $subjectCode,
                        'original_faculty' => $faculty,
                        'actual_faculty'   => $faculty,
                        'rolls'            => $studentRolls,
                        'status'           => 'SCHEDULED',
                        'start_time'       => $entry['start_time'],
                        'end_time'         => $entry['end_time'],
                        'session_type'     => 'REGULAR',
                        'created_at'       => new MongoDB\BSON\UTCDateTime(),
                        'updated_at'       => new MongoDB\BSON\UTCDateTime(),
                    ]);
                    $autoGenCount++;
                }
            }
        }

        $msg = 'Timetable slot saved successfully.';
        if ($autoGenCount > 0) {
            $msg .= " Generated {$autoGenCount} session(s) for working days ({$holidaySkipCount} holidays skipped).";
        }
        jsonOut(['status' => 'success', 'message' => $msg, 'generated_count' => $autoGenCount, 'holidays_skipped' => $holidaySkipCount]);
    }

    // ── Mentor: Bulk Generate Sessions from Timetable for All Days (skipping holidays) ──
    if ($action === 'generate_timetable_sessions' && $isMentor) {
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $month = (int)($_POST['month'] ?? date('n'));
        $year  = (int)($_POST['year']  ?? date('Y'));
        if ($month < 1) $month = 1;
        if ($month > 12) $month = 12;

        if (!$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'Batch and semester required']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($batch, $semester, $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        // Fetch all timetable slots for this batch + semester
        $ttEntries = iterator_to_array($timetables->find([
            'mentor_id' => $mentorId,
            'batch'     => $batch,
            'semester'  => $semester,
        ], ['sort' => ['day' => 1, 'hour' => 1]]));

        if (empty($ttEntries)) {
            jsonOut(['status' => 'error', 'message' => "No timetable found for {$batch} Semester {$semester}. Please add timetable slots first."]);
        }

        // Group timetable by day-of-week (0=Sun, 1=Mon, ..., 6=Sat)
        $ttByDay = [];
        foreach ($ttEntries as $tt) {
            $ttByDay[$tt['day']][] = $tt;
        }

        // Get students linked to this batch
        $studentCursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $studentRolls = [];
        foreach ($studentCursor as $s) {
            $studentRolls[] = $s['roll'];
        }
        if (empty($studentRolls)) {
            $allStu = $users->find(['batch_no' => $batch]);
            foreach ($allStu as $s) {
                $studentRolls[] = $s['roll'];
            }
        }
        if (empty($studentRolls)) {
            jsonOut(['status' => 'error', 'message' => "No students found linked to batch {$batch}."]);
        }

        $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $generatedCount = 0;
        $holidaySkipCount = 0;
        $workingDaysWithClasses = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $ts = mktime(0, 0, 0, $month, $d, $year);
            $dayOfWeek = (int)date('w', $ts);
            $dateObj = new MongoDB\BSON\UTCDateTime($ts * 1000);

            // If no timetable on this day of week, skip
            if (empty($ttByDay[$dayOfWeek])) continue;

            // Check if this date is a declared holiday or calendar holiday
            $holidayName = sgiIsHoliday($db, $ts, $batch);
            if ($holidayName) {
                $holidaySkipCount++;
                continue; // CRITICAL: Strict holiday exclusion
            }

            $dayHadClasses = false;
            foreach ($ttByDay[$dayOfWeek] as $tt) {
                // Prevent duplicate session for same date, batch and hour
                $existing = $attendance_sessions->findOne([
                    'date'  => $dateObj,
                    'batch' => $batch,
                    'hour'  => $tt['hour'],
                ]);
                if ($existing) continue;

                $sessionId = genSessionId();
                $attendance_sessions->insertOne([
                    'attendance_session_id' => $sessionId,
                    'date'             => $dateObj,
                    'mentor_id'        => $mentorId,
                    'batch'            => $batch,
                    'semester'         => $semester,
                    'hour'             => $tt['hour'],
                    'subject'          => $tt['subject'],
                    'subject_code'     => $tt['subject_code'] ?? '',
                    'original_faculty' => $tt['faculty'] ?? '',
                    'actual_faculty'   => $tt['faculty'] ?? '',
                    'rolls'            => $studentRolls,
                    'status'           => 'SCHEDULED',
                    'start_time'       => $tt['start_time'] ?? ($HOURS[$tt['hour']]['start'] ?? ''),
                    'end_time'         => $tt['end_time']   ?? ($HOURS[$tt['hour']]['end'] ?? ''),
                    'session_type'     => 'REGULAR',
                    'created_at'       => new MongoDB\BSON\UTCDateTime(),
                    'updated_at'       => new MongoDB\BSON\UTCDateTime(),
                ]);
                $generatedCount++;
                $dayHadClasses = true;
            }
            if ($dayHadClasses) $workingDaysWithClasses++;
        }

        jsonOut([
            'status'             => 'success',
            'generated_count'    => $generatedCount,
            'holidays_skipped'   => $holidaySkipCount,
            'days_with_classes'  => $workingDaysWithClasses,
            'message'            => "Generated {$generatedCount} sessions across {$workingDaysWithClasses} working days for " . date('F Y', mktime(0,0,0,$month,1,$year)) . " ({$holidaySkipCount} holidays skipped).",
        ]);
    }

    // ── Mentor: Delete Timetable Slot ──
    if ($action === 'delete_timetable' && $isMentor) {
        $id = $_POST['id'] ?? '';
        if (!validObjectId($id)) jsonOut(['status' => 'error', 'message' => 'Valid ID required']);
        $timetables->deleteOne([
            '_id'       => new MongoDB\BSON\ObjectId($id),
            'mentor_id' => $mentorId,
        ]);
        jsonOut(['status' => 'success', 'message' => 'Timetable entry deleted']);
    }

    // ── Mentor: Generate Sessions from Timetable ──
    if ($action === 'generate_sessions' && $isMentor) {
        $dateStr = $_POST['date'] ?? '';
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);

        if (!$dateStr || !$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'Date, batch and semester required']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($batch, $semester, $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        $ts = strtotime($dateStr);
        $dayOfWeek = (int)date('w', $ts);
        $dateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);

        // Check if date is a holiday
        $holidayName = sgiIsHoliday($db, $ts, $batch);
        if ($holidayName) {
            jsonOut(['status' => 'error', 'message' => "This date is a declared holiday: $holidayName. Sessions cannot be generated."]);
        }

        // Load timetable for this day
        $ttEntries = iterator_to_array($timetables->find([
            'mentor_id' => $mentorId,
            'batch'     => $batch,
            'semester'  => $semester,
            'day'       => $dayOfWeek,
        ], ['sort' => ['hour' => 1]]));

        if (empty($ttEntries)) {
            jsonOut(['status' => 'error', 'message' => 'No timetable found for ' . $DAY_NAMES[$dayOfWeek] . '. Please configure timetable first.']);
        }

        // Get students for this batch
        $studentCursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $studentRolls = [];
        foreach ($studentCursor as $s) {
            $studentRolls[] = $s['roll'];
        }

        if (empty($studentRolls)) {
            jsonOut(['status' => 'error', 'message' => 'No students found linked to this batch']);
        }

        $created = 0;
        foreach ($ttEntries as $tt) {
            // Prevent duplicate sessions for same date, batch and hour
            $existing = $attendance_sessions->findOne([
                'date'  => $dateObj,
                'batch' => $batch,
                'hour'  => $tt['hour'],
            ]);

            if ($existing) continue;

            $sessionId = genSessionId();
            $attendance_sessions->insertOne([
                'attendance_session_id' => $sessionId,
                'date'             => $dateObj,
                'mentor_id'        => $mentorId,
                'batch'            => $batch,
                'semester'         => $semester,
                'hour'             => $tt['hour'],
                'start_time'       => $tt['start_time'] ?? $HOURS[$tt['hour']]['start'],
                'end_time'         => $tt['end_time'] ?? $HOURS[$tt['hour']]['end'],
                'subject'          => $tt['subject'],
                'subject_code'     => $tt['subject_code'] ?? '',
                'original_faculty' => $tt['faculty'] ?? '',
                'actual_faculty'   => $tt['faculty'] ?? '',
                'class_section'    => $tt['class_section'] ?? '',
                'session_type'     => 'REGULAR',
                'status'           => 'SCHEDULED',
                'rolls'            => $studentRolls,
                'created_at'       => new MongoDB\BSON\UTCDateTime(),
                'updated_at'       => new MongoDB\BSON\UTCDateTime(),
            ]);
            $created++;
        }

        jsonOut(['status' => 'success', 'message' => "$created attendance sessions created for " . date('d M Y', $ts)]);
    }

    // ── Mentor: Mark / Edit Attendance (Duplicate Safe) ──
    if ($action === 'mark_attendance' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $statusesJson = $_POST['statuses'] ?? '[]';
        $statuses = json_decode($statusesJson, true);

        if (!$sessionId || empty($statuses)) {
            jsonOut(['status' => 'error', 'message' => 'Session ID and student statuses required']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($sess['batch'], (int)$sess['semester'], $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        $now = new MongoDB\BSON\UTCDateTime();
        $absentRolls = [];

        foreach ($statuses as $s) {
            $roll = $s['roll'];
            $status = strtoupper($s['status'] ?? 'ABSENT');
            if (!in_array($status, ['PRESENT', 'ABSENT', 'OD', 'LEAVE'])) {
                $status = 'ABSENT';
            }

            // DUPLICATE PREVENTION: Find existing record by (student_roll, attendance_session_id)
            $existing = $student_attendance->findOne([
                'student_roll'          => $roll,
                'attendance_session_id' => $sessionId,
            ]);

            $effectiveStatus = $status;
            // PRESERVE APPROVED OD:
            // If student already has approved OD for this session, their effective status remains OD (Effective Present)
            if ($existing && !empty($existing['od_approved'])) {
                $effectiveStatus = 'OD';
            }

            $attData = [
                'student_roll'          => $roll,
                'attendance_session_id' => $sessionId,
                'status'                => $status,
                'original_status'       => $status,
                'effective_status'      => $effectiveStatus,
                'marked_by'             => $mentorId,
                'updated_at'            => $now,
            ];

            if ($existing) {
                // If it already had an OD approval attached, keep that link
                if (!empty($existing['od_approved'])) {
                    $attData['od_approved'] = true;
                    if (!empty($existing['od_id'])) $attData['od_id'] = $existing['od_id'];
                    $attData['original_status'] = $existing['original_status'] ?? 'ABSENT';
                }
                $student_attendance->updateOne(
                    ['_id' => $existing['_id']],
                    ['$set' => $attData]
                );
            } else {
                $attData['marked_at'] = $now;
                $student_attendance->insertOne($attData);
            }

            if ($effectiveStatus === 'ABSENT') {
                $absentRolls[] = $roll;
            }
        }

        // Update session status to CONDUCTED and clear suspension reason
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status'            => 'CONDUCTED',
                'suspension_reason' => null,
                'updated_at'        => $now,
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
                ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour, 'link' => 'attendance.php']
            );
        }

        jsonOut(['status' => 'success', 'message' => 'Attendance saved successfully']);
    }

    // ── Mentor: Mark All Present ──
    if ($action === 'mark_all_present' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        if (!$sessionId) jsonOut(['status' => 'error', 'message' => 'Session ID required']);

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($sess['batch'], (int)$sess['semester'], $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        $rolls = $sess['rolls'] ?? [];
        $now = new MongoDB\BSON\UTCDateTime();

        foreach ($rolls as $roll) {
            $existing = $student_attendance->findOne([
                'student_roll'          => $roll,
                'attendance_session_id' => $sessionId,
            ]);

            // Keep approved OD intact
            if ($existing && !empty($existing['od_approved'])) {
                continue;
            }

            $attData = [
                'student_roll'          => $roll,
                'attendance_session_id' => $sessionId,
                'status'                => 'PRESENT',
                'original_status'       => 'PRESENT',
                'effective_status'      => 'PRESENT',
                'marked_by'             => $mentorId,
                'updated_at'            => $now,
            ];

            if ($existing) {
                $student_attendance->updateOne(
                    ['_id' => $existing['_id']],
                    ['$set' => $attData]
                );
            } else {
                $attData['marked_at'] = $now;
                $student_attendance->insertOne($attData);
            }
        }

        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status'            => 'CONDUCTED',
                'suspension_reason' => null,
                'updated_at'        => $now,
            ]]
        );

        jsonOut(['status' => 'success', 'message' => 'All students marked present']);
    }

    // ── Mentor: Suspend Session (Mandatory Reason) ──
    if ($action === 'suspend_session' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (!$sessionId) {
            jsonOut(['status' => 'error', 'message' => 'Session ID required']);
        }
        if (!$reason) {
            jsonOut(['status' => 'error', 'message' => 'A suspension reason is mandatory']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($sess['batch'], (int)$sess['semester'], $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        // CRITICAL RULE: Update session to SUSPENDED.
        // It must NOT mark students absent/present and is excluded from attendance calculation everywhere.
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status'            => 'SUSPENDED',
                'suspension_reason' => $reason,
                'updated_at'        => new MongoDB\BSON\UTCDateTime(),
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
            ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour, 'link' => 'attendance.php']
        );

        jsonOut(['status' => 'success', 'message' => 'Session suspended successfully']);
    }

    // ── Mentor: Unsuspend Session ──
    if ($action === 'unsuspend_session' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        if (!$sessionId) jsonOut(['status' => 'error', 'message' => 'Session ID required']);

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($sess['batch'], (int)$sess['semester'], $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status'            => 'SCHEDULED',
                'suspension_reason' => null,
                'updated_at'        => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        jsonOut(['status' => 'success', 'message' => 'Session un-suspended and set to SCHEDULED']);
    }

    // ── Mentor: Declare Holiday ──
    if ($action === 'declare_holiday' && $isMentor) {
        $dateStr = $_POST['date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $batch = trim($_POST['batch'] ?? '*');

        if (!$dateStr || !$description) {
            jsonOut(['status' => 'error', 'message' => 'Date and description required']);
        }

        $ts = strtotime($dateStr);
        $dateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);

        $attendance_exceptions->insertOne([
            'type'        => 'HOLIDAY',
            'date'        => $dateObj,
            'description' => $description,
            'batch'       => $batch,
            'mentor_id'   => $mentorId,
            'created_at'  => new MongoDB\BSON\UTCDateTime(),
        ]);

        // Cancel existing sessions on that date for this batch
        $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
        $to   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);

        $sessionQuery = ['date' => ['$gte' => $from, '$lte' => $to]];
        if ($batch !== '*') $sessionQuery['batch'] = $batch;

        $attendance_sessions->updateMany(
            $sessionQuery,
            ['$set' => ['status' => 'CANCELLED', 'cancellation_reason' => "Holiday: $description", 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
        );

        // Notify students
        $studentQuery = ['mentor_id' => $mentorId];
        if ($batch !== '*') $studentQuery['batch_no'] = $batch;
        $students = iterator_to_array($users->find($studentQuery));
        $rolls = array_map(fn($s) => $s['roll'], $students);
        notifyStudents($users, $notifications, $rolls,
            "Holiday declared for " . date('d M Y', $ts) . ": $description",
            'HOLIDAY',
            ['date' => $dateStr, 'link' => 'attendance_calendar.php']
        );

        jsonOut(['status' => 'success', 'message' => 'Holiday declared successfully']);
    }

    // ── Mentor: Remove Holiday / Convert to Working Day ──
    if ($action === 'remove_holiday' && $isMentor) {
        $id = $_POST['id'] ?? '';
        $dateStr = $_POST['date'] ?? '';
        $reason = trim($_POST['reason'] ?? 'Working Day adjustment');

        if ($id && validObjectId($id)) {
            $exDoc = $attendance_exceptions->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            if ($exDoc && isset($exDoc['date'])) {
                $ts = $exDoc['date']->toDateTime()->getTimestamp();
                $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
                $to   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
                $attendance_sessions->updateMany(
                    ['date' => ['$gte' => $from, '$lte' => $to], 'status' => 'CANCELLED', 'cancellation_reason' => ['$regex' => '^Holiday']],
                    ['$set' => ['status' => 'SCHEDULED', 'cancellation_reason' => null, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
                );
            }
            $attendance_exceptions->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        } elseif ($dateStr) {
            $ts = strtotime($dateStr);
            $from = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
            $to   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
            $attendance_exceptions->deleteMany(['type' => 'HOLIDAY', 'date' => ['$gte' => $from, '$lte' => $to]]);
            // Restore sessions that were cancelled due to holiday
            $attendance_sessions->updateMany(
                ['date' => ['$gte' => $from, '$lte' => $to], 'status' => 'CANCELLED', 'cancellation_reason' => ['$regex' => '^Holiday']],
                ['$set' => ['status' => 'SCHEDULED', 'cancellation_reason' => null, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
            );
        } else {
            jsonOut(['status' => 'error', 'message' => 'Holiday ID or date required']);
        }

        jsonOut(['status' => 'success', 'message' => 'Holiday removed. Date is now a working day and scheduled classes restored.']);
    }

    // ── Mentor: Substitute Faculty ──
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

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($sess['batch'], (int)$sess['semester'], $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        $originalFaculty = $sess['original_faculty'] ?? '';
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'actual_faculty' => $subFaculty,
                'session_type'   => 'SUBSTITUTION',
                'updated_at'     => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        $rolls = $sess['rolls'] ?? [];
        $subject = $sess['subject'] ?? '';
        $hour = $sess['hour'] ?? '';
        $dateStr = date('d M Y', $sess['date']->toDateTime()->getTimestamp());
        notifyStudents($users, $notifications, $rolls,
            "Substitution: H$hour $subject on $dateStr — $originalFaculty substituted by $subFaculty",
            'SUBSTITUTION',
            ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour, 'link' => 'attendance.php']
        );

        jsonOut(['status' => 'success', 'message' => 'Substitution recorded successfully']);
    }

    // ── Mentor: Reschedule Session ──
    if ($action === 'reschedule_session' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $newDate = $_POST['new_date'] ?? '';
        $newHour = (int)($_POST['new_hour'] ?? 0);

        if (!$sessionId || !$newDate || $newHour < 1 || $newHour > 7) {
            jsonOut(['status' => 'error', 'message' => 'Session ID, new date and valid hour (1-7) required']);
        }

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found']);
        if ($sess['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($sess['batch'], (int)$sess['semester'], $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        // Mark original session as RESCHEDULED
        $attendance_sessions->updateOne(
            ['attendance_session_id' => $sessionId],
            ['$set' => [
                'status'     => 'RESCHEDULED',
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        // Create new conducted/scheduled session
        $ts = strtotime($newDate);
        $newDateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
        $newSessionId = genSessionId();

        $attendance_sessions->insertOne([
            'attendance_session_id' => $newSessionId,
            'date'             => $newDateObj,
            'mentor_id'        => $mentorId,
            'batch'            => $sess['batch'],
            'semester'         => $sess['semester'],
            'hour'             => $newHour,
            'start_time'       => $HOURS[$newHour]['start'],
            'end_time'         => $HOURS[$newHour]['end'],
            'subject'          => $sess['subject'],
            'subject_code'     => $sess['subject_code'] ?? '',
            'original_faculty' => $sess['original_faculty'] ?? '',
            'actual_faculty'   => $sess['actual_faculty'] ?? $sess['original_faculty'] ?? '',
            'class_section'    => $sess['class_section'] ?? '',
            'session_type'     => 'RESCHEDULED',
            'status'           => 'SCHEDULED',
            'rolls'            => $sess['rolls'] ?? [],
            'rescheduled_from' => $sessionId,
            'created_at'       => new MongoDB\BSON\UTCDateTime(),
            'updated_at'       => new MongoDB\BSON\UTCDateTime(),
        ]);

        $rolls = $sess['rolls'] ?? [];
        $subject = $sess['subject'] ?? '';
        $oldDate = date('d M Y', $sess['date']->toDateTime()->getTimestamp());
        notifyStudents($users, $notifications, $rolls,
            "Rescheduled: H" . $sess['hour'] . " $subject from $oldDate to " . date('d M Y', $ts) . " (H$newHour)",
            'RESCHEDULED',
            ['attendance_session_id' => $newSessionId, 'subject' => $subject, 'hour' => $newHour, 'link' => 'attendance.php']
        );

        jsonOut(['status' => 'success', 'message' => 'Session rescheduled successfully']);
    }

    // ── Mentor: Create Special / Sunday / Extra Class ──
    if ($action === 'create_special_class' && $isMentor) {
        $dateStr = $_POST['date'] ?? '';
        $hour = (int)($_POST['hour'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');
        $batch = trim($_POST['batch'] ?? '');
        $semester = (int)($_POST['semester'] ?? 0);
        $classType = $_POST['class_type'] ?? 'SPECIAL';

        $customStart = trim($_POST['start_time'] ?? '');
        $customEnd   = trim($_POST['end_time'] ?? '');

        if (!$dateStr || $hour < 1 || $hour > 15 || !$subject || !$batch || !$semester) {
            jsonOut(['status' => 'error', 'message' => 'Date, valid hour (H1-H15), subject, batch and semester are required']);
        }

        // SEMESTER ATTENDANCE LOCK ENFORCEMENT
        $statusCheck = sgiCanMarkAttendance($batch, $semester, $db);
        if (!$statusCheck['allowed']) {
            jsonOut(['status' => 'error', 'message' => $statusCheck['message']], 403);
        }

        $ts = strtotime($dateStr);
        $dateObj = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$ts),(int)date('j',$ts),(int)date('Y',$ts))*1000);
        $dayOfWeek = (int)date('w', $ts);

        $validTypes = ['SPECIAL', 'EXTRA', 'SATURDAY', 'SUNDAY', 'REMEDIAL', 'LAB_EXTRA'];
        if (!in_array($classType, $validTypes)) $classType = 'EXTRA';
        if ($dayOfWeek === 6 && $classType === 'SPECIAL') $classType = 'SATURDAY';
        if ($dayOfWeek === 0 && $classType === 'SPECIAL') $classType = 'SUNDAY';

        $studentCursor = $users->find(['mentor_id' => $mentorId, 'batch_no' => $batch]);
        $studentRolls = [];
        foreach ($studentCursor as $s) {
            $studentRolls[] = $s['roll'];
        }
        if (empty($studentRolls)) {
            $allStu = $users->find(['batch_no' => $batch]);
            foreach ($allStu as $s) $studentRolls[] = $s['roll'];
        }

        if (empty($studentRolls)) {
            jsonOut(['status' => 'error', 'message' => 'No students found linked to this batch']);
        }

        $startTime = $customStart ?: ($HOURS[$hour]['start'] ?? '16:20');
        $endTime   = $customEnd   ?: ($HOURS[$hour]['end']   ?? '17:10');

        $sessionId = genSessionId();
        $attendance_sessions->insertOne([
            'attendance_session_id' => $sessionId,
            'date'             => $dateObj,
            'mentor_id'        => $mentorId,
            'batch'            => $batch,
            'semester'         => $semester,
            'hour'             => $hour,
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'subject'          => $subject,
            'subject_code'     => $subjectCode,
            'original_faculty' => $faculty,
            'actual_faculty'   => $faculty,
            'session_type'     => $classType,
            'status'           => 'SCHEDULED',
            'rolls'            => $studentRolls,
            'created_at'       => new MongoDB\BSON\UTCDateTime(),
            'updated_at'       => new MongoDB\BSON\UTCDateTime(),
        ]);

        $typeLabel = strtolower(str_replace('_', ' ', $classType));
        notifyStudents($users, $notifications, $studentRolls,
            "New $typeLabel class: $subject (H$hour $startTime-$endTime) on " . date('d M Y', $ts),
            'SPECIAL',
            ['attendance_session_id' => $sessionId, 'subject' => $subject, 'hour' => $hour, 'link' => 'attendance.php']
        );

        jsonOut(['status' => 'success', 'message' => ucfirst($typeLabel) . " class (H{$hour}) created successfully"]);
    }

    // ── Mentor: Edit / Change Scheduled Session ──
    if ($action === 'update_session' && $isMentor) {
        $sessionId = $_POST['session_id'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $faculty = trim($_POST['faculty'] ?? '');
        $hour = (int)($_POST['hour'] ?? 0);
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $status = strtoupper(trim($_POST['status'] ?? ''));

        if (!$sessionId) jsonOut(['status' => 'error', 'message' => 'Session ID required']);

        $sess = $attendance_sessions->findOne(['attendance_session_id' => $sessionId]);
        if (!$sess) jsonOut(['status' => 'error', 'message' => 'Session not found'], 404);

        $update = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
        if ($subject) $update['subject'] = $subject;
        if ($subjectCode !== '') $update['subject_code'] = $subjectCode;
        if ($faculty) {
            $update['actual_faculty'] = $faculty;
            $update['original_faculty'] = $faculty;
        }
        if ($hour >= 1 && $hour <= 15) {
            $update['hour'] = $hour;
            if (!$startTime && isset($HOURS[$hour])) $update['start_time'] = $HOURS[$hour]['start'];
            if (!$endTime && isset($HOURS[$hour])) $update['end_time'] = $HOURS[$hour]['end'];
        }
        if ($startTime) $update['start_time'] = $startTime;
        if ($endTime) $update['end_time'] = $endTime;
        if ($status && in_array($status, ['SCHEDULED', 'SUSPENDED', 'CANCELLED'])) {
            $update['status'] = $status;
            if ($status === 'SCHEDULED') {
                $update['cancellation_reason'] = null;
                $update['suspension_reason'] = null;
            }
        }

        $attendance_sessions->updateOne(['attendance_session_id' => $sessionId], ['$set' => $update]);
        jsonOut(['status' => 'success', 'message' => 'Class updated successfully']);
    }

    // ── Student: Submit OD / Leave Request ──
    if ($action === 'submit_od' && $isStudent) {
        $roll = $_SESSION['user']['roll'];
        $mentorId = $_SESSION['user']['mentor_id'] ?? '';
        $odType = trim($_POST['od_type'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $dateFrom = $_POST['date_from'] ?? '';
        $dateTo = $_POST['date_to'] ?? '';
        $halfDayType = trim($_POST['half_day_type'] ?? 'morning'); // 'morning' or 'afternoon'
        $hours = $_POST['hours'] ?? [];
        $reason = trim($_POST['reason'] ?? '');

        if (!$odType || !$duration || !$reason) {
            jsonOut(['status' => 'error', 'message' => 'Type, duration and reason are required']);
        }

        if (!$dateFrom) {
            jsonOut(['status' => 'error', 'message' => 'Date is required']);
        }

        if ($duration === 'select_hours' && empty($hours)) {
            jsonOut(['status' => 'error', 'message' => 'Please select at least one hour for multi-hour OD']);
        }

        $fromTs = strtotime($dateFrom);
        $toTs = $dateTo ? strtotime($dateTo) : $fromTs;

        // Check if requested dates fall in a closed semester
        $studentUser = $users->findOne(['roll' => $roll]);
        $stuBatch = $studentUser['batch_no'] ?? '';
        if ($stuBatch) {
            $sessInRange = iterator_to_array($attendance_sessions->find([
                'rolls' => $roll,
                'date'  => ['$gte' => new MongoDB\BSON\UTCDateTime($fromTs * 1000), '$lte' => new MongoDB\BSON\UTCDateTime(($toTs + 86399) * 1000)]
            ]));
            foreach ($sessInRange as $s) {
                $sSem = (int)($s['semester'] ?? 0);
                if ($sSem) {
                    $sStatus = sgiGetSemesterAttendanceStatus($stuBatch, $sSem, $db);
                    if ($sStatus['status'] === 'CLOSED') {
                        jsonOut(['status' => 'error', 'message' => "Cannot submit OD/Leave: Semester {$sSem} attendance has been closed and finalized."], 403);
                    }
                }
            }
        }

        $odData = [
            'student_roll'  => $roll,
            'student_name'  => $_SESSION['user']['name'] ?? $roll,
            'mentor_id'     => $mentorId,
            'od_type'       => $odType,
            'duration'      => $duration,
            'half_day_type' => ($duration === 'half_day') ? $halfDayType : null,
            'date_from'     => new MongoDB\BSON\UTCDateTime($fromTs * 1000),
            'date_to'       => new MongoDB\BSON\UTCDateTime($toTs * 1000),
            'hours'         => ($duration === 'select_hours') ? array_map('intval', $hours) : [],
            'reason'        => $reason,
            'status'        => 'pending',
            'created_at'    => new MongoDB\BSON\UTCDateTime(),
        ];

        $od_requests->insertOne($odData);

        // Notify mentor
        if ($mentorId) {
            notifyMentor($notifications, $mentorId,
                "New OD/Leave request from " . ($_SESSION['user']['name'] ?? $roll) . " ($roll) — $odType ($duration)",
                'OD_REQUEST',
                ['student_roll' => $roll, 'link' => 'mentor_attendance_od.php']
            );
        }

        jsonOut(['status' => 'success', 'message' => 'OD/Leave request submitted successfully']);
    }

    // ── Mentor: Process OD Request (Approve / Reject) ──
    if ($action === 'process_od' && $isMentor) {
        $odId = $_POST['od_id'] ?? '';
        $decision = $_POST['decision'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');

        if (!validObjectId($odId) || !in_array($decision, ['approved', 'rejected'])) {
            jsonOut(['status' => 'error', 'message' => 'Invalid request parameters']);
        }

        $od = $od_requests->findOne(['_id' => new MongoDB\BSON\ObjectId($odId)]);
        if (!$od) jsonOut(['status' => 'error', 'message' => 'OD request not found']);
        if ($od['mentor_id'] !== $mentorId) {
            jsonOut(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $od_requests->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($odId)],
            ['$set' => [
                'status'         => $decision,
                'mentor_remarks' => $remarks,
                'processed_at'   => new MongoDB\BSON\UTCDateTime(),
            ]]
        );

        $roll = $od['student_roll'] ?? '';
        $odType = $od['od_type'] ?? '';
        $duration = $od['duration'] ?? 'full_day';

        if ($decision === 'approved') {
            // Find ALL applicable timetable sessions covered by the OD
            $fromTs = $od['date_from'] ? $od['date_from']->toDateTime()->getTimestamp() : null;
            $toTs   = $od['date_to']   ? $od['date_to']->toDateTime()->getTimestamp()   : $fromTs;

            if ($fromTs && $toTs) {
                $fromDate = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$fromTs),(int)date('j',$fromTs),(int)date('Y',$fromTs))*1000);
                $toDate   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$toTs),(int)date('j',$toTs),(int)date('Y',$toTs))*1000);

                $sessQuery = [
                    'rolls'  => $roll,
                    'date'   => ['$gte' => $fromDate, '$lte' => $toDate],
                    'status' => 'CONDUCTED',
                ];

                if ($duration === 'half_day') {
                    $halfType = $od['half_day_type'] ?? 'morning';
                    if ($halfType === 'afternoon') {
                        $sessQuery['hour'] = ['$in' => [5, 6, 7]];
                    } else {
                        $sessQuery['hour'] = ['$in' => [1, 2, 3, 4]];
                    }
                } elseif ($duration === 'select_hours') {
                    $selectedHours = !empty($od['hours']) ? (array)$od['hours'] : [];
                    if (!empty($selectedHours)) {
                        $sessQuery['hour'] = ['$in' => array_map('intval', $selectedHours)];
                    }
                }

                $matchingSessions = iterator_to_array($attendance_sessions->find($sessQuery));

                // Verify none of the sessions belong to a closed semester
                foreach ($matchingSessions as $sess) {
                    $sBatch = $sess['batch'] ?? '';
                    $sSem   = (int)($sess['semester'] ?? 0);
                    if ($sBatch && $sSem) {
                        $sStatus = sgiGetSemesterAttendanceStatus($sBatch, $sSem, $db);
                        if ($sStatus['status'] === 'CLOSED') {
                            jsonOut(['status' => 'error', 'message' => "Cannot approve OD: Semester {$sSem} attendance is closed and can no longer be modified."], 403);
                        }
                    }
                }

                foreach ($matchingSessions as $sess) {
                    $sId = $sess['attendance_session_id'];
                    $existing = $student_attendance->findOne([
                        'student_roll'          => $roll,
                        'attendance_session_id' => $sId,
                    ]);

                    if ($existing) {
                        // Regularize to OD (effective Present) while retaining original status history
                        $student_attendance->updateOne(
                            ['_id' => $existing['_id']],
                            ['$set' => [
                                'effective_status' => 'OD',
                                'od_approved'      => true,
                                'od_id'            => new MongoDB\BSON\ObjectId($odId),
                                'updated_at'       => new MongoDB\BSON\UTCDateTime(),
                            ]]
                        );
                    } else {
                        // Pre-regularize before marking
                        $student_attendance->insertOne([
                            'student_roll'          => $roll,
                            'attendance_session_id' => $sId,
                            'status'                => 'ABSENT',
                            'original_status'       => 'ABSENT',
                            'effective_status'      => 'OD',
                            'od_approved'           => true,
                            'od_id'                 => new MongoDB\BSON\ObjectId($odId),
                            'marked_by'             => $mentorId,
                            'marked_at'             => new MongoDB\BSON\UTCDateTime(),
                            'updated_at'            => new MongoDB\BSON\UTCDateTime(),
                        ]);
                    }
                }
            }

            // Notify student
            $notifications->insertOne([
                'roll'       => $roll,
                'message'    => "Your OD/Leave request ($odType) has been APPROVED.",
                'type'       => 'OD_APPROVED',
                'read'       => false,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'link'       => 'attendance.php',
            ]);
        } else {
            // Rejected — attendance remains ABSENT, record rejection note
            $fromTs = $od['date_from'] ? $od['date_from']->toDateTime()->getTimestamp() : null;
            $toTs   = $od['date_to']   ? $od['date_to']->toDateTime()->getTimestamp()   : $fromTs;
            if ($fromTs && $toTs) {
                $fromDate = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$fromTs),(int)date('j',$fromTs),(int)date('Y',$fromTs))*1000);
                $toDate   = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$toTs),(int)date('j',$toTs),(int)date('Y',$toTs))*1000);
                $student_attendance->updateMany(
                    [
                        'student_roll' => $roll,
                        'attendance_session_id' => [
                            '$in' => array_map(fn($s) => $s['attendance_session_id'], iterator_to_array($attendance_sessions->find([
                                'rolls' => $roll,
                                'date'  => ['$gte' => $fromDate, '$lte' => $toDate]
                            ])))
                        ]
                    ],
                    ['$set' => ['od_rejected' => true, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
                );
            }

            $notifications->insertOne([
                'roll'       => $roll,
                'message'    => "Your OD/Leave request ($odType) has been REJECTED." . ($remarks ? " Reason: $remarks" : ''),
                'type'       => 'OD_REJECTED',
                'read'       => false,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'link'       => 'attendance.php',
            ]);
        }

        jsonOut(['status' => 'success', 'message' => "OD/Leave request $decision successfully"]);
    }

    jsonOut(['status' => 'error', 'message' => 'Unknown POST action']);
}

jsonOut(['status' => 'error', 'message' => 'Method not allowed']);
