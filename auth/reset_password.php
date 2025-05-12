<?php
// Start output buffering
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set error handling
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getUsersConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate input
    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception('No input received');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!isset($data['token']) || !isset($data['password'])) {
        throw new Exception('Token and new password are required');
    }

    // Get the reset request details
    $stmt = $pdo->prepare("
        SELECT pr.user_id, pr.token, pr.expires_at, pr.used 
        FROM password_resets pr 
        WHERE pr.token = ? 
        AND pr.used = 0 
        AND pr.expires_at > NOW()
        ORDER BY pr.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$data['token']]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        throw new Exception('Invalid or expired reset token');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Update password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed_password, $reset['user_id']]);

        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$data['token']]);

        // Get user email for notification
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $stmt->execute([$reset['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Send email notification
        $mail = new PHPMailer();
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'adeyekunadelola2009@gmail.com';
        $mail->Password = 'afhl rzxt czix hhsq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Recipients
        $mail->setFrom('adeyekunadelola2009@gmail.com', 'Scentsation Support');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Changed - Scentsation';
        $mail->Body = "
            <h2>Password Change Confirmation</h2>
            <p>Hi {$user['name']},</p>
            <p>Your password has been successfully changed.</p>
            <p>If you did not request this change, please contact our support team immediately.</p>
            <p>Best regards,<br>Scentsation Security Team</p>
        ";
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $mail->Body));

        $mail->send();

        // Create notification in database
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message) 
            VALUES (?, ?)
        ");
        $stmt->execute(['Password Changed', 'Your password was successfully changed']);
        
        $notification_id = $pdo->lastInsertId();
        
        // Link notification to user
        $stmt = $pdo->prepare("
            INSERT INTO user_notifications (user_id, notification_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$reset['user_id'], $notification_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Failed to reset password: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while resetting your password: ' . $e->getMessage(),
        'debug_info' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

// Flush output buffer
ob_end_flush();