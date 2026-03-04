<?php
require "db.php";
require_once __DIR__ . '/vendor/autoload.php';

use App\Log;

$logfile = "itemUpdate";

$data = json_decode(file_get_contents("php://input"), true);
$items = $data["items"] ?? [];

if (empty($items)) {
    Log::error("No items provided for update", $logfile);
    echo json_encode(["status" => "failed", "cause" => "no_items"]);
    exit;
}

Log::info("Items update started with " . count($items) . " items", $logfile);

$checkStmt = $conn->prepare(
    "SELECT 1 FROM items WHERE item_id = ?"
);

$insertStmt = $conn->prepare(
    "INSERT INTO items (name, type, rarity, item_id, price, weaponType, imagePath, mesh_path, damageMultiplier)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

foreach ($items as $item) {

    // 🔹 Vérification existence
    $checkStmt->bind_param("i", $item["id"]);
    $checkStmt->execute();
    $checkStmt->store_result();

     // 🔹 Si existe → on skip
    if ($checkStmt->num_rows > 0) {
        continue;
    }

    // 🔹 Sinon → insertion
    $insertStmt->bind_param(
        "sssiisssi",
        $item["name"],
        $item["type"],
        $item["rarity"],
        $item["id"],
        $item["price"],
        $item["weaponType"],
        $item["iconPath"],
        $item["meshPath"],
        $item["damageMultiplier"]
    );
    $insertStmt->execute();
}

Log::info("Items updated successfully", $logfile);
echo json_encode(["status" => "ok"]);