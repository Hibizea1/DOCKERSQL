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
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

$jwtConfig = require __DIR__ . '/../../config/jwt.php';

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

if ($clientType === "web") {
    $refreshToken = $_COOKIE['refresh_token'] ?? '';
} else {
    $refreshToken = $data['refresh_token'] ?? '';
}

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
