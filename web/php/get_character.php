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
$logFile = "getCharacter";

/* =========================
   Vérification JWT
========================= */
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    Log::warning("Missing JWT token", $logFile);
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
    Log::info("JWT token validated for user: $userId", $logFile);
} catch (Exception $e) {
    Log::error("Invalid JWT token: " . $e->getMessage(), $logFile);
    http_response_code(401);
    echo json_encode([
        "status" => "invalid_token",
        "error" => $e->getMessage()
    ]);
    exit;
}

if (CheckUser($conn, $userId)) {
    Log::error("User check failed for user ID: $userId", $logFile);
    echo json_encode(["status" => "failed due to unknown user"]);
    exit;
}

$character = GetCharacterFromUserId($conn, $userId);

if (!$character) {
    Log::error("Character not found", $logFile);
    echo json_encode(["status" => "error", "message" => "Character not found"]);
    exit;
}

Log::info("Character found", $logFile);

$inventoriesBrut = GetAllItemsFromInventoryAndUserID($conn, $userId);
$equipment = GetAllItemsFromEquipmentAndUserID($conn, $userId);


echo json_encode([
    "status" => "success",
    "character" => $character,
    "inventories" => $inventoriesBrut,
    "Equipment" => $equipment
]);