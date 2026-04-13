<?php
// CORS applies to browser clients (web). Unreal native client is not constrained by CORS.
$allowedOriginsEnv = getenv("CORS_ALLOWED_ORIGINS") ?: "https://localhost:8443,http://localhost:8080";
$allowedOrigins = array_filter(array_map("trim", explode(",", $allowedOriginsEnv)));
$origin = $_SERVER["HTTP_ORIGIN"] ?? "";

if ($origin !== "" && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
}

header("Vary: Origin");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Client-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use App\Log;
use Firebase\JWT\JWT;

$jwtConfig = require __DIR__ . '/../../config/jwt.php';
$logFile = "login";

$clientTypeHeader = strtolower(trim((string)($_SERVER["HTTP_X_CLIENT_TYPE"] ?? "")));
if ($clientTypeHeader === "web" || $clientTypeHeader === "game") {
    $clientType = $clientTypeHeader;
} else {
    $clientType = ($origin !== "") ? "web" : "game";
}

/* =========================
   Lecture input
========================= */
$data = json_decode(file_get_contents("php://input"), true);

$login = trim($data['username'] ?? $data['email'] ?? '');
$password = $data['password'] ?? '';

$isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;


if($isEmail){
    Log::info("Connection with email", $logFile);
}else{
    Log::info("Connection with username", $logFile);
}



if ($login === '' || $password === '') {
    log::error("Connection failed due to missing field", $logFile);
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
    Log::error("Invalid Credential", $logFile);
    echo json_encode(["status" => "invalid_credentials"]);
    exit;
}

$user = $result->fetch_assoc();

Log::info("User found", $logFile);

/* =========================
   Vérif password
========================= */
if (!password_verify($password, $user['password'])) {
    Log::error("invalid password", $logFile);
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

Log::info("Access token created", $logFile);
/* =========================
   REFRESH TOKEN (DB)
========================= */
$refreshToken = bin2hex(random_bytes(32));
Log::info("Refresh token created", $logFile);

$update = $conn->prepare(
    "UPDATE users SET refresh_token = ? WHERE id = ?"
);
$update->bind_param("si", $refreshToken, $userId);
$update->execute();

if ($clientType === "web") {
    setcookie("refresh_token", $refreshToken, [
        "expires" => time() + (30 * 24 * 60 * 60),
        "path" => "/",
        "secure" => true,
        "httponly" => true,
        "samesite" => "Lax"
    ]);
}

/* =========================
   Character
========================= */
$character = GetCharacterFromUserId($conn, $userId);

if(!$character){
    Log::error("Character not found", $logFile);
    echo json_encode(["status" => "error", "message" => "Character not found"]);
    exit;
}

Log::info("Character found", $logFile);

$inventories = GetAllItemsFromUserId($conn, $userId);
$equipment = GetAllEquipmentFromUserId($conn, $userId);

/* =========================
   Réponse
========================= */
if ($clientType === "web") {
    $response = [
        "status"       => "success",
        "access_token" => $accessToken,
        "character"    => $character,
        "inventories"  => $inventories,
        "equipment"    => $equipment,
        "client_type"  => "web"
    ];
} else {
    $response = [
        "status"        => "success",
        "access_token"  => $accessToken,
        "refresh_token" => $refreshToken,
        "character"     => $character,
        "inventories"   => $inventories,
        "equipment"     => $equipment,
        "client_type"   => "game"
    ];
}

echo json_encode($response);
