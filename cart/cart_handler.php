<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/cors_handler.php';

// Content-Type header after CORS is handled
header("Content-Type: application/json");

// Start output buffering
ob_start();

require_once __DIR__ . '/../config/config.php';

try {
    // Use users database connection for cart operations
    $pdo = getUsersConnection();

    // Create user_carts table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_carts (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        cart_data TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id)
    )");

    // Create cart_history table for tracking changes
    $pdo->exec("CREATE TABLE IF NOT EXISTS cart_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        cart_data TEXT NOT NULL,
        action VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'create':
            if (!isset($input['user_id']) || !isset($input['cart'])) {
                throw new Exception('User ID and cart data are required');
            }

            $cart_json = json_encode($input['cart']);
            if ($cart_json === false) {
                throw new Exception('Invalid cart data format');
            }

            try {
                // Check if cart already exists
                $checkStmt = $pdo->prepare("SELECT cart_id FROM user_carts WHERE user_id = ?");
                $checkStmt->execute([$input['user_id']]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('Cart already exists for this user. Use update instead.');
                }

                // Insert new cart
                $stmt = $pdo->prepare("INSERT INTO user_carts (user_id, cart_data) VALUES (?, ?)");
                if ($stmt->execute([$input['user_id'], $cart_json])) {
                    // Log the cart creation in history
                    $historyStmt = $pdo->prepare("
                        INSERT INTO cart_history (user_id, cart_data, action)
                        VALUES (?, ?, 'create')
                    ");
                    $historyStmt->execute([$input['user_id'], $cart_json]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cart created successfully'
                    ]);
                } else {
                    throw new Exception('Failed to create cart');
                }
            } catch (PDOException $e) {
                throw new Exception('Database error: ' . $e->getMessage());
            }
            break;

        case 'get':
            $user_id = $_GET['user_id'] ?? null;

            if (!$user_id) {
                throw new Exception('User ID is required');
            }

            try {
                // Get user's cart
                $stmt = $pdo->prepare("
                    SELECT cart_data 
                    FROM user_carts 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                ob_clean();

                if ($result === false) {
                    // No cart found, return empty cart with success status
                    echo json_encode([
                        'success' => true,
                        'cart' => []
                    ]);
                    exit;
                }

                $cart_data = json_decode($result['cart_data'], true);
                if ($cart_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    // Invalid JSON in cart_data
                    echo json_encode([
                        'success' => true,
                        'cart' => []
                    ]);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'cart' => $cart_data
                ]);
            } catch (PDOException $e) {
                throw new Exception('Database error: ' . $e->getMessage());
            }
            break;

        case 'update':
            if (!isset($input['user_id']) || !isset($input['cart'])) {
                throw new Exception('User ID and cart data are required');
            }

            $cart_json = json_encode($input['cart']);
            if ($cart_json === false) {
                throw new Exception('Invalid cart data format');
            }

            try {
                // Use REPLACE INTO to handle both insert and update
                $stmt = $pdo->prepare("
                    REPLACE INTO user_carts (user_id, cart_data)
                    VALUES (?, ?)
                ");
                
                if ($stmt->execute([$input['user_id'], $cart_json])) {
                    // Log the cart update in history
                    $historyStmt = $pdo->prepare("
                        INSERT INTO cart_history (user_id, cart_data, action)
                        VALUES (?, ?, 'update')
                    ");
                    $historyStmt->execute([$input['user_id'], $cart_json]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cart updated successfully'
                    ]);
                } else {
                    throw new Exception('Failed to update cart');
                }
            } catch (PDOException $e) {
                throw new Exception('Database error: ' . $e->getMessage());
            }
            break;

        case 'delete':
            if (!isset($input['user_id'])) {
                throw new Exception('User ID is required');
            }

            try {
                // Get current cart data for history
                $stmt = $pdo->prepare("SELECT cart_data FROM user_carts WHERE user_id = ?");
                $stmt->execute([$input['user_id']]);
                $currentCart = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($currentCart) {
                    // Log the deletion in history
                    $historyStmt = $pdo->prepare("
                        INSERT INTO cart_history (user_id, cart_data, action)
                        VALUES (?, ?, 'delete')
                    ");
                    $historyStmt->execute([$input['user_id'], $currentCart['cart_data']]);
                }

                // Delete the cart
                $deleteStmt = $pdo->prepare("DELETE FROM user_carts WHERE user_id = ?");
                
                ob_clean();
                if ($deleteStmt->execute([$input['user_id']])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cart deleted successfully'
                    ]);
                } else {
                    throw new Exception('Failed to delete cart');
                }
            } catch (PDOException $e) {
                throw new Exception('Database error: ' . $e->getMessage());
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Cart handler error: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($pdo)) {
        $pdo = null;
    }
    // End output buffering
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>