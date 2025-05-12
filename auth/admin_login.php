<?php
// Get the requesting origin
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// List of allowed origins - including production domains
$allowed_origins = [
    'https://scentsation-admin.great-site.net',
    'https://apiscentsation.great-site.net',
];

// Check if the origin is allowed
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: " . $allowed_origins[0]);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once('../config/db_connect.php');

// Initialize database connection
$pdo = getMainConnection();

// Get JSON POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required']);
    exit;
}

try {
    $username = $data['username'];
    $password = $data['password'];

    // Query the database for the admin user
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);    if ($admin && password_verify($password, $admin['password_hash'])) {
        // Generate a session token
        $token = bin2hex(random_bytes(32));
          // Store the token in the database
        $stmt = $pdo->prepare("UPDATE admin_users SET session_token = ? WHERE id = ?");
        $stmt->execute([$token, $admin['id']]);

        // Set session cookie
        setcookie('adminToken', $token, time() + (24 * 60 * 60), '/', '', true, true);

        echo json_encode([
            'success' => true,
            'token' => $token
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid username or password'
        ]);
    }
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred during login'
    ]);
}
