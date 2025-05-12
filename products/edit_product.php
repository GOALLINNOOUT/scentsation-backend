<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/cors_handler.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        throw new Exception('Invalid product ID');
    }

    $product_name = filter_var($_POST['product_name'], FILTER_SANITIZE_STRING);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
    $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);

    if (!$product_name || $price === false || $quantity === false || !$category || !$description) {
        throw new Exception('Invalid product data');
    }

    $pdo = getConnection();
    $pdo->beginTransaction();

    try {
        // Check if image was uploaded
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {            // Process image upload
            $image = $_FILES['image'];
            $imagePath = $_POST['image_path'] ?? null;
            
            if (!$imagePath) {
                throw new Exception('Image path not provided');
            }
            
            $extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            
            // Validate image type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];
            if (!in_array($extension, $allowedTypes)) {
                throw new Exception('Invalid image type. Only JPG, JPEG, PNG, GIF, WEBP & JFIF files are allowed.');
            }

            // Use the image path provided by frontend
            $filename = $imagePath;
            $uploadPath = __DIR__ . '/../images/products/' . $filename;

            // Create directory if it doesn't exist
            if (!file_exists(dirname($uploadPath))) {
                mkdir(dirname($uploadPath), 0777, true);
            }

            if (!move_uploaded_file($image['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to upload image');
            }            // Update product with new image
            $stmt = $pdo->prepare("UPDATE products SET 
                product_name = :product_name,
                price = :price,
                quantity = :quantity,
                category = :category,
                description = :description,
                image = :image
                WHERE id = :id");
            
            $stmt->bindValue(':image', $filename);
        } else {
            // Update product without changing image
            $stmt = $pdo->prepare("UPDATE products SET 
                product_name = :product_name,
                price = :price,
                quantity = :quantity,
                category = :category,
                description = :description
                WHERE id = :id");
        }

        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':product_name', $product_name);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':description', $description);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update product');
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>