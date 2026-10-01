<?php
include 'config.php';
requireLogin();
include 'leap_auth.php';
$mem = requireLeapMember();
$u   = $_SESSION['user'];

// Fetch current application
$app = $leap_applications->findOne(['_id' => new MongoDB\BSON\ObjectId($mem['application_id'])]);
if (!$app) { header('Location: leap.php'); exit; }

function validateProfileUrl(string $url, string $domain): bool {
    if ($url === '') return true;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = ltrim($host, 'www.');
    return ($host === $domain || str_ends_with($host, '.' . $domain));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profiles'])) {
    $leetcode   = trim($_POST['leetcode_profile']  ?? '');
    $hackerrank = trim($_POST['hackerrank_profile'] ?? '');

    if ($leetcode   && !validateProfileUrl($leetcode,   'leetcode.com'))   $error = 'LeetCode URL must be a valid leetcode.com link.';
    if (!$error && $hackerrank && !validateProfileUrl($hackerrank, 'hackerrank.com')) $error = 'HackerRank URL must be a valid hackerrank.com link.';

    if (!$error) {
        $leap_applications->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($mem['application_id'])],
            ['$set' => [
                'leetcode_profile'   => $leetcode,
                'hackerrank_profile' => $hackerrank,
            ]]
        );
        $success = 'Coding profiles updated.';
        // Refresh local values
        $app['leetcode_profile']   = $leetcode;
        $app['hackerrank_profile'] = $hackerrank;
    }
}

$unreadCount = $notifications->countDocuments(['roll' => $u['roll'], 'read' => false]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LEAP – Edit Coding Profiles</title>
    <link rel="stylesheet" href="/css/style.css?v=3">
    <link rel="icon" type="image/png" href="https://res.cloudinary.com/dsqwvarrs/image/upload/v1781704367/logo1_dorpv5.png">
</head>
<body>
<?php leapStudentNav($u, $unreadCount); ?>
<div class="container">
    <div class="form-box" style="max-width:600px;margin:0 auto;padding:32px;">
        <a href="leap.php" style="font-size:13px;color:#888;text-decoration:none;display:inline-block;margin-bottom:20px;">← Back to LEAP Home</a>
        <h2 style="color:#1a1a2e;margin-bottom:6px;">💻 Coding Profiles</h2>
        <p style="color:#888;font-size:13px;margin-bottom:24px;">Update your LeetCode and HackerRank profile links. Only valid platform URLs are accepted.</p>

        <?php if ($error):   ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

        <form method="POST" id="editForm">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#555;">
                <img src="https://leetcode.com/favicon.ico" style="width:14px;height:14px;"> LeetCode Profile
            </label>
            <input type="url" name="leetcode_profile" id="leetcodeInput"
                   placeholder="https://leetcode.com/u/yourname/"
                   value="<?= htmlspecialchars($app['leetcode_profile'] ?? '') ?>">
            <div id="leetcodeErr" style="color:#e94560;font-size:12px;margin-top:2px;display:none;"></div>

            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#555;margin-top:14px;">
                <img src="https://www.hackerrank.com/favicon.ico" style="width:14px;height:14px;"> HackerRank Profile
            </label>
            <input type="url" name="hackerrank_profile" id="hackerrankInput"
                   placeholder="https://www.hackerrank.com/profile/yourname"
                   value="<?= htmlspecialchars($app['hackerrank_profile'] ?? '') ?>">
            <div id="hackerrankErr" style="color:#e94560;font-size:12px;margin-top:2px;display:none;"></div>

            <button type="submit" name="save_profiles" class="btn-primary" style="margin-top:24px;">Save Profiles</button>
        </form>
    </div>
</div>
<?php leapNotifJS(); leapFooter(); ?>
<script>
function checkDomain(inputId, errId, domain) {
    const val = document.getElementById(inputId).value.trim();
    const err = document.getElementById(errId);
    if (!val) { err.style.display = 'none'; return true; }
    try {
        const host = new URL(val).hostname.replace(/^www\./, '');
        if (host !== domain && !host.endsWith('.' + domain)) {
            err.textContent = 'URL must be from ' + domain;
            err.style.display = 'block';
            return false;
        }
    } catch(e) {
        err.textContent = 'Enter a valid URL.';
        err.style.display = 'block';
        return false;
    }
    err.style.display = 'none';
    return true;
}
document.getElementById('leetcodeInput').addEventListener('blur',    () => checkDomain('leetcodeInput',   'leetcodeErr',   'leetcode.com'));
document.getElementById('hackerrankInput').addEventListener('blur',  () => checkDomain('hackerrankInput', 'hackerrankErr', 'hackerrank.com'));
document.getElementById('editForm').addEventListener('submit', function(e) {
    const ok1 = checkDomain('leetcodeInput',   'leetcodeErr',   'leetcode.com');
    const ok2 = checkDomain('hackerrankInput', 'hackerrankErr', 'hackerrank.com');
    if (!ok1 || !ok2) e.preventDefault();
});
</script>
</body>
</html>
