<?php
/**
 * Admin Chat Panel
 * Allows owner/admin to view all customer messages and respond
 */

// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../database/connect_database.php';

// Check if user is authenticated and authorized
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=not_logged_in');
    exit;
}

// Get user from database to check permissions
try {
    $userQuery = "SELECT user_id, user_level_id FROM users WHERE user_id = :user_id LIMIT 1";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user is authorized (owner level 1 or IT staff level 5)
    if (!$user || ($user['user_level_id'] != 1 && $user['user_level_id'] != 5)) {
        header('Location: ../../index.php?error=unauthorized_access');
        exit;
    }
} catch (Exception $e) {
    header('Location: ../../index.php?error=database_error');
    exit;
}

// Get page info
$page_title = 'Customer Messages';
$current_page = 'chat-admin';

// Get current conversation
$current_session = isset($_GET['session_id']) && !empty($_GET['session_id']) ? trim($_GET['session_id']) : null;
$conversations = [];
$current_messages = [];
$error_message = null;

try {
    // Get all conversations - group only by session_id
    $convQuery = "SELECT 
                    session_id, 
                    MAX(sender_name) as sender_name, 
                    MAX(sender_email) as sender_email,
                    MAX(created_at) as last_message_time,
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN sender_type = 'customer' THEN 1 ELSE 0 END) as customer_messages,
                    SUM(CASE WHEN is_read = 0 AND sender_type = 'customer' THEN 1 ELSE 0 END) as unread_count
                  FROM chat_messages 
                  GROUP BY session_id
                  ORDER BY last_message_time DESC";
    
    $convStmt = $pdo->prepare($convQuery);
    $convStmt->execute();
    $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get messages for current conversation
    if ($current_session) {
        $msgQuery = "SELECT * FROM chat_messages 
                    WHERE session_id = :session_id 
                    ORDER BY created_at ASC";
        
        $msgStmt = $pdo->prepare($msgQuery);
        $msgStmt->execute([':session_id' => $current_session]);
        $current_messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read
        $readQuery = "UPDATE chat_messages 
                     SET is_read = 1, read_at = NOW() 
                     WHERE session_id = :session_id AND sender_type = 'customer' AND is_read = 0";
        
        $readStmt = $pdo->prepare($readQuery);
        $readStmt->execute([':session_id' => $current_session]);
    }
    
    // Get total unread count
    $unreadQuery = "SELECT COUNT(*) as unread_count FROM chat_messages 
                   WHERE sender_type = 'customer' AND is_read = 0";
    $unreadStmt = $pdo->prepare($unreadQuery);
    $unreadStmt->execute();
    $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $unreadResult['unread_count'] ?? 0;
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MinC Auto Supply</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-left-pane { width: 384px; }
        @media (max-width: 768px) {
            .chat-left-pane { width: 100%; }
            .chat-right-pane { display: none; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'app.php'; ?>
    
    <!-- Main Container -->
    <div class="ml-0 lg:ml-64 flex h-screen bg-gray-50" style="margin-left: 0;">
        <!-- LEFT PANE: Conversations List -->
        <div class="chat-left-pane border-r border-gray-200 bg-white flex flex-col overflow-hidden">
            <div class="px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Customer Messages</h1>
                        <p class="text-sm text-gray-500 mt-1">Manage and respond to customer inquiries</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-2xl font-bold text-[#08415c]"><?php echo count($conversations); ?></div>
                            <div class="text-xs text-gray-500">Conversations</div>
                        </div>
                        <?php if ($unread_count > 0): ?>
                        <div class="bg-red-100 border border-red-300 rounded-lg px-3 py-2">
                            <div class="text-lg font-bold text-red-600"><?php echo $unread_count; ?></div>
                            <div class="text-xs text-red-600">Unread</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content - Two Pane Layout -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Debug Info (temporary) -->
            <div style="display: none; position: fixed; top: 100px; right: 10px; background: #f0f0f0; padding: 10px; border: 1px solid #ccc; border-radius: 5px; z-index: 9999; font-size: 11px;">
                <div><strong>Current Session:</strong> <?php echo htmlspecialchars($current_session ?? 'NULL'); ?></div>
                <div><strong>Messages Count:</strong> <?php echo count($current_messages); ?></div>
                <div><strong>Total Conversations:</strong> <?php echo count($conversations); ?></div>
                <div style="margin-top: 5px; background: white; padding: 5px; max-height: 200px; overflow-y: auto;">
                    <strong>Session IDs:</strong><br>
                    <?php foreach ($conversations as $c): ?>
                        <div><?php echo htmlspecialchars($c['session_id']); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Error Alert (full width if present) -->
            <?php if ($error_message): ?>
            <div class="w-full bg-red-50 border border-red-200 p-4 m-4 rounded-lg absolute top-20 left-0 right-0 z-50">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl mt-1"></i>
                    <div>
                        <h3 class="font-semibold text-red-900">Error Loading Messages</h3>
                        <p class="text-red-700 text-sm mt-1"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Conversations List (Left Pane) -->
            <div class="w-full md:w-96 bg-white border-r border-gray-200 overflow-y-auto flex-shrink-0">
                <?php if (empty($conversations)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No messages yet</p>
                        <p class="text-sm text-gray-400 mt-2">Customer messages will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($conversations as $conv): 
                            $isSelected = ($current_session === $conv['session_id']);
                        ?>
                        <a href="?session_id=<?php echo urlencode($conv['session_id']); ?>" 
                           class="block px-4 py-4 hover:bg-gray-50 transition-colors cursor-pointer <?php echo $isSelected ? 'bg-blue-50 border-l-4 border-[#08415c]' : ''; ?>"
                           onclick="window.location.href='?session_id=<?php echo urlencode($conv['session_id']); ?>'; return false;">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900 truncate">
                                            <?php echo htmlspecialchars($conv['sender_name']); ?>
                                        </h3>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-red-500 text-white rounded-full text-xs font-bold flex-shrink-0">
                                            <?php echo intval($conv['unread_count']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($conv['sender_email']): ?>
                                    <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($conv['sender_email']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="fas fa-message text-gray-400 mr-1"></i>
                                        <?php echo intval($conv['customer_messages']); ?> message<?php echo $conv['customer_messages'] != 1 ? 's' : ''; ?>
                                    </p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs text-gray-500">
                                        <?php echo date('M d', strtotime($conv['last_message_time'])); ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Area (Right Pane) -->
            <div class="flex-1 flex flex-col bg-gray-50 overflow-hidden">
                <?php if ($current_session): 
                    // Get current conversation info
                    $currentConv = array_filter($conversations, function($c) { 
                        return $c['session_id'] === $current_session; 
                    });
                    $currentConv = reset($currentConv);
                    
                    if ($currentConv):
                ?>
                    <!-- Chat Header -->
                    <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4 flex items-center justify-between flex-shrink-0">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">
                                <?php echo htmlspecialchars($currentConv['sender_name']); ?>
                            </h2>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($currentConv['sender_email'] ?? 'No email'); ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 text-sm text-gray-500">
                            <span><i class="fas fa-clock text-gray-400 mr-1"></i><?php echo $currentConv['total_messages']; ?> messages</span>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="chat-messages-container" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
                        <?php if (empty($current_messages)): ?>
                            <div class="flex items-center justify-center h-full">
                                <div class="text-center">
                                    <i class="fas fa-comments text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No messages yet</p>
                                    <p class="text-sm text-gray-400 mt-2">Start the conversation</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($current_messages as $msg): ?>
                        <div class="flex <?php echo $msg['sender_type'] === 'admin' ? 'justify-end' : 'justify-start'; ?>">
                            <div class="max-w-xs lg:max-w-md">
                                <div class="flex items-start gap-2 <?php echo $msg['sender_type'] === 'admin' ? 'flex-row-reverse' : ''; ?>">
                                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-white text-sm font-bold <?php echo $msg['sender_type'] === 'admin' ? 'bg-[#08415c]' : 'bg-gray-400'; ?>">
                                        <?php echo substr($msg['sender_name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-sm font-semibold text-gray-900">
                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="<?php echo $msg['sender_type'] === 'admin' ? 'bg-[#08415c] text-white' : 'bg-white text-gray-900 border border-gray-200'; ?> rounded-lg px-4 py-2">
                                            <?php echo nl2br(htmlspecialchars($msg['message_content'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Input Area -->
                    <div class="bg-white border-t border-gray-200 px-4 sm:px-6 py-4 flex-shrink-0">
                        <form id="admin-chat-form" class="flex gap-2">
                            <input 
                                type="hidden" 
                                name="session_id" 
                                value="<?php echo htmlspecialchars($current_session); ?>"
                            >
                            <textarea 
                                id="admin-message-input"
                                name="message" 
                                placeholder="Type your response..." 
                                rows="3"
                                class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#08415c]/50 resize-none text-sm"
                            ></textarea>
                            <button 
                                type="submit" 
                                class="bg-[#08415c] text-white px-6 py-2 rounded-lg hover:bg-[#0a5273] transition-colors flex items-center justify-center h-fit self-end flex-shrink-0"
                            >
                                <i class="fas fa-paper-plane mr-2"></i> Send
                            </button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <!-- Conversation not found -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Conversation Not Found</h3>
                            <p class="text-gray-500">The selected conversation could not be found</p>
                        </div>
                    </div>
                <?php endif; ?>
                    
                <?php else: ?>
                    <!-- No Conversation Selected -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-comment text-2xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Conversation Selected</h3>
                            <p class="text-gray-500">Select a conversation from the list to view messages</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('admin-chat-form');
        const input = document.getElementById('admin-message-input');
        const messagesContainer = document.getElementById('chat-messages-container');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const sessionId = this.querySelector('input[name="session_id"]').value;
                const message = input.value.trim();
                
                if (!message) return;
                
                // Send admin message
                fetch('../../backend/chat/send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message_content: message,
                        sender_name: 'MinC Support',
                        sender_type: 'admin',
                        session_id: sessionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = '';
                        input.style.height = 'auto';
                        
                        // Add message to UI
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'flex justify-end';
                        messageDiv.innerHTML = `
                            <div class="max-w-xs lg:max-w-md">
                                <div class="flex items-start gap-2 flex-row-reverse">
                                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-white text-sm font-bold bg-[#08415c]">
                                        M
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-sm font-semibold text-gray-900">MinC Support</span>
                                            <span class="text-xs text-gray-500">now</span>
                                        </div>
                                        <div class="bg-[#08415c] text-white rounded-lg px-4 py-2">
                                            ${escapeHtml(message)}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        if (messagesContainer) {
                            messagesContainer.appendChild(messageDiv);
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }
                    } else {
                        showAlertModal('Error: ' + data.message, 'error', 'Message Error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlertModal('Failed to send message', 'error', 'Message Error');
                });
            });
            
            // Auto-resize textarea
            if (input) {
                input.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
        }
        
        // Refresh messages every 3 seconds
        if (messagesContainer) {
            setInterval(function() {
                location.reload();
            }, 5000);
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
    </script>
</body>
</html>
