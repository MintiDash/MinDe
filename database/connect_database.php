<?php
/**
 * Database Connection File
 * Include this file in your PHP scripts to connect to the dmmmsu_db database
 * Usage: include_once 'connect_database.php';
 */

// Database configuration
define('DB_HOST', '127.0.0.1'); // Database host
define('DB_USERNAME', 'root');        // Database username
define('DB_PASSWORD', '');       // Database password
define('DB_NAME', 'minc');       // Database name

// Create connection using MySQLi (procedural)
$connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 for proper character encoding (matches your database)
mysqli_set_charset($connection, "utf8mb4");

// Create PDO connection with proper charset (utf8mb4 to match your database schema)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    // Ensure we're using the correct charset and collation
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

} catch (PDOException $e) {
    error_log("PDO Connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Function to close connections (call this at the end of your scripts if needed)
function closeConnections()
{
    global $connection, $pdo;
    if ($connection) {
        mysqli_close($connection);
    }
    $pdo = null;
}

// Optional: Display success message (remove in production)
// echo "Connected to minc successfully!";
?>