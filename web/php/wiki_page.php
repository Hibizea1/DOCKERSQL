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
if ($slug === '') {
    http_response_code(400);
    echo json_encode([
        "status" => "missing_slug"
    ]);
    exit;
}

$sql = "
    SELECT
        p.id,
        p.slug,
        p.title,
        p.excerpt,
        p.content_md,
        p.content_html,
        p.updated_at,
        GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS categories
    FROM wiki_pages p
    LEFT JOIN wiki_page_categories wpc ON wpc.page_id = p.id
    LEFT JOIN wiki_categories c ON c.id = wpc.category_id
    WHERE p.slug = ? AND p.status = 'published'
    GROUP BY p.id, p.slug, p.title, p.excerpt, p.content_md, p.content_html, p.updated_at
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $slug);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    http_response_code(404);
    echo json_encode([
        "status" => "not_found"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "page" => $page
]);
