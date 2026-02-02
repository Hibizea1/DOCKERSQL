<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require "db.php";

// Requête SQL
$result = $conn->query("SELECT username, score FROM players");

$scores = [];

while ($row = $result->fetch_assoc()) {
    $scores[] = $row;
}

// Envoi du JSON au client (Unreal)
echo json_encode($scores);
?>
