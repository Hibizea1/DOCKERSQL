<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use App\Log;

// Récupération du pseudo depuis GET si fetch GET, ou body si fetch POST JSON
$username = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $username = isset($data["username"]) ? $data["username"] : "";
} else {
    $username = isset($_GET["username"]) ? $_GET["username"] : "";
}

if ($username === ""){
    Log::error("Missing fields", "searchBar");
    echo json_encode(["status" => "missing_fields", "users" => []]);
    exit;
}

// Préparer la requête
$check = $conn->prepare(
    "SELECT username FROM users WHERE username LIKE ? LIMIT 10"
);
$likeQuery = $username . "%";
$check->bind_param("s", $likeQuery);
$check->execute();
$result = $check->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Toujours renvoyer un JSON
if (count($users) > 0) {
    echo json_encode(["status" => "success", "users" => $users]);
} else {
    echo json_encode(["status" => "not_found", "users" => []]);
}

$check->close();
$conn->close();