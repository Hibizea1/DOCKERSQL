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

$slug = trim($_GET['slug'] ?? '');
$q = trim($_GET['q'] ?? '');

if ($slug !== '') {
    $sql = "
        SELECT
            m.id,
            m.slug,
            m.name,
            m.description,
            m.level_min,
            m.level_max,
            m.difficulty,
            GROUP_CONCAT(DISTINCT mc.name ORDER BY mc.name SEPARATOR ', ') AS categories,
            GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS spawn_biomes
        FROM wiki_monsters m
        LEFT JOIN wiki_monster_category_map mcm ON mcm.monster_id = m.id
        LEFT JOIN wiki_monster_categories mc ON mc.id = mcm.category_id
        LEFT JOIN wiki_monster_spawns s ON s.monster_id = m.id
        LEFT JOIN wiki_biomes b ON b.id = s.biome_id
        WHERE m.slug = ?
        GROUP BY m.id, m.slug, m.name, m.description, m.level_min, m.level_max, m.difficulty
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $monster = $stmt->get_result()->fetch_assoc();

    if (!$monster) {
        http_response_code(404);
        echo json_encode(["status" => "not_found"]);
        exit;
    }

    $lootSql = "
        SELECT
            l.item_id,
            i.name AS item_name,
            i.type,
            i.weaponType,
            l.drop_rate,
            l.min_qty,
            l.max_qty,
            COALESCE(b.name, 'Any biome') AS biome_name
        FROM wiki_monster_loot l
        LEFT JOIN items i ON i.Item_ID = l.item_id
        LEFT JOIN wiki_biomes b ON b.id = l.biome_id
        WHERE l.monster_id = ?
        ORDER BY l.drop_rate DESC, i.name ASC
    ";

    $lootStmt = $conn->prepare($lootSql);
    $lootStmt->bind_param('i', $monster['id']);
    $lootStmt->execute();
    $loot = $lootStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "status" => "success",
        "monster" => $monster,
        "loot" => $loot
    ]);
    exit;
}

$where = "";
$params = [];
$types = '';
if ($q !== '') {
    $where = "WHERE (m.name LIKE ? OR m.description LIKE ? OR m.slug LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types = 'sss';
}

$listSql = "
    SELECT
        m.id,
        m.slug,
        m.name,
        m.description,
        m.level_min,
        m.level_max,
        m.difficulty,
        GROUP_CONCAT(DISTINCT mc.name ORDER BY mc.name SEPARATOR ', ') AS categories,
        GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS spawn_biomes,
        GROUP_CONCAT(DISTINCT CONCAT(b.slug, '::', b.name) ORDER BY b.name SEPARATOR ', ') AS spawn_biome_refs,
        (
            SELECT GROUP_CONCAT(DISTINCT b2.name ORDER BY b2.name SEPARATOR ', ')
            FROM wiki_monster_category_map mcm2
            JOIN wiki_monster_categories mc2 ON mc2.id = mcm2.category_id
            JOIN wiki_biome_categories bc2 ON bc2.slug = mc2.slug
            JOIN wiki_biome_category_map bcm2 ON bcm2.category_id = bc2.id
            JOIN wiki_biomes b2 ON b2.id = bcm2.biome_id
            WHERE mcm2.monster_id = m.id
        ) AS spawn_biomes_by_category,
        (
            SELECT GROUP_CONCAT(DISTINCT CONCAT(b2.slug, '::', b2.name) ORDER BY b2.name SEPARATOR ', ')
            FROM wiki_monster_category_map mcm2
            JOIN wiki_monster_categories mc2 ON mc2.id = mcm2.category_id
            JOIN wiki_biome_categories bc2 ON bc2.slug = mc2.slug
            JOIN wiki_biome_category_map bcm2 ON bcm2.category_id = bc2.id
            JOIN wiki_biomes b2 ON b2.id = bcm2.biome_id
            WHERE mcm2.monster_id = m.id
        ) AS spawn_biome_refs_by_category,
        (
            SELECT GROUP_CONCAT(DISTINCT b3.name ORDER BY b3.name SEPARATOR ', ')
            FROM wiki_monster_loot l3
            JOIN wiki_biomes b3 ON b3.id = l3.biome_id
            WHERE l3.monster_id = m.id AND l3.biome_id IS NOT NULL
        ) AS spawn_biomes_by_loot,
        (
            SELECT GROUP_CONCAT(DISTINCT CONCAT(b3.slug, '::', b3.name) ORDER BY b3.name SEPARATOR ', ')
            FROM wiki_monster_loot l3
            JOIN wiki_biomes b3 ON b3.id = l3.biome_id
            WHERE l3.monster_id = m.id AND l3.biome_id IS NOT NULL
        ) AS spawn_biome_refs_by_loot,
        GROUP_CONCAT(DISTINCT li.name ORDER BY li.name SEPARATOR ', ') AS loot_items,
        GROUP_CONCAT(DISTINCT CONCAT(LOWER(REPLACE(TRIM(li.name), ' ', '-')), '::', li.name) ORDER BY li.name SEPARATOR ', ') AS loot_item_refs,
        COUNT(DISTINCT l.id) AS loot_entries
    FROM wiki_monsters m
    LEFT JOIN wiki_monster_category_map mcm ON mcm.monster_id = m.id
    LEFT JOIN wiki_monster_categories mc ON mc.id = mcm.category_id
    LEFT JOIN wiki_monster_spawns s ON s.monster_id = m.id
    LEFT JOIN wiki_biomes b ON b.id = s.biome_id
    LEFT JOIN wiki_monster_loot l ON l.monster_id = m.id
    LEFT JOIN items li ON li.Item_ID = l.item_id
    {$where}
    GROUP BY m.id, m.slug, m.name, m.description, m.level_min, m.level_max, m.difficulty
    ORDER BY m.level_min ASC, m.name ASC
";

$stmt = $conn->prepare($listSql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$monsters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "monsters" => $monsters,
    "filters" => ["q" => $q]
]);
