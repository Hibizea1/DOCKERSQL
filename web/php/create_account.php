<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use App\Log;
use App\Mailer;

$jwtConfig   = require __DIR__ . '/../../config/jwt.php';
$emailConfig = require __DIR__ . '/../../config/email.php';
$logFile = "create";
/* =========================
   Lecture input
========================= */
$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data["username"] ?? "");
$email    = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($username === "" || $email === "" || $password === "") {
    Log::error("Missing fields", $logFile);
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
    Log::error("User already exists !", $logFile);
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

Log::info("User created", $logFile);
/* =========================
   Création character
========================= */

InsertIntoTable('characters', [
    'user_id' => $userId
]);
Log::info("Character created", $logFile);

InsertIntoTable('Param', [
    'user_id' => $userId,
    'darkMode' => 0,
    'inventorypreview' => 0
]);

/* =========================
   Envoi email de bienvenue
========================= */
$mailer = new Mailer($emailConfig);
if ($mailer->sendWelcome($email, $username)) {
    Log::info("Welcome email sent to $email", $logFile);
} else {
    Log::warning("Welcome email could not be sent to $email", $logFile);
}

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

Log::info("Access token created", $logFile);

/* =========================
   REFRESH TOKEN
========================= */
$refreshToken = bin2hex(random_bytes(32));
Log::info("refresh token created", $logFile);

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
    "access_token"  => $accessToken,
    "refresh_token" => $refreshToken,
    "character"     => GetCharacterFromUserId($conn, $userId)
]);
