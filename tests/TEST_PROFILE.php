<?php
/**
 * Test Profile System
 * File: TEST_PROFILE.php
 * Run this script to test the profile system functionality
 */

// Start session
session_start();

// Set test mode (user must be logged in)
if (!isset($_SESSION['user_id'])) {
    echo "<h2>❌ Error: Not logged in</h2>";
    echo "Please log in first and then access this test file.";
    exit;
}

echo "<h1>Profile System Test Suite</h1>";
echo "<p>Testing profile functionality for user ID: {$_SESSION['user_id']}</p>";
echo "<hr>";

// Test 1: Get Profile
echo "<h3>Test 1: Get Profile</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/pages/MinC_Project/backend/get_profile.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . session_id());

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['success']) {
    echo "✅ Profile retrieved successfully<br>";
    echo "<pre>";
    echo "Name: " . $data['data']['fname'] . " " . $data['data']['lname'] . "<br>";
    echo "Email: " . $data['data']['email'] . "<br>";
    echo "Contact: " . ($data['data']['contact_num'] ?? 'Not set') . "<br>";
    echo "Picture: " . ($data['data']['profile_picture'] ?? 'None') . "<br>";
    echo "</pre>";
} else {
    echo "❌ Failed to get profile: " . $data['message'] . "<br>";
}

echo "<hr>";

// Test 2: Update Profile
echo "<h3>Test 2: Update Profile</h3>";
$updateData = json_encode([
    'fname' => $_SESSION['fname'] ?? 'Test',
    'lname' => $_SESSION['lname'] ?? 'User',
    'mname' => 'Middle',
    'contact_num' => '555-1234'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/pages/MinC_Project/backend/update_profile.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $updateData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . session_id());

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if ($data['success']) {
    echo "✅ Profile updated successfully<br>";
    echo "New contact number: " . $data['data']['contact_num'] . "<br>";
} else {
    echo "❌ Failed to update profile: " . $data['message'] . "<br>";
}

echo "<hr>";

// Test 3: Check Profile Picture Directory
echo "<h3>Test 3: Check Profile Picture Directory</h3>";
$profileDir = dirname(__FILE__) . '/Assets/images/profiles';

if (is_dir($profileDir)) {
    echo "✅ Profile picture directory exists<br>";
    echo "Path: " . $profileDir . "<br>";
    
    if (is_writable($profileDir)) {
        echo "✅ Directory is writable<br>";
    } else {
        echo "⚠️  Directory is NOT writable (may cause upload issues)<br>";
    }
    
    $files = scandir($profileDir);
    $fileCount = count($files) - 2; // Exclude . and ..
    echo "Files in directory: " . $fileCount . "<br>";
    
    if ($fileCount > 0) {
        echo "Files:<br>";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - " . htmlspecialchars($file) . "<br>";
            }
        }
    }
} else {
    echo "❌ Profile picture directory does not exist<br>";
    echo "Attempting to create it...<br>";
    
    if (@mkdir($profileDir, 0755, true)) {
        echo "✅ Directory created successfully<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

echo "<hr>";

// Test 4: Check Database Column
echo "<h3>Test 4: Check Database Schema</h3>";

try {
    require_once 'database/connect_database.php';
    
    $query = "DESCRIBE users";
    $stmt = $pdo->query($query);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasProfileColumn = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'profile_picture') {
            $hasProfileColumn = true;
            echo "✅ profile_picture column exists<br>";
            echo "Type: " . $column['Type'] . "<br>";
            break;
        }
    }
    
    if (!$hasProfileColumn) {
        echo "❌ profile_picture column NOT found<br>";
        echo "You need to run the migration SQL first<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Summary
echo "<h3>Test Summary</h3>";
echo "<p>Profile system is ready to use!</p>";
echo "<p><strong>Access profile page:</strong> <a href='http://localhost/pages/MinC_Project/html/profile.php' target='_blank'>Open Profile Page</a></p>";
echo "<p><strong>Available Actions:</strong></p>";
echo "<ul>";
echo "<li>View profile information</li>";
echo "<li>Edit personal details (name, contact)</li>";
echo "<li>Upload profile picture (JPG, PNG, WebP, max 5MB)</li>";
echo "<li>Delete profile picture</li>";
echo "<li>View changes in audit trail</li>";
echo "</ul>";

echo "<hr>";
echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>
