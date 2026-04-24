<?php
require 'vendor/autoload.php';

$client = new MongoDB\Client(
    "mongodb+srv://smt2007:1561%40SPSslm@smt2007.bwry6zp.mongodb.net/?appName=smt2007&tls=true&tlsAllowInvalidCertificates=true&connectTimeoutMS=30000&serverSelectionTimeoutMS=30000"
);
$db        = $client->sgi_db;
$users     = $db->users;
$semesters = $db->semesters;
$subjects  = $db->subjects;

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

function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit;
    }
}
?>
