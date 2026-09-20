<?php
// Set the default timezone
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer dependencies
require __DIR__ . '/vendor/autoload.php';

// Load .env file — only for local development.
// On production the platform injects env vars directly; the file won't exist.
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            // Never overwrite a value already set by the platform
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Cloudinary config — only initialise when credentials are present
if (getenv('CLOUDINARY_CLOUD_NAME') && getenv('CLOUDINARY_API_KEY') && getenv('CLOUDINARY_API_SECRET')) {
    \Cloudinary\Configuration\Configuration::instance([
        'cloud' => [
            'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME'),
            'api_key'    => getenv('CLOUDINARY_API_KEY'),
            'api_secret' => getenv('CLOUDINARY_API_SECRET'),
        ],
        'url' => ['secure' => true]
    ]);
}

function uploadToCloudinary($fileTmpPath, $folder = 'sgi', $resourceType = 'image', $publicId = null) {
    if (!getenv('CLOUDINARY_CLOUD_NAME') || !getenv('CLOUDINARY_API_KEY') || !getenv('CLOUDINARY_API_SECRET')) {
        // Fallback: save locally under uploads/
        // $_FILES original name gives us the real extension; tmp path has none
        $origName = '';
        foreach ($_FILES as $f) {
            if (isset($f['tmp_name']) && $f['tmp_name'] === $fileTmpPath) {
                $origName = $f['name']; break;
            }
            if (is_array($f['tmp_name'] ?? null)) {
                foreach ($f['tmp_name'] as $i => $t) {
                    if ($t === $fileTmpPath) { $origName = $f['name'][$i]; break 2; }
                }
            }
        }
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION)) ?: 'bin';
        $filename = uniqid('sgi_', true) . '.' . $ext;
        $dest     = __DIR__ . '/uploads/' . $filename;
        if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
        move_uploaded_file($fileTmpPath, $dest);
        return 'uploads/' . $filename;
    }
    $uploader      = new \Cloudinary\Api\Upload\UploadApi();
    $uploadOptions = ['folder' => $folder, 'resource_type' => $resourceType];
    if ($publicId !== null) $uploadOptions['public_id'] = $publicId;
    $result = $uploader->upload($fileTmpPath, $uploadOptions);
    return $result['secure_url'];
}

// Support both MONGODB_URI (Atlas) and MONGODB_HOST/PORT/DB (self-hosted)
$_mongoUri = getenv('MONGODB_URI');
if (empty($_mongoUri)) {
    $_mongoHost = getenv('MONGODB_HOST') ?: 'localhost';
    $_mongoPort = getenv('MONGODB_PORT') ?: '27017';
    $_mongoUri  = "mongodb://{$_mongoHost}:{$_mongoPort}";
    unset($_mongoHost, $_mongoPort);
}
unset($_mongoUri); // connection string used below; don't leave credentials in scope

$client = new MongoDB\Client(getenv('MONGODB_URI') ?: (function(){
    $h = getenv('MONGODB_HOST') ?: 'localhost';
    $p = getenv('MONGODB_PORT') ?: '27017';
    return "mongodb://{$h}:{$p}";
})());
$db     = $client->sgi_db;
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
