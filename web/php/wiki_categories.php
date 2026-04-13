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

$sql = "
    SELECT
        c.id,
        c.slug,
        c.name,
        c.description,
        COUNT(pc.page_id) AS page_count
    FROM wiki_categories c
    LEFT JOIN wiki_page_categories pc ON pc.category_id = c.id
    LEFT JOIN wiki_pages p ON p.id = pc.page_id AND p.status = 'published'
    GROUP BY c.id, c.slug, c.name, c.description
    ORDER BY c.name ASC
";

$result = $conn->query($sql);
$categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode([
    "status" => "success",
    "categories" => $categories
]);
