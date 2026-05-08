<?php
/**
 * Simplified Email Send Test
 */

$host = 'sandbox.smtp.mailtrap.io';
$port = 2525;
$username = '36189565f0742e';
$password = '40b7151ab14a27';

echo "Connecting...\n";
$socket = stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 30);

if (!$socket) {
    die("Connection failed: " . $errstr);
}

echo "Connected!\n";

// Helper function
function send_command($socket, $cmd) {
    fputs($socket, $cmd . "\r\n");
    sleep(0.1); // Small delay
    $response = fgets($socket, 1024);
    echo $cmd . " → " . trim($response) . "\n";
    return $response;
}

send_command($socket, "HELO localhost");
send_command($socket, "AUTH LOGIN");
send_command($socket, base64_encode($username));
send_command($socket, base64_encode($password));

send_command($socket, "MAIL FROM:<noreply@minc.com>");
send_command($socket, "RCPT TO:<36189565f0742e@inbox.mailtrap.io>");
send_command($socket, "DATA");

// Send email
$msg = "From: MinC <noreply@minc.com>\r\n";
$msg .= "To: test@test.com\r\n";
$msg .= "Subject: Test Email\r\n";
$msg .= "Content-Type: text/html; charset=UTF-8\r\n";
$msg .= "\r\n";
$msg .= "<h1>Hello!</h1>\n";
$msg .= "<p>This is a test email.</p>\n";

fputs($socket, $msg . "\r\n.\r\n");
$response = fgets($socket, 1024);
echo "Email body → " . trim($response) . "\n";

send_command($socket, "QUIT");
fclose($socket);

echo "\nDone! Check Mailtrap inbox.\n";
?>
