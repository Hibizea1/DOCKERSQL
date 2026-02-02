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


$data = json_decode(file_get_contents("php://input"), true);

$item_id = $data["item_id"] ?? null;
$rarity = $data["rarity"] ?? 1;
$lvl = $data["lvl"] ?? 1;

if ($item_id === null) {
    echo json_encode(["status" => "failed", "cause" => "missing_parameters"]);
    exit;
}

$checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->bind_param("i", $userId);
$checkUser->execute();
$checkUser->store_result();

if ($checkUser->num_rows === 0) {
    echo json_encode(["status" => "failed", "cause" => "user_not_found"]);
    exit;
}

$checkItem = $conn->prepare("
    SELECT id FROM instance_items
    WHERE item_id = ? AND rarity = ? AND lvl = ?
");
$checkItem->bind_param("iii", $item_id, $rarity, $lvl);
$checkItem->execute();
$checkItem->bind_result($instanceItemID);

if (!$checkItem->fetch()) {

    $createItem = $conn->prepare("
        INSERT INTO instance_items (item_id, rarity, lvl)
        VALUES (?, ?, ?)
    ");
    $createItem->bind_param("iii", $item_id, $rarity, $lvl);
    $createItem->execute();

    $instanceItemID = $createItem->insert_id;
}
$checkItem->close();
$quantity = 1;
$characters = GetCharacterFromUserId($conn, $userId);
$character = $characters[0];

InsertIntoTable('inventories',
    [
        'character_id' => $character["id"],
        'item_id' => $instanceItemID,
        'quantity' => $quantity
    ],
);


echo json_encode([
    "status" => "success",
    "instance_item_id" => $instanceItemID
]);
