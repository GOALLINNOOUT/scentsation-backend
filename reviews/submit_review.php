<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db_connect.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['rating']) || !isset($data['review_text'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $conn = getConnection();

        // If product_id is provided, verify it exists
        if (isset($data['product_id'])) {
            $productStmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
            $productStmt->execute([$data['product_id']]);
            
            if (!$productStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }
        }

        // Set user_id to 'anonymous' by default
        $userId = 'anonymous';

        // If user_id is provided and not 'anonymous', verify it exists
        if (isset($data['user_id']) && $data['user_id'] !== 'anonymous') {
            $userStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $userStmt->execute([$data['user_id']]);
            
            if ($userStmt->fetch()) {
                $userId = $data['user_id'];
            }
        }

        // Insert the review
        if (isset($data['product_id'])) {
            // Product-specific review
            $stmt = $conn->prepare("INSERT INTO reviews (user_id, product_id, rating, review_text, date) VALUES (?, ?, ?, ?, NOW())");
            $result = $stmt->execute([$userId, $data['product_id'], $data['rating'], $data['review_text']]);
        } else {
            // General review
            $stmt = $conn->prepare("INSERT INTO reviews (user_id, rating, review_text, date) VALUES (?, ?, ?, NOW())");
            $result = $stmt->execute([$userId, $data['rating'], $data['review_text']]);
        }

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
        }
    } catch(PDOException $e) {
        error_log("Review submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}