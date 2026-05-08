<?php
/**
 * Email Service Class
 * Path: library/EmailService.php
 * 
 * Handles sending emails using PHP's mail() function or SMTP
 * Compatible with standard PHP without external dependencies
 */

class EmailService {
    private $config;
    private $headers;
    
    public function __construct() {
        // Load email configuration
        require_once __DIR__ . '/../config/email_config.php';
        
        $this->config = [
            'driver' => MAIL_DRIVER,
            'host' => SMTP_HOST,
            'port' => SMTP_PORT,
            'username' => SMTP_USERNAME,
            'password' => SMTP_PASSWORD,
            'encryption' => SMTP_ENCRYPTION,
            'from_address' => MAIL_FROM_ADDRESS,
            'from_name' => MAIL_FROM_NAME,
        ];
        
        // Set default headers
        $this->setDefaultHeaders();
    }
    
    /**
     * Set default email headers
     */
    private function setDefaultHeaders() {
        $this->headers = "MIME-Version: 1.0" . "\r\n";
        $this->headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $this->headers .= "From: " . $this->config['from_name'] . " <" . $this->config['from_address'] . ">" . "\r\n";
        $this->headers .= "Reply-To: " . $this->config['from_address'] . "\r\n";
    }
    
    /**
     * Send email verification email
     * 
     * @param string $to Recipient email address
     * @param string $name Recipient name
     * @param string $verificationLink Verification link
     * @param string $token Verification token
     * @return bool Success status
     */
    public function sendVerificationEmail($to, $name, $verificationLink, $token) {
        $subject = "Verify Your Email Address - MinC Automotive Parts";
        
        // Build email body
        $body = $this->getVerificationEmailTemplate($name, $verificationLink, $token);
        
        return $this->send($to, $subject, $body);
    }

