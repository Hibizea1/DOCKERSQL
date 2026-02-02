<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

$jwtConfig = require __DIR__ . '/../config/jwt.php';

/* =========================
   Lecture input
========================= */
$data = json_decode(file_get_contents("php://input"), true);
$refreshToken = $data['refresh_token'] ?? '';

if ($refreshToken === '') {
    http_response_code(400);
    echo json_encode(["status" => "missing_refresh_token"]);
    exit;
}

/* =========================
   Vérification DB
========================= */
$stmt = $conn->prepare(
    "SELECT id FROM users WHERE refresh_token = ? LIMIT 1"
);
$stmt->bind_param("s", $refreshToken);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "invalid_refresh_token"]);
    exit;
}

$user = $result->fetch_assoc();
$userId = (int)$user['id'];

/* =========================
   Nouveau JWT
========================= */
$payload = [
    'iss' => 'localhost',
    'iat' => time(),
    'exp' => time() + $jwtConfig['ttl'],
    'uid' => $userId
];

$accessToken = JWT::encode(
    $payload,
    $jwtConfig['secret'],
    $jwtConfig['algo']
);

/* =========================
   Réponse
========================= */
echo json_encode([
    "status"        => "success",
    "access_token" => $accessToken
]);
