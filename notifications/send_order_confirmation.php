<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    $pdo = getUsersConnection();
    
    // Create notification
    $notificationTitle = "Order Confirmation #{$data['transactionReference']}";
    $notificationMessage = "Thank you for your order! Your order has been confirmed and will be delivered within 7 days.\n";
    $notificationMessage .= "Order Details:\n";
    
    // Format items for notification
    $itemsList = "";
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $itemsList .= "- {$item['name']} (Qty: {$item['quantity']}) - ₦" . number_format($item['price'] * $item['quantity'], 2) . "\n";
        $subtotal += $item['price'] * $item['quantity'];
    }

    // Add subtotal
    $notificationMessage .= "\nSubtotal: ₦" . number_format($subtotal, 2);

    // Get discount value safely
    $discount = 0;
    if (isset($data['discount']) && is_numeric($data['discount'])) {
        $discount = floatval($data['discount']);
    } elseif (isset($data['couponDetails']['discount']) && is_numeric($data['couponDetails']['discount'])) {
        $discount = floatval($data['couponDetails']['discount']);
    }

    // Add discount information if applicable
    if ($discount > 0) {
        $notificationMessage .= "\nDiscount Applied: ₦" . number_format($discount, 2);
    }

    // Add delivery fee and total
    $deliveryFee = floatval($data['deliveryFee'] ?? 0);
    $total = floatval($data['total'] ?? 0);

    $notificationMessage .= "\nDelivery Fee: ₦" . number_format($deliveryFee, 2);
    $notificationMessage .= "\nTotal Amount: ₦" . number_format($total, 2);
    $notificationMessage .= "\n\nDelivery Information:";
    $notificationMessage .= "\nState: {$data['state']}";
    $notificationMessage .= "\nLocation: {$data['location']}";

    // Add notification to database
    $stmt = $pdo->prepare("INSERT INTO notifications (title, message) VALUES (?, ?)");
    $stmt->execute([$notificationTitle, $notificationMessage . $itemsList]);
    $notification_id = $pdo->lastInsertId();

    // Link notification to user
    $stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
    $stmt->execute([$data['userId'], $notification_id]);

    // Prepare email content
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    // Enable verbose debug output
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    // Use ENCRYPTION_SMTPS for port 465 or ENCRYPTION_STARTTLS for port 587
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465; // Use 465 for SMTPS instead of 587
    
    // Set longer timeout and configure SSL options
    $mail->Timeout = 60;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, 'Scentsation by JC');
    $mail->addAddress($data['email'], $data['name']); // Changed from customerName to name
    $mail->isHTML(true);
    $mail->Subject = 'Order Confirmation - Scentsation by JC';

    // Format the email body with new styling
    $emailBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #FFF8EA; padding: 30px; border-radius: 10px;'>
        <div style='background-color: #1B3F8B; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; margin-bottom: 30px;'>
            <h1 style='color: #FFF8EA; margin: 0; font-size: 28px;'>Order Confirmation</h1>
        </div>

        <div style='background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
            <h2 style='color: #1B3F8B; margin-bottom: 20px; text-align: center;'>Thank you for your order!</h2>
            
            <p style='color: #1B3F8B; font-size: 16px; margin-bottom: 20px;'>Dear {$data['name']},</p> <!-- Changed from customerName to name -->
            
            <p style='color: #555; line-height: 1.6;'>Your order has been received and is being processed. Here are your order details:</p>
            
            <div style='background-color: #FFF8EA; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #1B3F8B; border-bottom: 2px solid #1B3F8B; padding-bottom: 10px; margin-bottom: 20px;'>Order Summary</h3>
                
                <table style='width: 100%; border-collapse: collapse;'>
                    <thead>
                        <tr style='background-color: #1B3F8B;'>
                            <th style='padding: 12px; color: #FFF8EA; text-align: left; border-radius: 6px 0 0 0;'>Item</th>
                            <th style='padding: 12px; color: #FFF8EA; text-align: center;'>Quantity</th>
                            <th style='padding: 12px; color: #FFF8EA; text-align: right;'>Price</th>
                            <th style='padding: 12px; color: #FFF8EA; text-align: right; border-radius: 0 6px 0 0;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>";

    foreach ($data['items'] as $item) {
        $itemPrice = floatval($item['price']);
        $itemQty = intval($item['quantity']);
        $itemTotal = $itemPrice * $itemQty;
        $emailBody .= "
                        <tr style='border-bottom: 1px solid #E8E0D5;'>
                            <td style='padding: 12px; color: #1B3F8B;'>{$item['name']}</td>
                            <td style='padding: 12px; color: #1B3F8B; text-align: center;'>{$itemQty}</td>
                            <td style='padding: 12px; color: #1B3F8B; text-align: right;'>₦" . number_format($itemPrice, 2) . "</td>
                            <td style='padding: 12px; color: #1B3F8B; text-align: right;'>₦" . number_format($itemTotal, 2) . "</td>
                        </tr>";
    }

    $emailBody .= "
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding: 12px; text-align: right; font-weight: bold; color: #1B3F8B;'>Subtotal:</td>
                            <td style='padding: 12px; text-align: right; color: #1B3F8B;'>₦" . number_format($subtotal, 2) . "</td>
                        </tr>";

    if ($discount > 0) {
        $emailBody .= "
                        <tr>
                            <td colspan='3' style='padding: 12px; text-align: right; font-weight: bold; color: #1B3F8B;'>Discount:</td>
                            <td style='padding: 12px; text-align: right; color: #E63946;'>-₦" . number_format($discount, 2) . "</td>
                        </tr>";
    }

    $emailBody .= "
                        <tr>
                            <td colspan='3' style='padding: 12px; text-align: right; font-weight: bold; color: #1B3F8B;'>Delivery Fee:</td>
                            <td style='padding: 12px; text-align: right; color: #1B3F8B;'>₦" . number_format($deliveryFee, 2) . "</td>
                        </tr>
                        <tr style='background-color: #1B3F8B;'>
                            <td colspan='3' style='padding: 12px; text-align: right; font-weight: bold; color: #FFF8EA; border-radius: 6px 0 0 6px;'>Total Amount:</td>
                            <td style='padding: 12px; text-align: right; font-weight: bold; color: #FFF8EA; border-radius: 0 6px 6px 0;'>₦" . number_format($total, 2) . "</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style='background-color: #FFF8EA; padding: 20px; border-radius: 8px; margin-top: 30px;'>
                <h3 style='color: #1B3F8B; border-bottom: 2px solid #1B3F8B; padding-bottom: 10px; margin-bottom: 20px;'>Delivery Information</h3>
                <p style='margin: 10px 0; color: #1B3F8B;'><strong>Location:</strong> " . (isset($data['location']) ? $data['location'] : 'Not provided') . "</p>
                <p style='margin: 10px 0; color: #1B3F8B;'><strong>State:</strong> " . (isset($data['state']) ? $data['state'] : 'Not provided') . "</p>
                <p style='margin: 10px 0; color: #1B3F8B;'><strong>Phone Number:</strong> " . (isset($data['phoneNumber']) ? $data['phoneNumber'] : 'Not provided') . "</p>
            </div>

            <div style='text-align: center; margin-top: 30px; padding: 20px; background-color: #FFF8EA; border-radius: 8px;'>
                <p style='color: #1B3F8B; font-size: 16px; margin-bottom: 15px;'>We will notify you when your order has been shipped.</p>
                <p style='color: #1B3F8B;'>For any questions, please:</p>
                <ul style='list-style: none; padding: 0; margin: 15px 0;'>
                    <li style='margin: 10px 0;'>
                        <a href='mailto:support@scentsation.com' style='color: #1B3F8B; text-decoration: underline;'>Email us at support@scentsation.com</a>
                    </li>
                    <li style='margin: 10px 0;'>
                        <a href='http://scentsation_api.local/scentsation/contact.html' style='color: #1B3F8B; text-decoration: underline;'>Visit our Contact Page</a>
                    </li>
                </ul>
            </div>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #E8E0D5; text-align: center;'>
                <p style='font-size: 12px; color: #777;'>
                    This is an automated message, please do not reply to this email.<br>
                    If you need assistance, please use the contact options above.
                </p>
            </div>
        </div>
    </div>";

    $mail->Body = $emailBody;
    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Order confirmation email sent successfully'
    ]);

} catch (Exception $e) {
    error_log("Failed to send order confirmation email: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send order confirmation email: ' . $e->getMessage()
    ]);
}
?>