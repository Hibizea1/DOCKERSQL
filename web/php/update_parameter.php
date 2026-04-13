<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Client-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    Log::warning("Missing JWT token for parameter update", $logFile);
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
    Log::error("Invalid JWT for parameter update: " . $e->getMessage(), $logFile);
    http_response_code(401);
    echo json_encode(["status" => "invalid_token"]);
    exit;
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput ?: "", true);

if (!is_array($data)) {
    Log::error("Invalid JSON for parameter update", $logFile);
    http_response_code(400);
    echo json_encode(["status" => "invalid_json"]);
    exit;
}

$payload = isset($data['param']) && is_array($data['param']) ? $data['param'] : $data;

$darkMode = $payload['darkMode'] ?? null;
$inventoryPreview = $payload['inventorypreview'] ?? $payload['inventoryPreview'] ?? null;

if ($darkMode === null || $inventoryPreview === null) {
    Log::error("Missing parameter fields", $logFile);
    http_response_code(400);
    echo json_encode([
        "status" => "invalid_data",
        "required" => ["darkMode", "inventorypreview"]
    ]);
    exit;
}

$darkMode = (int)(bool)$darkMode;
$inventoryPreview = (int)(bool)$inventoryPreview;

$update = $conn->prepare("UPDATE Param SET darkMode = ?, inventorypreview = ? WHERE user_id = ?");
$update->bind_param("iii", $darkMode, $inventoryPreview, $userId);
$update->execute();

if ($update->errno) {
    Log::error("Update failed: " . $update->error, $logFile);
    http_response_code(500);
    echo json_encode(["status" => "db_error"]);
    exit;
}

$check = $conn->prepare("SELECT user_id FROM Param WHERE user_id = ? LIMIT 1");
$check->bind_param("i", $userId);
$check->execute();
$exists = $check->get_result()->num_rows > 0;

if (!$exists) {
    $insert = $conn->prepare("INSERT INTO Param (user_id, darkMode, inventorypreview) VALUES (?, ?, ?)");
    $insert->bind_param("iii", $userId, $darkMode, $inventoryPreview);
    $insert->execute();

    if ($insert->errno) {
        Log::error("Insert failed: " . $insert->error, $logFile);
        http_response_code(500);
        echo json_encode(["status" => "db_error"]);
        exit;
    }
}

Log::info("Parameters updated for user $userId", $logFile);
echo json_encode([
    "status" => "success",
    "params" => [
        "darkMode" => $darkMode,
        "inventorypreview" => $inventoryPreview
    ]
]);
