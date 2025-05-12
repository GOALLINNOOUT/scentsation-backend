<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Get the requesting origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = array(
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net'
);

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400"); // Cache preflight for 24 hours
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/Exception.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    // Get users database connection
    $pdo = getUsersConnection();
    
    // Get and validate input
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input || !isset($input['title']) || !isset($input['message']) || !isset($input['users']) || !is_array($input['users'])) {
        throw new Exception('Missing or invalid required fields');
    }

    // Validate delivery method
    $validDeliveryMethods = ['both', 'email', 'app'];
    $deliveryMethod = isset($input['deliveryMethod']) ? $input['deliveryMethod'] : 'both';
    if (!in_array($deliveryMethod, $validDeliveryMethods)) {
        throw new Exception('Invalid delivery method');
    }

    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert into notifications table
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, created_at)
            VALUES (:title, :message, NOW())
        ");
        
        $stmt->execute([
            ':title' => $input['title'],
            ':message' => $input['message']
        ]);
        
        $notification_id = $pdo->lastInsertId();
        
        // Insert into user_notifications table for each user if app notification is needed
        if ($deliveryMethod === 'both' || $deliveryMethod === 'app') {
            $stmt = $pdo->prepare("
                INSERT INTO user_notifications (user_id, notification_id, read_status)
                VALUES (:user_id, :notification_id, FALSE)
            ");
            
            foreach ($input['users'] as $user_id) {
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':notification_id' => $notification_id
                ]);
            }
        }
        
        // Send email if email delivery is needed
        if ($deliveryMethod === 'both' || $deliveryMethod === 'email') {
            // Get user emails
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE user_id IN (" . str_repeat('?,', count($input['users']) - 1) . "?)");
            $stmt->execute($input['users']);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create a new PHPMailer instance for sending emails
            $mail = new PHPMailer(true);
            
            try {
                // Server settings with better error handling
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    error_log("SMTP Debug: $str");
                };
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                
                // Use TLS instead of SSL
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Set longer timeout and retry options
                $mail->Timeout = 60;
                $mail->SMTPKeepAlive = true;
                
                // SSL/TLS options for Windows compatibility
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Retry logic
                $maxRetries = 3;
                $retryCount = 0;
                while ($retryCount < $maxRetries) {
                    try {
                        $mail->smtpConnect();
                        break;
                    } catch (Exception $e) {
                        $retryCount++;
                        error_log("SMTP retry $retryCount: " . $e->getMessage());
                        if ($retryCount < $maxRetries) sleep(2);
                        else throw $e;
                    }
                }
                
                // Set sender
                $mail->setFrom(SMTP_FROM_EMAIL, 'Scentsation Store');
                
                // Send emails to each user
                foreach ($users as $user) {
                    try {
                        // Clear previous recipients
                        $mail->clearAllRecipients();
                        
                        $mail->addAddress($user['email'], $user['name']);
                        $mail->Subject = $input['title'];
                        
                        // Create HTML message
                        $mailContent = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                <h2>{$input['title']}</h2>
                                <p>{$input['message']}</p>
                                <hr>
                                <p style='font-size: 12px; color: #666;'>This is an automated notification from Scentsation Store.</p>
                            </div>
                        ";
                        
                        $mail->isHTML(true);
                        $mail->Body = $mailContent;
                        $mail->AltBody = strip_tags($input['message']);
                        
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Email sending failed for user {$user['email']}: " . $e->getMessage());
                    }
                }
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
                // Don't throw the exception - we still want to save the notification in the database
                // Just log the error and continue
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent successfully',
            'notification_id' => $notification_id,
            'delivery_method' => $deliveryMethod
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in send_notification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send notification: ' . $e->getMessage()
    ]);
}