<?php
/**
 * Token Generator Utility
 * Path: library/TokenGenerator.php
 * 
 * Generates secure tokens for email verification and password reset
 */

class TokenGenerator {
    
    /**
     * Generate a cryptographically secure token
     * 
     * @param int $length Token length in bytes
     * @return string Generated token
     */
    public static function generateToken($length = 32) {
        try {
            // Use random_bytes for cryptographically secure randomness
            if (function_exists('random_bytes')) {
                $bytes = random_bytes($length);
                return bin2hex($bytes);
            } else {
                // Fallback for older PHP versions
                $bytes = openssl_random_pseudo_bytes($length, $strong);
                if ($strong) {
                    return bin2hex($bytes);
                }
            }
        } catch (Exception $e) {
            // Final fallback using mt_rand
            $token = '';
            $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            for ($i = 0; $i < ($length * 2); $i++) {
                $token .= $charset[mt_rand(0, strlen($charset) - 1)];
            }
            return $token;
        }
    }
    
    /**
     * Generate a token hash for secure storage
     * 
     * @param string $token The raw token
     * @return string Hashed token
     */
    public static function hashToken($token) {
        return hash('sha256', $token);
    }
    
    /**
     * Generate a readable verification code (6 digits)
     * 
     * @return string 6-digit code
     */
    public static function generateVerificationCode() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify a token against its hash
     * 
     * @param string $token The raw token
     * @param string $hash The stored hash
     * @return bool True if token matches hash
     */
    public static function verifyToken($token, $hash) {
        return hash_equals($hash, self::hashToken($token));
    }
}
?>
