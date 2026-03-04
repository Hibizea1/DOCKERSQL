<?php
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
    Log::warning("Missing JWT token", "inventories");
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
    Log::info("JWT token validated for user: $userId", "inventories");
} catch (Exception $e) {
    Log::error("Invalid JWT token: " . $e->getMessage(), "inventories");
    http_response_code(401);
    echo json_encode([
        "status" => "invalid_token",
        "error"  => $e->getMessage()
    ]);
    exit;
}

/* =========================
        Check User
========================= */

if(CheckUser($conn, $userId)){
    Log::error("User check failed for user ID: $userId", "inventories");
    echo json_encode(["status" => "failed due to unknown user"]);
    exit;
}

$data = GetAllItemsFromUserId($conn, $userId);
$equipment = GetAllEquipmentFromUserId($conn, $userId);
Log::info("Inventories loaded successfully for user: $userId", "inventories");
echo json_encode(["Items" => $data,
"Equipment" => $equipment]);