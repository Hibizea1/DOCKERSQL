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

$csvPath = __DIR__ . "/items.csv";
$file = fopen($csvPath, "w");

if (!$file) {
    log::critical("Impossible to create files ", $logFile);
    echo "Impossible de créer le fichier CSV";
    exit;
}


if (!empty($rows)) {
    fputcsv($file, array_keys($rows[0]));

    foreach ($rows as $row) {
        fputcsv($file, $row);
    }
}

fclose($file);
$stmt->close();

Log::info("Files created", $logFile);

/**
 * 4️⃣ Confirmation
 */
echo "CSV exporté : items.csv";
