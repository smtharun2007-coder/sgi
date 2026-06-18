<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'vendor/autoload.php';

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
$mentors   = $db->mentors;

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