<?php
require "db.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
require "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$username = $data["username"];
$score = $data["score"];

$check = $conn->prepare("SELECT id FROM players WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "exists"]);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO players (username, score) VALUES (?, ?)"
);

$stmt->bind_param("si", $username, $score);
$stmt->execute();

echo json_encode(["status" => "success"]);


?>