<?php
// Set UTF-8 encoding for proper emoji and character support
header('Content-Type: text/html; charset=utf-8');

// Start output buffering to prevent header issues
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer dependencies
require __DIR__ . '/vendor/autoload.php';

// Load .env file manually (fallback if Dotenv is not available)
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Cloudinary config
\Cloudinary\Configuration\Configuration::instance([
    'cloud' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => getenv('CLOUDINARY_API_KEY'),
        'api_secret' => getenv('CLOUDINARY_API_SECRET'),
    ],
    'url' => ['secure' => true]
]);

function uploadToCloudinary($fileTmpPath, $folder = 'sgi', $resourceType = 'image') {
    $uploader = new \Cloudinary\Api\Upload\UploadApi();
    $result = $uploader->upload($fileTmpPath, [
        'folder' => $folder,
        'resource_type' => $resourceType
    ]);
    return $result['secure_url'];
}

$client = new MongoDB\Client(getenv('MONGODB_URI'));
$db        = $client->sgi_db;
$users     = $db->users;
$semesters = $db->semesters;
$subjects  = $db->subjects;
$mentors       = $db->mentors;
$announcements = $db->announcements;
$calendar_events = $db->calendar_events;
$notifications = $db->notifications;
$approvals     = $db->approvals;

// Session timeout - 30 minutes
if (isset($_SESSION['user'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_destroy();
        header("Location: index.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function imgUrl($path) {
    if (empty($path)) return '';

    if (strpos($path, 'http') === 0) {
        if (strpos($path, '/raw/upload/') !== false) {
            return str_replace('/raw/upload/', '/raw/upload/fl_attachment:false/', $path);
        }
        return $path;
    }

    return 'uploads/' . $path;
}

function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit;
    }
}
