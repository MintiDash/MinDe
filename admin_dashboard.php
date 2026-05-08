<?php
/**
 * Admin Dashboard Redirect
 * This page redirects to the correct admin dashboard
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Check if user has admin access (user_level_id 1, 2, or 3)
if (!isset($_SESSION['user_level_id']) || $_SESSION['user_level_id'] > 3) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Redirect to the actual dashboard
header('Location: app/frontend/dashboard.php');
exit;
?>
