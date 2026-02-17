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

$jwtConfig = require __DIR__ . '/../config/jwt.php';

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
   Lecture input
========================= */
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['character'])) {
    Log::error("Invalid data for character update", "character");
    echo json_encode(["status" => "invalid_data"]);
    exit;
}

$character = $data['character'];

$xp    = $character['xp']    ?? null;
$level = $character['level'] ?? null;
$gold  = $character['gold']  ?? null;

if ($xp === null || $level === null || $gold === null) {
    Log::error("Character data invalid: missing xp, level or gold", "character");
    echo json_encode(["status" => "characters_data_invalid"]);
    exit;
}

/* =========================
   Update sécurisé
========================= */
UpdateCharacter($conn, $userId, [
    'xp'    => (int)$xp,
    'gold'  => (int)$gold,
    'level' => (int)$level
]);

Log::info("Character updated successfully for user: $userId (xp: $xp, level: $level, gold: $gold)", "character");

echo json_encode([
    "status"    => "success",
    "user_id"   => $userId,
    "character" => GetCharacterFromUserId($conn, $userId)
]);
