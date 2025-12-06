<?php
/**
 * Database Connection - Animal Bite Monitoring System
 * ----------------------------------------------
 * This script creates a MySQLi connection and stores it in $conn
 * so other PHP files can simply include this file.
 */

// Database credentials
$servername = "localhost";     // usually 'localhost'
$username   = "u655303832_abc";          // change if you use another username
$password   = "Popoy4682.";              // add password if needed
$database   = "u655303832_abc"; // change to your actual database name

// Enable MySQLi error reporting (for debugging)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Establish database connection
    $conn = new mysqli($servername, $username, $password, $database);

    // Set charset to UTF-8 for proper encoding
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // If connection fails, show a friendly message
    error_log($e->getMessage()); // log error internally
    exit("Database connection failed. Please check your configuration in db.php.");
}
?>
