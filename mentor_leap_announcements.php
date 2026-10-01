<?php
include 'config.php';
include 'leap_auth.php';
$m = requireMentorLeap();

// Post announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post'])) {
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if ($title && $desc) {
        $leap_announcements->insertOne([
            'mentor_id'       => $m['mentor_id'],
            'created_by_name' => $m['name'],
            'title'           => $title,
            'description'     => $desc,
            'status'          => 'PUBLISHED',
            'created_at'      => new MongoDB\BSON\UTCDateTime(),
            'published_at'    => new MongoDB\BSON\UTCDateTime(),
        ]);
        // Notify all active LEAP students under this mentor
        $mems = $leap_memberships->find(['mentor_id' => $m['mentor_id'], 'status' => 'ACTIVE']);
        foreach ($mems as $mem) {
            leapNotifyStudent($mem['student_id'],
                "📢 New LEAP announcement: {$title}",
                'leap_announcements.php'
            );
        }
        header('Location: mentor_leap_announcements.php?posted=1');
        exit;
    }
}

// Delete
if (isset($_GET['delete'])) {
    $leap_announcements->deleteOne([
        '_id'       => new MongoDB\BSON\ObjectId($_GET['delete']),
        'mentor_id' => $m['mentor_id'],
    ]);
    header('Location: mentor_leap_announcements.php');
    exit;
}

$anns = iterator_to_array($leap_announcements->find(
    ['mentor_id' => $m['mentor_id']],
    ['sort' => ['created_at' => -1]]
));
$unreadCount = $notifications->countDocuments(['mentor_id' => $m['mentor_id'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Announcements (Mentor)</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapMentorNav($m, $unreadCount); ?>
<div class="container">
    <h2 style="color:#1a1a2e;margin-bottom:20px;">📢 LEAP Announcements</h2>

    <?php if (isset($_GET['posted'])): ?><p class="success" style="margin-bottom:16px;">✅ Announcement posted and students notified.</p><?php endif; ?>

    <!-- Post form -->
    <div class="form-box" style="padding:24px;margin-bottom:28px;">
        <h3 style="color:#1a1a2e;margin-bottom:16px;">Post New Announcement</h3>
        <form method="POST">
            <label>Title</label>
            <input type="text" name="title" placeholder="Announcement title…" required>
            <label>Description</label>
            <textarea name="description" rows="4" placeholder="Write your announcement…" required></textarea>
            <button type="submit" name="post" class="btn-primary" style="margin-top:12px;">Post Announcement</button>
        </form>
    </div>

    <!-- List -->
    <h3 style="color:#1a1a2e;margin-bottom:14px;">Posted Announcements</h3>
    <?php if (empty($anns)): ?>
        <p class="no-data">No announcements yet.</p>
    <?php else: ?>
        <?php foreach ($anns as $a): ?>
        <div style="background:#fff;border-radius:12px;padding:18px 22px;margin-bottom:14px;box-shadow:0 2px 8px rgba(0,0,0,0.07);border-left:4px solid #f5a623;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
            <div style="flex:1;">
                <div style="font-size:16px;font-weight:700;color:#1a1a2e;"><?= htmlspecialchars($a['title']) ?></div>
                <div style="font-size:13px;color:#555;margin-top:6px;line-height:1.6;"><?= nl2br(htmlspecialchars($a['description'])) ?></div>
                <div style="font-size:12px;color:#aaa;margin-top:8px;"><?= date('d M Y, h:i A', $a['created_at']->toDateTime()->getTimestamp()) ?></div>
            </div>
            <a href="mentor_leap_announcements.php?delete=<?= (string)$a['_id'] ?>"
               onclick="return confirm('Delete this announcement?')"
               style="color:#e94560;font-size:18px;text-decoration:none;flex-shrink:0;">🗑</a>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php leapMentorNotifJS(); leapFooter(); ?>
</body>
</html>
