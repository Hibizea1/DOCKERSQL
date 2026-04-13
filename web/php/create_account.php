<?php
// #region Headers
// CORS is only relevant for browsers (website client), not Unreal native client.
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
// #endregion

// #region Dependencies
require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use App\Log;
use App\Mailer;
// #endregion

// #region Config
$jwtConfig   = require __DIR__ . '/../../config/jwt.php';
$emailConfig = require __DIR__ . '/../../config/email.php';
$logFile = "create";

$clientTypeHeader = strtolower(trim((string)($_SERVER["HTTP_X_CLIENT_TYPE"] ?? "")));
if ($clientTypeHeader === "web" || $clientTypeHeader === "game") {
    $clientType = $clientTypeHeader;
} else {
    // Fallback: browser requests usually have Origin, native game requests usually don't.
    $clientType = ($origin !== "") ? "web" : "game";
}
// #endregion

// #region Input
$rawInput = file_get_contents("php://input");
if ($rawInput === false) {
    $rawInput = "";
}

$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $jsonError = json_last_error_msg();
    Log::error("Invalid JSON payload: $jsonError", $logFile);
    echo json_encode([
        "status" => "invalid_json",
        "debug" => [
            "json_error" => $jsonError,
            "raw_length" => strlen($rawInput)
        ]
    ]);
    exit;
}

$username = trim((string)($data["username"] ?? ""));
$email    = trim((string)($data["email"] ?? ""));
$password = (string)($data["password"] ?? "");

$fieldErrors = [];

if ($username === "") {
    $fieldErrors["username"] = "empty";
} elseif (strlen($username) < 3) {
    $fieldErrors["username"] = "too_short_min_3";
}

if ($email === "") {
    $fieldErrors["email"] = "empty";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fieldErrors["email"] = "invalid_format";
}

if ($password === "") {
    $fieldErrors["password"] = "empty";
} elseif (strlen($password) < 8) {
    $fieldErrors["password"] = "too_short_min_8";
}

if (!empty($fieldErrors)) {
    Log::error("Validation error: " . json_encode($fieldErrors), $logFile);
    echo json_encode([
        "status" => "invalid_fields",
        "field_errors" => $fieldErrors,
        "debug" => [
            "received_keys" => array_keys($data),
            "received_values" => [
                "username" => $username,
                "email" => $email,
                "password_length" => strlen($password)
            ]
        ]
    ]);
    exit;
}
// #endregion

// #region CreateUser
$hash = password_hash($password, PASSWORD_BCRYPT);

$createUserResult = CreateUserIfNotExists($conn, $username, $email, $hash);
if ($createUserResult["exists"]) {
    Log::error("User already exists !", $logFile);
    echo json_encode(["status" => "exists"]);
    exit;
}

if (!$createUserResult["created"] || empty($createUserResult["user_id"])) {
    Log::error("User creation failed", $logFile);
    echo json_encode(["status" => "create_user_failed"]);
    exit;
}

$userId = (int)$createUserResult["user_id"];

Log::info("User created", $logFile);
// #endregion

// #region CreateCharacter
InsertIntoTable('characters', [
    'user_id' => $userId
]);
Log::info("Character created", $logFile);
// #endregion

// #region CreateDefaultParams
InsertIntoTable('Param', [
    'user_id' => $userId,
    'darkMode' => 0,
    'inventorypreview' => 0
]);
// #endregion

// #region WelcomeEmail
$mailer = new Mailer($emailConfig);
if ($mailer->sendWelcome($email, $username)) {
    Log::info("Welcome email sent to $email", $logFile);
} else {
    Log::warning("Welcome email could not be sent to $email", $logFile);
}
// #endregion

// #region AccessToken
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
// #endregion

// #region RefreshToken
$refreshToken = bin2hex(random_bytes(32));
Log::info("refresh token created", $logFile);

$stmt = $conn->prepare(
    "UPDATE users SET refresh_token = ? WHERE id = ?"
);
$stmt->bind_param("si", $refreshToken, $userId);
$stmt->execute();

if ($clientType === "web") {
    setcookie("refresh_token", $refreshToken, [
        "expires" => time() + (30 * 24 * 60 * 60),
        "path" => "/",
        "secure" => true,
        "httponly" => true,
        "samesite" => "Lax"
    ]);
}
// #endregion

// #region Response
if ($clientType === "web") {
    $response = [
        "status"         => "success",
        "access_token"  => $accessToken,
        "character"     => GetCharacterFromUserId($conn, $userId),
        "client_type"   => "web"
    ];
} else {
    $response = [
        "status"         => "success",
        "access_token"  => $accessToken,
        "refresh_token" => $refreshToken,
        "character"     => GetCharacterFromUserId($conn, $userId),
        "client_type"   => "game"
    ];
}

echo json_encode($response);
// #endregion
