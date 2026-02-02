<?php
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
        Check User
========================= */

if(CheckUser($conn, $userId)){
    echo json_encode(["status" => "failed due to unknown user"]);
    exit;
}

$data = GetAllItemsFromUserId($conn, $userId);
echo json_encode(["result", $data]);