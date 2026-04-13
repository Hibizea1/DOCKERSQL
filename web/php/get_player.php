<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use App\Log;

$logFile = "getPlayer";

// Récupérer le pseudo depuis l’URL
$username = $_GET['user'] ?? '';

if ($username === "") {
    Log::error("Missing username in GET", $logFile);
    echo json_encode(["status" => "error", "message" => "Missing username"]);
    exit;
}

// Récupérer l’ID du joueur
$stmtUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmtUser->bind_param("s", $username);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows === 0) {
    Log::error("User not found: $username", $logFile);
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$userRow = $resultUser->fetch_assoc();
$userId = $userRow['id'];

// -------------------------------
//   UTILISATION DE TES FONCTIONS
// -------------------------------

// Character
$character = GetCharacterFromUserId($conn, $userId);
if (!$character) {
    Log::error("Character not found for userId $userId", $logFile);
    echo json_encode(["status" => "error", "message" => "Character not found"]);
    exit;
}

// Inventaire
$inventoriesBrut = GetAllItemsFromInventoryAndUserID($conn, $userId);

// Équipement
$equipmentBrut = GetAllItemsFromEquipmentAndUserID($conn, $userId);

$stmtParam = $conn->prepare("SELECT darkMode, inventorypreview FROM Param WHERE user_id = ? LIMIT 1");
$stmtParam->bind_param("i", $userId);
$stmtParam->execute();
$paramResult = $stmtParam->get_result()->fetch_assoc();

$params = [
    "darkMode" => (int)($paramResult['darkMode'] ?? 0),
    "inventorypreview" => (int)($paramResult['inventorypreview'] ?? 0)
];


// -------------------------------
//   RENVOI DU JSON
// -------------------------------

echo json_encode([
    "status" => "success",
    "username" => $username,
    "character" => $character,
    "inventories" => $inventoriesBrut,
    "equipment" => $equipmentBrut,
    "params" => $params
]);