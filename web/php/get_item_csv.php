<?php
require "db.php";
require_once __DIR__ . '/vendor/autoload.php';

use App\Log;

$logFile = "item";

$stmt = $conn->query("SELECT * FROM items");

if (!$stmt) {
    Log::critical("Database query failed for items", $logFile);
    echo json_encode(["status" => "failed", "error" => "Database query failed"]);
    exit;
}

$rows = $stmt->fetch_all(MYSQLI_ASSOC);

Log::info("Item retrieval started, found " . count($rows) . " items", $logFile);

// GROUPE PAR TYPE
$grouped = [];

foreach ($rows as $row) {
    $type = $row["weaponType"] ?? "Unknown";

    // Ajoute l'objet dans le bon groupe
    $grouped[$type][] = $row;
}

// ROOT = OBJECT -> "items": { ... }
$root = [
    "items" => $grouped
];

$jsonPath = __DIR__ . "/items.json";

$jsonData = json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($jsonPath, $jsonData) === false) {
    Log::critical("Impossible to create JSON file", $logFile);
    echo "Impossible de créer le fichier JSON";
    exit;
}

$stmt->close();

Log::info("JSON file created: items.json", $logFile);

echo "JSON exporté : items.json";