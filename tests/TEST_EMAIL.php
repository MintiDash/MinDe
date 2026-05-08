<?php
/**
 * Email Diagnostic Test
 * Tests SMTP connection and email sending
 */

require_once __DIR__ . '/database/connect_database.php';
require_once __DIR__ . '/config/email_config.php';
require_once __DIR__ . '/library/EmailService.php';

echo "=== Email Configuration Diagnostic ===\n\n";

// Check configuration
echo "1. Configuration Check:\n";
echo "   MAIL_DRIVER: " . MAIL_DRIVER . "\n";
echo "   SMTP_HOST: " . SMTP_HOST . "\n";
echo "   SMTP_PORT: " . SMTP_PORT . "\n";
echo "   SMTP_USERNAME: " . SMTP_USERNAME . "\n";
echo "   SMTP_PASSWORD: " . (strlen(SMTP_PASSWORD) > 0 ? "***SET***" : "NOT SET") . "\n";
echo "   SMTP_ENCRYPTION: " . SMTP_ENCRYPTION . "\n";
echo "   MAIL_FROM_ADDRESS: " . MAIL_FROM_ADDRESS . "\n\n";

// Test SMTP connection
echo "2. SMTP Connection Test:\n";
$host = SMTP_HOST;
$port = SMTP_PORT;

// Try to connect
$connection = @fsockopen($host, $port, $errno, $errstr, 10);

if ($connection) {
    echo "   ✓ Connection to " . $host . ":" . $port . " successful\n";
    fclose($connection);
} else {
    echo "   ✗ Connection to " . $host . ":" . $port . " failed\n";
    echo "   Error: " . $errstr . " (Code: " . $errno . ")\n";
}

// Test email sending
echo "\n3. Sending Test Email:\n";
$service = new EmailService();
$testEmail = SMTP_USERNAME; // Send to same email for testing

$result = $service->sendVerificationEmail(
    $testEmail,
    'Test User',
    'http://localhost/test',
    'test_token_123456789'
);

if ($result) {
    echo "   ✓ Email sent successfully!\n";
    echo "   Check your inbox at: " . $testEmail . "\n";
} else {
    echo "   ✗ Email failed to send\n";
    echo "   Check PHP error log for details\n";
}

echo "\n4. Troubleshooting Steps:\n";
echo "   a) Check PHP error_log for SMTP errors\n";
echo "   b) Verify Gmail App Password (no spaces)\n";
echo "   c) Ensure 2-Factor Auth is enabled on Gmail\n";
echo "   d) Check Gmail 'Less secure apps' if App Password doesn't work\n";
echo "   e) Try changing SMTP_PORT to 465 with SMTP_ENCRYPTION = 'ssl'\n";
echo "\n";
?>
