<?php
/**
 * Email Verification Helper
 * Path: library/EmailVerificationHelper.php
 * 
 * Helper functions for email verification operations
 */

class EmailVerificationHelper {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if user's email is verified
     * 
     * @param int $userId
     * @return bool
     */
    public function isEmailVerified($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT is_email_verified FROM users WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $user ? (bool)$user['is_email_verified'] : false;
        } catch (PDOException $e) {
            error_log("Email verification check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's verification status and details
     * 
     * @param int $userId
     * @return array User verification details or empty array
     */
    public function getUserVerificationStatus($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    user_id,
                    email,
                    is_email_verified,
                    email_verified_at,
                    created_at
                FROM users 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
        } catch (PDOException $e) {
            error_log("Get verification status error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending verification tokens for a user
     * 
     * @param int $userId
     * @return array Pending tokens
     */
    public function getPendingTokens($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    token_id,
                    token,
                    email,
                    created_at,
                    expires_at,
                    is_used
                FROM email_verification_tokens 
                WHERE user_id = :user_id 
                AND is_used = 0 
                AND expires_at > NOW()
                ORDER BY created_at DESC
            ");
            $stmt->execute([':user_id' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get pending tokens error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Revoke all pending tokens for a user
     * 
     * @param int $userId
     * @return bool
     */
    public function revokePendingTokens($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE email_verification_tokens 
                SET expires_at = NOW() 
                WHERE user_id = :user_id 
                AND is_used = 0 
                AND expires_at > NOW()
            ");
            
            return $stmt->execute([':user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Revoke tokens error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a token is valid and not expired
     * 
     * @param string $token
     * @return array Token details or empty array if invalid
     */
    public function validateToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    token_id,
                    user_id,
                    email,
                    created_at,
                    expires_at,
                    is_used
                FROM email_verification_tokens 
                WHERE token = :token 
                LIMIT 1
            ");
            $stmt->execute([':token' => $token]);
            $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenRecord) {
                return [];
            }
            
            // Check if expired
            if (strtotime($tokenRecord['expires_at']) < time()) {
                return [];
            }
            
            // Check if already used
            if ($tokenRecord['is_used']) {
                return [];
            }
            
            return $tokenRecord;
        } catch (PDOException $e) {
            error_log("Token validation error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get verification statistics
     * 
     * @return array Statistics data
     */
    public function getVerificationStatistics() {
        try {
            $stats = [];
            
            // Total users
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users WHERE user_level_id = 4");
            $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Verified users
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users WHERE user_level_id = 4 AND is_email_verified = 1");
            $stats['verified_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Unverified users
            $stats['unverified_users'] = $stats['total_users'] - $stats['verified_users'];
            
            // Verification rate
            $stats['verification_rate'] = $stats['total_users'] > 0 
                ? round(($stats['verified_users'] / $stats['total_users']) * 100, 2) 
                : 0;
            
            // Pending tokens
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM email_verification_tokens WHERE is_used = 0 AND expires_at > NOW()");
            $stats['pending_tokens'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Used tokens
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM email_verification_tokens WHERE is_used = 1");
            $stats['used_tokens'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Expired tokens
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM email_verification_tokens WHERE expires_at <= NOW()");
            $stats['expired_tokens'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Verification statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get list of unverified users
     * 
     * @param int $limit Number of results to return
     * @param int $offset Offset for pagination
     * @return array List of unverified users
     */
    public function getUnverifiedUsers($limit = 50, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    user_id,
                    fname,
                    lname,
                    email,
                    created_at,
                    email_verified_at,
                    (SELECT COUNT(*) FROM email_verification_tokens 
                     WHERE user_id = users.user_id AND is_used = 0 AND expires_at > NOW()) as pending_tokens
                FROM users 
                WHERE user_level_id = 4 
                AND is_email_verified = 0
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get unverified users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Force verify a user's email (admin function)
     * 
     * @param int $userId
     * @return bool
     */
    public function forceVerifyEmail($userId) {
        try {
            $this->pdo->beginTransaction();
            
            // Mark email as verified
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET is_email_verified = 1, email_verified_at = NOW()
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            
            // Mark all tokens as used
            $stmt = $this->pdo->prepare("
                UPDATE email_verification_tokens 
                SET is_used = 1, verified_at = NOW()
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $userId]);
            
            $this->pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Force verify email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete expired tokens (cleanup)
     * 
     * @return int Number of tokens deleted
     */
    public function cleanupExpiredTokens() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM email_verification_tokens 
                WHERE expires_at < NOW() AND is_used = 0
            ");
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Cleanup tokens error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send verification email to user
     * 
     * @param int $userId
     * @return bool
     */
    public function sendVerificationEmail($userId) {
        try {
            // Get user details
            $userStmt = $this->pdo->prepare("
                SELECT fname, lname, email FROM users WHERE user_id = :user_id
            ");
            $userStmt->execute([':user_id' => $userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            // Get active token
            $tokenStmt = $this->pdo->prepare("
                SELECT token FROM email_verification_tokens 
                WHERE user_id = :user_id 
                AND is_used = 0 
                AND expires_at > NOW()
                LIMIT 1
            ");
            $tokenStmt->execute([':user_id' => $userId]);
            $tokenRecord = $tokenStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenRecord) {
                return false;
            }
            
            // Send email
            require_once __DIR__ . '/EmailService.php';
            $emailService = new EmailService();
            
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $verificationLink = $baseUrl . '/backend/verify_email.php?token=' . urlencode($tokenRecord['token']);
            
            return $emailService->sendVerificationEmail(
                $user['email'],
                $user['fname'] . ' ' . $user['lname'],
                $verificationLink,
                $tokenRecord['token']
            );
        } catch (PDOException $e) {
            error_log("Send verification email error: " . $e->getMessage());
            return false;
        }
    }
}
?>
