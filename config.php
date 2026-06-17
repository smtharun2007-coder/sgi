<?php
require 'vendor/autoload.php';

// Cloudinary config
\Cloudinary\Configuration\Configuration::instance([
    'cloud' => [
        'cloud_name' => 'dsqwvarrs',
        'api_key'    => '866835938264269',
        'api_secret' => 'T_cArk8I_fbU1QIi6sOFTRUL21s',
    ],
    'url' => ['secure' => true]
]);

function uploadToCloudinary($fileTmpPath, $folder = 'sgi') {
    $uploader = new \Cloudinary\Api\Upload\UploadApi();
    $result   = $uploader->upload($fileTmpPath, ['folder' => $folder]);
    return $result['secure_url'];
}

$client = new MongoDB\Client(
    "mongodb+srv://smt2007:1561%40SPSslm@smt2007.bwry6zp.mongodb.net/?appName=smt2007&tls=true&tlsAllowInvalidCertificates=true&connectTimeoutMS=30000&serverSelectionTimeoutMS=30000"
);
$db        = $client->sgi_db;
$users     = $db->users;
$semesters = $db->semesters;
$subjects  = $db->subjects;
$mentors   = $db->mentors;

session_start();

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
    return (strpos($path, 'http') === 0) ? $path : 'uploads/' . $path;
}

function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit;
    }
}
?>
