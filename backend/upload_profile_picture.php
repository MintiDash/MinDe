<?php
/**
 * Upload Profile Picture Backend
 * Handles profile picture upload with validation and optimization
 * File: backend/upload_profile_picture.php
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
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER address");
        } catch (Exception $schemaError) {
            echo json_encode([
                'success' => false,
                'message' => 'Database schema mismatch: profile picture column is missing'
            ]);
            exit;
        }
    }

    // Check if file was uploaded
    if (!isset($_FILES['profile_picture'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'No file uploaded'
        ]);
        exit;
    }

    $file = $_FILES['profile_picture'];

    // Validate file error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        echo json_encode([
            'success' => false, 
            'message' => $errorMessages[$file['error']] ?? 'Unknown upload error'
        ]);
        exit;
    }

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        echo json_encode([
            'success' => false, 
            'message' => 'File size exceeds maximum of 5MB'
        ]);
        exit;
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Only JPG, PNG, and WebP images are allowed'
        ]);
        exit;
    }

    // Create upload directory path
    $uploadDir = '../Assets/images/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $filename;

    // Get current profile picture for deletion
    $currentQuery = "SELECT profile_picture FROM users WHERE user_id = :user_id";
    $currentStmt = $pdo->prepare($currentQuery);
    $currentStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $currentStmt->execute();
    $currentUser = $currentStmt->fetch(PDO::FETCH_ASSOC);

    // Delete old profile picture if exists
    if ($currentUser && $currentUser['profile_picture']) {
        $oldPath = $uploadDir . $currentUser['profile_picture'];
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to save uploaded file'
        ]);
        exit;
    }

    // Update database
    $updateQuery = "UPDATE users SET profile_picture = :profile_picture WHERE user_id = :user_id";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute([
        ':profile_picture' => $filename,
        ':user_id' => $user_id
    ]);

    // Log audit trail
    try {
        $auditQuery = "INSERT INTO audit_trail (user_id, session_username, action, entity_type, entity_id, change_reason, ip_address, user_agent) 
                       VALUES (:user_id, :session_username, :action, :entity_type, :entity_id, :change_reason, :ip_address, :user_agent)";
        
        $auditStmt = $pdo->prepare($auditQuery);
        $auditStmt->execute([
            ':user_id' => $user_id,
            ':session_username' => $_SESSION['fname'] . ' ' . $_SESSION['lname'],
            ':action' => 'upload_profile_picture',
            ':entity_type' => 'user',
            ':entity_id' => $user_id,
            ':change_reason' => 'User uploaded profile picture',
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $auditError) {
        error_log('Audit log failed in upload_profile_picture.php: ' . $auditError->getMessage());
    }

    $projectBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/upload_profile_picture.php', 2)), '/');
    if ($projectBase === '') {
        $projectBase = '/';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'data' => [
            'filename' => $filename,
            'picture_url' => rtrim($projectBase, '/') . '/Assets/images/profiles/' . $filename
        ]
    ]);

} catch (Exception $e) {
    error_log('Error in upload_profile_picture.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while uploading profile picture'
    ]);
}

// Flush output buffer
ob_end_flush();
?>
