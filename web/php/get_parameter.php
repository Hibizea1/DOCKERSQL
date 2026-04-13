<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Client-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require "db.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Log;

$jwtConfig = require __DIR__ . '/../../config/jwt.php';
$logFile = "parameter";

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    Log::warning("Missing JWT token for parameter read", $logFile);
    http_response_code(401);
    echo json_encode(["status" => "missing_token"]);
    exit;
}

try {
    $decoded = JWT::decode(
        $matches[1],
        new Key($jwtConfig['secret'], $jwtConfig['algo'])
    );
    $userId = (int)$decoded->uid;
} catch (Exception $e) {
    Log::error("Invalid JWT for parameter read: " . $e->getMessage(), $logFile);
    http_response_code(401);
    echo json_encode(["status" => "invalid_token"]);
    exit;
}

$stmt = $conn->prepare("SELECT darkMode, inventorypreview FROM Param WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode([
        "status" => "success",
        "params" => [
            "darkMode" => 0,
            "inventorypreview" => 0
        ]
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "params" => [
        "darkMode" => (int)$row['darkMode'],
        "inventorypreview" => (int)$row['inventorypreview']
    ]
]);
