<?php
include 'config.php';

$isMentor = isset($_SESSION['mentor']);
$isStudent = isset($_SESSION['user']);

if (!$isMentor && !$isStudent) {
    header('Content-Type: application/json');
    echo json_encode([]); exit;
}

// Only allow safe same-app relative links. Maps legacy/wrong-side links
// to the correct page for the viewer's role.
function sanitizeNotifLink($raw, $isMentorCtx) {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    // Block absolute URLs / protocols / traversal.
    if (preg_match('#^\s*(https?:|//|javascript:|data:|mailto:)#i', $raw)) return '';
    if (strpos($raw, '..') !== false) return '';
    // Keep only path + optional query, strip leading /.
    $raw = ltrim($raw, '/');
    $parts = explode('?', $raw, 2);
    $page = strtolower(trim($parts[0]));
    $query = isset($parts[1]) ? ('?' . $parts[1]) : '';
    // Query whitelist: allow only safe chars (ids, test, tr, sess params).
    if ($query !== '' && !preg_match('/^[\w=&%\-\.\?]+$/', $parts[1])) $query = '';

    $studentPages = [
        'leap.php' => 'leap.php',
        'leap_apply.php' => 'leap_apply.php',
        'leap_announcements.php' => 'leap_announcements.php',
        'leap_activities.php' => 'leap_activities.php',
        'leap_training.php' => 'leap_training.php',
        'leap_tests.php' => 'leap_tests.php',
        'leap_progress.php' => 'leap_progress.php',
        'leap_results.php' => 'leap_results.php',
        'leap_profile_edit.php' => 'leap_profile_edit.php',
        'announcements.php' => 'announcements.php',
        'attendance.php' => 'attendance.php',
        'calendar.php' => 'calendar.php',
        'dashboard.php' => 'dashboard.php',
        'student_approvals.php' => 'student_approvals.php',
        'verify_marks.php' => 'verify_marks.php',
    ];
    $mentorPages = [
        'mentor_leap_applications.php' => 'mentor_leap_applications.php',
        'mentor_leap_pacc.php' => 'mentor_leap_pacc.php',
        'mentor_leap_announcements.php' => 'mentor_leap_announcements.php',
        'mentor_leap_activities.php' => 'mentor_leap_activities.php',
        'mentor_leap_training.php' => 'mentor_leap_training.php',
        'mentor_leap_attendance.php' => 'mentor_leap_attendance.php',
        'mentor_leap_tests.php' => 'mentor_leap_tests.php',
        'mentor_leap_results.php' => 'mentor_leap_results.php',
        'mentor_dashboard.php' => 'mentor_dashboard.php',
        'mentor_announcements.php' => 'mentor_announcements.php',
        'mentor_approvals.php' => 'mentor_approvals.php',
        'mentor_attendance.php' => 'mentor_attendance.php',
        'mentor_calendar.php' => 'mentor_calendar.php',
    ];
    if (isset($studentPages[$page])) {
        // Mentor clicking a student page: redirect to mentor equivalent.
        if ($isMentorCtx) {
            $map = [
                'leap.php' => 'mentor_leap_applications.php',
                'leap_apply.php' => 'mentor_leap_applications.php',
                'leap_announcements.php' => 'mentor_leap_announcements.php',
                'leap_activities.php' => 'mentor_leap_activities.php',
                'leap_training.php' => 'mentor_leap_training.php',
                'leap_tests.php' => 'mentor_leap_tests.php',
                'leap_results.php' => 'mentor_leap_results.php',
                'leap_progress.php' => 'mentor_leap_attendance.php',
                'leap_profile_edit.php' => 'mentor_leap_applications.php',
            ];
            return ($map[$page] ?? 'mentor_dashboard.php') . $query;
        }
        return $studentPages[$page] . $query;
    }
    if (isset($mentorPages[$page])) {
        // Student clicking a mentor page: redirect to student equivalent.
        if (!$isMentorCtx) {
            $map = [
                'mentor_leap_applications.php' => 'leap_apply.php',
                'mentor_leap_pacc.php' => 'leap.php',
                'mentor_leap_announcements.php' => 'leap_announcements.php',
                'mentor_leap_activities.php' => 'leap_activities.php',
                'mentor_leap_training.php' => 'leap_training.php',
                'mentor_leap_attendance.php' => 'leap_training.php',
                'mentor_leap_tests.php' => 'leap_tests.php',
                'mentor_leap_results.php' => 'leap_results.php',
            ];
            return ($map[$page] ?? 'dashboard.php') . $query;
        }
        return $mentorPages[$page] . $query;
    }
    return '';
}


// Mark all read — always return JSON, never plain text
if (isset($_GET['mark_all'])) {
    if ($isMentor) {
        $notifications->updateMany(['mentor_id'=>$_SESSION['mentor']['mentor_id'],'read'=>false],['$set'=>['read'=>true]]);
    } else {
        $notifications->updateMany(['roll'=>$_SESSION['user']['roll'],'read'=>false],['$set'=>['read'=>true]]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']); exit;
}

// Delete all notifications
if (isset($_GET['delete_all'])) {
    if ($isMentor) {
        $notifications->deleteMany(['mentor_id'=>$_SESSION['mentor']['mentor_id']]);
    } else {
        $notifications->deleteMany(['roll'=>$_SESSION['user']['roll']]);
    }
    header('Content-Type: application/json');
    echo json_encode(['status'=>'ok']); exit;
}

// Fetch notifications as JSON
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    if ($isMentor) {
        $cur = $notifications->find(['mentor_id'=>$_SESSION['mentor']['mentor_id']],['sort'=>['created_at'=>-1],'limit'=>20]);
    } else {
        $cur = $notifications->find(['roll'=>$_SESSION['user']['roll']],['sort'=>['created_at'=>-1],'limit'=>20]);
    }
    $result = [];
    foreach ($cur as $n) {
        $rawMsg  = isset($n['message']) ? (string)$n['message'] : '';
        $rawLink = isset($n['link']) ? (string)$n['link'] : '';

        // Backward compat: old LEAP rows stored HTML inside message:
        //   '... text ... <a href="leap_x.php">View</a>'
        // Extract that href so it still becomes clickable after we escape the text.
        if ($rawLink === '' && preg_match('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1/i', $rawMsg, $mm)) {
            $rawLink = $mm[2];
        }
        // Strip any legacy anchor tags from the text (we render the button separately).
        $plainMsg = trim(preg_replace('/<a\s+[^>]*>.*?<\/a>/is', '', $rawMsg));
        // Fallback: strip any other stray HTML that may be in old rows.
        $plainMsg = trim(strip_tags($plainMsg));
        if ($plainMsg === '') $plainMsg = $rawMsg;

        $result[] = [
            'message' => htmlspecialchars($plainMsg, ENT_QUOTES, 'UTF-8'),
            'link'    => sanitizeNotifLink($rawLink, $isMentor),
            'read'    => (bool)$n['read'],
            'time'    => date('d M, h:i A', $n['created_at']->toDateTime()->getTimestamp())
        ];
    }
    echo json_encode($result);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status'=>'ok']);
