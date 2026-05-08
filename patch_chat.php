<?php
$bubble = 'src/html/components/chat_bubble.php';
$admin = 'src/app/frontend/chat-admin.php';

if (file_exists($bubble)) {
    $c = file_get_contents($bubble);
    
    // Fix loadMessages promise
    $c = str_replace('if (!sessionId) return;', 'if (!sessionId) return Promise.resolve();', $c);
    $c = str_replace("fetch('./backend", "return fetch('./backend", $c);
    
    // Fix openChat logic
    $c = str_replace(
        "chatInput.focus();\n        if (usePolling) {", 
        "chatInput.focus();\n        loadMessages().then(() => {\n            if (usePolling) {", 
        $c
    );
    $c = str_replace(
        "connectSSE();\n        }", 
        "connectSSE();\n            }\n        });", 
        $c
    );
    
    // Add duplicate check
    $c = str_replace(
        "const senderType", 
        "if (msg.message_id && document.querySelector(\`.chat-message-row[data-id=\"\${msg.message_id}\"]\`)) return;\n        const senderType", 
        $c
    );
    $c = str_replace(
        "row.className = 'chat-message-row chat-message-' + senderType;", 
        "row.className = 'chat-message-row chat-message-' + senderType;\n        if (msg.message_id) row.setAttribute('data-id', msg.message_id);", 
        $c
    );
    
    // Add SSE last_event_id
    $c = str_replace(
        "const url = './backend/chat/sse.php?type=customer&session_id=' + encodeURIComponent(sessionId);", 
        "const url = './backend/chat/sse.php?type=customer&session_id=' + encodeURIComponent(sessionId) + '&last_event_id=' + lastMessageId;", 
        $c
    );
    file_put_contents($bubble, $c);
    echo "Bubble patched!\n";
}

if (file_exists($admin)) {
    $c = file_get_contents($admin);
    
    // Add duplicate check
    $c = str_replace(
        "const senderType", 
        "const msgId = parseInt(msg.message_id, 10);\n        if (document.querySelector(\`div[data-id=\"\${msgId}\"]\`)) return;\n        const senderType", 
        $c
    );
    $c = str_replace(
        "div.className = 'flex ' + justifyClass;", 
        "div.className = 'flex ' + justifyClass;\n        div.setAttribute('data-id', msgId);", 
        $c
    );
    
    // Add SSE last_event_id
    $c = str_replace(
        "new EventSource('../../backend/chat/sse.php?type=admin&session_id=' + encodeURIComponent(currentSessionId));", 
        "new EventSource('../../backend/chat/sse.php?type=admin&session_id=' + encodeURIComponent(currentSessionId) + '&last_event_id=' + lastMessageId);", 
        $c
    );
    file_put_contents($admin, $c);
    echo "Admin patched!\n";
}
?>
