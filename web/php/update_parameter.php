<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Log;

$jwtConfig = require __DIR__ . '/../../config/jwt.php';

/* =========================
   Vérification JWT
========================= */
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    Log::warning("Missing JWT token for character update", "character");
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
    Log::info("JWT validated for character update, user: $userId", "character");
} catch (Exception $e) {
    Log::error("Invalid JWT for character update: " . $e->getMessage(), "character");
    http_response_code(401);
    echo json_encode([
        "status" => "invalid_token",
        "error"  => $e->getMessage()
    ]);
    exit;
}

/* =========================
   Modification des parametre
========================= */

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['param'])) {
    Log::error("Invalid data for param update", "Parameter");
    echo json_encode(["status" => "invalid_data"]);
    exit;
}

$param = $data['param'];

$inventoryPreview = $param['inventorypreview'];
$darkMode = $param['darkMode'];

if($darkMode === null || $inventoryPreview === null){
    Log::error("Invalid data", "Parameter");
    echo json_encode(["status" => "invalid_data"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE Param 
    SET darkMode = ?, inventoryPreview = ?
    WHERE user_id = ?
");
$stmt->bind_param("iii", $darkMode, $inventoryPreview, $userId);

$stmt->execute();

if ($stmt->affected_rows <= 0) {
    log::error("No Row detect", "Parameter");
    exit;
}

log::info("Parameters updated", "Parameter");