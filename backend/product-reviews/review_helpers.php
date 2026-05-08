<?php

function productReviewsTableExists(PDO $pdo, ?bool $setValue = null): bool
{
    static $tableExists = null;

    if ($setValue !== null) {
        $tableExists = $setValue;
        return $tableExists;
    }

    if ($tableExists !== null) {
        return $tableExists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'product_reviews'");
        $tableExists = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $tableExists = false;
    }

    return $tableExists;
}

function ensureProductReviewsTable(PDO $pdo, bool $throwOnFailure = true): bool
{
    if (productReviewsTableExists($pdo)) {
        return true;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_reviews (
                review_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT(11) NOT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                rating TINYINT(1) UNSIGNED NOT NULL,
                review_title VARCHAR(255) DEFAULT NULL,
                review_text TEXT NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (review_id),
                UNIQUE KEY uk_product_reviews_product_user (product_id, user_id),
                KEY idx_product_reviews_product_created (product_id, created_at),
                KEY idx_product_reviews_user (user_id),
                CONSTRAINT fk_product_reviews_product
                    FOREIGN KEY (product_id) REFERENCES products(product_id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_product_reviews_user
                    FOREIGN KEY (user_id) REFERENCES users(user_id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        productReviewsTableExists($pdo, true);
        return true;
    } catch (Throwable $e) {
        productReviewsTableExists($pdo, false);

        if ($throwOnFailure) {
            throw $e;
        }

        return false;
    }
}

function productReviewReportsTableExists(PDO $pdo, ?bool $setValue = null): bool
{
    static $tableExists = null;

    if ($setValue !== null) {
        $tableExists = $setValue;
        return $tableExists;
    }

    if ($tableExists !== null) {
        return $tableExists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'product_review_reports'");
        $tableExists = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $tableExists = false;
    }

    return $tableExists;
}

function ensureProductReviewReportsTable(PDO $pdo, bool $throwOnFailure = true): bool
{
    if (productReviewReportsTableExists($pdo)) {
        return true;
    }

    if (!ensureProductReviewsTable($pdo, $throwOnFailure)) {
        return false;
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_review_reports (
                report_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                review_id BIGINT(20) UNSIGNED NOT NULL,
                reporter_user_id BIGINT(20) UNSIGNED NOT NULL,
                report_reason VARCHAR(100) NOT NULL,
                report_details VARCHAR(500) DEFAULT NULL,
                report_status ENUM('open', 'reviewed', 'dismissed') NOT NULL DEFAULT 'open',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (report_id),
                UNIQUE KEY uk_product_review_reports_review_user (review_id, reporter_user_id),
                KEY idx_product_review_reports_review (review_id),
                KEY idx_product_review_reports_status (report_status),
                KEY idx_product_review_reports_user (reporter_user_id),
                CONSTRAINT fk_product_review_reports_review
                    FOREIGN KEY (review_id) REFERENCES product_reviews(review_id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_product_review_reports_user
                    FOREIGN KEY (reporter_user_id) REFERENCES users(user_id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        productReviewReportsTableExists($pdo, true);
        return true;
    } catch (Throwable $e) {
        productReviewReportsTableExists($pdo, false);

        if ($throwOnFailure) {
            throw $e;
        }

        return false;
    }
}

function getDefaultProductReviewSummary(int $productId = 0): array
{
    return [
        'product_id' => $productId,
        'average_rating' => 0.0,
        'review_count' => 0,
        'rating_breakdown' => [
            '5' => 0,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0,
        ],
    ];
}

function getProductReviewSummary(PDO $pdo, int $productId): array
{
    $summary = getDefaultProductReviewSummary($productId);
    if ($productId <= 0) {
        return $summary;
    }

    if (!ensureProductReviewsTable($pdo, false)) {
        return $summary;
    }

    $stmt = $pdo->prepare("
        SELECT
            pr.product_id,
            COUNT(*) AS review_count,
            ROUND(AVG(pr.rating), 1) AS average_rating,
            SUM(CASE WHEN pr.rating = 5 THEN 1 ELSE 0 END) AS rating_5_count,
            SUM(CASE WHEN pr.rating = 4 THEN 1 ELSE 0 END) AS rating_4_count,
            SUM(CASE WHEN pr.rating = 3 THEN 1 ELSE 0 END) AS rating_3_count,
            SUM(CASE WHEN pr.rating = 2 THEN 1 ELSE 0 END) AS rating_2_count,
            SUM(CASE WHEN pr.rating = 1 THEN 1 ELSE 0 END) AS rating_1_count
        FROM product_reviews pr
        WHERE pr.product_id = :product_id
        GROUP BY pr.product_id
    ");
    $stmt->execute([':product_id' => $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return $summary;
    }

    return [
        'product_id' => (int)$row['product_id'],
        'average_rating' => (float)($row['average_rating'] ?? 0),
        'review_count' => (int)($row['review_count'] ?? 0),
        'rating_breakdown' => [
            '5' => (int)($row['rating_5_count'] ?? 0),
            '4' => (int)($row['rating_4_count'] ?? 0),
            '3' => (int)($row['rating_3_count'] ?? 0),
            '2' => (int)($row['rating_2_count'] ?? 0),
            '1' => (int)($row['rating_1_count'] ?? 0),
        ],
    ];
}

function getProductReviewSummaryMap(PDO $pdo, array $productIds): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    $productIds = array_values(array_filter($productIds, static function ($value) {
        return $value > 0;
    }));

    $summaryMap = [];
    foreach ($productIds as $productId) {
        $summaryMap[$productId] = getDefaultProductReviewSummary($productId);
    }

    if (empty($productIds)) {
        return $summaryMap;
    }

    if (!ensureProductReviewsTable($pdo, false)) {
        return $summaryMap;
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            pr.product_id,
            COUNT(*) AS review_count,
            ROUND(AVG(pr.rating), 1) AS average_rating
        FROM product_reviews pr
        WHERE pr.product_id IN ($placeholders)
        GROUP BY pr.product_id
    ");
    $stmt->execute($productIds);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productId = (int)$row['product_id'];
        $summaryMap[$productId]['average_rating'] = (float)($row['average_rating'] ?? 0);
        $summaryMap[$productId]['review_count'] = (int)($row['review_count'] ?? 0);
    }

    return $summaryMap;
}

function hasVerifiedPurchaseForProduct(PDO $pdo, int $userId, int $productId, string $email = ''): bool
{
    if ($userId <= 0 || $productId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM customers c
        INNER JOIN orders o ON o.customer_id = c.customer_id
        INNER JOIN order_items oi ON oi.order_id = o.order_id
        WHERE oi.product_id = :product_id
          AND o.order_status != 'cancelled'
          AND (
              c.user_id = :user_id
              OR (:email_present != '' AND c.email = :email_match)
          )
        LIMIT 1
    ");
    $stmt->execute([
        ':product_id' => $productId,
        ':user_id' => $userId,
        ':email_present' => $email,
        ':email_match' => $email,
    ]);

    return (bool)$stmt->fetchColumn();
}

function getProductReviewReportCounts(PDO $pdo, array $reviewIds): array
{
    $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
    $reviewIds = array_values(array_filter($reviewIds, static function ($value) {
        return $value > 0;
    }));

    $reportCountMap = [];
    foreach ($reviewIds as $reviewId) {
        $reportCountMap[$reviewId] = 0;
    }

    if (empty($reviewIds) || !ensureProductReviewReportsTable($pdo, false)) {
        return $reportCountMap;
    }

    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
    $stmt = $pdo->prepare("
        SELECT review_id, COUNT(*) AS report_count
        FROM product_review_reports
        WHERE review_id IN ($placeholders)
          AND report_status = 'open'
        GROUP BY review_id
    ");
    $stmt->execute($reviewIds);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reportCountMap[(int)$row['review_id']] = (int)($row['report_count'] ?? 0);
    }

    return $reportCountMap;
}

function getReportedReviewIdsByUser(PDO $pdo, array $reviewIds, int $currentUserId): array
{
    if ($currentUserId <= 0) {
        return [];
    }

    $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));
    $reviewIds = array_values(array_filter($reviewIds, static function ($value) {
        return $value > 0;
    }));

    if (empty($reviewIds) || !ensureProductReviewReportsTable($pdo, false)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
    $params = array_merge([$currentUserId], $reviewIds);
    $stmt = $pdo->prepare("
        SELECT review_id
        FROM product_review_reports
        WHERE reporter_user_id = ?
          AND review_id IN ($placeholders)
    ");
    $stmt->execute($params);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function getProductReviewsPayload(PDO $pdo, int $productId, int $currentUserId = 0, bool $canManageAllReviews = false): array
{
    if (!ensureProductReviewsTable($pdo, false)) {
        return [
            'summary' => getDefaultProductReviewSummary($productId),
            'reviews' => [],
            'current_user_review' => null,
            'is_logged_in' => $currentUserId > 0,
            'can_manage_all_reviews' => $canManageAllReviews,
        ];
    }

    $summary = getProductReviewSummary($pdo, $productId);

    $reviewsStmt = $pdo->prepare("
        SELECT
            pr.review_id,
            pr.product_id,
            pr.user_id,
            pr.rating,
            pr.review_title,
            pr.review_text,
            pr.created_at,
            pr.updated_at,
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(u.fname, ''), ' ', COALESCE(u.lname, ''))), ''),
                NULLIF(TRIM(COALESCE(u.username, '')), ''),
                NULLIF(TRIM(COALESCE(u.email, '')), ''),
                'Customer'
            ) AS reviewer_name,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM customers c
                    INNER JOIN orders o ON o.customer_id = c.customer_id
                    INNER JOIN order_items oi ON oi.order_id = o.order_id
                    WHERE oi.product_id = pr.product_id
                      AND o.order_status != 'cancelled'
                      AND (c.user_id = pr.user_id OR (c.user_id IS NULL AND c.email = u.email))
                    LIMIT 1
                ) THEN 1
                ELSE 0
            END AS is_verified_purchase,
            CASE
                WHEN pr.user_id = :current_user_id THEN 1
                ELSE 0
            END AS is_current_user_review
        FROM product_reviews pr
        INNER JOIN users u ON u.user_id = pr.user_id
        WHERE pr.product_id = :product_id
        ORDER BY is_current_user_review DESC, pr.updated_at DESC, pr.created_at DESC
    ");
    $reviewsStmt->execute([
        ':product_id' => $productId,
        ':current_user_id' => $currentUserId,
    ]);
    $reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);

    $reviewIds = array_map(static function ($review) {
        return (int)($review['review_id'] ?? 0);
    }, $reviews);
    $reportCountMap = getProductReviewReportCounts($pdo, $reviewIds);
    $reportedReviewIds = getReportedReviewIdsByUser($pdo, $reviewIds, $currentUserId);
    $reportedReviewLookup = array_fill_keys($reportedReviewIds, true);

    $currentUserReview = null;
    foreach ($reviews as &$review) {
        $review['review_id'] = (int)$review['review_id'];
        $review['product_id'] = (int)$review['product_id'];
        $review['user_id'] = (int)$review['user_id'];
        $review['rating'] = (int)$review['rating'];
        $review['is_verified_purchase'] = (bool)$review['is_verified_purchase'];
        $review['is_current_user_review'] = (bool)$review['is_current_user_review'];
        $review['report_count'] = (int)($reportCountMap[$review['review_id']] ?? 0);
        $review['is_reported_by_current_user'] = isset($reportedReviewLookup[$review['review_id']]);
        $review['can_edit'] = $review['is_current_user_review'] || $canManageAllReviews;
        $review['can_delete'] = $review['is_current_user_review'] || $canManageAllReviews;
        $review['can_report'] = $currentUserId > 0
            && !$review['is_current_user_review']
            && !$canManageAllReviews;

        if ($review['is_current_user_review']) {
            $currentUserReview = $review;
        }
    }
    unset($review);

    return [
        'summary' => $summary,
        'reviews' => $reviews,
        'current_user_review' => $currentUserReview,
        'is_logged_in' => $currentUserId > 0,
        'can_manage_all_reviews' => $canManageAllReviews,
    ];
}
