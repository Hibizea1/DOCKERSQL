<?php
require "db.php";

$data = json_decode(file_get_contents("php://input"), true);
$items = $data["items"] ?? [];

$checkStmt = $conn->prepare(
    "SELECT 1 FROM items WHERE item_id = ?"
);

$insertStmt = $conn->prepare(
    "INSERT INTO items (name, type, rarity, item_id, price)
     VALUES (?, ?, ?, ?, ?)"
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
        "sssii",
        $item["name"],
        $item["type"],
        $item["rarity"],
        $item["id"],
        $item["price"]
    );
    $insertStmt->execute();
}

echo json_encode(["status" => "ok"]);
