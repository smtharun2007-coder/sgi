<?php
// attendance_helper.php — Centralized SGI Attendance Service & Calculation Engine

if (!defined('SGI_ATTENDANCE_HELPER_LOADED')) {
    define('SGI_ATTENDANCE_HELPER_LOADED', true);

    /**
     * Standard SGI Timetable Hour Definitions
     */
    function sgiGetStandardHours() {
        return [
            1 => ['hour' => 1, 'start' => '08:45', 'end' => '09:35', 'label' => 'H1'],
            2 => ['hour' => 2, 'start' => '09:35', 'end' => '10:25', 'label' => 'H2'],
            3 => ['hour' => 3, 'start' => '10:45', 'end' => '11:35', 'label' => 'H3'],
            4 => ['hour' => 4, 'start' => '11:35', 'end' => '12:25', 'label' => 'H4'],
            5 => ['hour' => 5, 'start' => '13:25', 'end' => '14:15', 'label' => 'H5'],
            6 => ['hour' => 6, 'start' => '14:15', 'end' => '15:05', 'label' => 'H6'],
            7 => ['hour' => 7, 'start' => '15:25', 'end' => '16:15', 'label' => 'H7'],
        ];
    }

    /**
     * Ensure required MongoDB indexes exist for attendance collections
     */
    function ensureAttendanceIndexes($db) {
        static $ensured = false;
        if ($ensured || !$db) return;
        try {
            // Compound unique index on student_attendance: 1 record per student per session
            $db->student_attendance->createIndex(
                ['student_roll' => 1, 'attendance_session_id' => 1],
                ['unique' => true, 'background' => true]
            );

            // Unique index on attendance_session_id in attendance_sessions
            $db->attendance_sessions->createIndex(
                ['attendance_session_id' => 1],
                ['unique' => true, 'background' => true]
            );

            // Index for fast session querying by date and rolls
            $db->attendance_sessions->createIndex(
                ['rolls' => 1, 'status' => 1, 'date' => 1],
                ['background' => true]
            );

            // Unique index on timetables per slot
            $db->timetables->createIndex(
                ['mentor_id' => 1, 'batch' => 1, 'semester' => 1, 'day' => 1, 'hour' => 1],
                ['unique' => true, 'background' => true]
            );

            // Index for exceptions (holidays)
            $db->attendance_exceptions->createIndex(
                ['type' => 1, 'date' => 1, 'batch' => 1],
                ['background' => true]
            );

            // Index for OD requests
            $db->od_requests->createIndex(
                ['student_roll' => 1, 'status' => 1, 'created_at' => -1],
                ['background' => true]
            );

            // Unique index for attendance_semester_status (batch + semester)
            $db->attendance_semester_status->createIndex(
                ['batch' => 1, 'semester' => 1],
                ['unique' => true, 'background' => true]
            );

            $ensured = true;
        } catch (Exception $e) {
            error_log("SGI Attendance index creation error: " . $e->getMessage());
        }
    }

    /**
     * Check if a specific date is a declared holiday for a batch
     */
    function sgiIsHoliday($db, $dateTimestamp, $batch = '*') {
        if (!$db) return null;
        $dayStart = new MongoDB\BSON\UTCDateTime(mktime(0, 0, 0, (int)date('n', $dateTimestamp), (int)date('j', $dateTimestamp), (int)date('Y', $dateTimestamp)) * 1000);
        $dayEnd   = new MongoDB\BSON\UTCDateTime(mktime(23, 59, 59, (int)date('n', $dateTimestamp), (int)date('j', $dateTimestamp), (int)date('Y', $dateTimestamp)) * 1000);

        $query = [
            'type' => 'HOLIDAY',
            'date' => ['$gte' => $dayStart, '$lte' => $dayEnd],
        ];
        if ($batch && $batch !== '*') {
            $query['$or'] = [
                ['batch' => $batch],
                ['batch' => '*'],
                ['batch' => ['$exists' => false]],
            ];
        }

        $ex = $db->attendance_exceptions->findOne($query);
        if ($ex) {
            return $ex['description'] ?? 'Holiday';
        }

        // Also check calendar_events for holiday
        $calEvent = $db->calendar_events->findOne([
            'type' => ['$in' => ['holiday', 'Holiday']],
            'date' => ['$gte' => $dayStart, '$lte' => $dayEnd],
        ]);
        if ($calEvent) {
            return $calEvent['title'] ?? 'Holiday';
        }

        return null;
    }

    /**
     * Get the attendance status of a semester for a batch
     * Status can be:
     * - 'CLOSED': Explicitly closed by mentor/admin. Read-only.
     * - 'LOCKED': Previous semester attendance is still OPEN. Cannot be marked.
     * - 'OPEN': Attendance can be marked and modified.
     *
     * @param string $batch Batch ID (e.g. '25TG086')
     * @param int $semester Semester number (1 to 8)
     * @param MongoDB\Database $database
     * @return array
     */
    function sgiGetSemesterAttendanceStatus($batch, $semester, $database = null) {
        global $db;
        $activeDb = $database ?: $db;
        $sem = (int)$semester;
        if ($sem < 1) $sem = 1;

        if (!$activeDb) {
            return [
                'batch'       => $batch,
                'semester'    => $sem,
                'status'      => 'OPEN',
                'can_mark'    => true,
                'is_closed'   => false,
                'is_locked'   => false,
                'lock_reason' => null,
            ];
        }

        ensureAttendanceIndexes($activeDb);

        // Check if this semester itself is explicitly CLOSED
        $curStatus = $activeDb->attendance_semester_status->findOne([
            'batch'    => $batch,
            'semester' => $sem,
        ]);

        if ($curStatus && strtoupper($curStatus['status'] ?? '') === 'CLOSED') {
            $closedAtStr = '';
            if (isset($curStatus['closed_at']) && $curStatus['closed_at'] instanceof MongoDB\BSON\UTCDateTime) {
                $closedAtStr = date('d-m-Y H:i', $curStatus['closed_at']->toDateTime()->getTimestamp());
            } elseif (!empty($curStatus['closed_at'])) {
                $closedAtStr = (string)$curStatus['closed_at'];
            }
            return [
                'batch'          => $batch,
                'semester'       => $sem,
                'status'         => 'CLOSED',
                'can_mark'       => false,
                'is_closed'      => true,
                'is_locked'      => false,
                'lock_reason'    => "Attendance for Semester {$sem} is closed and can no longer be modified.",
                'closed_by'      => $curStatus['closed_by'] ?? '',
                'closed_by_name' => $curStatus['closed_by_name'] ?? ($curStatus['closed_by'] ?? 'Mentor/Admin'),
                'closed_at'      => $closedAtStr,
            ];
        }

        // For Semester 1: No previous semesters exist. It is OPEN unless explicitly closed.
        if ($sem === 1) {
            return [
                'batch'       => $batch,
                'semester'    => 1,
                'status'      => 'OPEN',
                'can_mark'    => true,
                'is_closed'   => false,
                'is_locked'   => false,
                'lock_reason' => null,
            ];
        }

        // For Semester > 1: All previous semesters 1..(sem-1) must be CLOSED!
        for ($p = 1; $p < $sem; $p++) {
            $prevStatus = $activeDb->attendance_semester_status->findOne([
                'batch'    => $batch,
                'semester' => $p,
            ]);
            if (!$prevStatus || strtoupper($prevStatus['status'] ?? '') !== 'CLOSED') {
                return [
                    'batch'             => $batch,
                    'semester'          => $sem,
                    'status'            => 'LOCKED',
                    'can_mark'          => false,
                    'is_closed'         => false,
                    'is_locked'         => true,
                    'unclosed_semester' => $p,
                    'lock_reason'       => "Attendance cannot be marked for Semester {$sem} because Semester {$p} attendance is still open. Please close Semester {$p} attendance first.",
                ];
            }
        }

        // All previous semesters are closed, and current semester is not closed -> OPEN!
        return [
            'batch'       => $batch,
            'semester'    => $sem,
            'status'      => 'OPEN',
            'can_mark'    => true,
            'is_closed'   => false,
            'is_locked'   => false,
            'lock_reason' => null,
        ];
    }

    /**
     * Check if attendance can be marked for a batch and semester
     *
     * @param string $batch
     * @param int $semester
     * @param MongoDB\Database $database
     * @return array ['allowed' => bool, 'status' => string, 'message' => string]
     */
    function sgiCanMarkAttendance($batch, $semester, $database = null) {
        $st = sgiGetSemesterAttendanceStatus($batch, $semester, $database);
        return [
            'allowed' => $st['can_mark'],
            'status'  => $st['status'],
            'message' => $st['lock_reason'] ?: "Attendance marking is allowed for Semester " . (int)$semester . "."
        ];
    }

    /**
     * Close attendance for a semester
     *
     * @param string $batch
     * @param int $semester
     * @param string $closedBy User/Mentor ID
     * @param string $closedByName Name of mentor/admin
     * @param MongoDB\Database $database
     * @return array
     */
    function sgiCloseSemesterAttendance($batch, $semester, $closedBy, $closedByName = '', $database = null) {
        global $db;
        $activeDb = $database ?: $db;
        $sem = (int)$semester;
        if ($sem < 1 || $sem > 8) {
            return ['status' => 'error', 'message' => 'Invalid semester number (1 to 8).'];
        }
        if (empty($batch)) {
            return ['status' => 'error', 'message' => 'Batch is required.'];
        }

        // If semester > 1, ensure all previous semesters are closed
        for ($p = 1; $p < $sem; $p++) {
            $prev = $activeDb->attendance_semester_status->findOne([
                'batch'    => $batch,
                'semester' => $p,
            ]);
            if (!$prev || strtoupper($prev['status'] ?? '') !== 'CLOSED') {
                return [
                    'status'  => 'error',
                    'message' => "Cannot close Semester {$sem} attendance because Semester {$p} attendance is not closed yet. Please close Semester {$p} first."
                ];
            }
        }

        $now = new MongoDB\BSON\UTCDateTime();
        $activeDb->attendance_semester_status->updateOne(
            ['batch' => $batch, 'semester' => $sem],
            ['$set' => [
                'status'         => 'CLOSED',
                'closed_by'      => $closedBy,
                'closed_by_name' => $closedByName ?: $closedBy,
                'closed_at'      => $now,
                'updated_at'     => $now,
            ]],
            ['upsert' => true]
        );

        // Notify students in this batch
        $students = iterator_to_array($activeDb->users->find(['batch_no' => $batch]));
        foreach ($students as $stu) {
            $roll = $stu['roll'] ?? '';
            if ($roll) {
                $activeDb->notifications->insertOne([
                    'roll'       => $roll,
                    'message'    => "🔒 Semester {$sem} Attendance has been closed. Attendance records are now finalized and read-only.",
                    'type'       => 'attendance',
                    'read'       => false,
                    'link'       => "attendance.php?semester={$sem}",
                    'created_at' => $now,
                ]);
            }
        }

        return [
            'status'  => 'success',
            'message' => "Semester {$sem} attendance has been closed successfully. It is now finalized and read-only."
        ];
    }

    /**
     * Reopen attendance for a semester (Restricted administrative action)
     */
    function sgiReopenSemesterAttendance($batch, $semester, $reopenedBy, $reason, $database = null) {
        global $db;
        $activeDb = $database ?: $db;
        $sem = (int)$semester;
        if ($sem < 1 || $sem > 8) {
            return ['status' => 'error', 'message' => 'Invalid semester number (1 to 8).'];
        }
        if (empty($reason)) {
            return ['status' => 'error', 'message' => 'An explicit reason is mandatory to reopen semester attendance.'];
        }

        // If any subsequent semester (sem+1..8) has already been closed, prevent reopening
        for ($n = $sem + 1; $n <= 8; $n++) {
            $sub = $activeDb->attendance_semester_status->findOne(['batch' => $batch, 'semester' => $n]);
            if ($sub && strtoupper($sub['status'] ?? '') === 'CLOSED') {
                return [
                    'status'  => 'error',
                    'message' => "Cannot reopen Semester {$sem} because subsequent Semester {$n} attendance is already closed."
                ];
            }
        }

        $now = new MongoDB\BSON\UTCDateTime();
        $activeDb->attendance_semester_status->updateOne(
            ['batch' => $batch, 'semester' => $sem],
            ['$set' => [
                'status'         => 'OPEN',
                'reopened_by'    => $reopenedBy,
                'reopen_reason'  => $reason,
                'reopened_at'    => $now,
                'updated_at'     => $now,
            ]]
        );

        return [
            'status'  => 'success',
            'message' => "Semester {$sem} attendance has been reopened."
        ];
    }

    /**
     * Get status overview for all 8 semesters of a batch
     */
    function sgiGetAllSemestersStatus($batch, $database = null) {
        $result = [];
        for ($s = 1; $s <= 8; $s++) {
            $result[$s] = sgiGetSemesterAttendanceStatus($batch, $s, $database);
        }
        return $result;
    }

    /**
     * CENTRAL ATTENDANCE CALCULATION ENGINE
     * 
     * Computes:
     * 1. Session-level attendance (Subject-wise & overall):
     *    - Total conducted sessions (excluding SUSPENDED, CANCELLED, and HOLIDAYS)
     *    - Attended sessions (PRESENT or approved OD)
     *    - Absent sessions
     *    - Percentage
     * 2. Daily attendance (Institutional standard based on Morning H1 + Afternoon H5 rule):
     *    - Morning Rule: H1 Present -> Morning Present; H1 Absent -> Morning Absent.
     *      If H1 is SUSPENDED or not conducted -> EXCLUDED from morning denominator.
     *    - Afternoon Rule: H5 Present -> Afternoon Present; H5 Absent -> Afternoon Absent.
     *      If H5 is SUSPENDED or not conducted -> EXCLUDED from afternoon denominator.
     *    - Daily Combination:
     *      - H1 Present + H5 Present => Full Day (1.0)
     *      - H1 Present + H5 Absent  => Half Day (0.5)
     *      - H1 Absent  + H5 Present => Half Day (0.5)
     *      - H1 Absent  + H5 Absent  => Full Day Absent (0.0)
     *      - H1 conducted, H5 suspended => H1 determines day (0.5/0.5 or 0/0.5)
     *      - H1 suspended, H5 conducted => H5 determines day (0.5/0.5 or 0/0.5)
     *      - Both H1 & H5 suspended     => Day completely excluded from calculation.
     * 3. SGI Discipline Score: Attendance % / 20.
     * 
     * @param string $roll Student roll number
     * @param MongoDB\Database $database Optional DB instance
     * @param array $options Optional filters: 'semester', 'batch', 'date_from', 'date_to'
     * @return array
     */
    function calculateStudentAttendance($roll, $database = null, $options = []) {
        global $db;
        $activeDb = $database ?: $db;
        if (!$activeDb || empty($roll)) {
            return [
                'attendance_percentage' => 0.0,
                'discipline_score'      => 0.0,
                'daily_percentage'      => 0.0,
                'session_percentage'    => 0.0,
                'total_conducted'       => 0,
                'total_attended'        => 0,
                'total_absent'          => 0,
                'total_suspended'       => 0,
                'total_od_approved'     => 0,
                'total_days_conducted'  => 0,
                'total_days_present'    => 0.0,
                'total_days_absent'     => 0.0,
                'subjects'              => [],
                'days'                  => [],
                'daily_breakdown'       => [],
            ];
        }

        ensureAttendanceIndexes($activeDb);

        // Fetch user to determine batch if not provided
        $studentUser = $activeDb->users->findOne(['roll' => $roll]);
        $studentBatch = $options['batch'] ?? $studentUser['batch_no'] ?? null;

        // Fetch all attendance records for this student
        $attRecords = iterator_to_array($activeDb->student_attendance->find(['student_roll' => $roll]));
        $attBySession = [];
        $attSessionIds = [];
        foreach ($attRecords as $a) {
            $sid = $a['attendance_session_id'] ?? '';
            if ($sid) {
                $attBySession[$sid] = $a;
                $attSessionIds[] = $sid;
            }
        }

        // Build query for sessions containing this student
        $rollOrConditions = [
            ['rolls' => $roll]
        ];
        if (!empty($studentBatch)) {
            $rollOrConditions[] = ['batch' => $studentBatch];
        }
        if (!empty($attSessionIds)) {
            $rollOrConditions[] = ['attendance_session_id' => ['$in' => $attSessionIds]];
        }
        $sessionQuery = ['$or' => $rollOrConditions];

        if (!empty($options['semester'])) {
            $sessionQuery['semester'] = (int)$options['semester'];
        }
        if (!empty($options['batch'])) {
            $sessionQuery['batch'] = $options['batch'];
        }

        $dateFrom = $options['date_from'] ?? $options['start_date'] ?? null;
        $dateTo   = $options['date_to']   ?? $options['end_date']   ?? null;
        if (!empty($dateFrom) || !empty($dateTo)) {
            $dateFilter = [];
            if (!empty($dateFrom)) {
                $fromTs = is_numeric($dateFrom) ? $dateFrom : strtotime($dateFrom);
                $dateFilter['$gte'] = new MongoDB\BSON\UTCDateTime(mktime(0,0,0,(int)date('n',$fromTs),(int)date('j',$fromTs),(int)date('Y',$fromTs))*1000);
            }
            if (!empty($dateTo)) {
                $toTs = is_numeric($dateTo) ? $dateTo : strtotime($dateTo);
                $dateFilter['$lte'] = new MongoDB\BSON\UTCDateTime(mktime(23,59,59,(int)date('n',$toTs),(int)date('j',$toTs),(int)date('Y',$toTs))*1000);
            }
            if (!empty($dateFilter)) {
                // Support both UTCDateTime and string dates
                $sessionQuery['$and'] = [
                    ['$or' => [
                        ['date' => $dateFilter],
                        ['date' => ['$exists' => true]]
                    ]]
                ];
            }
        }

        $allSessions = iterator_to_array($activeDb->attendance_sessions->find(
            $sessionQuery,
            ['sort' => ['date' => 1, 'hour' => 1]]
        ));

        // Fetch holiday exceptions in this timeframe if applicable
        $exceptions = iterator_to_array($activeDb->attendance_exceptions->find(['type' => 'HOLIDAY']));
        $holidayDates = [];
        foreach ($exceptions as $ex) {
            $exDate = $ex['date'] ?? null;
            if ($exDate instanceof MongoDB\BSON\UTCDateTime) {
                $dStr = date('Y-m-d', $exDate->toDateTime()->getTimestamp());
                $holidayDates[$dStr] = $ex['description'] ?? 'Holiday';
            } elseif (is_string($exDate)) {
                $holidayDates[substr($exDate, 0, 10)] = $ex['description'] ?? 'Holiday';
            }
        }

        // Also check attendance_holidays if present
        if ($activeDb->attendance_holidays) {
            $hols = iterator_to_array($activeDb->attendance_holidays->find());
            foreach ($hols as $h) {
                $hDate = $h['date'] ?? null;
                if ($hDate instanceof MongoDB\BSON\UTCDateTime) {
                    $dStr = date('Y-m-d', $hDate->toDateTime()->getTimestamp());
                    $holidayDates[$dStr] = $h['description'] ?? 'Holiday';
                } elseif (is_string($hDate)) {
                    $holidayDates[substr($hDate, 0, 10)] = $h['description'] ?? 'Holiday';
                }
            }
        }

        // Aggregators
        $totalConductedSessions = 0;
        $totalAttendedSessions  = 0;
        $totalAbsentSessions    = 0;
        $totalSuspendedSessions = 0;
        $totalODApproved        = 0;

        $subjectMap = []; // [subjectKey => ['subject', 'code', 'conducted', 'attended', 'absent', 'percentage']]
        $dayMap     = []; // [dateStr => [hour => sessionDetails]]

        foreach ($allSessions as $sess) {
            $sessId   = $sess['attendance_session_id'] ?? '';
            $status   = strtoupper($sess['status'] ?? 'SCHEDULED');
            $dateVal  = $sess['date'] ?? null;
            if ($dateVal instanceof MongoDB\BSON\UTCDateTime) {
                $dateTs  = $dateVal->toDateTime()->getTimestamp();
                $dateStr = date('Y-m-d', $dateTs);
            } elseif (is_string($dateVal)) {
                $dateStr = substr($dateVal, 0, 10);
                $dateTs  = strtotime($dateStr);
            } elseif (is_numeric($dateVal)) {
                $dateTs  = (int)$dateVal;
                $dateStr = date('Y-m-d', $dateTs);
            } else {
                $dateTs  = time();
                $dateStr = date('Y-m-d');
            }

            // In-memory date filter check for string date compliance
            if (!empty($dateFrom)) {
                $fromTs = is_numeric($dateFrom) ? $dateFrom : strtotime($dateFrom);
                if ($dateTs < mktime(0,0,0,(int)date('n',$fromTs),(int)date('j',$fromTs),(int)date('Y',$fromTs))) continue;
            }
            if (!empty($dateTo)) {
                $toTs = is_numeric($dateTo) ? $dateTo : strtotime($dateTo);
                if ($dateTs > mktime(23,59,59,(int)date('n',$toTs),(int)date('j',$toTs),(int)date('Y',$toTs))) continue;
            }
            $hour     = (int)($sess['hour'] ?? 0);
            $subject  = $sess['subject'] ?? 'Unknown';
            $subjCode = $sess['subject_code'] ?? '';

            // Check if this date was a declared holiday
            $isHoliday = isset($holidayDates[$dateStr]) || sgiIsHoliday($activeDb, $dateTs, $sess['batch'] ?? '*');

            // If session is SUSPENDED or CANCELLED, or date is a holiday:
            // CRITICAL RULE: It must NOT be counted in conducted denominator,
            // must NOT count as present or absent, must NOT reduce attendance percentage.
            if ($status === 'SUSPENDED') {
                $totalSuspendedSessions++;
                $dayMap[$dateStr][$hour] = [
                    'attendance_session_id' => $sessId,
                    'hour'           => $hour,
                    'subject'        => $subject,
                    'subject_code'   => $subjCode,
                    'session_status' => 'SUSPENDED',
                    'student_status' => 'SUSPENDED',
                    'effective'      => 'SUSPENDED',
                    'reason'         => $sess['suspension_reason'] ?? 'Suspended',
                    'is_conducted'   => false,
                ];
                continue;
            }

            if ($status === 'CANCELLED' || $status === 'RESCHEDULED' || $isHoliday) {
                $dayMap[$dateStr][$hour] = [
                    'attendance_session_id' => $sessId,
                    'hour'           => $hour,
                    'subject'        => $subject,
                    'subject_code'   => $subjCode,
                    'session_status' => $status === 'RESCHEDULED' ? 'RESCHEDULED' : ($isHoliday ? 'HOLIDAY' : 'CANCELLED'),
                    'student_status' => 'EXCLUDED',
                    'effective'      => 'EXCLUDED',
                    'reason'         => $isHoliday ?: ($sess['cancellation_reason'] ?? $status),
                    'is_conducted'   => false,
                ];
                continue;
            }

            // Only CONDUCTED sessions participate in attendance
            if ($status !== 'CONDUCTED') {
                $dayMap[$dateStr][$hour] = [
                    'attendance_session_id' => $sessId,
                    'hour'           => $hour,
                    'subject'        => $subject,
                    'subject_code'   => $subjCode,
                    'session_status' => $status,
                    'student_status' => 'SCHEDULED',
                    'effective'      => 'SCHEDULED',
                    'is_conducted'   => false,
                ];
                continue;
            }

            // This is a legitimate CONDUCTED session
            $att = $attBySession[$sessId] ?? null;
            $eff = 'ABSENT';
            $orig = 'ABSENT';
            $isOD = false;

            if ($att) {
                $eff  = strtoupper($att['effective_status'] ?? $att['status'] ?? 'ABSENT');
                $orig = strtoupper($att['original_status'] ?? $att['status'] ?? 'ABSENT');
                $isOD = (!empty($att['od_approved']) || $eff === 'OD');
            }

            // If not marked OD on the attendance record, check approved OD collections
            if (!$isOD) {
                $odQuery = [
                    'student_roll' => $roll,
                    'status'       => 'APPROVED',
                    'date_from'    => ['$lte' => $dateStr],
                    'date_to'      => ['$gte' => $dateStr],
                ];
                $apprOd = $activeDb->attendance_od_requests->findOne($odQuery);
                if (!$apprOd && $activeDb->od_requests) {
                    $apprOd = $activeDb->od_requests->findOne($odQuery);
                }
                if ($apprOd) {
                    $cat = $apprOd['category'] ?? 'full_day';
                    $applies = false;
                    if ($cat === 'full_day') {
                        $applies = true;
                    } elseif ($cat === 'half_day') {
                        $half = $apprOd['half'] ?? 'morning';
                        if ($half === 'morning' && $hour >= 1 && $hour <= 4) $applies = true;
                        if ($half === 'afternoon' && $hour >= 5 && $hour <= 7) $applies = true;
                    } elseif ($cat === 'select_hours') {
                        $sh = (array)($apprOd['selected_hours'] ?? []);
                        if (in_array($hour, $sh) || in_array((string)$hour, $sh)) $applies = true;
                    }
                    if ($applies) {
                        $isOD = true;
                        $eff  = 'OD';
                    }
                }
            }

            // Determine if attended
            $isAttended = ($eff === 'PRESENT' || $eff === 'OD');
            if ($isOD) {
                $totalODApproved++;
            }

            $totalConductedSessions++;
            if ($isAttended) {
                $totalAttendedSessions++;
            } else {
                $totalAbsentSessions++;
            }

            // Update Subject-wise Statistics (SUBJECT RULE: purely conducted sessions for that subject)
            $subjKey = $subject . ($subjCode ? "_{$subjCode}" : '');
            if (!isset($subjectMap[$subjKey])) {
                $subjectMap[$subjKey] = [
                    'subject'   => $subject,
                    'code'      => $subjCode,
                    'conducted' => 0,
                    'total'     => 0,
                    'attended'  => 0,
                    'absent'    => 0,
                    'percentage'=> 0.0,
                ];
            }
            $subjectMap[$subjKey]['conducted']++;
            $subjectMap[$subjKey]['total']++;
            if ($isAttended) {
                $subjectMap[$subjKey]['attended']++;
            } else {
                $subjectMap[$subjKey]['absent']++;
            }

            // Record in Day map for Day-wise view & Daily H1/H5 calculation
            $dayMap[$dateStr][$hour] = [
                'attendance_session_id' => $sessId,
                'hour'           => $hour,
                'subject'        => $subject,
                'subject_code'   => $subjCode,
                'faculty'        => $sess['actual_faculty'] ?? $sess['original_faculty'] ?? '',
                'session_status' => 'CONDUCTED',
                'student_status' => $orig,
                'effective'      => $eff,
                'is_od'          => $isOD,
                'is_conducted'   => true,
                'start_time'     => $sess['start_time'] ?? '',
                'end_time'       => $sess['end_time'] ?? '',
            ];
        }

        // Calculate Subject percentages
        foreach ($subjectMap as $k => $s) {
            $subjectMap[$k]['percentage'] = ($s['conducted'] > 0)
                ? round(($s['attended'] / $s['conducted']) * 100, 2)
                : 0.0;
        }

        // Overall Session Percentage
        $sessionPercentage = ($totalConductedSessions > 0)
            ? round(($totalAttendedSessions / $totalConductedSessions) * 100, 2)
            : 0.0;

        // ─────────────────────────────────────────────────────────────
        // DAILY ATTENDANCE CALCULATION (MORNING H1 + AFTERNOON H5 RULE)
        // ─────────────────────────────────────────────────────────────
        $totalDailyUnitsConducted = 0.0; // Denominator in half-day units (each half day = 0.5)
        $totalDailyUnitsPresent   = 0.0; // Numerator in half-day units
        $dailyBreakdown           = [];

        foreach ($dayMap as $dateStr => $hoursOnDay) {
            $h1Session = $hoursOnDay[1] ?? null;
            $h5Session = $hoursOnDay[5] ?? null;

            $h1Conducted = ($h1Session && !empty($h1Session['is_conducted']));
            $h5Conducted = ($h5Session && !empty($h5Session['is_conducted']));

            // If neither H1 nor H5 was conducted (e.g. both suspended, or only other hours had classes):
            if (!$h1Conducted && !$h5Conducted) {
                // If there were other conducted sessions on this day, we still document the day
                $hasAnyConducted = false;
                foreach ($hoursOnDay as $hrData) {
                    if (!empty($hrData['is_conducted'])) {
                        $hasAnyConducted = true;
                        break;
                    }
                }
                $dailyBreakdown[$dateStr] = [
                    'date'             => $dateStr,
                    'morning_status'   => ($h1Session && $h1Session['session_status'] === 'SUSPENDED') ? 'SUSPENDED' : 'NOT_CONDUCTED',
                    'afternoon_status' => ($h5Session && $h5Session['session_status'] === 'SUSPENDED') ? 'SUSPENDED' : 'NOT_CONDUCTED',
                    'day_status'       => $hasAnyConducted ? 'PARTIAL_EXCLUDED' : 'EXCLUDED',
                    'units_conducted'  => 0.0,
                    'units_present'    => 0.0,
                    'sessions'         => array_values($hoursOnDay),
                ];
                continue;
            }

            $morningPresent = false;
            $afternoonPresent = false;
            $unitsCond = 0.0;
            $unitsPres = 0.0;

            // Morning evaluation (H1)
            $mStatus = 'EXCLUDED';
            if ($h1Conducted) {
                $unitsCond += 0.5;
                $eff1 = $h1Session['effective'] ?? 'ABSENT';
                if ($eff1 === 'PRESENT' || $eff1 === 'OD') {
                    $morningPresent = true;
                    $unitsPres += 0.5;
                    $mStatus = 'PRESENT';
                } else {
                    $mStatus = 'ABSENT';
                }
            } elseif ($h1Session && $h1Session['session_status'] === 'SUSPENDED') {
                $mStatus = 'SUSPENDED';
            }

            // Afternoon evaluation (H5)
            $aStatus = 'EXCLUDED';
            if ($h5Conducted) {
                $unitsCond += 0.5;
                $eff5 = $h5Session['effective'] ?? 'ABSENT';
                if ($eff5 === 'PRESENT' || $eff5 === 'OD') {
                    $afternoonPresent = true;
                    $unitsPres += 0.5;
                    $aStatus = 'PRESENT';
                } else {
                    $aStatus = 'ABSENT';
                }
            } elseif ($h5Session && $h5Session['session_status'] === 'SUSPENDED') {
                $aStatus = 'SUSPENDED';
            }

            $totalDailyUnitsConducted += $unitsCond;
            $totalDailyUnitsPresent   += $unitsPres;

            // Determine day label
            $dayLabel = 'FULL_ABSENT';
            if ($unitsCond == 1.0) {
                if ($unitsPres == 1.0) $dayLabel = 'FULL_PRESENT';
                elseif ($unitsPres == 0.5) $dayLabel = 'HALF_DAY';
                else $dayLabel = 'FULL_ABSENT';
            } elseif ($unitsCond == 0.5) {
                if ($unitsPres == 0.5) $dayLabel = 'HALF_PRESENT';
                else $dayLabel = 'HALF_ABSENT';
            }

            $dailyBreakdown[$dateStr] = [
                'date'             => $dateStr,
                'morning_status'   => $mStatus,
                'afternoon_status' => $aStatus,
                'day_status'       => $dayLabel,
                'units_conducted'  => $unitsCond,
                'units_present'    => $unitsPres,
                'sessions'         => array_values($hoursOnDay),
            ];
        }

        // Daily Attendance %
        $dailyPercentage = ($totalDailyUnitsConducted > 0)
            ? round(($totalDailyUnitsPresent / $totalDailyUnitsConducted) * 100, 2)
            : 0.0;

        // OFFICIAL OVERALL ATTENDANCE %:
        // By standard academic convention, total conducted session attendance is the primary overall percentage.
        // If daily anchor calculation is explicitly requested via options, use daily percentage.
        $useDaily = !empty($options['use_daily']) || (!empty($options['mode']) && $options['mode'] === 'daily');
        $officialAttendance = $useDaily ? $dailyPercentage : $sessionPercentage;

        // Discipline Score: Attendance % / 20
        $disciplineScore = round($officialAttendance / 20, 2);

        return [
            'attendance_percentage' => $officialAttendance,
            'percentage'            => $officialAttendance,
            'discipline_score'      => $disciplineScore,
            'daily_percentage'      => $dailyPercentage,
            'session_percentage'    => $sessionPercentage,
            'total_conducted'       => $totalConductedSessions,
            'total_periods'         => $totalConductedSessions,
            'total_attended'        => $totalAttendedSessions,
            'attended_periods'      => $totalAttendedSessions,
            'total_absent'          => $totalAbsentSessions,
            'absent_periods'        => $totalAbsentSessions,
            'total_suspended'       => $totalSuspendedSessions,
            'suspended_sessions_count' => $totalSuspendedSessions,
            'total_od_approved'     => $totalODApproved,
            'od_periods'            => $totalODApproved,
            'total_days_conducted'  => $totalDailyUnitsConducted,
            'days_conducted'        => $totalDailyUnitsConducted,
            'total_days_present'    => $totalDailyUnitsPresent,
            'days_present'          => $totalDailyUnitsPresent,
            'total_days_absent'     => max(0.0, $totalDailyUnitsConducted - $totalDailyUnitsPresent),
            'days_absent'           => max(0.0, $totalDailyUnitsConducted - $totalDailyUnitsPresent),
            'subjects'              => array_values($subjectMap),
            'subject_wise'          => array_values($subjectMap),
            'days'                  => $dayMap,
            'daily_breakdown'       => $dailyBreakdown,
        ];
    }
}
