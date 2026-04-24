<?php
require 'vendor/autoload.php';
try {
    $client = new MongoDB\Client(
        "mongodb+srv://smt2007:1561%40SPSslm@smt2007.bwry6zp.mongodb.net/?appName=smt2007&tls=true&tlsAllowInvalidCertificates=false",
        ['tlsCAFile' => __DIR__ . '/cacert.pem']
    );
    $client->selectDatabase('sgi_db')->command(['ping' => 1]);
    echo "<h2 style='color:green'>✅ MongoDB Connected Successfully!</h2>";
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Connection Failed</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>MongoDB Extension: " . (extension_loaded('mongodb') ? '<span style=\"color:green\">Loaded ✅</span>' : '<span style=\"color:red\">NOT Loaded ❌</span>') . "</p>";
?>
