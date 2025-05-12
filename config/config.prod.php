<?php
// Production configuration
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Database configuration constants
// You'll replace these with your hosting provider's credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('DB_NAME_USERS', getenv('DB_NAME_USERS') ?: 'scentsation_users_db');
define('DB_NAME_MAIN', getenv('DB_NAME_MAIN') ?: 'scentsation_db');

// SMTP configuration
// Update with your production email service credentials
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_USER', getenv('SMTP_USER') ?: 'your-email@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'your-app-password');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'your-email@gmail.com');

// JWT configuration - Change this to a secure random string
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-secure-production-key');
define('JWT_EXPIRY', 3600); // 1 hour

// API URL - Will be updated with your production domain
define('ROOT_URL', getenv('API_URL') ?: 'https://your-api-domain.com');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// CORS settings
$allowed_origins = array(
    'https://your-frontend-domain.com',
    'https://your-admin-domain.com'
);

// Set production environment
define('ENVIRONMENT', 'production');
