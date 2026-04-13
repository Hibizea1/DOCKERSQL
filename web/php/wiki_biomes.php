<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Client-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require "db.php";

$q = trim($_GET['q'] ?? '');

$where = "";
$params = [];
$types = '';

if ($q !== '') {
    $where = "WHERE (b.name LIKE ? OR b.description LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $types = 'ss';
}

$sql = "
    SELECT
        b.id,
        b.slug,
        b.name,
        b.description,
        b.level_min,
        b.level_max,
        GROUP_CONCAT(DISTINCT bc.name ORDER BY bc.name SEPARATOR ', ') AS categories,
        GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS spawn_monsters,
        COUNT(DISTINCT s.monster_id) AS monster_count,
        COUNT(DISTINCT l.id) AS loot_entries
    FROM wiki_biomes b
    LEFT JOIN wiki_biome_category_map bcm ON bcm.biome_id = b.id
    LEFT JOIN wiki_biome_categories bc ON bc.id = bcm.category_id
    LEFT JOIN wiki_monster_spawns s ON s.biome_id = b.id
    LEFT JOIN wiki_monsters m ON m.id = s.monster_id
    LEFT JOIN wiki_monster_loot l ON l.biome_id = b.id
    {$where}
    GROUP BY b.id, b.slug, b.name, b.description, b.level_min, b.level_max
    ORDER BY b.level_min ASC, b.name ASC
";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$biomes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "biomes" => $biomes,
    "filters" => ["q" => $q]
]);
