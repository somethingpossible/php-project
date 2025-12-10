<?php
// Run this script once to ensure the database file and table exist
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/bootstrap.php';

try {
    $pdo = \App\Config::pdo();
    echo "Database initialized at: " . realpath(\App\Config::dbPath());
} catch (Exception $e) {
    echo "Error initializing database: " . $e->getMessage();
}

?>
