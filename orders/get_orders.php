<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/cors_handler.php';
require_once __DIR__ . '/../auth/validate_token.php';
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Get user_id from POST request
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($data['user_id']) ? $data['user_id'] : null;

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        exit;
    }

    $pdo = getConnection();

    // Create or update tables with correct structure
    $create_orders = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phoneNumber VARCHAR(20),
        street_address VARCHAR(255),
        apartment VARCHAR(100),
        city VARCHAR(100),
        state VARCHAR(100),
        zip_code VARCHAR(20),
        location TEXT,
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        deliveryFee DECIMAL(10,2),
        discount DECIMAL(10,2) DEFAULT 0.00,
        coupon_code VARCHAR(50),
        transactionReference VARCHAR(255),
        status_id INT,
        user_id VARCHAR(255),
        INDEX idx_email (email),
        INDEX idx_phone (phoneNumber),
        INDEX idx_status_id (status_id),
        INDEX idx_user_id (user_id)
    )";

    $create_items = "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    )";

    $create_status = "CREATE TABLE IF NOT EXISTS order_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status_code VARCHAR(50) NOT NULL,
        status_name VARCHAR(255) NOT NULL
    )";

    $pdo->exec($create_orders);
    $pdo->exec($create_items);
    $pdo->exec($create_status);

    // Check if a specific order ID was requested
    $single_order_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    // Query to get order details
    $query = "SELECT o.id, o.customer_name, o.email, o.phoneNumber,
              o.street_address, o.apartment, o.city, o.state, o.zip_code, o.location,
              o.alternative_contact_name, o.alternative_contact_phone,
              o.order_date as date, o.total, o.deliveryFee, o.transactionReference,
              o.discount, o.coupon_code,
              os.status_code as status, os.status_name as statusText,
              GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR ',') as items
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              JOIN order_status os ON o.status_id = os.id
              WHERE o.user_id = :user_id";

    if ($single_order_id) {
        $query .= " AND o.id = :id";
    }

    $query .= " GROUP BY o.id ORDER BY o.order_date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);

    if ($single_order_id) {
        $stmt->bindParam(':id', $single_order_id, PDO::PARAM_INT);
    }

    $stmt->execute();

    $orders = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Parse the items string into an array
        $itemsArray = array();
        if (!empty($row['items'])) {
            $items = explode(',', $row['items']);
            foreach ($items as $item) {
                list($name, $quantity, $price) = explode(':', $item);
                $itemsArray[] = array(
                    'name' => $name,
                    'quantity' => (int)$quantity,
                    'price' => (float)$price
                );
            }
        }

        // Format the order data
        $orderData = array(
            'id' => (int)$row['id'],
            'customer_name' => $row['customer_name'],
            'email' => $row['email'],
            'phoneNumber' => $row['phoneNumber'],
            'street_address' => $row['street_address'],
            'apartment' => $row['apartment'],
            'city' => $row['city'],
            'state' => $row['state'],
            'zip_code' => $row['zip_code'],
            'location' => $row['location'],
            'date' => $row['date'],
            'total' => (float)$row['total'],
            'status' => $row['status'],
            'statusText' => $row['statusText'],
            'deliveryFee' => (float)$row['deliveryFee'],
            'transactionReference' => $row['transactionReference'],
            'discount' => (float)$row['discount'],
            'coupon_code' => $row['coupon_code'],
            'items' => $itemsArray
        );

        if ($single_order_id) {
            // Return single order directly
            echo json_encode([
                'success' => true,
                'order' => $orderData
            ]);
            exit;
        }

        $orders[] = $orderData;
    }

    // Return all orders if no specific ID was requested
    if (!$single_order_id) {
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
    } else {
        // If we got here with a single_order_id, the order wasn't found
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Order not found'
        ]);
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Error in get_orders.php: " . $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching orders: ' . $e->getMessage()
    ]);
}
?>