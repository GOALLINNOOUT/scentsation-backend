<?php
require_once __DIR__ . '/config.php';

class JWTUtils {
    private static $secretKey;

    public static function init() {
        self::$secretKey = JWT_SECRET;
    }

    public static function generateToken($data) {
        self::init();
        
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);

        $payload = json_encode([
            'user_id' => $data['user_id'],
            'email' => $data['email'],
            'iat' => time(),
            'exp' => time() + JWT_EXPIRY
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validateToken($token) {
        self::init();
        
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return false;
            }

            $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[0]));
            $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
            $signatureProvided = $tokenParts[2];

            // Check token expiration
            $payloadData = json_decode($payload, true);
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return false;
            }

            // Verify signature
            $base64UrlHeader = $tokenParts[0];
            $base64UrlPayload = $tokenParts[1];
            $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secretKey, true);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            if ($base64UrlSignature !== $signatureProvided) {
                return false;
            }

            return json_decode($payload, true);
        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            return false;
        }
    }

    public static function getBearerToken() {
        $headers = apache_request_headers();
        if (!isset($headers['Authorization'])) {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }

        return null;
    }
}