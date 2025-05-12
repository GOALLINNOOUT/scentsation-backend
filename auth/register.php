<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once 'cors_handler.php';

try {
    require_once __DIR__ . '/../config/db_connect.php';
    $pdo = getUsersConnection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }

        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || !isset($data['verificationCode'])) {
            throw new Exception('Missing required fields');
        }

        // Verify the code
        $stmt = $pdo->prepare("SELECT * FROM verification_codes 
            WHERE email = ? AND code = ? AND used = FALSE AND expires_at > NOW() 
            ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$data['email'], $data['verificationCode']]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verification) {
            throw new Exception('Invalid or expired verification code');
        }

        // Mark verification code as used
        $stmt = $pdo->prepare("UPDATE verification_codes SET used = TRUE WHERE id = ?");
        $stmt->execute([$verification['id']]);

        // Check if email already exists using prepared statement
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Email already registered'
            ]);
            exit;
        }
        
        // Generate secure user ID (UUID v4)
        $user_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert new user with prepared statement
        $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, phone_number) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$user_id, $data['name'], $data['email'], $hashed_password, $data['phone'] ?? null])) {
            // Create initial cart with prepared statement
            $stmt = $pdo->prepare("INSERT INTO carts (user_id, status) VALUES (?, 'active')");
            $stmt->execute([$user_id]);
            $cart_id = $pdo->lastInsertId();
            
            // Create welcome notification for user
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)");
            $stmt->execute([
                'Welcome to Scentsation!',
                'Thank you for joining Scentsation. Explore our collection of amazing fragrances!'
            ]);
            $notification_id = $pdo->lastInsertId();
            
            // Link notification to user
            $stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $notification_id]);

            // Create admin notification for new user registration
            $stmt = $pdo->prepare("INSERT INTO admin_notifications (product_id, product_name, message) VALUES (0, ?, ?)");
            $stmt->execute([
                'New User Registration',
                "New user registered: {$data['name']} ({$data['email']})"
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $user_id,
                'cart_id' => $cart_id
            ]);
        } else {
            throw new Exception('Registration failed');
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($pdo)) {
        $pdo = null; // Close the connection
    }
}
?>