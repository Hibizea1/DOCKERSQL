<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$jwtConfig = require __DIR__ . '/../config/jwt.php';

/* =========================
   Vérification JWT
========================= */
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
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
    echo json_encode(["status" => "invalid_data"]);
    exit;
}

$character = $data['character'];

$xp    = $character['xp']    ?? null;
$level = $character['level'] ?? null;
$gold  = $character['gold']  ?? null;

if ($xp === null || $level === null || $gold === null) {
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

echo json_encode([
    "status"    => "success",
    "user_id"   => $userId,
    "character" => GetCharacterFromUserId($conn, $userId)
]);
