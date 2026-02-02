<?php
require "db.php";


$stmt = $conn->query("SELECT * FROM items");
$rows = $stmt->fetch_all(MYSQLI_ASSOC);


$csvPath = __DIR__ . "/items.csv";
$file = fopen($csvPath, "w");

if (!$file) {
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

/**
 * 4️⃣ Confirmation
 */
echo "CSV exporté : items.csv";
