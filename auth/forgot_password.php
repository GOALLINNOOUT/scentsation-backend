<?php
require_once 'cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/config.php';

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// PHPMailer classes
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    $pdo = getUsersConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email'])) {
        throw new Exception('Email is required');
    }

    error_log("Processing password reset request for email: " . $data['email']);

    // Check if email exists
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("Email not found in database: " . $data['email']);
        // For security, don't reveal if email exists or not
        echo json_encode([
            'success' => true,
            'message' => 'If your email is registered, you will receive reset instructions shortly.'
        ]);
        exit;
    }

    error_log("User found, generating reset token");

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store reset token
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['user_id'], $token, $expires]);

    error_log("Reset token stored in database");

    // Create reset link
    $resetLink = "http://apiscentsation.great-site.net/reset-password.html?token=" . $token;

    // Create new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        error_log("Configuring email settings");
        
        // Server settings
        $mail->SMTPDebug = 3;  // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer [$level] : $str");
        };
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'adeyekunadelola2009@gmail.com';
        $mail->Password = 'afhl rzxt czix hhsq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        error_log("Setting up email content");

        // Recipients
        $mail->setFrom('adeyekunadelola2009@gmail.com', 'Scentsation Support');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Scentsation';
        $mail->Body = "Hi " . htmlspecialchars($user['name']) . ",<br><br>"
            . "We received a request to reset your password. Click the link below to reset it:<br><br>"
            . "<a href='" . $resetLink . "'>" . $resetLink . "</a><br><br>"
            . "This link will expire in 1 hour.<br><br>"
            . "If you didn't request this, please ignore this email.<br><br>"
            . "Best regards,<br>Scentsation Team";
        $mail->AltBody = "Hi " . $user['name'] . ",\n\n"
            . "We received a request to reset your password. Click the link below to reset it:\n\n"
            . $resetLink . "\n\n"
            . "This link will expire in 1 hour.\n\n"
            . "If you didn't request this, please ignore this email.\n\n"
            . "Best regards,\nScentsation Team";

        error_log("Attempting to send email");
        $mail->send();
        error_log("Email sent successfully");
        
        echo json_encode([
            'success' => true,
            'message' => 'Password reset instructions have been sent to your email.'
        ]);

    } catch (Exception $e) {
        error_log("Failed to send password reset email: " . $mail->ErrorInfo);
        throw new Exception('Failed to send reset email: ' . $mail->ErrorInfo);
    }

} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again later.'
    ]);
}
?>