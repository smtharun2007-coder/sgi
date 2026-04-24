<?php
$host = 'ac-zyc2qzh-shard-00-00.bwry6zp.mongodb.net';
$port = 27017;
$timeout = 5;

$connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
if ($connection) {
    echo "Port 27017 is OPEN - connection successful!";
    fclose($connection);
} else {
    echo "Port 27017 is BLOCKED - Error: $errstr ($errno)";
}
?>


