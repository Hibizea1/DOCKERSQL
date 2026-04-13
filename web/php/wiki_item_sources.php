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

$itemId = (int)($_GET['item_id'] ?? 0);
$q = trim($_GET['q'] ?? '');

if ($itemId > 0) {
    $sql = "
        SELECT
            i.Item_ID AS item_id,
            i.name,
            i.type,
            i.weaponType,
            i.Price,
            i.imagePath,
            m.name AS monster_name,
            m.slug AS monster_slug,
            COALESCE(b.name, 'Any biome') AS biome_name,
            l.drop_rate,
            l.min_qty,
            l.max_qty
        FROM items i
        LEFT JOIN wiki_monster_loot l ON l.item_id = i.Item_ID
        LEFT JOIN wiki_monsters m ON m.id = l.monster_id
        LEFT JOIN wiki_biomes b ON b.id = l.biome_id
        WHERE i.Item_ID = ?
        ORDER BY l.drop_rate DESC, monster_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!$rows) {
        http_response_code(404);
        echo json_encode(["status" => "not_found"]);
        exit;
    }

    $catSql = "
        SELECT GROUP_CONCAT(DISTINCT ic.name ORDER BY ic.name SEPARATOR ', ') AS categories
        FROM wiki_item_category_map icm
        JOIN wiki_item_categories ic ON ic.id = icm.category_id
        WHERE icm.item_id = ?
    ";
    $catStmt = $conn->prepare($catSql);
    $catStmt->bind_param('i', $itemId);
    $catStmt->execute();
    $categoriesRow = $catStmt->get_result()->fetch_assoc();

    $item = [
        "item_id" => (int)$rows[0]['item_id'],
        "name" => $rows[0]['name'],
        "type" => $rows[0]['type'],
        "weaponType" => $rows[0]['weaponType'],
        "price" => $rows[0]['Price'],
        "imagePath" => $rows[0]['imagePath'] ?? null,
        "categories" => $categoriesRow['categories'] ?? null,
        "sources" => []
    ];

    foreach ($rows as $row) {
        if (!$row['monster_name']) {
            continue;
        }
        $item['sources'][] = [
            "monster_name" => $row['monster_name'],
            "monster_slug" => $row['monster_slug'],
            "biome_name" => $row['biome_name'],
            "drop_rate" => $row['drop_rate'],
            "min_qty" => $row['min_qty'],
            "max_qty" => $row['max_qty']
        ];
    }

    echo json_encode(["status" => "success", "item" => $item]);
    exit;
}

$where = "";
$params = [];
$types = '';
if ($q !== '') {
    $where = "WHERE (i.name LIKE ? OR i.type LIKE ? OR i.weaponType LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types = 'sss';
}

$sql = "
    SELECT
        i.Item_ID AS item_id,
        i.name,
        i.type,
        i.weaponType,
        i.Price,
        i.imagePath,
        GROUP_CONCAT(DISTINCT ic.name ORDER BY ic.name SEPARATOR ', ') AS categories,
        COUNT(DISTINCT l.id) AS source_count,
        GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS dropped_by,
        GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS biomes
    FROM items i
    LEFT JOIN wiki_item_category_map icm ON icm.item_id = i.Item_ID
    LEFT JOIN wiki_item_categories ic ON ic.id = icm.category_id
    LEFT JOIN wiki_monster_loot l ON l.item_id = i.Item_ID
    LEFT JOIN wiki_monsters m ON m.id = l.monster_id
    LEFT JOIN wiki_biomes b ON b.id = l.biome_id
    {$where}
    GROUP BY i.Item_ID, i.name, i.type, i.weaponType, i.Price, i.imagePath
    ORDER BY i.name ASC
";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "items" => $items,
    "filters" => ["q" => $q]
]);
