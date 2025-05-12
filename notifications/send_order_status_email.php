<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    // Get both database connections
    $usersPdo = getUsersConnection();
    $mainPdo = getMainConnection();
    
    // Get and validate input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !isset($data['order_id']) || !isset($data['status']) || !isset($data['user_id'])) {
        throw new Exception('Missing required fields: order_id, status, and user_id');
    }

    // Get user email - using users database
    $stmt = $usersPdo->prepare("SELECT email, name FROM users WHERE user_id = ?");
    $stmt->execute([$data['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Get order details - using main database
    $stmt = $mainPdo->prepare("
        SELECT o.*, GROUP_CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price SEPARATOR ',') as items_data
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$data['order_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Parse items data into array
    $order['items'] = [];
    if (!empty($order['items_data'])) {
        $items = explode(',', $order['items_data']);
        foreach ($items as $item) {
            list($product_name, $quantity, $price) = explode(':', $item);
            $order['items'][] = [
                'product_name' => $product_name,
                'quantity' => (int)$quantity,
                'price' => (float)$price
            ];
        }
    }

    // Create status message based on new status
    $statusMessages = [
        'pending' => 'Your order has been received and is pending processing.',
        'processing' => 'Your order is now being processed.',
        'shipped' => 'Your order has been shipped and is on its way! It will get to you today or tomorrow.',
        'delivered' => 'Your order has been delivered. Thank you for shopping with us!',
        'cancelled' => 'Your order has been cancelled.'
    ];

    $statusMessage = $statusMessages[$data['status']] ?? 'Your order status has been updated.';

    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Additional debug settings for development
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug: $str");
        };

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, 'Scentsation Store');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order #{$data['order_id']} Status Update";
        
        // Create email body with enhanced styling
        $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #333333; margin: 0;'>Order Status Update</h1>
                    <p style='color: #666666; font-size: 16px; margin-top: 10px;'>Order #{$data['order_id']}</p>
                </div>

                <div style='margin-bottom: 30px;'>
                    <p style='font-size: 16px; color: #333333;'>Dear {$user['name']},</p>
                    <p style='font-size: 16px; line-height: 1.5; color: #333333; background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        {$statusMessage}
                    </p>
                </div>

                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;'>
                    <h2 style='color: #333333; font-size: 18px; margin-top: 0; margin-bottom: 15px;'>Order Details</h2>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <thead>
                            <tr style='border-bottom: 2px solid #dee2e6;'>
                                <th style='text-align: left; padding: 12px 8px; color: #666666;'>Product</th>
                                <th style='text-align: center; padding: 12px 8px; color: #666666;'>Quantity</th>
                                <th style='text-align: right; padding: 12px 8px; color: #666666;'>Price</th>
                                <th style='text-align: right; padding: 12px 8px; color: #666666;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>";

        // Add order items
        foreach ($order['items'] as $item) {
            $itemTotal = $item['quantity'] * $item['price'];
            $emailBody .= "
                            <tr style='border-bottom: 1px solid #dee2e6;'>
                                <td style='padding: 12px 8px; color: #333333;'>{$item['product_name']}</td>
                                <td style='text-align: center; padding: 12px 8px; color: #333333;'>{$item['quantity']}</td>
                                <td style='text-align: right; padding: 12px 8px; color: #333333;'>₦" . number_format($item['price'], 2) . "</td>
                                <td style='text-align: right; padding: 12px 8px; color: #333333;'>₦" . number_format($itemTotal, 2) . "</td>
                            </tr>";
        }

        // Calculate subtotal (total - delivery fee + discount)
        $subtotal = $order['total'] - ($order['deliveryFee'] ?? 0) + ($order['discount'] ?? 0);

        $emailBody .= "
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan='3' style='text-align: right; padding: 12px 8px; color: #666666;'><strong>Subtotal:</strong></td>
                                <td style='text-align: right; padding: 12px 8px; color: #333333;'>₦" . number_format($subtotal, 2) . "</td>
                            </tr>";

        // Add discount if applicable
        if (!empty($order['discount'])) {
            $emailBody .= "
                            <tr>
                                <td colspan='3' style='text-align: right; padding: 12px 8px; color: #666666;'><strong>Discount:</strong></td>
                                <td style='text-align: right; padding: 12px 8px; color: #333333;'>-₦" . number_format($order['discount'], 2) . "</td>
                            </tr>";
        }

        // Add delivery fee if applicable
        if (!empty($order['deliveryFee'])) {
            $emailBody .= "
                            <tr>
                                <td colspan='3' style='text-align: right; padding: 12px 8px; color: #666666;'><strong>Delivery Fee:</strong></td>
                                <td style='text-align: right; padding: 12px 8px; color: #333333;'>₦" . number_format($order['deliveryFee'], 2) . "</td>
                            </tr>";
        }

        // Add total
        $emailBody .= "
                            <tr>
                                <td colspan='3' style='text-align: right; padding: 12px 8px; font-weight: bold; color: #333333;'><strong>Total:</strong></td>
                                <td style='text-align: right; padding: 12px 8px; font-weight: bold; color: #333333;'>₦" . number_format($order['total'], 2) . "</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style='margin-top: 30px;'>
                    <h2 style='color: #333333; font-size: 18px; margin-bottom: 15px;'>Delivery Information</h2>
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px;'>
                        <p style='margin: 5px 0; color: #333333;'><strong>Address:</strong> {$order['street_address']}" . 
                        (!empty($order['apartment']) ? ", {$order['apartment']}" : "") . "</p>
                        <p style='margin: 5px 0; color: #333333;'><strong>City:</strong> {$order['city']}</p>
                        <p style='margin: 5px 0; color: #333333;'><strong>State:</strong> {$order['state']}</p>
                        <p style='margin: 5px 0; color: #333333;'><strong>Location:</strong> {$order['location']}</p>
                    </div>
                </div>

                <div style='margin-top: 30px; text-align: center; color: #666666;'>
                    <p>If you have any questions about your order, please don't hesitate to contact us.</p>
                    <p style='margin-top: 20px;'>Thank you for choosing Scentsation!</p>
                </div>
            </div>
        ";
        
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $emailBody));

        $mail->send();
        
        echo json_encode([
            'success' => true,
            'message' => 'Status update email sent successfully'
        ]);

    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        throw new Exception('Error sending email: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Error in send_order_status_email.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}