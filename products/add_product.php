<?php
// filepath: c:\xampp\htdocs\scentsation_api\add_product.php
// Disable error display to ensure clean JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Handle CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (empty($origin)) {
    $origin = 'null'; // For local file access
}

// List of allowed origins
$allowed_origins = array(
    'https://apiscentsation.great-site.net',
		'https://scentsation-admin.great-site.net',
		'https://scentsation.great-site.net',
);

// Check if the origin is allowed
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Buffer the output to catch any unwanted output
ob_start();

try {
    // Database connection
    require_once __DIR__ . '/../config/db_connect.php';
    $pdo = getConnection();

    // Validate required fields
    $requiredFields = ['product_name', 'price', 'quantity', 'category', 'description'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Get and sanitize POST data
    $productName = htmlspecialchars($_POST['product_name'], ENT_QUOTES, 'UTF-8');
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
    $category = htmlspecialchars($_POST['category'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');

    if ($price === false || $quantity === false) {
        throw new Exception("Invalid price or quantity value");
    }    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../images/products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileInfo = pathinfo($_FILES['image']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('Invalid image format. Allowed types: ' . implode(', ', $allowedTypes));
        }

        // Use original filename
        $filename = $_FILES['image']['name'];
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'images/products/' . $filename;
        } else {
            throw new Exception('Failed to upload image');
        }
    }

    // Prepare SQL statement using PDO
    $stmt = $pdo->prepare("INSERT INTO products (product_name, price, quantity, category, image, description) VALUES (:name, :price, :quantity, :category, :image, :description)");
    
    $stmt->bindParam(':name', $productName);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':quantity', $quantity);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':image', $imagePath);
    $stmt->bindParam(':description', $description);

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Failed to add product");
    }

    // Get the inserted product ID
    $newProductId = $pdo->lastInsertId();

    // Clear any output buffered so far
    ob_clean();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Product added successfully',
        'product_id' => $newProductId
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in add_product.php: " . $e->getMessage());
    
    // Clear any output buffered so far
    ob_clean();
    
    // Set appropriate status code
    http_response_code(500);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// End output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}
?>