<?php
require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Log;

$jwtConfig = require __DIR__ . '/../../config/jwt.php';


/* =========================
   Vérification JWT
========================= */
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    Log::warning("Missing JWT token", "inventories");
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
    Log::info("JWT validated for user: $userId", "inventories");
} catch (Exception $e) {
    Log::error("Invalid JWT token: " . $e->getMessage(), "inventories");
    http_response_code(401);
    echo json_encode([
        "status" => "invalid_token",
        "error"  => $e->getMessage()
    ]);
    exit;
}


$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["Items"]) || !is_array($data["Items"]) || !isset($data["Equipment"]) || !is_array($data["Equipment"])) {
    Log::error("Invalid format for inventories save", "inventories");
    echo json_encode(["status" => "failed", "cause" => "invalid_format"]);
    exit;
}



$checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->bind_param("i", $userId);
$checkUser->execute();
$checkUser->store_result();

if ($checkUser->num_rows === 0) {
    Log::error("User not found: $userId", "inventories");
    echo json_encode(["status" => "failed", "cause" => "user_not_found"]);
    exit;
}

$characters = GetCharacterFromUserId($conn, $userId);
$character = $characters[0];
$charId = $character["id"];

$conn->query("DELETE FROM inventories WHERE character_id = $charId");


foreach ($data["Items"] as $entry) {

    if (!array_key_exists("item_id", $entry)) {
        Log::error("Missing item_id in inventory entry", "inventories");
        echo json_encode(["status" => "failed", "cause" => "missing_item_id"]);
        exit;
    }

    $item_id = intval($entry["item_id"]);
    $rarity  = intval($entry["rarity"] ?? 1);
    $lvl     = intval($entry["lvl"] ?? 1);

    /* Vérifie si instance_items existe */
    $checkItem = $conn->prepare("
        SELECT id FROM instance_items
        WHERE item_id = ? AND rarity = ? AND lvl = ?
    ");
    $checkItem->bind_param("iii", $item_id, $rarity, $lvl);
    $checkItem->execute();
    $checkItem->bind_result($instanceItemID);

    if (!$checkItem->fetch()) {
        // Création nouvelle instance
        $createItem = $conn->prepare("
            INSERT INTO instance_items (item_id, rarity, lvl)
            VALUES (?, ?, ?)
        ");
        $createItem->bind_param("iii", $item_id, $rarity, $lvl);
        $createItem->execute();

        $instanceItemID = $createItem->insert_id;
    }
    $checkItem->close();

    // Ajout inventaire ( quantité = 1 par défaut )
    $quantity = 1;
    InsertIntoTable('inventories', [
        'character_id' => $charId,
        'item_id' => $instanceItemID,
        'quantity' => $quantity
    ]);

}

$delete = $conn->prepare("
    DELETE FROM equipment WHERE characters_id = ?
");
$delete->bind_param("i", $charId);
$delete->execute();

if ($delete->affected_rows > 0) {
    Log::info("Delete", 'inventories');
} else {
        Log::info("Nothing Delete", 'inventories');
}

$delete->close();

foreach ($data["Equipment"] as $entry) {

    if (!array_key_exists("item_id", $entry)) {
        Log::error("Missing item_id in inventory entry", "inventories");
        echo json_encode(["status" => "failed", "cause" => "missing_item_id"]);
        exit;
    }

    $item_id = intval($entry["item_id"]);
    $rarity  = intval($entry["rarity"] ?? 1);
    $lvl     = intval($entry["lvl"] ?? 1);

    /* Vérifie si instance_items existe */
    $checkItem = $conn->prepare("
        SELECT id FROM instance_items
        WHERE item_id = ? AND rarity = ? AND lvl = ?
    ");
    $checkItem->bind_param("iii", $item_id, $rarity, $lvl);
    $checkItem->execute();
    $checkItem->bind_result($instanceItemID);

    $found = $checkItem->fetch();
    $checkItem->close();

    if (!$found) {

        $createItem = $conn->prepare("
            INSERT INTO instance_items (item_id, rarity, lvl)
            VALUES (?, ?, ?)
        ");
        $createItem->bind_param("iii", $item_id, $rarity, $lvl);
        $createItem->execute();

        $instanceItemID = $createItem->insert_id;
        $createItem->close();

    } else {

        // Vérifie si déjà équipé
        $checkItemExist = $conn->prepare("
            SELECT 1 
            FROM equipment
            WHERE characters_id = ?
              AND item_id = ?
            LIMIT 1
        ");
        $checkItemExist->bind_param("ii", $charId, $instanceItemID);
        $checkItemExist->execute();
        $resultExist = $checkItemExist->get_result();

        if ($resultExist->num_rows > 0) {
            echo json_encode(["error" => "Item already equipped"]);
            $checkItemExist->close();
            continue; // passe au suivant
        }

        $checkItemExist->close();
    }

    // Ajout inventaire ( quantité = 1 par défaut )
    $quantity = 1;
    InsertIntoTable('equipment', [
        'characters_id' => $charId,
        'item_id' => $instanceItemID,
        'quantity' => $quantity
    ]);
}
/* =========================
   Réponse finale
========================= */

Log::info("Inventories saved successfully for user: $userId", "inventories");
echo json_encode([
    "status" => "success",
]);
