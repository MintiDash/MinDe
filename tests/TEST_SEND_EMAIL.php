<?php
/**
 * Full Email Send Test
 * Sends a test email through Mailtrap
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Full Email Send Test ===\n\n";

$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;
$username = '36189565f0742e';
$password = '40b7151ab14a27';
$from_email = 'noreply@minc.com';
$from_name = 'MinC';
$to_email = '36189565f0742e@inbox.mailtrap.io'; // Mailtrap test inbox

echo "Connecting to " . $host . ":" . $port . "...\n";

$socket = @stream_socket_client(
    'tcp://' . $host . ':' . $port,
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT
);

if (!$socket) {
    echo "✗ Connection failed: " . $errstr . "\n";
    exit;
}

function smtp_response($socket) {
    $response = '';
    while (strpos($response, '\n') === false && !feof($socket)) {
        $response .= fgets($socket, 1024);
    }
    return $response;
}

function smtp_send($socket, $command) {
    fputs($socket, $command . "\r\n");
    $response = smtp_response($socket);
    echo $command . "\n  → " . trim($response) . "\n";
    return $response;
}

echo "\n1. Connected ✓\n\n";

// HELO
smtp_send($socket, "HELO localhost");

// AUTH LOGIN
smtp_send($socket, "AUTH LOGIN");
smtp_send($socket, base64_encode($username));
smtp_send($socket, base64_encode($password));

// MAIL FROM
$resp = smtp_send($socket, "MAIL FROM:<" . $from_email . ">");

// RCPT TO
$resp = smtp_send($socket, "RCPT TO:<" . $to_email . ">");

// DATA
smtp_send($socket, "DATA");

// Email headers and body
$email = "From: " . $from_name . " <" . $from_email . ">\r\n";
$email .= "To: " . $to_email . "\r\n";
$email .= "Subject: Test Email from MinC\r\n";
$email .= "MIME-Version: 1.0\r\n";
$email .= "Content-Type: text/html; charset=UTF-8\r\n";
$email .= "\r\n";
$email .= "<h1>Test Email</h1>\n";
$email .= "<p>This is a test email from MinC registration system.</p>\n";
$email .= "<p>If you receive this, Mailtrap is working!</p>\n";

fputs($socket, $email . "\r\n.\r\n");
$response = smtp_response($socket);
echo "Email body sent\n  → " . trim($response) . "\n";

// QUIT
smtp_send($socket, "QUIT");
fclose($socket);

echo "\n✓ Test complete!\n";
echo "Check your Mailtrap inbox at: https://mailtrap.io/inboxes\n";
?>
