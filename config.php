<?php

// --- Database connection (edit these) ---
$dbHost = 'localhost';
$dbName = 'something_netools_db';
$dbUser = 'something_netools_dbuser';
$dbPass = 'Blah-Blah';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

?>

