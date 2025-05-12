<?php
require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getUsersConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get POST data
        $product_id = $_POST['product_id'] ?? null;
        $product_name = $_POST['product_name'] ?? '';
        $message = $_POST['message'] ?? '';
        
        if (!$message) {
            throw new Exception('Message is required');
        }

        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (product_id, product_name, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$product_id, $product_name, $message]);
        
        echo json_encode(['success' => true, 'message' => 'Notification sent successfully']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Fetch all notifications
        $stmt = $pdo->query("SELECT * FROM admin_notifications ORDER BY created_at DESC");
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($notifications);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        if (isset($_POST['notification_id'])) {
            // Delete specific notification
            $stmt = $pdo->prepare("DELETE FROM admin_notifications WHERE id = ?");
            $stmt->execute([$_POST['notification_id']]);
        } else {
            // Clear all notifications
            $stmt = $pdo->query("DELETE FROM admin_notifications");
        }
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Error in notify_admin.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}