    /**
     * Send OTP verification email for registration flow.
     *
     * @param string $to
     * @param string $name
     * @param string $otpCode
     * @param int $expiryMinutes
     * @return bool
     */
    public function sendOtpVerificationEmail($to, $name, $otpCode, $expiryMinutes = 10) {
        $subject = "Your MinC verification code";
        $body = $this->getOtpVerificationTemplate($name, $otpCode, $expiryMinutes);
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send welcome email after verification
     * 
     * @param string $to Recipient email address
     * @param string $name Recipient name
     * @return bool Success status
     */
    public function sendWelcomeEmail($to, $name) {
        $subject = "Welcome to MinC - Automotive Parts!";
        $body = $this->getWelcomeEmailTemplate($name);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send password reset email
     * 
     * @param string $to Recipient email address
     * @param string $name Recipient name
     * @param string $resetLink Password reset link
     * @return bool Success status
     */
    public function sendPasswordResetEmail($to, $name, $resetLink) {
        $subject = "Reset Your Password - MinC Automotive Parts";
        $body = $this->getPasswordResetTemplate($name, $resetLink);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Generic email sending method
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body HTML email body
     * @return bool Success status
     */
    public function send($to, $subject, $body) {
        try {
            // Validate email
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                error_log("Invalid email address: " . $to);
                return false;
            }
            
            // Log attempt
            $logMessage = date('Y-m-d H:i:s') . " - Attempting to send email to: " . $to . " | Driver: " . $this->config['driver'];
            error_log($logMessage);
            
            // Use appropriate driver
            switch ($this->config['driver']) {
                case 'smtp':
                    $result = $this->sendViaSMTP($to, $subject, $body);
                    error_log("SMTP Send Result: " . ($result ? "SUCCESS" : "FAILED"));
                    return $result;
                case 'mail':
                default:
                    $result = $this->sendViaMail($to, $subject, $body);
                    error_log("Mail Send Result: " . ($result ? "SUCCESS" : "FAILED"));
                    return $result;
            }
        } catch (Exception $e) {
            error_log("Email sending exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHP's mail() function
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    private function sendViaMail($to, $subject, $body) {
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        
        return mail($to, $subject, $body, $this->headers);
    }
    
    /**
     * Send email via SMTP (using basic socket connection)
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return bool
     */
    private function sendViaSMTP($to, $subject, $body) {
        // Build full email message
        $email_message = "To: " . $to . "\r\n";
        $email_message .= "Subject: " . $subject . "\r\n";
        $email_message .= $this->headers . "\r\n";
        $email_message .= $body;
        
        // SMTP parameters
        $host = $this->config['host'];
        $port = $this->config['port'];
        $username = $this->config['username'];
        $password = $this->config['password'];
        $encryption = strtolower($this->config['encryption']);
        
        // Determine stream context
        $stream_options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];
        
        try {
            // Create stream context
            $context = stream_context_create($stream_options);
            
            // Determine protocol prefix
            if ($encryption === 'ssl') {
                $protocol = 'ssl://';
            } elseif ($encryption === 'tls') {
                $protocol = 'tcp://';
            } else {
                $protocol = 'tcp://';
            }
            
            // Connect to SMTP server
            $smtp = @stream_socket_client(
                $protocol . $host . ':' . $port,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$smtp) {
                error_log("SMTP Connection failed: " . $errstr . " (Error: " . $errno . ")");
                return false;
            }
            
            error_log("Connected to SMTP server: " . $host . ":" . $port);
            
            // Read server greeting
            $response = fgets($smtp, 1024);
            error_log("SMTP Response 1: " . trim($response));
            
            if (strpos($response, '220') === false) {
                fclose($smtp);
                return false;
            }
            
            // Send HELO
            fputs($smtp, "HELO localhost\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response HELO: " . trim($response));
            
            // STARTTLS if needed
            if ($encryption === 'tls') {
                fputs($smtp, "STARTTLS\r\n");
                $response = fgets($smtp, 1024);
                error_log("SMTP Response STARTTLS: " . trim($response));
                
                if (strpos($response, '220') !== false) {
                    stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    error_log("TLS enabled");
                }
            }
            
            // Authentication
            fputs($smtp, "AUTH LOGIN\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response AUTH: " . trim($response));
            
            // Send username
            fputs($smtp, base64_encode($username) . "\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response Username: " . trim($response));
            
            // Send password
            fputs($smtp, base64_encode($password) . "\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response Password: " . trim($response));
            
            if (strpos($response, '235') === false) {
                error_log("Authentication failed");
                fclose($smtp);
                return false;
            }
            
            error_log("Authentication successful");
            
            // Send FROM
            fputs($smtp, "MAIL FROM: <" . $this->config['from_address'] . ">\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response MAIL FROM: " . trim($response));
            
            // Send TO
            fputs($smtp, "RCPT TO: <" . $to . ">\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response RCPT TO: " . trim($response));
            
            // Send DATA
            fputs($smtp, "DATA\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response DATA: " . trim($response));
            
            // Send email body
            fputs($smtp, $email_message . "\r\n.\r\n");
            $response = fgets($smtp, 1024);
            error_log("SMTP Response Body: " . trim($response));
            
            if (strpos($response, '250') === false) {
                error_log("Email sending failed at body submission");
                fclose($smtp);
                return false;
            }
            
            // QUIT
            fputs($smtp, "QUIT\r\n");
            fclose($smtp);
            
            error_log("Email sent successfully via SMTP");
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email verification template
     * 
     * @param string $name
     * @param string $verificationLink
     * @param string $token
     * @return string HTML email body
     */
    private function getVerificationEmailTemplate($name, $verificationLink, $token) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #08415c; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .button { display: inline-block; background-color: #08415c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #333; color: white; text-align: center; padding: 10px; font-size: 12px; border-radius: 0 0 5px 5px; }
                .token-code { background-color: #e8e8e8; padding: 10px; text-align: center; font-family: monospace; font-size: 14px; margin: 15px 0; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Email Verification</h2>
                    <p>MinC - Automotive Parts</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    
                    <p>Thank you for registering with MinC! To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                    
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($verificationLink) . "' class='button'>Verify Email Address</a>
                    </p>
                    
                    <p>Or, if the button doesn't work, copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background-color: #f0f0f0; padding: 10px; border-radius: 3px;'>
                        " . htmlspecialchars($verificationLink) . "
                    </p>
                    
                    <p>Your verification code: </p>
                    <div class='token-code'>" . htmlspecialchars(substr($token, 0, 8)) . "...</div>
                    
                    <p><strong>Important:</strong> This verification link will expire in 24 hours. If you didn't register for this account, please ignore this email.</p>
                    
                    <p>Best regards,<br>
                    <strong>MinC Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " MinC - Automotive Parts. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get OTP verification template.
     *
     * @param string $name
     * @param string $otpCode
     * @param int $expiryMinutes
     * @return string
     */
    private function getOtpVerificationTemplate($name, $otpCode, $expiryMinutes) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #08415c; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .otp-box { background-color: #ffffff; border: 1px solid #d1d5db; border-radius: 8px; text-align: center; font-size: 28px; letter-spacing: 10px; font-weight: 700; color: #08415c; padding: 14px; margin: 20px 0; }
                .footer { background-color: #333; color: white; text-align: center; padding: 10px; font-size: 12px; border-radius: 0 0 5px 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Email Verification Code</h2>
                    <p>MinC - Automotive Parts</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>Use this 6-digit code to continue your registration:</p>
                    <div class='otp-box'>" . htmlspecialchars($otpCode) . "</div>
                    <p>This code will expire in <strong>" . (int)$expiryMinutes . " minutes</strong>.</p>
                    <p>If you did not request this code, you can ignore this email.</p>
                    <p>Best regards,<br><strong>MinC Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " MinC - Automotive Parts. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get welcome email template
     * 
     * @param string $name
     * @return string HTML email body
     */
    private function getWelcomeEmailTemplate($name) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #08415c; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .footer { background-color: #333; color: white; text-align: center; padding: 10px; font-size: 12px; border-radius: 0 0 5px 5px; }
                .feature { margin: 15px 0; padding: 10px; background-color: #fff; border-left: 4px solid #08415c; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Welcome!</h2>
                    <p>MinC - Automotive Parts</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    
                    <p>Your email has been verified successfully! Your account is now active and ready to use.</p>
                    
                    <p>You can now:</p>
                    <div class='feature'>
                        ✓ Browse our complete catalog of automotive parts
                    </div>
                    <div class='feature'>
                        ✓ Add products to your shopping cart
                    </div>
                    <div class='feature'>
                        ✓ Place orders and track shipments
                    </div>
                    <div class='feature'>
                        ✓ Save your preferences and addresses
                    </div>
                    
                    <p>If you have any questions or need assistance, feel free to contact our customer support team at MinC@gmail.com</p>
                    
                    <p>Happy shopping!<br>
                    <strong>MinC Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " MinC - Automotive Parts. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get password reset email template
     * 
     * @param string $name
     * @param string $resetLink
     * @return string HTML email body
     */
    private function getPasswordResetTemplate($name, $resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #08415c; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                .button { display: inline-block; background-color: #08415c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #333; color: white; text-align: center; padding: 10px; font-size: 12px; border-radius: 0 0 5px 5px; }
                .warning { background-color: #ffe6e6; padding: 10px; border-radius: 3px; border-left: 4px solid #ff0000; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Reset Request</h2>
                    <p>MinC - Automotive Parts</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    
                    <p>We received a request to reset the password for your MinC account. Click the button below to create a new password:</p>
                    
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($resetLink) . "' class='button'>Reset Password</a>
                    </p>
                    
                    <p>Or copy and paste this link in your browser:</p>
                    <p style='word-break: break-all; background-color: #f0f0f0; padding: 10px; border-radius: 3px;'>
                        " . htmlspecialchars($resetLink) . "
                    </p>
                    
                    <p><strong>This link will expire in 24 hours.</strong></p>
                    
                    <div class='warning'>
                        <strong>⚠ Security Warning:</strong> If you didn't request this password reset, please ignore this email. Your account is still secure.
                    </div>
                    
                    <p>Best regards,<br>
                    <strong>MinC Team</strong></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " MinC - Automotive Parts. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>
