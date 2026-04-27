<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require "db.php";
require "Helper.php";
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Log;

$jwtConfig = require __DIR__ . '/../../config/jwt.php';
$logFile = "getMonstersBiomes";

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    Log::warning("Missing JWT token", $logFile);
    http_response_code(401);
    echo json_encode(["status" => "missing_token"]);
    exit;
}

try {
    $decoded = JWT::decode(
        $matches[1],
        new Key($jwtConfig['secret'], $jwtConfig['algo'])
    );
    $userId = (int)$decoded->uid;
    Log::info("JWT token validated for user: $userId", $logFile);
} catch (Exception $e) {
    Log::error("Invalid JWT token: " . $e->getMessage(), $logFile);
    http_response_code(401);
    echo json_encode([
        "status" => "invalid_token",
        "error" => $e->getMessage()
    ]);
    exit;
}

if (CheckUser($conn, $userId)) {
    Log::error("User check failed for user ID: $userId", $logFile);
    echo json_encode(["status" => "failed due to unknown user"]);
    exit;
}

$monsterSql = "
    SELECT
        m.id,
        m.slug,
        m.name,
        m.description,
        m.level_min,
        m.level_max,
        m.difficulty,
        ms.drop_xp,
        ms.drop_gold,
        ms.stamina,
        ms.health,
        ms.strength,
        ms.mana
    FROM wiki_monsters m
    LEFT JOIN monsters_stats ms ON ms.slug = m.slug
    ORDER BY m.id ASC
";

$lootSql = "
    SELECT
        l.monster_id,
        l.item_id,
        i.name AS item_name,
        i.type AS item_type,
        i.weaponType AS item_weapon_type,
        l.drop_rate,
        l.min_qty,
        l.max_qty,
        l.notes,
        b.id AS biome_id,
        b.slug AS biome_slug,
        b.name AS biome_name
    FROM wiki_monster_loot l
    LEFT JOIN items i ON i.Item_ID = l.item_id
    LEFT JOIN wiki_biomes b ON b.id = l.biome_id
    ORDER BY l.monster_id ASC, l.drop_rate DESC, i.name ASC
";

$biomeSql = "
    SELECT
        b.id,
        b.slug,
        b.name,
        b.description,
        b.level_min,
        b.level_max
    FROM wiki_biomes b
    ORDER BY b.id ASC
";

$monsterResult = $conn->query($monsterSql);
if (!$monsterResult) {
    Log::critical("Database query failed for monsters", $logFile);
    http_response_code(500);
    echo json_encode(["status" => "failed", "error" => "Database query failed for monsters"]);
    exit;
}

$lootResult = $conn->query($lootSql);
if (!$lootResult) {
    Log::critical("Database query failed for monster loot", $logFile);
    http_response_code(500);
    echo json_encode(["status" => "failed", "error" => "Database query failed for monster loot"]);
    exit;
}

$biomeResult = $conn->query($biomeSql);
if (!$biomeResult) {
    Log::critical("Database query failed for biomes", $logFile);
    http_response_code(500);
    echo json_encode(["status" => "failed", "error" => "Database query failed for biomes"]);
    exit;
}

$monsters = $monsterResult->fetch_all(MYSQLI_ASSOC);
$lootRows = $lootResult->fetch_all(MYSQLI_ASSOC);

$lootByMonster = [];
foreach ($lootRows as $lootRow) {
    $monsterId = (int)$lootRow['monster_id'];
    if (!isset($lootByMonster[$monsterId])) {
        $lootByMonster[$monsterId] = [];
    }
    $lootByMonster[$monsterId][] = [
        'item_id' => $lootRow['item_id'],
        'item_name' => $lootRow['item_name'],
        'item_type' => $lootRow['item_type'],
        'item_weapon_type' => $lootRow['item_weapon_type'],
        'drop_rate' => $lootRow['drop_rate'],
        'min_qty' => $lootRow['min_qty'],
        'max_qty' => $lootRow['max_qty'],
        'notes' => $lootRow['notes'],
        'biome_id' => $lootRow['biome_id'],
        'biome_slug' => $lootRow['biome_slug'],
        'biome_name' => $lootRow['biome_name']
    ];
}

$monsters = array_map(function (array $monster) use ($lootByMonster): array {
    $monsterId = (int)$monster['id'];
    $monster['drops'] = $lootByMonster[$monsterId] ?? [];

    return $monster;
}, $monsters);
$biomes = $biomeResult->fetch_all(MYSQLI_ASSOC);

Log::info("Returned " . count($monsters) . " monsters and " . count($biomes) . " biomes", $logFile);

echo json_encode([
    "status" => "success",
    "monsters" => $monsters,
    "biomes" => $biomes
], JSON_UNESCAPED_UNICODE);
