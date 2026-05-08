<?php
/**
 * Chat System Database Setup
 * Run this script once to create the chat_messages table if it doesn't exist
 * Access via: http://localhost/pages/MinC_Project/setup/setup_chat.php
 */

require_once '../database/connect_database.php';

try {
    // Check if table exists
    $checkQuery = "SELECT 1 FROM information_schema.TABLES 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'chat_messages'";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $message = "✓ Chat messages table already exists!";
        $status = "success";
    } else {
        // Create the table
        $createQuery = "CREATE TABLE `chat_messages` (
            `message_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
            `sender_name` varchar(255) NOT NULL,
            `sender_email` varchar(255) DEFAULT NULL,
            `sender_type` enum('customer','admin') NOT NULL DEFAULT 'customer',
            `message_content` longtext NOT NULL,
            `is_read` tinyint(1) DEFAULT 0,
            `read_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `session_id` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`message_id`),
            KEY `sender_id` (`sender_id`),
            KEY `sender_type` (`sender_type`),
            KEY `created_at` (`created_at`),
            KEY `is_read` (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createQuery);
        
        $message = "✓ Chat messages table created successfully!";
        $status = "success";
    }
    
} catch (Exception $e) {
    $message = "✗ Error: " . $e->getMessage();
    $status = "error";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat System Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-slate-900 to-slate-800 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-xl p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 mx-auto bg-gradient-to-br from-[#08415c] to-[#0a5273] rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-comment-dots text-2xl text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Chat System Setup</h1>
            </div>
            
            <div class="mb-6 p-4 rounded-lg <?php echo $status === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                <div class="flex items-start gap-3">
                    <i class="fas <?php echo $status === 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600'; ?> text-xl mt-0.5"></i>
                    <div>
                        <p class="font-semibold <?php echo $status === 'success' ? 'text-green-900' : 'text-red-900'; ?>">
                            <?php echo $message; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <?php if ($status === 'success'): ?>
            <div class="space-y-3 mb-6">
                <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                    <div>
                        <p class="text-sm text-blue-900">
                            <strong>Chat System Activated!</strong><br>
                            Customers can now chat via the bubble on the home page.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-2 text-sm text-gray-600 mb-6">
                <h3 class="font-semibold text-gray-900">Next Steps:</h3>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Go to <strong>Home Page</strong> - Chat bubble appears at bottom-right</li>
                    <li>Go to <strong>Dashboard</strong> → <strong>Customer Messages</strong> to respond</li>
                    <li>Customers can expand chat to full-screen modal</li>
                </ul>
            </div>
            
            <div class="flex gap-2">
                <a href="../../index.php" class="flex-1 bg-[#08415c] text-white py-2 px-4 rounded-lg hover:bg-[#0a5273] transition-colors text-center font-semibold">
                    <i class="fas fa-home mr-2"></i> Home Page
                </a>
                <a href="../app/frontend/app.php" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors text-center font-semibold">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="mt-6 text-center text-gray-400 text-sm">
            <p>MinC Auto Supply | Chat System v1.0</p>
        </div>
    </div>
</body>
</html>
