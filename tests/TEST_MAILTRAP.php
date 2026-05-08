<?php
/**
 * Simple Email Test
 * Tests direct SMTP connection to Mailtrap
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Mailtrap Email Test ===\n\n";

// Test 1: Direct Socket Connection
echo "Test 1: Socket Connection\n";
$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;
$username = '36189565f0742e';
$password = '40b7151ab14a27';

$socket = @stream_socket_client(
    'tcp://' . $host . ':' . $port,
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT
);

if (!$socket) {
    echo "✗ Connection failed: " . $errstr . " (Error: " . $errno . ")\n";
    exit;
} else {
    echo "✓ Connected to " . $host . ":" . $port . "\n";
}

// Read greeting
$response = fgets($socket, 1024);
echo "Server says: " . trim($response) . "\n";

// Test basic SMTP commands
echo "\nTest 2: SMTP Commands\n";

// HELO
fputs($socket, "HELO test\r\n");
$response = fgets($socket, 1024);
echo "HELO response: " . trim($response) . "\n";

// AUTH LOGIN
fputs($socket, "AUTH LOGIN\r\n");
$response = fgets($socket, 1024);
echo "AUTH response: " . trim($response) . "\n";

// Username
fputs($socket, base64_encode($username) . "\r\n");
$response = fgets($socket, 1024);
echo "Username response: " . trim($response) . "\n";

// Password
fputs($socket, base64_encode($password) . "\r\n");
$response = fgets($socket, 1024);
echo "Password response: " . trim($response) . "\n";

if (strpos($response, '235') !== false) {
    echo "✓ Authentication successful!\n";
} else {
    echo "✗ Authentication failed\n";
}

// Close
fputs($socket, "QUIT\r\n");
fclose($socket);

echo "\nTest complete. Check your Mailtrap inbox.\n";
?>
