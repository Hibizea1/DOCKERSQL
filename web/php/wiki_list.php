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

$search = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 12);
$limit = max(1, min(50, $limit));
$offset = ($page - 1) * $limit;

$where = ["p.status = 'published'"];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(p.title LIKE ? OR p.excerpt LIKE ? OR p.content_md LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($categorySlug !== '') {
    $where[] = "EXISTS (
        SELECT 1
        FROM wiki_page_categories wpc2
        JOIN wiki_categories wc2 ON wc2.id = wpc2.category_id
        WHERE wpc2.page_id = p.id AND wc2.slug = ?
    )";
    $params[] = $categorySlug;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) AS total FROM wiki_pages p WHERE {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['total'];

$listSql = "
    SELECT
        p.id,
        p.slug,
        p.title,
        p.excerpt,
        p.updated_at,
        GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS categories
    FROM wiki_pages p
    LEFT JOIN wiki_page_categories wpc ON wpc.page_id = p.id
    LEFT JOIN wiki_categories c ON c.id = wpc.category_id
    WHERE {$whereSql}
    GROUP BY p.id, p.slug, p.title, p.excerpt, p.updated_at
    ORDER BY p.updated_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$listStmt = $conn->prepare($listSql);
if ($types !== '') {
    $listStmt->bind_param($types, ...$params);
}
$listStmt->execute();
$pages = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "status" => "success",
    "pagination" => [
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "pages" => (int)ceil($total / $limit)
    ],
    "filters" => [
        "q" => $search,
        "category" => $categorySlug
    ],
    "pages" => $pages
]);
