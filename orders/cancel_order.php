<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    if (empty($data['orderId']) || empty($data['bankDetails'])) {
        throw new Exception('Missing required fields: orderId and bankDetails');
    }

    $orderId = $data['orderId'];
    $bankDetails = $data['bankDetails'];

    // Validate bank details
    if (empty($bankDetails['bankName']) || empty($bankDetails['accountNumber']) || empty($bankDetails['accountName'])) {
        throw new Exception('Missing required bank details');
    }

    // Get last 4 digits of account number for email
    $accountLastFour = substr($bankDetails['accountNumber'], -4);

    $pdo = getConnection();
    $pdo->beginTransaction();

    // Get order details with status information
    $stmt = $pdo->prepare("
        SELECT o.*, os.status_code as status
        FROM orders o
        JOIN order_status os ON o.status_id = os.id
        WHERE o.id = ? AND os.status_code NOT IN ('cancelled', 'processing')
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($order['status'] === 'cancelled') {
        throw new Exception('Order is already cancelled');
    }

    if ($order['status'] === 'processing') {
        throw new Exception('Cannot cancel order that is already being processed');
    }

    // Check if order is within cancellation window (3 hours)
    $orderDate = new DateTime($order['order_date']);
    $threeHoursAgo = new DateTime('-3 hours');
    if ($orderDate < $threeHoursAgo) {
        throw new Exception('Order cannot be cancelled as it is more than 3 hours old');
    }

    // Update order status using status_id
    $updateStmt = $pdo->prepare("
        UPDATE orders o 
        JOIN order_status os ON os.status_code = 'cancelled'
        SET o.status_id = os.id 
        WHERE o.id = ?
    ");
    $updateStmt->execute([$orderId]);

    // Store refund details
    $refundStmt = $pdo->prepare("
        INSERT INTO refunds (
            order_id, bank_name, account_number, account_name, 
            amount, status, created_at
        ) VALUES (
            ?, ?, ?, ?, 
            ?, 'pending', NOW()
        )
    ");
    $refundStmt->execute([
        $orderId,
        $bankDetails['bankName'],
        $bankDetails['accountNumber'],
        $bankDetails['accountName'],
        $order['total']
    ]);

    // Send email to customer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, 'Scentsation');
        $mail->addAddress($order['email'], $order['customer_name']);
        $mail->Subject = 'Order Cancellation Confirmation';
        
        $mail->isHTML(true);
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Order Cancellation Confirmation</h2>
                <p>Dear {$order['customer_name']},</p>
                <p>Your order #{$orderId} has been successfully cancelled.</p>
                <p>Refund Details:</p>
                <ul>
                    <li>Bank Name: {$bankDetails['bankName']}</li>
                    <li>Account Number: ****{$accountLastFour}</li>
                    <li>Account Name: {$bankDetails['accountName']}</li>
                    <li>Amount: ₦{$order['total']}</li>
                </ul>
                <p>Your refund will be processed within 24 hours.</p>
                <p>If you have any questions, please contact our support team.</p>
                <p>Best regards,<br>Scentsation Team</p>
            </div>
        ";

        $mail->send();

        // Send notification to admin
        $adminMail = new PHPMailer(true);
        $adminMail->isSMTP();
        $adminMail->Host = SMTP_HOST;
        $adminMail->SMTPAuth = true;
        $adminMail->Username = SMTP_USER;
        $adminMail->Password = SMTP_PASS;
        $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $adminMail->Port = SMTP_PORT;

        $adminMail->setFrom(SMTP_FROM_EMAIL, 'Scentsation System');
        $adminMail->addAddress(SMTP_FROM_EMAIL, 'Admin'); // Send to admin email
        $adminMail->Subject = "Order #{$orderId} Cancelled - Refund Required";
        
        $adminMail->isHTML(true);
        $adminMail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Order Cancellation Notice</h2>
                <p>Order #{$orderId} has been cancelled by the customer.</p>
                <p>Customer Details:</p>
                <ul>
                    <li>Name: {$order['customer_name']}</li>
                    <li>Email: {$order['email']}</li>
                </ul>
                <p>Refund Details:</p>
                <ul>
                    <li>Bank Name: {$bankDetails['bankName']}</li>
                    <li>Account Number: {$bankDetails['accountNumber']}</li>
                    <li>Account Name: {$bankDetails['accountName']}</li>
                    <li>Amount: ₦{$order['total']}</li>
                </ul>
                <p>Please process this refund within 24 hours.</p>
            </div>
        ";

        $adminMail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        // Continue with the transaction even if email fails
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully. You will receive a confirmation email shortly.'
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in cancel_order.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}