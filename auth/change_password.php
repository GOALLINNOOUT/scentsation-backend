<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set allowed origins
$allowed_origins = [
    'https://apiscentsation.great-site.net',
        'https://scentsation-admin.great-site.net',
        'https://scentsation.great-site.net'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getUsersConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['user_id']) || !isset($data['current_password']) || !isset($data['new_password'])) {
        throw new Exception('Missing required fields');
    }

    // Validate new password
    if (strlen($data['new_password']) < 8 ||
        !preg_match('/[A-Z]/', $data['new_password']) ||
        !preg_match('/[a-z]/', $data['new_password']) ||
        !preg_match('/[0-9]/', $data['new_password']) ||
        !preg_match('/[!@#$%^&*.]/', $data['new_password'])) {
        throw new Exception('New password does not meet requirements');
    }

    // Get user's current password
    $stmt = $pdo->prepare("SELECT password, name, email FROM users WHERE user_id = ?");
    $stmt->execute([$data['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Verify current password
    if (!password_verify($data['current_password'], $user['password'])) {
        throw new Exception('Current password is incorrect');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Update password
        $hashed_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed_password, $data['user_id']]);

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
            <p>If you did not make this change, please contact our support team immediately.</p>
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
        $stmt->execute([$data['user_id'], $notification_id]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Failed to change password: ' . $e->getMessage());
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}