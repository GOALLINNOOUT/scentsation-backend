<?php
require_once __DIR__ . '/config/cors_handler.php';

header('Content-Type: application/json');

echo json_encode([
    'status' => 'connected',
    'timestamp' => time()
]);