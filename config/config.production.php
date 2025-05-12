<?php
// Production configuration
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Database configuration constants - InfinityFree credentials
define('DB_HOST', 'YOUR_INFINITYFREE_DB_HOST'); // Replace with the hostname from InfinityFree
define('DB_USER', 'YOUR_INFINITYFREE_DB_USER'); // Replace with the username from InfinityFree
define('DB_PASS', 'YOUR_INFINITYFREE_DB_PASSWORD'); // Replace with the password from InfinityFree
define('DB_NAME_USERS', 'YOUR_INFINITYFREE_DB_NAME_1'); // Replace with your first database name
define('DB_NAME_MAIN', 'YOUR_INFINITYFREE_DB_NAME_2'); // Replace with your second database name

// SMTP configuration - UPDATE WITH YOUR EMAIL SERVICE CREDENTIALS
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-specific-password');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');

// JWT configuration - CHANGE THIS TO A SECURE RANDOM STRING
define('JWT_SECRET', 'generate-a-secure-random-string-here');
define('JWT_EXPIRY', 3600); // 1 hour

// API URL - InfinityFree domain (update with your actual subdomain)
define('ROOT_URL', 'https://scentsation-api.infinityfree.net');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// CORS settings
define('ALLOWED_ORIGINS', [
    'https://scentsation.infinityfree.net',
    'https://scentsation-admin.infinityfree.net'
]);

?>
