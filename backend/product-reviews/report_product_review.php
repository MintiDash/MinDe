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
        'message' => 'Please login to report a review.',
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
    $reportReason = trim((string)($input['report_reason'] ?? ''));
    $reportDetails = trim((string)($input['report_details'] ?? ''));
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $canManageAllReviews = in_array((int)($_SESSION['user_level_id'] ?? 0), [1, 2], true);
    $allowedReasons = ['spam', 'abuse', 'false_information', 'off_topic', 'other'];

    if ($reviewId <= 0) {
        throw new Exception('Review ID is required.');
    }

    if (!in_array($reportReason, $allowedReasons, true)) {
        throw new Exception('Please select a valid report reason.');
    }

    if (strlen($reportDetails) > 500) {
        throw new Exception('Report details must be 500 characters or fewer.');
    }

    if ($canManageAllReviews) {
        throw new Exception('Admins can moderate reviews directly without reporting them.');
    }

    ensureProductReviewReportsTable($pdo);

    $reviewStmt = $pdo->prepare("
        SELECT pr.review_id, pr.user_id, pr.product_id
        FROM product_reviews pr
        INNER JOIN products p ON p.product_id = pr.product_id
        WHERE pr.review_id = :review_id
          AND p.status = 'active'
        LIMIT 1
    ");
    $reviewStmt->execute([':review_id' => $reviewId]);
    $review = $reviewStmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        throw new Exception('Review not found.');
    }

    if ((int)$review['user_id'] === $currentUserId) {
        throw new Exception('You cannot report your own review.');
    }

    $existingReportStmt = $pdo->prepare("
        SELECT report_id
        FROM product_review_reports
        WHERE review_id = :review_id
          AND reporter_user_id = :reporter_user_id
        LIMIT 1
    ");
    $existingReportStmt->execute([
        ':review_id' => $reviewId,
        ':reporter_user_id' => $currentUserId,
    ]);
    $existingReport = $existingReportStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingReport) {
        $reportStmt = $pdo->prepare("
            UPDATE product_review_reports
            SET report_reason = :report_reason,
                report_details = :report_details,
                report_status = 'open',
                updated_at = NOW()
            WHERE report_id = :report_id
            LIMIT 1
        ");
        $reportStmt->execute([
            ':report_reason' => $reportReason,
            ':report_details' => $reportDetails !== '' ? $reportDetails : null,
            ':report_id' => (int)$existingReport['report_id'],
        ]);

        $message = 'Your report has been updated.';
    } else {
        $reportStmt = $pdo->prepare("
            INSERT INTO product_review_reports (
                review_id,
                reporter_user_id,
                report_reason,
                report_details,
                report_status,
                created_at,
                updated_at
            ) VALUES (
                :review_id,
                :reporter_user_id,
                :report_reason,
                :report_details,
                'open',
                NOW(),
                NOW()
            )
        ");
        $reportStmt->execute([
            ':review_id' => $reviewId,
            ':reporter_user_id' => $currentUserId,
            ':report_reason' => $reportReason,
            ':report_details' => $reportDetails !== '' ? $reportDetails : null,
        ]);

        $message = 'Review reported successfully.';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'review_id' => $reviewId,
        'product_id' => (int)$review['product_id'],
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
