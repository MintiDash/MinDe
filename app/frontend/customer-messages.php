<?php
/**
 * Employee Chat Panel - Customer Messages
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../database/connect_database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php?error=not_logged_in');
    exit;
}

$page_title = 'Customer Messages';
$current_page = 'customer-messages';
$custom_title = 'Customer Messages - MinC Project';

// Load user data for authorization check (employee only and active role).
$user = [
    'full_name' => 'Guest User',
    'user_type_status' => null,
    'user_level_id' => null
];

try {
    $user_query = "
        SELECT 
            u.user_id,
            CONCAT(u.fname, ' ', u.lname) AS full_name,
            ul.user_type_status,
            u.user_status,
            u.user_level_id
        FROM users u
        LEFT JOIN user_levels ul ON u.user_level_id = ul.user_level_id
        WHERE u.user_id = :user_id
          AND u.user_status = 'active'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($user_query);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user = [
            'full_name' => trim((string)($user_data['full_name'] ?? 'Guest User')),
            'user_type_status' => $user_data['user_type_status'] ?? null,
            'user_level_id' => $user_data['user_level_id'] ?? null
        ];
    }
} catch (Exception $e) {
    error_log('Error fetching user data in customer-messages.php: ' . $e->getMessage());
}

$isEmployeeRole = isset($user['user_level_id']) && (int)$user['user_level_id'] === 2;
$isRoleActive = isset($user['user_type_status']) && strtolower((string)$user['user_type_status']) === 'active';
if (!$isEmployeeRole || !$isRoleActive) {
    header('Location: ../../index.php?error=unauthorized');
    exit;
}

$isGenericDisplayName = static function ($name) {
    $value = strtolower(trim((string)$name));
    if ($value === '') {
        return true;
    }

    $generic = ['customer', 'anonymous customer', 'anonymous', 'guest', 'user', 'unknown'];
    return in_array($value, $generic, true);
};

$extractSessionUserId = static function ($sessionId) {
    if (preg_match('/^user[_-]?(\d+)$/i', (string)$sessionId, $matches)) {
        $userId = (int)$matches[1];
        return $userId > 0 ? $userId : null;
    }

    return null;
};

$resolveDisplayName = static function ($name, $email, $sessionId = '') use ($isGenericDisplayName) {
    $value = trim((string)$name);

    if (!$isGenericDisplayName($value)) {
        return $value;
    }

    $email = trim((string)$email);
    if ($email !== '' && strpos($email, '@') !== false) {
        $localPart = explode('@', $email)[0];
        $localPart = preg_replace('/[._-]+/', ' ', $localPart);
        $localPart = trim(preg_replace('/\s+/', ' ', (string)$localPart));
        if ($localPart !== '') {
            return ucwords(strtolower($localPart), " -'");
        }
    }

    if (preg_match('/^user[_-]?(\d+)$/i', (string)$sessionId, $matches)) {
        return 'User #' . $matches[1];
    }

    return 'Customer';
};

$formatConversationTime = static function ($timestamp) {
    if (empty($timestamp)) {
        return '';
    }

    $time = strtotime((string)$timestamp);
    if (!$time) {
        return '';
    }

    $today = strtotime(date('Y-m-d'));
    $messageDay = strtotime(date('Y-m-d', $time));

    if ($messageDay === $today) {
        return date('g:i A', $time);
    }

    return date('M d', $time);
};

$normalizeMessageBody = static function ($content) {
    $content = (string)$content;
    for ($i = 0; $i < 2; $i++) {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $content) {
            break;
        }
        $content = $decoded;
    }

    $content = strip_tags($content);
    $content = str_replace(["\xC2\xA0", '&nbsp;'], ' ', $content);
    $content = str_replace(["\r\n", "\r", "\n"], ' ', $content);
    $content = preg_replace('/\s+/u', ' ', $content);
    return trim($content);
};

$current_session = isset($_GET['session_id']) && trim((string)$_GET['session_id']) !== ''
    ? trim((string)$_GET['session_id'])
    : null;

$conversations = [];
$current_messages = [];
$unread_count = 0;
$selectedConv = null;

try {
    $convQuery = "
        SELECT 
            session_id,
            MAX(CASE WHEN sender_type = 'customer' THEN sender_id END) AS sender_id,
            MAX(CASE WHEN sender_type = 'customer' THEN sender_name END) AS sender_name,
            MAX(CASE WHEN sender_type = 'customer' THEN sender_email END) AS sender_email,
            MAX(created_at) AS last_message_time,
            COUNT(*) AS total_messages,
            SUM(CASE WHEN sender_type = 'customer' THEN 1 ELSE 0 END) AS customer_messages,
            SUM(CASE WHEN is_read = 0 AND sender_type = 'customer' THEN 1 ELSE 0 END) AS unread_count
        FROM chat_messages
        WHERE session_id IS NOT NULL
          AND session_id != ''
        GROUP BY session_id
        ORDER BY last_message_time DESC
    ";

    $convStmt = $pdo->query($convQuery);
    $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);
    $usersById = [];

    if (!empty($conversations)) {
        $conversationUserIds = [];
        foreach ($conversations as $convRow) {
            $senderId = (int)($convRow['sender_id'] ?? 0);
            if ($senderId > 0) {
                $conversationUserIds[$senderId] = $senderId;
            }

            $sessionUserId = $extractSessionUserId($convRow['session_id'] ?? '');
            if ($sessionUserId !== null) {
                $conversationUserIds[$sessionUserId] = $sessionUserId;
            }
        }

        if (!empty($conversationUserIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($conversationUserIds), '?'));
            $userLookupStmt = $pdo->prepare("
                SELECT
                    user_id,
                    TRIM(CONCAT(COALESCE(fname, ''), ' ', COALESCE(lname, ''))) AS full_name,
                    email
                FROM users
                WHERE user_id IN ($idPlaceholders)
            ");
            $userLookupStmt->execute(array_values($conversationUserIds));
            $userRows = $userLookupStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($userRows as $userRow) {
                $uid = (int)($userRow['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }

                $usersById[$uid] = [
                    'full_name' => trim((string)($userRow['full_name'] ?? '')),
                    'email' => trim((string)($userRow['email'] ?? ''))
                ];
            }
        }
    }

    foreach ($conversations as &$conv) {
        $convSessionUserId = $extractSessionUserId($conv['session_id'] ?? '');
        $convSenderUserId = (int)($conv['sender_id'] ?? 0);
        $convUserId = $convSenderUserId > 0 ? $convSenderUserId : ($convSessionUserId ?? 0);
        $convUser = ($convUserId > 0 && isset($usersById[$convUserId])) ? $usersById[$convUserId] : null;

        $displayName = $resolveDisplayName(
            $conv['sender_name'] ?? '',
            $conv['sender_email'] ?? '',
            $conv['session_id'] ?? ''
        );
        $displayEmailRaw = trim((string)($conv['sender_email'] ?? ''));

        if ($convUser !== null) {
            $userFullName = trim((string)($convUser['full_name'] ?? ''));
            $userEmail = trim((string)($convUser['email'] ?? ''));

            if ($userFullName !== '' && $isGenericDisplayName($displayName)) {
                $displayName = $userFullName;
            }

            if ($displayEmailRaw === '' && $userEmail !== '') {
                $displayEmailRaw = $userEmail;
            }
        }

        $conv['_display_name'] = $displayName;
        $conv['_display_email_raw'] = $displayEmailRaw;
        $conv['_display_email'] = $displayEmailRaw !== '' ? $displayEmailRaw : 'No email provided';
        $conv['_avatar'] = strtoupper(substr($displayName, 0, 1));
        $conv['_time_label'] = $formatConversationTime($conv['last_message_time'] ?? null);
        $conv['_resolved_user_id'] = $convUserId > 0 ? $convUserId : null;

        if ($current_session !== null && ($conv['session_id'] ?? '') === $current_session) {
            $selectedConv = $conv;
        }
    }
    unset($conv);

    if ($current_session) {
        $msgQuery = "
            SELECT *
            FROM chat_messages
            WHERE session_id = :session_id
            ORDER BY created_at ASC
        ";
        $msgStmt = $pdo->prepare($msgQuery);
        $msgStmt->execute([':session_id' => $current_session]);
        $current_messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize legacy records for the active conversation so rendering is consistent.
        $normalizeUpdateStmt = $pdo->prepare("
            UPDATE chat_messages
            SET message_content = :message_content
            WHERE message_id = :message_id
            LIMIT 1
        ");

        foreach ($current_messages as &$messageRow) {
            $normalized = $normalizeMessageBody($messageRow['message_content'] ?? '');
            if ($normalized === '') {
                continue;
            }

            $encodedNormalized = htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if ((string)($messageRow['message_content'] ?? '') !== $encodedNormalized) {
                $normalizeUpdateStmt->execute([
                    ':message_content' => $encodedNormalized,
                    ':message_id' => (int)($messageRow['message_id'] ?? 0)
                ]);
                $messageRow['message_content'] = $encodedNormalized;
            }
        }
        unset($messageRow);

        $updateQuery = "
            UPDATE chat_messages
            SET is_read = 1, read_at = NOW()
            WHERE session_id = :session_id
              AND sender_type = 'customer'
              AND is_read = 0
        ";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([':session_id' => $current_session]);
    }

    $unreadQuery = "
        SELECT COUNT(*) AS unread_count
        FROM chat_messages
        WHERE sender_type = 'customer'
          AND is_read = 0
    ";
    $unreadStmt = $pdo->query($unreadQuery);
    $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = (int)($unreadResult['unread_count'] ?? 0);
} catch (Exception $e) {
    error_log('Chat admin error in customer-messages.php: ' . $e->getMessage());
}

$additional_styles = <<<CSS
.messages-shell {
    height: calc(100vh - 150px);
    min-height: 700px;
    display: flex;
}

.messages-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.messages-grid {
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    flex: 1;
    min-height: 0;
    height: 100%;
}

.conversation-pane {
    border-right: 1px solid #e5e7eb;
    background: #fff;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.conversation-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 14px 16px;
    border-bottom: 1px solid #eef2f7;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.conversation-item:hover {
    background: #f8fafc;
}

.conversation-item.active {
    background: #eef6fb;
    border-left: 3px solid #08415c;
    padding-left: 13px;
}

.conversation-avatar {
    width: 36px;
    height: 36px;
    border-radius: 9999px;
    background: #dbeafe;
    color: #1e3a8a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}

.conversation-name {
    font-size: 0.92rem;
    line-height: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}

.conversation-email {
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 2px;
}

.conversation-count {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 4px;
}

.conversation-time {
    font-size: 0.72rem;
    color: #64748b;
}

.chat-pane {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    min-height: 0;
}

.chat-header {
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
}

.chat-stream {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 18px 18px 12px;
}

.chat-stream::-webkit-scrollbar,
.conversation-scroll::-webkit-scrollbar {
    width: 8px;
}

.chat-stream::-webkit-scrollbar-thumb,
.conversation-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}

.chat-row {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
    justify-content: flex-start;
}

.chat-row.outgoing {
    justify-content: flex-end;
}

.chat-row.outgoing .chat-stack {
    align-items: flex-end;
}

.chat-row.outgoing .chat-meta {
    justify-content: flex-end;
}

.chat-stack {
    display: flex;
    flex-direction: column;
    max-width: min(78%, 420px);
    align-items: flex-start;
}

.chat-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    flex-wrap: wrap;
}

.chat-name {
    font-size: 0.76rem;
    font-weight: 700;
    color: #0f172a;
}

.chat-email {
    font-size: 0.72rem;
    color: #64748b;
}

.chat-time {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 4px;
    display: inline-block;
}

.chat-bubble {
    border-radius: 14px;
    padding: 10px 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    word-break: break-word;
    white-space: normal;
    text-align: left !important;
    line-height: 1.35;
    display: block;
}

.chat-bubble.customer {
    background: #fff;
    color: #0f172a;
    border: 1px solid #dbe3ee;
}

.chat-bubble.agent {
    background: linear-gradient(135deg, #08415c 0%, #0a5273 100%);
    color: #fff;
}

.chat-start-marker {
    display: flex;
    justify-content: center;
    margin-bottom: 16px;
}

.chat-start-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    border: 1px solid #dbe3ee;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.75rem;
    line-height: 1;
    padding: 0.35rem 0.8rem;
}

.chat-input-wrap {
    border-top: 1px solid #e5e7eb;
    background: #fff;
    padding: 10px 16px;
    margin: 0;
}

.chat-input-area {
    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: 10px 12px;
    resize: none;
    min-height: 44px;
    max-height: 140px;
    line-height: 1.25rem;
}

.chat-input-area:focus {
    outline: none;
    border-color: #08415c;
    box-shadow: 0 0 0 3px rgba(8, 65, 92, 0.15);
}

.chat-send-btn {
    border-radius: 10px;
    padding: 0 16px;
    min-height: 44px;
    font-weight: 600;
}

@media (max-width: 1024px) {
    .messages-shell {
        height: auto;
        min-height: calc(100vh - 130px);
    }

    .messages-grid {
        grid-template-columns: 1fr;
    }

    .conversation-pane {
        border-right: 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .conversation-scroll {
        max-height: 300px;
        flex: initial;
    }

    .chat-stream {
        max-height: 56vh;
    }

    .chat-stack {
        max-width: 86%;
    }
}
CSS;

ob_start();
?>
<section class="messages-shell">
    <div class="professional-card rounded-2xl overflow-hidden messages-card">
        <div class="px-6 py-5 border-b border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-[#08415c]">Customer Messages</h1>
                    <p class="text-sm text-gray-500 mt-1">Respond to support inquiries with full conversation context.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-slate-100 rounded-xl px-4 py-2 text-center">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Conversations</p>
                        <p id="conversationCountValue" class="text-xl font-bold text-slate-800"><?php echo count($conversations); ?></p>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-2 text-center">
                        <p class="text-xs uppercase tracking-wide text-red-500">Unread</p>
                        <p id="unreadCountValue" class="text-xl font-bold text-red-600"><?php echo $unread_count; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="messages-grid">
            <aside class="conversation-pane">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                    <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Inbox</p>
                </div>

                <div class="conversation-scroll">
                    <?php if (empty($conversations)): ?>
                        <div class="h-64 flex items-center justify-center px-4 text-center text-gray-500">
                            <div>
                                <i class="fas fa-inbox text-4xl text-gray-300 mb-3 block"></i>
                                <p class="font-semibold">No conversations yet</p>
                                <p class="text-sm mt-1">Incoming customer chats will appear here.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <?php
                            $sessionId = (string)($conv['session_id'] ?? '');
                            $isSelected = $current_session !== null && $current_session === $sessionId;
                            $unread = (int)($conv['unread_count'] ?? 0);
                            ?>
                            <a href="customer-messages.php?session_id=<?php echo urlencode($sessionId); ?>"
                               class="conversation-item no-underline <?php echo $isSelected ? 'active' : ''; ?>"
                               data-session-id="<?php echo htmlspecialchars($sessionId); ?>"
                               data-conversation-item="1"
                               onclick="return navigateToConversation(this);">
                                <span class="conversation-avatar"><?php echo htmlspecialchars($conv['_avatar'] ?: 'U'); ?></span>
                                <div class="flex-1 min-w-0">
                                    <p class="conversation-name truncate"><?php echo htmlspecialchars($conv['_display_name']); ?></p>
                                    <p class="conversation-email truncate"><?php echo htmlspecialchars($conv['_display_email']); ?></p>
                                    <p class="conversation-count" data-conversation-count><?php echo (int)$conv['customer_messages']; ?> message<?php echo ((int)$conv['customer_messages'] === 1) ? '' : 's'; ?></p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="conversation-time" data-conversation-time><?php echo htmlspecialchars($conv['_time_label']); ?></span>
                                    <?php if ($unread > 0): ?>
                                        <span data-unread-badge class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold">
                                            <?php echo $unread; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="chat-pane flex flex-col">
                <?php if (!$current_session): ?>
                    <div class="flex-1 flex items-center justify-center px-6">
                        <div class="text-center text-gray-500">
                            <i class="fas fa-comments text-5xl text-gray-300 mb-4 block"></i>
                            <p class="text-xl font-semibold text-gray-700">Select a conversation</p>
                            <p class="mt-1">Pick a customer on the left to view and reply.</p>
                        </div>
                    </div>
                <?php elseif (!$selectedConv): ?>
                    <div class="flex-1 flex items-center justify-center px-6">
                        <div class="text-center text-gray-500">
                            <i class="fas fa-exclamation-circle text-5xl text-red-300 mb-4 block"></i>
                            <p class="text-xl font-semibold text-gray-700">Conversation not found</p>
                            <p class="mt-1">The selected thread may have been removed.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <header class="chat-header px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="conversation-avatar"><?php echo htmlspecialchars($selectedConv['_avatar'] ?: 'U'); ?></span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-bold text-slate-900 truncate"><?php echo htmlspecialchars($selectedConv['_display_name']); ?></h2>
                                <p class="text-sm text-slate-500 truncate"><?php echo htmlspecialchars($selectedConv['_display_email']); ?></p>
                            </div>
                        </div>
                        <div class="text-xs sm:text-sm text-slate-500 text-right">
                            <span id="selectedTotalMessages" class="font-semibold text-slate-700"><?php echo (int)$selectedConv['total_messages']; ?></span> total messages
                        </div>
                    </header>

                    <div id="messages" class="chat-stream">
                        <?php if (empty($current_messages)): ?>
                            <div class="h-full flex items-center justify-center text-center text-gray-500">
                                <div>
                                    <i class="fas fa-paper-plane text-4xl text-gray-300 mb-3 block"></i>
                                    <p class="font-semibold">No messages yet</p>
                                    <p class="text-sm mt-1">Send your first response below.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="chat-start-marker">
                                <span class="chat-start-pill">Chat started</span>
                            </div>
                            <?php foreach ($current_messages as $msg): ?>
                                <?php
                                $isAdminMessage = (($msg['sender_type'] ?? '') === 'admin');
                                $msgNameRaw = trim((string)($msg['sender_name'] ?? ''));
                                $msgEmailRaw = trim((string)($msg['sender_email'] ?? ''));

                                if ($isAdminMessage) {
                                    $msgDisplayName = 'You';
                                } else {
                                    $msgDisplayName = $resolveDisplayName($msgNameRaw, $msgEmailRaw, $current_session ?? '');

                                    $selectedEmailRaw = trim((string)($selectedConv['_display_email_raw'] ?? ''));
                                    $selectedName = trim((string)($selectedConv['_display_name'] ?? ''));
                                    if ($isGenericDisplayName($msgDisplayName) && $selectedName !== '') {
                                        $msgDisplayName = $selectedName;
                                    }

                                    if ($msgEmailRaw === '' && $selectedEmailRaw !== '' && strtolower($selectedEmailRaw) !== 'no email provided') {
                                        $msgEmailRaw = $selectedEmailRaw;
                                    }
                                }

                                $messageBody = $normalizeMessageBody($msg['message_content'] ?? '');
                                if ($messageBody === '') {
                                    continue;
                                }
                                ?>
                                <article class="chat-row <?php echo $isAdminMessage ? 'outgoing' : 'incoming'; ?>" data-message-id="<?php echo (int)($msg['message_id'] ?? 0); ?>">
                                    <div class="chat-stack">
                                        <div class="chat-meta">
                                            <span class="chat-name"><?php echo htmlspecialchars($msgDisplayName); ?></span>
                                        </div>
                                        <div class="chat-bubble <?php echo $isAdminMessage ? 'agent' : 'customer'; ?>">
                                            <?php echo nl2br(htmlspecialchars($messageBody)); ?>
                                        </div>
                                        <span class="chat-time"><?php echo date('g:i A', strtotime((string)$msg['created_at'])); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="chat-input-wrap">
                        <form id="messageForm" class="flex items-end gap-2">
                            <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($current_session); ?>">
                            <textarea id="messageText"
                                      name="message"
                                      rows="1"
                                      maxlength="2000"
                                      placeholder="Type your reply..."
                                      class="chat-input-area flex-1"></textarea>
                            <button type="submit" class="chat-send-btn bg-[#08415c] hover:bg-[#0a5273] text-white transition-colors">
                                <i class="fas fa-paper-plane mr-1.5"></i>Send
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<script>
function navigateToConversation(link) {
    const sessionId = (link.getAttribute('data-session-id') || '').trim();
    if (!sessionId) {
        return false;
    }
    window.location.href = 'customer-messages.php?session_id=' + encodeURIComponent(sessionId);
    return false;
}

const ACTIVE_SESSION_ID = <?php echo json_encode($current_session); ?>;
const SELECTED_CUSTOMER_NAME = <?php echo json_encode($selectedConv['_display_name'] ?? 'Customer'); ?>;
const SELECTED_CUSTOMER_EMAIL = <?php echo json_encode($selectedConv['_display_email'] ?? 'No email provided'); ?>;

const messageForm = document.getElementById('messageForm');
const messageText = document.getElementById('messageText');
const messagesContainer = document.getElementById('messages');
const conversationCountValue = document.getElementById('conversationCountValue');
const unreadCountValue = document.getElementById('unreadCountValue');
const selectedTotalMessages = document.getElementById('selectedTotalMessages');

const knownMessageIds = new Set();
let pollingInProgress = false;

function isGenericName(value) {
    return /^(customer|anonymous customer|anonymous|guest|user|unknown)$/i.test(String(value || '').trim());
}

function escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value || '');
    return node.innerHTML;
}

function normalizeMessageBody(text) {
    const decodeArea = document.createElement('textarea');
    decodeArea.innerHTML = String(text || '');

    const stripNode = document.createElement('div');
    stripNode.textContent = decodeArea.value;

    return String(stripNode.textContent || '')
        .replace(/\u00A0/g, ' ')
        .replace(/\r\n?/g, ' ')
        .replace(/\n/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function scrollMessagesToBottom() {
    if (!messagesContainer) return;
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function autoResizeTextarea(textarea) {
    if (!textarea) return;
    textarea.style.height = '44px';
    textarea.style.height = Math.min(textarea.scrollHeight, 140) + 'px';
}

function formatTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function formatConversationTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const now = new Date();
    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    return date.toLocaleDateString([], { month: 'short', day: '2-digit' });
}

function ensureStartMarker() {
    if (!messagesContainer) {
        return;
    }
    if (messagesContainer.querySelector('.chat-start-marker')) {
        return;
    }

    const marker = document.createElement('div');
    marker.className = 'chat-start-marker';
    marker.innerHTML = '<span class="chat-start-pill">Chat started</span>';
    messagesContainer.prepend(marker);
}

function hydrateKnownMessageIds() {
    if (!messagesContainer) {
        return;
    }
    messagesContainer.querySelectorAll('.chat-row[data-message-id]').forEach((row) => {
        const messageId = Number(row.getAttribute('data-message-id') || 0);
        if (messageId > 0) {
            knownMessageIds.add(messageId);
        }
    });
}

function findConversationItem(sessionId) {
    if (!sessionId) {
        return null;
    }

    const items = document.querySelectorAll('.conversation-item[data-session-id]');
    for (const item of items) {
        if ((item.getAttribute('data-session-id') || '') === sessionId) {
            return item;
        }
    }
    return null;
}

function updateSelectedConversationPreview(messages) {
    if (!ACTIVE_SESSION_ID || !Array.isArray(messages)) {
        return;
    }

    const item = findConversationItem(ACTIVE_SESSION_ID);
    if (!item) {
        return;
    }

    const customerMessages = messages.filter((msg) => (msg.sender_type || '') === 'customer').length;
    const countEl = item.querySelector('[data-conversation-count]');
    if (countEl) {
        countEl.textContent = `${customerMessages} message${customerMessages === 1 ? '' : 's'}`;
    }

    const timeEl = item.querySelector('[data-conversation-time]');
    const lastMessage = messages.length > 0 ? messages[messages.length - 1] : null;
    if (timeEl && lastMessage) {
        timeEl.textContent = formatConversationTime(lastMessage.created_at || '');
    }

    const unreadBadge = item.querySelector('[data-unread-badge]');
    if (unreadBadge) {
        unreadBadge.remove();
    }
}

function appendMessageRow(msg) {
    if (!messagesContainer || !msg) {
        return false;
    }

    const messageId = Number(msg.message_id || 0);
    if (messageId > 0 && knownMessageIds.has(messageId)) {
        return false;
    }

    const senderType = (msg.sender_type || '') === 'admin' ? 'admin' : 'customer';
    let displayName = senderType === 'admin' ? 'You' : normalizeMessageBody(msg.sender_name || '');
    if (displayName === '' || isGenericName(displayName)) {
        displayName = SELECTED_CUSTOMER_NAME || 'Customer';
    }

    const messageBody = normalizeMessageBody(msg.message_content || '');
    if (!messageBody) {
        return false;
    }

    if (!messagesContainer.querySelector('.chat-row')) {
        messagesContainer.innerHTML = '';
    }

    ensureStartMarker();

    const row = document.createElement('article');
    row.className = `chat-row ${senderType === 'admin' ? 'outgoing' : 'incoming'}`;
    if (messageId > 0) {
        row.setAttribute('data-message-id', String(messageId));
        knownMessageIds.add(messageId);
    }

    row.innerHTML = `
        <div class="chat-stack">
            <div class="chat-meta">
                <span class="chat-name">${escapeHtml(displayName)}</span>
            </div>
            <div class="chat-bubble ${senderType === 'admin' ? 'agent' : 'customer'}">${escapeHtml(messageBody)}</div>
            <span class="chat-time">${escapeHtml(formatTime(msg.created_at || ''))}</span>
        </div>
    `;

    messagesContainer.appendChild(row);
    return true;
}

function isNearBottom() {
    if (!messagesContainer) {
        return true;
    }
    const threshold = 120;
    const distanceFromBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
    return distanceFromBottom <= threshold;
}

function updateMessageCount(messageTotal) {
    if (selectedTotalMessages && Number.isFinite(messageTotal)) {
        selectedTotalMessages.textContent = String(messageTotal);
    }
}

async function pollMessages() {
    if (!ACTIVE_SESSION_ID || !messagesContainer || pollingInProgress) {
        return;
    }

    pollingInProgress = true;
    const keepBottom = isNearBottom();

    try {
        const response = await fetch('../../backend/chat/send_message.php?type=customer&session_id=' + encodeURIComponent(ACTIVE_SESSION_ID), {
            cache: 'no-store'
        });
        const data = await response.json();
        if (!data || data.status !== 'success' || !Array.isArray(data.data)) {
            return;
        }

        let appended = 0;
        data.data.forEach((msg) => {
            if (appendMessageRow(msg)) {
                appended++;
            }
        });

        updateMessageCount(data.data.length);
        updateSelectedConversationPreview(data.data);

        if (appended > 0 && keepBottom) {
            scrollMessagesToBottom();
        }
    } catch (error) {
        console.error('Message polling failed:', error);
    } finally {
        pollingInProgress = false;
    }
}

async function pollConversationSummary() {
    try {
        const response = await fetch('../../backend/chat/send_message.php?type=admin', { cache: 'no-store' });
        const data = await response.json();
        if (!data || data.status !== 'success' || !Array.isArray(data.data)) {
            return;
        }

        if (conversationCountValue) {
            conversationCountValue.textContent = String(data.data.length);
        }

        if (unreadCountValue) {
            const unreadTotal = data.data.reduce((total, row) => total + Number(row.unread_count || 0), 0);
            unreadCountValue.textContent = String(unreadTotal);
        }
    } catch (error) {
        console.error('Conversation summary polling failed:', error);
    }
}

if (messageText) {
    autoResizeTextarea(messageText);
    messageText.addEventListener('input', function () {
        autoResizeTextarea(this);
    });

    messageText.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (messageForm) {
                messageForm.requestSubmit();
            }
        }
    });
}

if (messageForm) {
    messageForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const sessionField = this.querySelector('input[name="session_id"]');
        const sessionId = sessionField ? sessionField.value : '';
        const message = normalizeMessageBody(messageText ? messageText.value : '');
        if (!sessionId || !message) {
            return;
        }

        const submitButton = this.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch('../../backend/chat/send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message_content: message,
                sender_name: 'MinC Support',
                sender_type: 'admin',
                session_id: sessionId
            })
        })
        .then((response) => response.json())
        .then((data) => {
            if (!data || data.status !== 'success') {
                const msg = (data && data.message) ? data.message : 'Failed to send message.';
                throw new Error(msg);
            }

            if (!appendMessageRow(data.data || null)) {
                appendMessageRow({
                    message_id: null,
                    sender_type: 'admin',
                    sender_name: 'You',
                    message_content: message,
                    created_at: new Date().toISOString()
                });
            }

            if (messageText) {
                messageText.value = '';
                autoResizeTextarea(messageText);
                messageText.focus();
            }

            pollMessages();
            pollConversationSummary();
        })
        .catch((error) => {
            const errMessage = error && error.message ? error.message : 'Failed to send message.';
            if (typeof window.showAppToast === 'function') {
                window.showAppToast(errMessage, 'error');
            } else if (typeof window.showAlertModal === 'function') {
                window.showAlertModal(errMessage, 'error', 'Message Error');
            } else {
                alert(errMessage);
            }
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
            }
        });
    });
}

hydrateKnownMessageIds();
if (messagesContainer && messagesContainer.querySelector('.chat-row')) {
    ensureStartMarker();
}
scrollMessagesToBottom();

if (ACTIVE_SESSION_ID) {
    pollMessages();
    setInterval(pollMessages, 3000);
}
pollConversationSummary();
setInterval(pollConversationSummary, 5000);
</script>
<?php
$content = ob_get_clean();
include 'app.php';
?>
