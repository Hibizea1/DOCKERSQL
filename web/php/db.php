<?php
$host = "db";
$user = "root";
$password = "root";
$dbname = "unreal_game";

try{
$conn = new mysqli($host, $user, $password, $dbname);
} catch (Exception $e) {
    echo json_encode(["status"=> "failed", "caused by :" => $e->getMessage()]);
    exit;
}


