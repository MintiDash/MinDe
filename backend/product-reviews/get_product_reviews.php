<?php

header('Content-Type: application/json');

require_once '../../database/connect_database.php';
require_once 'review_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    if ($productId <= 0) {
        throw new Exception('Product ID is required.');
    }

    $productStmt = $pdo->prepare("
        SELECT product_id
        FROM products
        WHERE product_id = :product_id
          AND status = 'active'
        LIMIT 1
    ");
    $productStmt->execute([':product_id' => $productId]);
    if (!$productStmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Product not found.');
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $canManageAllReviews = in_array((int)($_SESSION['user_level_id'] ?? 0), [1, 2], true);
    $payload = getProductReviewsPayload($pdo, $productId, $currentUserId, $canManageAllReviews);

    echo json_encode([
        'success' => true,
        'product_id' => $productId,
        'summary' => $payload['summary'],
        'reviews' => $payload['reviews'],
        'current_user_review' => $payload['current_user_review'],
        'is_logged_in' => $payload['is_logged_in'],
        'can_manage_all_reviews' => $payload['can_manage_all_reviews'],
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
