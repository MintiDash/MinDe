<?php
/**
 * Logout Handler
 * Path: C:\xampp\htdocs\MinC_Project\backend\logout.php
 * Handles user logout and session destruction
 */

session_start();

// Include database connection for audit trail
require_once __DIR__ . '/../database/connect_database.php';

// Function to log audit trail
function logAuditTrail($pdo, $userId, $username, $action, $entityType, $entityId, $oldValue = null, $newValue = null, $changeReason = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_trail 
            (user_id, session_username, action, entity_type, entity_id, old_value, new_value, change_reason, ip_address, user_agent, system_id) 
            VALUES 
            (:user_id, :session_username, :action, :entity_type, :entity_id, :old_value, :new_value, :change_reason, :ip_address, :user_agent, :system_id)
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':session_username' => $username,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':old_value' => $oldValue ? json_encode($oldValue) : null,
            ':new_value' => $newValue ? json_encode($newValue) : null,
            ':change_reason' => $changeReason,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':system_id' => 'minc_system'
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Audit trail error: " . $e->getMessage());
        return false;
    }
}

try {
    $isAjaxRequest =
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    // Log logout if user is logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        logAuditTrail(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['username'],
            'logout',
            'user',
            $_SESSION['user_id'],
            [
                'email' => $_SESSION['email'] ?? '',
                'logout_time' => date('Y-m-d H:i:s')
            ],
            null,
            'User logged out'
        );
    }
    
    // Store success message before destroying session
    $logout_message = 'You have been successfully logged out.';
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Start new session for the message
    session_start();
    $_SESSION['success_message'] = $logout_message;

    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        exit();
    }

    // Redirect to login page
    header('Location: ../index.php');
    exit();
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    
    // Even on error, try to destroy session and redirect
    session_unset();
    session_destroy();
    
    session_start();
    $_SESSION['error_message'] = 'An error occurred during logout, but you have been logged out.';

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Logout encountered an issue.'
        ]);
        exit();
    }

    header('Location: ../index.php');
    exit();
}
?>
