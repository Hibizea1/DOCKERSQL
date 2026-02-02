<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

$jwtConfig = require __DIR__ . '/../config/jwt.php';

/* =========================
   Lecture input
========================= */
$data = json_decode(file_get_contents("php://input"), true);

$login    = trim($data['login'] ?? ''); // username OU email
$password = $data['password'] ?? '';

if ($login === '' || $password === '') {
    echo json_encode(["status" => "missing_fields"]);
    exit;
}

/* =========================
   Récup user
========================= */
$stmt = $conn->prepare(
    "SELECT id, password FROM users WHERE username = ? OR email = ? LIMIT 1"
);
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "invalid_credentials"]);
    exit;
}

$user = $result->fetch_assoc();

/* =========================
   Vérif password
========================= */
if (!password_verify($password, $user['password'])) {
    echo json_encode(["status" => "invalid_credentials"]);
    exit;
}

$userId = (int)$user['id'];

/* =========================
   JWT ACCESS TOKEN
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
   REFRESH TOKEN (DB)
========================= */
$refreshToken = bin2hex(random_bytes(32));

$update = $conn->prepare(
    "UPDATE users SET refresh_token = ? WHERE id = ?"
);
$update->bind_param("si", $refreshToken, $userId);
$update->execute();

/* =========================
   Character
========================= */
$character = GetCharacterFromUserId($conn, $userId);

/* =========================
   Réponse
========================= */
echo json_encode([
    "status"        => "success",
    "access_token" => $accessToken,
    "refresh_token"=> $refreshToken,
    "character"    => $character
]);
