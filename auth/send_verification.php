<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';

try {
    require_once __DIR__ . '/../config/db_connect.php';
    $pdo = getUsersConnection();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email'])) {
        throw new Exception('Email is required');
    }

    // Generate a 6-digit verification code
    $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store the verification code
    $stmt = $pdo->prepare("INSERT INTO verification_codes (email, code, expires_at) VALUES (?, ?, ?)");
    $expiryTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $stmt->execute([$data['email'], $verificationCode, $expiryTime]);

    error_log("Sending verification email to: " . $data['email']);

    // Send email
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug: $str");
        };
        
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Add SSL options to handle potential certificate issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Increase timeout values
        $mail->Timeout = 60; // TCP timeout
        $mail->SMTPKeepAlive = true; // Don't close the connection between messages

        error_log("SMTP configuration complete");

        // Recipients
        $mail->setFrom('adeyekunadelola2009@gmail.com', 'Scentsation Support');
        $mail->addAddress($data['email']);

        error_log("Recipients configured");

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - Scentsation';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4CAF50;'>Welcome to Scentsation!</h2>
                <p>Your verification code is: <strong style='font-size: 24px; color: #333;'>{$verificationCode}</strong></p>
                <p>This code will expire in 15 minutes.</p>
                <p style='color: #666;'>If you didn't request this code, please ignore this email.</p>
                <hr>
                <p style='font-size: 12px; color: #999;'>This is an automated message, please do not reply.</p>
            </div>
        ";
        $mail->AltBody = "Your verification code is: {$verificationCode}\nThis code will expire in 5 minutes.";

        error_log("Attempting to send email");
        
        $mail->send();
        error_log("Email sent successfully");
        
        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent successfully'
        ]);

    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        throw new Exception('Failed to send verification email. Please try again later.');
    }

} catch (Exception $e) {
    error_log("Error in send_verification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($pdo)) {
        $pdo = null;
    }
}