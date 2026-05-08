<?php
// Authentication check
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
include_once '../../database/connect_database.php';

try {
    $notification_query = "
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE recipient_id = :user_id AND is_read = 0
    ";
    
    $stmt = $pdo->prepare($notification_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unread_count = (int)($result['unread_count'] ?? 0);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => $unread_count
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching notification count: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>