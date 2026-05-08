<?php
/**
 * Get User Data Backend
 * File: C:\xampp\htdocs\MinC_Project\backend\user-management\get_user.php
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
    require_once '../../database/connect_database.php';
    require_once '../auth.php';

    // Validate session WITHOUT redirect (pass false)
    $validation = validateSession(false);
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Session invalid: ' . ($validation['reason'] ?? 'unknown')
        ]);
        exit;
    }

    // Check if user has permission
    if (!isManagementLevel()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Access denied. Management level required.'
        ]);
        exit;
    }

    // Get user ID from GET parameter
    $user_id = intval($_GET['id'] ?? 0);

    if ($user_id === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid user ID provided'
        ]);
        exit;
    }

    // Fetch user data
    $query = "
        SELECT 
            user_id,
            fname,
            lname,
            email,
            username,
            contact_num,
            user_level_id,
            user_status
        FROM users
        WHERE user_id = :user_id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true, 
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'User not found in database'
        ]);
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Database error in get_user.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("Error in get_user.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// Clean and flush output buffer
ob_end_flush();
