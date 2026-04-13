<?php
$configPath = __DIR__ . '/../../config/db.conf';
$dbConfig = parse_ini_file($configPath);

if ($dbConfig === false) {
    echo json_encode(["status" => "failed", "cause" => "db_config_not_found"]);
    exit;
}

$host = $dbConfig['host'] ?? '';
$user = $dbConfig['user'] ?? '';
$password = $dbConfig['password'] ?? '';
$dbname = $dbConfig['dbname'] ?? '';

if ($host === '' || $user === '' || $dbname === '') {
    echo json_encode(["status" => "failed", "cause" => "db_config_invalid"]);
    exit;
}

try {
    $conn = new mysqli($host, $user, $password, $dbname);
} catch (Exception $e) {
    echo json_encode(["status"=> "failed", "caused by :" => $e->getMessage()]);
    exit;
}
