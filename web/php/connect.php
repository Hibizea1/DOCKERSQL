<?php

const HOST = 'db';
const USER = 'admin';
const PASS = 'admin';
const NAME = 'unreal_game';

$dsn = 'mysql:host=' . HOST . ';dbname=' . NAME;

try{
$db = new PDO($dsn, USER, PASS);

echo json_encode(["status"=>"connected"]);
}catch(PDOException $exception){
    echo json_encode(["status"=>"failed", "Caused by : " => $exception->getMessage()]);
    die;
}