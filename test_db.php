<?php
header('Content-Type: application/json');

require_once __DIR__ . '/database/connect_database.php';

try {
    // Test connection
    $stmt = $pdo->query('SELECT 1');
    $result = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection OK',
        'result' => $result
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>