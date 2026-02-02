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
$username = trim($data["username"] ?? "");
$email    = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($username === "" || $email === "" || $password === "") {
    echo json_encode(["status" => "missing_fields"]);
    exit;
}

/* =========================
   Verify exists
========================= */
$check = $conn->prepare(
    "SELECT id FROM users WHERE username = ? OR email = ?"
);
$check->bind_param("ss", $username, $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "exists"]);
    exit;
}

/* =========================
   Création user
========================= */
$hash = password_hash($password, PASSWORD_BCRYPT);

InsertIntoTable('users', [
    'username' => $username,
    'email'    => $email,
    'password' => $hash
]);

$userId = GetUserId($conn, $username);

/* =========================
   Création character
========================= */
InsertIntoTable('characters', [
    'user_id' => $userId
]);

/* =========================
   ACCESS TOKEN (JWT)
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
   REFRESH TOKEN
========================= */
$refreshToken = bin2hex(random_bytes(32));

$stmt = $conn->prepare(
    "UPDATE users SET refresh_token = ? WHERE id = ?"
);
$stmt->bind_param("si", $refreshToken, $userId);
$stmt->execute();

/* =========================
   Response
========================= */
echo json_encode([
    "status"         => "success",
    "user_id"        => $userId,
    "access_token"  => $accessToken,
    "refresh_token" => $refreshToken,
    "character"     => GetCharacterFromUserId($conn, $userId)
]);
