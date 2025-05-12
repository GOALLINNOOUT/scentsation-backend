<?php
require_once __DIR__ . '/../config/cors_handler.php';

session_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';

// Set specific origin instead of wildcard for credentialed requests
$allowed_origin = 'null';
if (isset($_SERVER['HTTP_ORIGIN'])) {
    if ($_SERVER['HTTP_ORIGIN'] === 'https://apiscentsation.great-site.net' ||
        $_SERVER['HTTP_ORIGIN'] === 'https://scentsation-admin.great-site.net' ||
        $_SERVER['HTTP_ORIGIN'] === 'https://scentsation.great-site.net') {
        $allowed_origin = $_SERVER['HTTP_ORIGIN'];
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . $allowed_origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    $pdo = getConnection();
    
    // Create chat_users table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_users (
        user_id VARCHAR(50) PRIMARY KEY,
        chat_id VARCHAR(50) NOT NULL,
        name VARCHAR(100),
        email VARCHAR(255),
        user_status ENUM('online', 'offline', 'away') DEFAULT 'offline',
        last_seen DATETIME,
        created_at DATETIME NOT NULL,
        last_active DATETIME NOT NULL,
        INDEX (chat_id),
        INDEX (user_status)
    )");

    // Create chat_messages table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        sender VARCHAR(50) NOT NULL,
        chat_id VARCHAR(50) NOT NULL,
        user_id VARCHAR(50),
        message_status VARCHAR(20) DEFAULT 'unread',
        timestamp DATETIME NOT NULL,
        INDEX (chat_id),
        INDEX (user_id),
        INDEX (message_status)
    )");    // Get request data
    $data = [];
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    // Try to get raw input first
    $rawInput = file_get_contents('php://input');
    
    if (!empty($rawInput)) {
        // Try to parse as JSON regardless of content type
        $jsonData = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $jsonData;
        }
    }
    
    // If we didn't get JSON data, check POST
    if (empty($data)) {
        $data = $_POST;
    }
    
    // If still empty and we have raw input, try to parse it as URL encoded
    if (empty($data) && !empty($rawInput)) {
        parse_str($rawInput, $data);
    }

    // Get action from either JSON or POST data
    $action = '';
    if (!empty($data['action'])) {
        $action = $data['action'];
    }
      // Check if request is from admin
    $isAdmin = isset($data['isAdmin']) ? filter_var($data['isAdmin'], FILTER_VALIDATE_BOOLEAN) : false;

    // Debug logging
    error_log("Received request - Action: " . $action . ", Is Admin: " . ($isAdmin ? 'true' : 'false'));
    error_log("Request content type: " . $contentType);
    error_log("Raw request data: " . file_get_contents('php://input'));
    error_log("Parsed request data: " . json_encode($data));

    if (empty($action)) {
        throw new Exception('Action is required');
    }

    switch($action) {
        case 'get_users':
            // Only admin can get all users
            if (!$isAdmin) {
                throw new Exception('Unauthorized access');
            }
            
            $stmt = $pdo->query("
                SELECT cu.*,
                       (SELECT message 
                        FROM chat_messages 
                        WHERE chat_id = cu.chat_id 
                        ORDER BY timestamp DESC 
                        LIMIT 1) as last_message,
                       (SELECT timestamp 
                        FROM chat_messages 
                        WHERE chat_id = cu.chat_id 
                        ORDER BY timestamp DESC 
                        LIMIT 1) as last_message_time,
                       (SELECT COUNT(*) 
                        FROM chat_messages 
                        WHERE chat_id = cu.chat_id 
                        AND message_status = 'unread' 
                        AND sender != 'admin') as unread_count
                FROM chat_users cu
                WHERE cu.user_id != 'admin'
                AND EXISTS (SELECT 1 FROM chat_messages WHERE chat_id = cu.chat_id)
                ORDER BY last_message_time DESC
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'users' => $users]);
            break;

        case 'start_chat':
            $user_id = isset($data['user_id']) ? $data['user_id'] : '';
            $name = isset($data['name']) ? $data['name'] : '';
            $email = isset($data['email']) ? $data['email'] : '';
            
            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }

            // For admin, create or update admin user
            if ($isAdmin && $user_id === 'admin') {
                $stmt = $pdo->prepare("
                    INSERT INTO chat_users (user_id, chat_id, name, user_status, created_at, last_active)
                    VALUES ('admin', 'admin_chat', 'Admin', 'online', NOW(), NOW())
                    ON DUPLICATE KEY UPDATE last_active = NOW(), user_status = 'online'
                ");
                $stmt->execute();
                echo json_encode(['status' => 'success', 'message' => 'Admin session started']);
                break;
            }
            
            $chat_id = 'chat_' . $user_id;
            $timestamp = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("
                INSERT INTO chat_users 
                    (user_id, chat_id, name, email, created_at, last_active, user_status)
                VALUES 
                    (?, ?, ?, ?, ?, ?, 'online')
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name),
                    email = VALUES(email),
                    last_active = VALUES(last_active),
                    user_status = VALUES(user_status)
            ");
            
            if ($stmt->execute([$user_id, $chat_id, $name, $email, $timestamp, $timestamp])) {
                $_SESSION['chat_id'] = $chat_id;
                $_SESSION['user_id'] = $user_id;
                echo json_encode([
                    'status' => 'success',
                    'chat_id' => $chat_id,
                    'message' => 'Chat session started'
                ]);
            } else {
                throw new Exception('Failed to start chat session');
            }
            break;

        case 'get_chat':
            $chat_id = isset($data['chat_id']) ? $data['chat_id'] : '';
            if (empty($chat_id)) {
                throw new Exception('Chat ID is required');
            }
            
            // Update last active timestamp
            if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("UPDATE chat_users SET last_active = NOW() WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
            
            $stmt = $pdo->prepare("
                SELECT cm.*, cu.name as user_name
                FROM chat_messages cm
                LEFT JOIN chat_users cu ON cm.user_id = cu.user_id
                WHERE cm.chat_id = ?
                ORDER BY cm.timestamp DESC
            ");
            $stmt->execute([$chat_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'messages' => $messages]);
            break;

        case 'send':
            $message = isset($data['message']) ? $data['message'] : '';
            $chat_id = isset($data['chat_id']) ? $data['chat_id'] : $_SESSION['chat_id'];
            $sender = $isAdmin ? 'admin' : 'user';
            $user_id = $isAdmin ? 'admin' : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
            
            if (empty($chat_id)) {
                throw new Exception('Chat ID is required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (message, sender, chat_id, user_id, timestamp) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$message, $sender, $chat_id, $user_id])) {
                if ($user_id) {
                    $stmt = $pdo->prepare("UPDATE chat_users SET last_active = NOW() WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                }
                echo json_encode(['status' => 'success', 'message' => 'Message sent']);
            } else {
                throw new Exception('Failed to send message');
            }
            break;

        case 'fetch':
            $lastId = isset($data['lastId']) ? (int)$data['lastId'] : 0;
            $chat_id = isset($data['chat_id']) ? $data['chat_id'] : $_SESSION['chat_id'];
            
            if (empty($chat_id)) {
                throw new Exception('Chat ID is required');
            }
            
            // Get messages
            $stmt = $pdo->prepare("
                SELECT cm.*, cu.name as user_name, cu.user_status 
                FROM chat_messages cm
                LEFT JOIN chat_users cu ON cm.user_id = cu.user_id
                WHERE cm.id > ? AND cm.chat_id = ? 
                ORDER BY cm.timestamp DESC
            ");
            $stmt->execute([$lastId, $chat_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get admin status
            $stmt = $pdo->prepare("
                SELECT user_status FROM chat_users 
                WHERE user_id = 'admin' 
                AND last_active >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
            ");
            $stmt->execute();
            $adminStatus = $stmt->fetchColumn();
            
            echo json_encode([
                'status' => 'success', 
                'messages' => $messages,
                'adminStatus' => $adminStatus
            ]);
            break;

        case 'mark_read':
            if (!$isAdmin) {
                throw new Exception('Unauthorized access');
            }
            
            $chat_id = isset($data['chat_id']) ? $data['chat_id'] : '';
            if (empty($chat_id)) {
                throw new Exception('Chat ID is required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE chat_messages 
                SET message_status = 'read' 
                WHERE chat_id = ? AND sender != 'admin'
            ");            $stmt->execute([$chat_id]);
            echo json_encode(['status' => 'success']);
            break;        case 'update_status':
            $status = isset($data['status']) ? $data['status'] : 'offline';

            // For admin users
            if ($isAdmin) {
                $user_id = 'admin';
                $stmt = $pdo->prepare("
                    INSERT INTO chat_users (user_id, chat_id, name, user_status, created_at, last_active)
                    VALUES ('admin', 'admin_chat', 'Admin', ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        user_status = VALUES(user_status),
                        last_active = VALUES(last_active)
                ");
                $stmt->execute([$status]);
                echo json_encode(['status' => 'success']);
                break;
            }
              // For regular users, we need both user_id and chat_id
            $chat_id = isset($data['chat_id']) ? $data['chat_id'] : (isset($_SESSION['chat_id']) ? $_SESSION['chat_id'] : null);
            $user_id = isset($data['user_id']) ? $data['user_id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
            
            // If we have a chat_id but no user_id, try to extract user_id from chat_id
            if ($chat_id && !$user_id && strpos($chat_id, 'chat_') === 0) {
                $user_id = substr($chat_id, 5); // Remove 'chat_' prefix
            }
            
            // Debug logging
            error_log("Update status check - Data user_id: " . (isset($data['user_id']) ? $data['user_id'] : 'not set'));
            error_log("Update status check - Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
            error_log("Update status check - Chat ID: " . ($chat_id ?: 'not set'));
            error_log("Update status check - Extracted user_id: " . ($user_id ?: 'not set'));
            
            if (!$user_id || !$chat_id) {
                // Don't throw an error, just update nothing and return success
                echo json_encode(['status' => 'success', 'message' => 'No authenticated user or chat session']);
                break;
            }
              // Verify the user exists in chat_users table
            $checkStmt = $pdo->prepare("SELECT 1 FROM chat_users WHERE user_id = ? AND chat_id = ?");
            $checkStmt->execute([$user_id, $chat_id]);
            
            if (!$checkStmt->fetch()) {
                // User not found in chat_users, try to create them
                $timestamp = date('Y-m-d H:i:s');
                $createStmt = $pdo->prepare("
                    INSERT IGNORE INTO chat_users 
                    (user_id, chat_id, user_status, created_at, last_active) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $createStmt->execute([$user_id, $chat_id, $status, $timestamp, $timestamp]);
            }
            
            // Now update the status
            $stmt = $pdo->prepare("
                UPDATE chat_users 
                SET user_status = ?, last_active = NOW() 
                WHERE user_id = ? AND chat_id = ?
            ");
            $stmt->execute([$status, $user_id, $chat_id]);

            // Also include the user status in the fetch response
            if ($status === 'typing') {
                $_SESSION['typing_status'] = true;
            } else {
                $_SESSION['typing_status'] = false;
            }

            echo json_encode(['status' => 'success']);
            break;

        default:
            throw new Exception('Invalid action. Available actions: get_users, start_chat, get_chat, send, fetch, mark_read, update_status');
    }

} catch (Exception $e) {
    error_log("Chat error: " . $e->getMessage());
    error_log("Request data: " . json_encode(isset($data) ? $data : []));
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'receivedData' => isset($data) ? $data : [],
            'contentType' => isset($contentType) ? $contentType : 'not set'
        ]
    ]);
}
?>