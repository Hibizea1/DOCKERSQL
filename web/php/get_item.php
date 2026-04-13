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
$logFile = "getItem";

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

$stmt = $conn->query("SELECT * FROM items");

if (!$stmt) {
    Log::critical("Database query failed for items", $logFile);
    echo json_encode(["status" => "failed", "error" => "Database query failed"]);
    exit;
}

$rows = $stmt->fetch_all(MYSQLI_ASSOC);

Log::info("Item retrieval started, found " . count($rows) . " items", $logFile);

// GROUPE PAR TYPE
$grouped = [];

foreach ($rows as $row) {
    $type = $row["weaponType"] ?? "Unknown";

    // Ajoute l'objet dans le bon groupe
    $grouped[$type][] = $row;
}

// ROOT = OBJECT -> "items": { ... }
$root = [
    "items" => $grouped
];

$jsonPath = __DIR__ . "/items.json";

$jsonData = json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($jsonPath, $jsonData) === false) {
    Log::critical("Impossible to create JSON file", $logFile);
    echo "Impossible de créer le fichier JSON";
    exit;
}

$stmt->close();

Log::info("JSON file created: items.json", $logFile);

echo $jsonData;