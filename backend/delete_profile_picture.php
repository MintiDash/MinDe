<?php
/**
 * Delete Profile Picture Backend
 * Removes user's profile picture
 * File: backend/delete_profile_picture.php
 */

// Prevent any output before JSON
ob_start();

// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous output
ob_clean();

// Set JSON header immediately
header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(0);

try {
    // Include files
    require_once '../database/connect_database.php';
    require_once 'auth.php';

    // Validate session
    $validation = validateSession(false);
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Session invalid: ' . ($validation['reason'] ?? 'unknown')
        ]);
        exit;
    }

    // Get user ID from session
    $user_id = $_SESSION['user_id'] ?? 0;

    if (!$user_id) {
        echo json_encode([
            'success' => false, 
            'message' => 'User ID not found in session'
        ]);
        exit;
    }

    // Ensure profile_picture column exists
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('profile_picture', $columns, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'No profile picture to delete'
        ]);
        exit;
    }

    // Get current profile picture
    $query = "SELECT profile_picture FROM users WHERE user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false, 
            'message' => 'User not found'
        ]);
        exit;
    }

    if (!$user['profile_picture']) {
        echo json_encode([
            'success' => false, 
            'message' => 'No profile picture to delete'
        ]);
        exit;
    }

    // Delete file from filesystem
    $uploadDir = '../Assets/images/profiles/';
    $filePath = $uploadDir . $user['profile_picture'];

    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Update database to remove profile picture reference
    $updateQuery = "UPDATE users SET profile_picture = NULL WHERE user_id = :user_id";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $updateStmt->execute();

    // Log audit trail
    try {
        $auditQuery = "INSERT INTO audit_trail (user_id, session_username, action, entity_type, entity_id, change_reason, ip_address, user_agent) 
                       VALUES (:user_id, :session_username, :action, :entity_type, :entity_id, :change_reason, :ip_address, :user_agent)";
        
        $auditStmt = $pdo->prepare($auditQuery);
        $auditStmt->execute([
            ':user_id' => $user_id,
            ':session_username' => $_SESSION['fname'] . ' ' . $_SESSION['lname'],
            ':action' => 'delete_profile_picture',
            ':entity_type' => 'user',
            ':entity_id' => $user_id,
            ':change_reason' => 'User deleted profile picture',
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $auditError) {
        error_log('Audit log failed in delete_profile_picture.php: ' . $auditError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture deleted successfully'
    ]);

} catch (Exception $e) {
    error_log('Error in delete_profile_picture.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while deleting profile picture'
    ]);
}

// Flush output buffer
ob_end_flush();
?>
