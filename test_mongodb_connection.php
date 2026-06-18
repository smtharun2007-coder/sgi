<?php
// Test MongoDB connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MongoDB Connection Test</h2>";

// Load .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') === false || strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        if (!getenv(trim($key))) {
            putenv(trim($key) . '=' . trim($value));
        }
    }
    echo "<p>✅ .env file loaded</p>";
} else {
    echo "<p>❌ .env file not found</p>";
    exit;
}

// Get MongoDB URI
$mongoUri = getenv('MONGODB_URI');
if (empty($mongoUri)) {
    echo "<p>❌ MONGODB_URI is empty</p>";
    exit;
} else {
    // Hide password in display
    $displayUri = preg_replace('/:(.*?)@/', ':****@', $mongoUri);
    echo "<p>✅ MONGODB_URI is set: <code>" . htmlspecialchars($displayUri) . "</code></p>";
}

// Try to connect
echo "<h3>Testing Connection...</h3>";
try {
    require __DIR__ . '/vendor/autoload.php';
    $client = new MongoDB\Client($mongoUri);
    
    // Try to list databases
    $databases = $client->listDatabases();
    echo "<p style='color: green; font-size: 20px;'>✅ MongoDB connection successful!</p>";
    echo "<p>Your password is correct!</p>";
    
    echo "<h3>Available Databases:</h3>";
    echo "<ul>";
    foreach ($databases as $database) {
        echo "<li>" . htmlspecialchars($database['name']) . "</li>";
    }
    echo "</ul>";
    
    // Try to access SGI database
    echo "<h3>Testing SGI Database:</h3>";
    $db = $client->sgi_db;
    $collections = $db->listCollections();
    echo "<p>✅ Connected to 'sgi_db' database</p>";
    
    echo "<h3>Collections in sgi_db:</h3>";
    echo "<ul>";
    foreach ($collections as $collection) {
        echo "<li>" . htmlspecialchars($collection->getName()) . "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 20px;'>❌ MongoDB connection failed!</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Common reasons:</strong></p>";
    echo "<ul>";
    echo "<li>Incorrect password</li>";
    echo "<li>Incorrect username</li>";
    echo "<li>IP address not whitelisted in MongoDB Atlas</li>";
    echo "<li>Network connectivity issues</li>";
    echo "</ul>";
}
?>