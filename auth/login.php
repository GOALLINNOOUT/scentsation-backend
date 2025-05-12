<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Handle CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (empty($origin)) {
    $origin = isset($_SERVER['HTTP_REFERER']) ? rtrim($_SERVER['HTTP_REFERER'], '/') : '';
}
if (empty($origin)) {
    $origin = 'null'; // Handle requests from file:// protocol
}

header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    require_once __DIR__ . '/../config/cors_handler.php';
    require_once __DIR__ . '/../config/db_connect.php';
    
    // PHPMailer autoloader
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer-6.8.0/src/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer-6.8.0/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer-6.8.0/src/SMTP.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!isset($data['password']) || (!isset($data['email']) && !isset($data['phone']))) {
        throw new Exception('Email/Phone and password are required');
    }

    $pdo = getUsersConnection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Create users table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id VARCHAR(255) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone_number VARCHAR(20),
        status ENUM('active', 'suspended') DEFAULT 'active',
        role ENUM('user', 'admin') DEFAULT 'user',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(email),
        INDEX(status),
        INDEX(role)
    )");

    // Create required tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_carts (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        cart_data TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cart_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        cart_data TEXT NOT NULL,
        action VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS carts (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        status ENUM('active', 'completed', 'abandoned') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(status)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        read_status BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(read_status)
    )");

    // Drop and recreate user_sessions table
    $pdo->exec("DROP TABLE IF EXISTS user_sessions");
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        session_id VARCHAR(255) PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        token_expiry TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(token),
        INDEX(token_expiry)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        notification_id INT NOT NULL,
        read_status BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (notification_id) REFERENCES notifications(id),
        INDEX(user_id)
    )");

    // Drop and recreate cart_sessions table to ensure correct schema
    $pdo->exec("DROP TABLE IF EXISTS cart_sessions");
    $pdo->exec("CREATE TABLE cart_sessions (
        session_id VARCHAR(255) PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        cart_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 7 DAY),
        INDEX(user_id),
        INDEX(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Prepare the SQL query based on whether email or phone is provided
    if (isset($data['email'])) {
        $stmt = $pdo->prepare("SELECT user_id, name, email, password, phone_number as phone, status FROM users WHERE email = ? AND status != 'suspended'");
        $stmt->execute([$data['email']]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id, name, email, password, phone_number as phone, status FROM users WHERE phone_number = ? AND status != 'suspended'");
        $stmt->execute([$data['phone']]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($data['password'], $user['password'])) {
            // Create session record with prepared statement
            $session_id = bin2hex(random_bytes(32));
            $token = bin2hex(random_bytes(32));
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            // Get device model from user agent
            $device_model = "Unknown Device";
            if (preg_match('/(iPhone|iPad|iPod|Android|Windows Phone|Mobile|Tablet)/', $user_agent, $matches)) {
                $device_model = $matches[0];
            } elseif (preg_match('/\((.*?)\)/', $user_agent, $matches)) {
                $device_model = $matches[1];
            }

            $stmt = $pdo->prepare("INSERT INTO user_sessions (session_id, user_id, token, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$session_id, $user['user_id'], $token, $ip_address, $user_agent]);

            // Create login notification
            $login_time = date('Y-m-d H:i:s');
            $notification_message = "New login detected from {$device_model} (IP: {$ip_address}) at {$login_time}";
            
            // Insert notification
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)");
            $stmt->execute(['New Login Alert', $notification_message]);
            $notification_id = $pdo->lastInsertId();

            // Link notification to user
            $stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
            $stmt->execute([$user['user_id'], $notification_id]);

            // Send email notification
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'adeyekunadelola2009@gmail.com';
                $mail->Password = 'afhl rzxt czix hhsq';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('adeyekunadelola2009@gmail.com', 'Scentsation Security');
                $mail->addAddress($user['email'], $user['name']);

                $mail->isHTML(true);
                $mail->Subject = 'New Login Alert - Scentsation';
                $mail->Body = "
                    <h2>New Login Detected</h2>
                    <p>Hi {$user['name']},</p>
                    <p>We detected a new login to your Scentsation account:</p>
                    <ul>
                        <li><strong>Time:</strong> {$login_time}</li>
                        <li><strong>Device:</strong> {$device_model}</li>
                        <li><strong>IP Address:</strong> {$ip_address}</li>
                    </ul>
                    <p>If this wasn't you, please change your password immediately and contact support.</p>
                    <p>Best regards,<br>Scentsation Security Team</p>
                ";
                $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $mail->Body));
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Failed to send login notification email: " . $mail->ErrorInfo);
            }

            // Update last login time with prepared statement
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);

            // Get user's cart data after successful login
            $stmt = $pdo->prepare("
                SELECT cart_data 
                FROM user_carts 
                WHERE user_id = ?
            ");
            $stmt->execute([$user['user_id']]);
            $cartData = $stmt->fetch(PDO::FETCH_ASSOC);

            $cart = [];
            if ($cartData && $cartData['cart_data']) {
                $cart = json_decode($cartData['cart_data'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $cart = [];
                }
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'user_id' => $user['user_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'token' => $token
                ],
                'cart' => $cart
            ]);
        } else {
            throw new Exception('Incorrect password');
        }
    } else {
        throw new Exception('User not found or account suspended');
    }
} catch (Exception $e) {
    http_response_code(200); // Always return 200 with error in JSON
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($pdo)) {
        $pdo = null; // Close the PDO connection
    }
}
?>