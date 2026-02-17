<?php

require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$payload = [
    "iss" => "ton-site",
    "iat" => time(),
    "exp" => time() + 3600,
    "user_id" => 123
];

$jwt = JWT::encode($payload, "SECRET_KEY", "HS256");
echo $jwt;
