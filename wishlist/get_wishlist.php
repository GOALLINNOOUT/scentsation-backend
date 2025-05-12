<?php
// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Handle CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = array(
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net',
);

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
        throw new Exception('User ID is required');
    }

    // Replace deprecated FILTER_SANITIZE_STRING with proper sanitization
    $user_id = htmlspecialchars(strip_tags($_GET['user_id']), ENT_QUOTES, 'UTF-8');
    
    $pdo = getConnection();
    
    // First verify if the user has any wishlist items
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $checkStmt->execute([$user_id]);
    $count = $checkStmt->fetchColumn();
    
    if ($count === 0) {
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        exit;
    }
    
    // Get wishlist items with product details
    $stmt = $pdo->prepare("
        SELECT w.*, p.product_name, p.price, p.image AS image_url, p.description, p.category
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.added_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($items === false) {
        throw new Exception('Error fetching wishlist items');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
    
} catch (Exception $e) {
    error_log("Wishlist error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}