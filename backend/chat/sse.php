<?php
/**
 * Chat SSE Endpoint
 * Server-Sent Events stream for real-time chat updates.
 *
 * Query params:
 *   type       — 'customer' or 'admin'
 *   session_id — required for customer, optional for admin (admin gets all sessions)
 *
 * Behavior:
 *   - Streams 'new_message' events as they arrive
 *   - Admin also gets 'new_conversation' events
 *   - 30s timeout, client auto-reconnects
 *   - 15s heartbeat ping
 *   - 500ms DB polling loop
 *   - Uses Last-Event-ID for reconnect recovery
 */

// Disable output buffering for streaming
while (ob_get_level()) {
    ob_end_flush();
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ob_implicit_flush(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering if present

require_once '../../database/connect_database.php';


// ADD THESE TWO LINES TO FIX THE FREEZING
if (session_status() == PHP_SESSION_NONE) { session_start(); }
session_write_close(); // This unlocks the app so you can navigate!

$type = $_GET['type'] ?? 'customer';

$sessionId = $_GET['session_id'] ?? '';

if ($type === 'customer' && empty($sessionId)) {
    echo "event: error\ndata: " . json_encode(['message' => 'session_id is required for customers']) . "\n\n";
    flush();
    exit;
}

// Last-Event-ID is auto-set by EventSource on reconnect
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID'])
    ? (int)$_SERVER['HTTP_LAST_EVENT_ID']
    : 0;

// Also check query param as fallback
if ($lastEventId === 0 && isset($_GET['last_event_id'])) {
    $lastEventId = (int)$_GET['last_event_id'];
}

$startTime = time();
$timeout = 30; // seconds
$heartbeatInterval = 15;
$pollInterval = 500000; // 500ms in microseconds
$lastHeartbeat = time();

/**
 * Send an SSE event
 */
function sendEvent($eventName, $data, $id = null) {
    if ($id !== null) {
        echo "id: " . $id . "\n";
    }
    echo "event: " . $eventName . "\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    flush();
}

/**
 * Send a heartbeat comment (keeps connection alive through proxies)
 */
function sendHeartbeat() {
    echo ": ping " . time() . "\n\n";
    flush();
}

/**
 * Fetch new messages since the given lastEventId
 */
function fetchNewMessages($pdo, $type, $sessionId, $lastEventId) {
    if ($type === 'admin') {
        // Admin: all new messages across all sessions
        $stmt = $pdo->prepare("
            SELECT message_id, sender_id, sender_name, sender_email,
                   sender_type, message_content, session_id, created_at, is_read
            FROM chat_messages
            WHERE message_id > :last_id
            ORDER BY message_id ASC
            LIMIT 50
        ");
        $stmt->execute([':last_id' => $lastEventId]);
    } else {
        // Customer: only messages in their session
        $stmt = $pdo->prepare("
            SELECT message_id, sender_id, sender_name, sender_email,
                   sender_type, message_content, session_id, created_at, is_read
            FROM chat_messages
            WHERE message_id > :last_id
              AND session_id = :session_id
            ORDER BY message_id ASC
            LIMIT 50
        ");
        $stmt->execute([
            ':last_id' => $lastEventId,
            ':session_id' => $sessionId
        ]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * For admin: check if any new conversation sessions have appeared
 */
function fetchNewSessions($pdo, $lastEventId) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT session_id,
               MAX(CASE WHEN sender_type = 'customer' THEN sender_name END) as sender_name,
               MAX(CASE WHEN sender_type = 'customer' THEN sender_email END) as sender_email,
               MAX(created_at) as last_message_time
        FROM chat_messages
        WHERE message_id > :last_id
          AND session_id IS NOT NULL AND session_id != ''
        GROUP BY session_id
    ");
    $stmt->execute([':last_id' => $lastEventId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Main SSE loop
while ((time() - $startTime) < $timeout) {

    // Heartbeat
    if ((time() - $lastHeartbeat) >= $heartbeatInterval) {
        sendHeartbeat();
        $lastHeartbeat = time();
    }

    // Check for new messages
    $messages = fetchNewMessages($pdo, $type, $sessionId, $lastEventId);

    if (!empty($messages)) {
        foreach ($messages as $msg) {
            sendEvent('new_message', $msg, (string)$msg['message_id']);
            $lastEventId = (int)$msg['message_id'];
        }

        // For admin, also notify about new sessions
        if ($type === 'admin') {
            $newSessions = fetchNewSessions($pdo, $lastEventId);
            foreach ($newSessions as $session) {
                sendEvent('new_conversation', $session);
            }
        }
    }

    // Sleep 500ms before next poll
    usleep($pollInterval);
}

// Graceful timeout — client EventSource will auto-reconnect
sendEvent('timeout', ['message' => 'Reconnecting...']);
flush();
