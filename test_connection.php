<?php
echo "<h2>PHP Version: " . phpversion() . "</h2>";
echo "<h3>MongoDB Extension: " . (extension_loaded('mongodb') ? '<span style="color:green">✅ Loaded</span>' : '<span style="color:red">❌ NOT Loaded</span>') . "</h3>";
echo "<h3>cacert.pem exists: " . (file_exists(__DIR__ . '/cacert.pem') ? '<span style="color:green">✅ Yes</span>' : '<span style="color:red">❌ No</span>') . "</h3>";
echo "<h3>vendor/autoload.php exists: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? '<span style="color:green">✅ Yes</span>' : '<span style="color:red">❌ No</span>') . "</h3>";

// Test port 27017
$host = 'ac-zyc2qzh-shard-00-00.bwry6zp.mongodb.net';
$port27017 = @fsockopen($host, 27017, $errno, $errstr, 5);
echo "<h3>Port 27017: " . ($port27017 ? '<span style="color:green">✅ Open</span>' : '<span style="color:red">❌ Blocked - ' . $errstr . '</span>') . "</h3>";
if ($port27017) fclose($port27017);

// Test port 443
$port443 = @fsockopen($host, 443, $errno, $errstr, 5);
echo "<h3>Port 443: " . ($port443 ? '<span style="color:green">✅ Open</span>' : '<span style="color:red">❌ Blocked - ' . $errstr . '</span>') . "</h3>";
if ($port443) fclose($port443);

// Try MongoDB connection
require 'vendor/autoload.php';
try {
    $client = new MongoDB\Client(
        "mongodb+srv://smt2007:1561%40SPSslm@smt2007.bwry6zp.mongodb.net/?appName=smt2007&tls=true&tlsAllowInvalidCertificates=true&connectTimeoutMS=10000&serverSelectionTimeoutMS=10000"
    );
    $client->selectDatabase('sgi_db')->command(['ping' => 1]);
    echo "<h2 style='color:green'>✅ MongoDB Connected Successfully!</h2>";
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Connection Failed</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
