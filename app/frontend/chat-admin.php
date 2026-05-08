<?php
/**
 * Admin Chat Panel - Customer Messages
 * Allows employee accounts to view and respond to customer inquiries
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../database/connect_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=not_logged_in');
    exit;
}

// Get page info BEFORE app.php so we can use it in sidebar
$page_title = 'Customer Messages';
$current_page = 'chat-admin';

// Load user data for authorization check
$user = [
    'full_name' => 'Guest User',
    'user_type' => 'User',
    'user_type_status' => null,
    'user_type_slug' => null,
    'is_logged_in' => false,
    'user_id' => null,
    'email' => null,
    'contact_num' => null
];

try {
    $user_query = "
        SELECT 
            u.user_id,
            CONCAT(u.fname, ' ', u.lname) as full_name,
            u.fname,
            u.lname,
            u.email,
            u.contact_num,
            ul.user_type_name as user_type,
            ul.user_type_status,
            u.user_status,
            u.user_level_id
        FROM users u
        LEFT JOIN user_levels ul ON u.user_level_id = ul.user_level_id
        WHERE u.user_id = :user_id AND u.user_status = 'active'
    ";
    
    $stmt = $pdo->prepare($user_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        $user = [
            'full_name' => trim($user_data['full_name']),
            'first_name' => $user_data['fname'],
            'last_name' => $user_data['lname'],
            'user_type' => $user_data['user_type'],
            'user_type_status' => $user_data['user_type_status'] ?? null,
            'user_type_slug' => strtolower(trim((string)($user_data['user_type'] ?? ''))),
            'is_logged_in' => true,
            'user_id' => $user_data['user_id'],
            'email' => $user_data['email'],
            'contact_num' => $user_data['contact_num'],
            'user_status' => $user_data['user_status'],
            'user_level_id' => $user_data['user_level_id']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching user data in chat-admin.php: " . $e->getMessage());
}

// Check authorization - Admins (level 1) and Employees (level 2) can chat
$isAuthorizedRole = isset($user['user_level_id']) && in_array((int)$user['user_level_id'], [1, 2], true);
$isRoleActive = isset($user['user_type_status']) && strtolower((string)$user['user_type_status']) === 'active';

if (!$isAuthorizedRole || !$isRoleActive) {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

// Get current session from URL
$current_session = isset($_GET['session_id']) && !empty(trim($_GET['session_id'])) ? trim($_GET['session_id']) : null;
$conversations = [];
$current_messages = [];
$unread_count = 0;

try {
    // Fetch all conversations
    $convQuery = "SELECT 
                    session_id, 
                    MAX(CASE WHEN sender_type = 'customer' THEN sender_name END) as sender_name,
                    MAX(CASE WHEN sender_type = 'customer' THEN sender_email END) as sender_email,
                    MAX(created_at) as last_message_time,
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN sender_type = 'customer' THEN 1 ELSE 0 END) as customer_messages,
                    SUM(CASE WHEN is_read = 0 AND sender_type = 'customer' THEN 1 ELSE 0 END) as unread_count
                  FROM chat_messages 
                  WHERE session_id IS NOT NULL AND session_id != ''
                  GROUP BY session_id
                  ORDER BY last_message_time DESC";
    
    $convStmt = $pdo->query($convQuery);
    $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch messages for current session if selected
    if ($current_session) {
        $msgQuery = "SELECT * FROM chat_messages WHERE session_id = :session_id ORDER BY created_at ASC";
        $msgStmt = $pdo->prepare($msgQuery);
        $msgStmt->execute([':session_id' => $current_session]);
        $current_messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark as read
        $updateQuery = "UPDATE chat_messages SET is_read = 1, read_at = NOW() 
                       WHERE session_id = :session_id AND sender_type = 'customer' AND is_read = 0";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([':session_id' => $current_session]);
    }
    
    // Get total unread
    $unreadQuery = "SELECT COUNT(*) as unread_count FROM chat_messages WHERE sender_type = 'customer' AND is_read = 0";
    $unreadStmt = $pdo->query($unreadQuery);
    $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = intval($unreadResult['unread_count'] ?? 0);
    
} catch (Exception $e) {
    // Handle error gracefully
    error_log("Chat admin error: " . $e->getMessage());
}

// Debug output (remove in production)
if (isset($_GET['debug'])) {
    error_log("Chat Debug: Conversations=" . count($conversations) . ", Session=" . ($current_session ?? 'NULL') . ", Messages=" . count($current_messages));
}

// Start output buffering for the chat content
ob_start();
?>
    <!-- Main Content -->
    <div class="flex flex-col bg-gray-50 h-[calc(100vh-100px)] -mx-6 -mt-6 -mb-6">
        
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 py-4 flex-shrink-0">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Customer Messages</h1>
                    <p class="text-sm text-gray-500">Manage customer inquiries and support requests</p>
                </div>
                <div class="flex items-center gap-8">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-[#08415c]"><?php echo count($conversations); ?></div>
                        <div class="text-xs text-gray-500">Total</div>
                    </div>
                    <?php if ($unread_count > 0): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2 text-center">
                        <div class="text-xl font-bold text-red-600"><?php echo $unread_count; ?></div>
                        <div class="text-xs text-red-600">Unread</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="flex flex-1 overflow-hidden">
            
            <!-- Left Pane: Conversations List -->
            <div class="w-96 border-r border-gray-200 bg-white flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto">
                    <?php if (empty($conversations)): ?>
                        <div class="h-full flex items-center justify-center">
                            <div class="text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl text-gray-300 mb-3 block"></i>
                                <p class="font-medium">No messages</p>
                                <p class="text-sm">Customers will be able to message you here</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($conversations as $conv): 
                                $selected = ($current_session === $conv['session_id']);
                                $sessionId = $conv['session_id']; // Safe now since we filter out NULLs in query
                            ?>
                            <a href="?session_id=<?php echo urlencode($sessionId); ?>" 
                               class="block px-4 py-3 hover:bg-blue-50 transition-colors no-underline conversation-link <?php echo $selected ? 'bg-blue-50 border-l-4 border-[#08415c]' : ''; ?>"
                               data-session-id="<?php echo htmlspecialchars($sessionId); ?>"
                               onclick="return navigateToConversation(this);">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1 mb-1">
                                            <h3 class="font-semibold text-gray-900 truncate text-sm">
                                                <?php echo htmlspecialchars($conv['sender_name'] ?? 'Customer'); ?>
                                            </h3>
                                            <?php if (intval($conv['unread_count']) > 0): ?>
                                                <span class="inline-flex items-center justify-center w-5 h-5 bg-red-500 text-white rounded-full text-xs font-bold flex-shrink-0">
                                                    <?php echo intval($conv['unread_count']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-gray-500 truncate">
                                            <?php echo htmlspecialchars($conv['sender_email'] ?? 'No email'); ?>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?php echo intval($conv['customer_messages']); ?> msg<?php echo $conv['customer_messages'] != 1 ? 's' : ''; ?>
                                        </p>
                                    </div>
                                    <div class="text-right flex-shrink-0 text-xs text-gray-500">
                                        <?php echo date('M d', strtotime($conv['last_message_time'])); ?>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Pane: Messages -->
            <div class="flex-1 flex flex-col bg-gray-50 overflow-hidden">
                <?php if (!$current_session): ?>
                    <!-- No selection -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-comments text-5xl text-gray-300 mb-4 block"></i>
                            <h2 class="text-xl font-semibold text-gray-900 mb-1">No Conversation Selected</h2>
                            <p class="text-gray-500">Select a conversation to view messages</p>
                        </div>
                    </div>
                <?php else:
                    // Find the selected conversation
                    $selectedConv = null;
                    foreach ($conversations as $c) {
                        if ($c['session_id'] === $current_session) {
                            $selectedConv = $c;
                            break;
                        }
                    }
                    
                    if (!$selectedConv):
                    ?>
                    <!-- Conversation not found -->
                    <div class="flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-exclamation-circle text-5xl text-red-400 mb-4 block"></i>
                            <h2 class="text-xl font-semibold text-gray-900 mb-1">Conversation Not Found</h2>
                            <p class="text-gray-500">This conversation no longer exists</p>
                        </div>
                    </div>
                    <?php else: ?>
                    
                    <!-- Chat Header -->
                    <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($selectedConv['sender_name'] ?? 'Customer'); ?></h2>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($selectedConv['sender_email'] ?? 'No email'); ?></p>
                        </div>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-envelope mr-1"></i> <?php echo intval($selectedConv['total_messages']); ?> messages
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="messages" class="flex-1 overflow-y-auto p-6 space-y-4">
                        <?php if (empty($current_messages)): ?>
                            <div class="h-full flex items-center justify-center">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-comments text-4xl text-gray-300 mb-3 block"></i>
                                    <p class="font-medium">No messages yet</p>
                                    <p class="text-sm">Send a message to start the conversation</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($current_messages as $msg): ?>
                                <div class="flex <?php echo $msg['sender_type'] === 'admin' ? 'justify-end' : 'justify-start'; ?>">
                                    <div class="max-w-xs">
                                        <div class="flex items-end gap-2 <?php echo $msg['sender_type'] === 'admin' ? 'flex-row-reverse' : ''; ?>">
                                            <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-white text-xs font-bold <?php echo $msg['sender_type'] === 'admin' ? 'bg-[#08415c]' : 'bg-gray-400'; ?>">
                                                <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-xs font-semibold text-gray-900">
                                                        <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500">
                                                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                                    </span>
                                                </div>
                                                <div class="<?php echo $msg['sender_type'] === 'admin' ? 'bg-[#08415c] text-white' : 'bg-white text-gray-900 border border-gray-200'; ?> rounded-lg px-3 py-2">
                                                    <p class="text-sm whitespace-pre-wrap">
                                                        <?php echo htmlspecialchars($msg['message_content']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Input -->
                    <div class="bg-white border-t border-gray-200 px-6 py-4 flex-shrink-0">
                        <form id="messageForm" class="flex gap-2">
                            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($current_session); ?>">
                            <textarea id="messageText" name="message" placeholder="Type your response..." rows="2" class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#08415c] resize-none text-sm"></textarea>
                            <button type="submit" class="bg-[#08415c] hover:bg-[#0a5273] text-white px-6 py-2 rounded-lg h-fit self-end transition-colors">
                                <i class="fas fa-paper-plane mr-2"></i>Send
                            </button>
                        </form>
                    </div>
                    
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    // Handle conversation link clicks
    function navigateToConversation(link) {
        const sessionId = link.getAttribute('data-session-id');
        if (sessionId && sessionId.trim().length > 0) {
            window.location.href = '?session_id=' + encodeURIComponent(sessionId);
            return false;
        }
        console.warn('Invalid session ID:', sessionId);
        return true;
    }

    // SSE state
    let listSource = null;
    let msgSource = null;
    let sseFailures = 0;
    let usePolling = false;
    let pollTimer = null;
    let lastMessageId = 0;

    // Capture initial lastMessageId from PHP-rendered messages
    const messagesContainer = document.getElementById('messages');
    <?php if (!empty($current_messages)): ?>
    lastMessageId = <?php echo (int)end($current_messages)['message_id']; ?>;
    <?php endif; ?>

    // SSE helpers
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function formatTime(dateValue) {
        if (!dateValue) return '';
        let dateString = String(dateValue);
        if (dateString.indexOf('T') === -1) {
            dateString = dateString.replace(' ', 'T');
        }
        if (!dateString.endsWith('Z')) {
            dateString += 'Z';
        }
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function appendAdminMessage(msg) {
        if (!messagesContainer) return;
        const senderType = msg.sender_type === 'admin' ? 'admin' : 'customer';
        const initial = (msg.sender_name || '?').charAt(0).toUpperCase();
        const avatarClass = senderType === 'admin' ? 'bg-[#08415c]' : 'bg-gray-400';
        const bubbleClass = senderType === 'admin'
            ? 'bg-[#08415c] text-white'
            : 'bg-white text-gray-900 border border-gray-200';
        const justifyClass = senderType === 'admin' ? 'justify-end' : 'justify-start';
        const flexDir = senderType === 'admin' ? 'flex-row-reverse' : '';
        const time = formatTime(msg.created_at);

        const div = document.createElement('div');
        div.className = 'flex ' + justifyClass;
        div.innerHTML = `
            <div class="max-w-xs">
                <div class="flex items-end gap-2 ${flexDir}">
                    <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-white text-xs font-bold ${avatarClass}">
                        ${escapeHtml(initial)}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold text-gray-900">${escapeHtml(msg.sender_name || '')}</span>
                            <span class="text-xs text-gray-500">${escapeHtml(time)}</span>
                        </div>
                        <div class="${bubbleClass} rounded-lg px-3 py-2">
                            <p class="text-sm whitespace-pre-wrap">${escapeHtml(msg.message_content)}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        messagesContainer.appendChild(div);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        const msgId = parseInt(msg.message_id, 10);
        if (!Number.isNaN(msgId) && msgId > lastMessageId) {
            lastMessageId = msgId;
        }
    }

    function refreshConversationList() {
        fetch('../../backend/chat/send_message.php?type=admin')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success' && Array.isArray(data.data)) {
                    updateUnreadBadge(data.data);
                }
            })
            .catch(() => {});
    }

    function updateUnreadBadge(conversations) {
        const totalUnread = conversations.reduce((sum, c) => sum + parseInt(c.unread_count || 0, 10), 0);
        const badgeEl = document.querySelector('.text-red-600.text-xl');
        const badgeContainer = badgeEl ? badgeEl.closest('.bg-red-50') : null;
        if (totalUnread > 0 && badgeContainer) {
            badgeContainer.style.display = '';
            badgeEl.textContent = totalUnread;
        } else if (badgeContainer) {
            badgeContainer.style.display = 'none';
        }
    }

    function connectSSE() {
        if (usePolling) return;

        disconnectSSE();

        // Stream A: conversation list updates
        listSource = new EventSource('../../backend/chat/sse.php?type=admin');

        listSource.addEventListener('new_message', () => {
            refreshConversationList();
            sseFailures = 0;
        });

        listSource.addEventListener('new_conversation', () => {
            refreshConversationList();
            sseFailures = 0;
        });

        listSource.onerror = () => {
            sseFailures++;
            console.warn('SSE list connection failed (' + sseFailures + '/3)');
            listSource.close();
            listSource = null;
            if (sseFailures >= 3) {
                console.warn('SSE failed 3 times, falling back to polling');
                usePolling = true;
                startPolling();
            } else {
                setTimeout(connectSSE, 3000);
            }
        };

        // Stream B: current conversation messages (only if a session is selected)
        <?php if ($current_session): ?>
        const currentSessionId = <?php echo json_encode($current_session); ?>;
        msgSource = new EventSource('../../backend/chat/sse.php?type=admin&session_id=' + encodeURIComponent(currentSessionId));

        msgSource.addEventListener('new_message', (e) => {
            try {
                const msg = JSON.parse(e.data);
                appendAdminMessage(msg);
                sseFailures = 0;
            } catch (err) {
                console.error('SSE parse error:', err);
            }
        });

        msgSource.onerror = () => {
            msgSource.close();
            msgSource = null;
            setTimeout(() => {
                if (!usePolling && msgSource === null) {
                    msgSource = new EventSource('../../backend/chat/sse.php?type=admin&session_id=' + encodeURIComponent(currentSessionId));
                    msgSource.addEventListener('new_message', (e) => {
                        try {
                            const msg = JSON.parse(e.data);
                            appendAdminMessage(msg);
                        } catch (err) {}
                    });
                }
            }, 3000);
        };
        <?php endif; ?>
    }

    function disconnectSSE() {
        if (listSource) {
            listSource.close();
            listSource = null;
        }
        if (msgSource) {
            msgSource.close();
            msgSource = null;
        }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(() => {
            refreshConversationList();
            <?php if ($current_session): ?>
            fetch('../../backend/chat/send_message.php?type=admin&session_id=' + encodeURIComponent(<?php echo json_encode($current_session); ?>))
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && Array.isArray(data.data)) {
                        data.data.forEach(msg => {
                            const msgId = parseInt(msg.message_id, 10);
                            if (!Number.isNaN(msgId) && msgId > lastMessageId) {
                                appendAdminMessage(msg);
                            }
                        });
                    }
                })
                .catch(() => {});
            <?php endif; ?>
        }, 3000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // Connect SSE on page load
    connectSSE();

    // Only attach event listener if form exists
    let isAdminSending = false;
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isAdminSending) return;

            const sessionId = this.querySelector('input[name="session_id"]').value;
            const message = document.getElementById('messageText').value.trim();
            const submitBtn = this.querySelector('button[type="submit"]');

            if (!message) return;

            isAdminSending = true;
            if (submitBtn) submitBtn.disabled = true;

            fetch('../../backend/chat/send_message.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    message_content: message,
                    sender_name: 'MinC Support',
                    sender_type: 'admin',
                    session_id: sessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('messageText').value = '';
                    refreshConversationList();
                } else {
                    showAlertModal('Error: ' + (data.message || 'Failed to send message'), 'error', 'Message Error');
                }
            })
            .catch(e => {
                console.error('Error:', e);
                showAlertModal('Failed to send message', 'error', 'Message Error');
            })
            .finally(() => {
                isAdminSending = false;
                if (submitBtn) submitBtn.disabled = false;
                document.getElementById('messageText').focus();
            });
        });
    }
    </script>
    </script>
</div>
<?php
// Capture the buffered content and pass it to app.php wrapper
$content = ob_get_clean();
include 'app.php';
?>
