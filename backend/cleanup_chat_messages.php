<?php
/**
 * Chat Messages Cleanup Script
 * Removes messages with NULL or empty session_id values
 * These messages were created before the session_id fix and can't be properly displayed
 */

require_once '../database/connect_database.php';

try {
    // Delete messages with NULL or empty session_id
    $deleteQuery = "DELETE FROM chat_messages WHERE session_id IS NULL OR session_id = ''";
    $stmt = $pdo->prepare($deleteQuery);
    $stmt->execute();
    
    $rowsDeleted = $stmt->rowCount();
    
    echo json_encode([
        'status' => 'success',
        'message' => "Cleaned up $rowsDeleted messages with invalid session IDs",
        'rows_deleted' => $rowsDeleted
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
