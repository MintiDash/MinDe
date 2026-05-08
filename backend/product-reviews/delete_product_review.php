<?php

header('Content-Type: application/json');

require_once '../../database/connect_database.php';
require_once '../auth.php';
require_once 'review_helpers.php';

$validation = validateSession(false);
if (!$validation['valid']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to manage reviews.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.',
    ]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new Exception('Invalid request payload.');
    }

    $reviewId = (int)($input['review_id'] ?? 0);
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $canManageAllReviews = in_array((int)($_SESSION['user_level_id'] ?? 0), [1, 2], true);

    if ($reviewId <= 0) {
        throw new Exception('Review ID is required.');
    }

    ensureProductReviewsTable($pdo);

    $reviewStmt = $pdo->prepare("
        SELECT review_id, product_id, user_id
        FROM product_reviews
        WHERE review_id = :review_id
        LIMIT 1
    ");
    $reviewStmt->execute([':review_id' => $reviewId]);
    $review = $reviewStmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        throw new Exception('Review not found.');
    }

    $reviewOwnerId = (int)$review['user_id'];
    $productId = (int)$review['product_id'];

    if (!$canManageAllReviews && $reviewOwnerId !== $currentUserId) {
        http_response_code(403);
        throw new Exception('You are not allowed to delete this review.');
    }

    $deleteStmt = $pdo->prepare("
        DELETE FROM product_reviews
        WHERE review_id = :review_id
        LIMIT 1
    ");
    $deleteStmt->execute([':review_id' => $reviewId]);

    $payload = getProductReviewsPayload($pdo, $productId, $currentUserId, $canManageAllReviews);

    echo json_encode([
        'success' => true,
        'message' => $reviewOwnerId === $currentUserId
            ? 'Your review has been deleted.'
            : 'Review deleted successfully.',
        'summary' => $payload['summary'],
        'reviews' => $payload['reviews'],
        'current_user_review' => $payload['current_user_review'],
        'is_logged_in' => $payload['is_logged_in'],
        'can_manage_all_reviews' => $payload['can_manage_all_reviews'],
    ]);
} catch (Exception $e) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
