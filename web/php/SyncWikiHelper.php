<?php

function getIdBySlug(mysqli $conn, string $table, string $slug): ?int
{
    $stmt = $conn->prepare("SELECT id FROM {$table} WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row["id"] : null;
}

function normalizeSlugList(array $values): array
{
    $result = [];
    foreach ($values as $value) {
        $slug = trim((string)$value);
        if ($slug === '') {
            continue;
        }
        $result[] = strtolower($slug);
    }
    return array_values(array_unique($result));
}

function upsertBySlug(mysqli $conn, string $table, array $row): int
{
    $slug = trim((string)($row["slug"] ?? $row["category_slug"] ?? ""));
    $name = trim((string)($row["name"] ?? $row["label"] ?? $row["title"] ?? ""));
    $description = (string)($row["description"] ?? "");

    if ($slug === "" && $name !== "") {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');
    }

    if ($name === "" && $slug !== "") {
        $name = ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    if ($slug === "" || $name === "") {
        throw new Exception("Missing slug/name for table {$table}");
    }

    $id = getIdBySlug($conn, $table, $slug);
    if ($id !== null) {
        $stmt = $conn->prepare("UPDATE {$table} SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $id);
        $stmt->execute();
        return $id;
    }

    $stmt = $conn->prepare("INSERT INTO {$table} (slug, name, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $slug, $name, $description);
    $stmt->execute();
    return (int)$conn->insert_id;
}

function upsertWikiItem(mysqli $conn, array $item): int
{
    $itemId = (int)($item['item_id'] ?? $item['id'] ?? 0);
    $name = trim((string)($item['name'] ?? ''));
    $slug = trim((string)($item['slug'] ?? ''));
    $description = (string)($item['description'] ?? '');
    $type = (string)($item['type'] ?? null);
    $weaponType = (string)($item['weaponType'] ?? null);
    $price = isset($item['price']) ? (int)$item['price'] : (isset($item['Price']) ? (int)$item['Price'] : null);

    $existingStmt = $conn->prepare('SELECT item_id, slug, name, description, type, weaponType, price FROM wiki_items WHERE item_id = ? LIMIT 1');
    $existingStmt->bind_param('i', $itemId);
    $existingStmt->execute();
    $existingRow = $existingStmt->get_result()->fetch_assoc() ?: null;

    if (($slug === '' || $name === '' || $type === '' || $weaponType === '' || $price === null) && $itemId >= 0) {
        $fallbackStmt = $conn->prepare('SELECT item_id, name, type, weaponType, price FROM items WHERE item_id = ? LIMIT 1');
        $fallbackStmt->bind_param('i', $itemId);
        $fallbackStmt->execute();
        $fallback = $fallbackStmt->get_result()->fetch_assoc() ?: null;

        if ($fallback) {
            if ($name === '') {
                $name = (string)($fallback['name'] ?? '');
            }
            if ($type === '') {
                $type = (string)($fallback['type'] ?? '');
            }
            if ($weaponType === '') {
                $weaponType = (string)($fallback['weaponType'] ?? '');
            }
            if ($price === null) {
                $price = isset($fallback['price']) ? (int)$fallback['price'] : null;
            }
        }
    }

    if ($existingRow !== null && $slug === '' && $name === '' && $description === '' && $type === '' && $weaponType === '' && $price === null) {
        return $itemId;
    }

    if ($slug === '' && $name !== '') {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');
    }

    if ($name === '' && $slug !== '') {
        $name = ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    if ($name === '') {
        $name = 'Item ' . (string)$itemId;
    }

    if ($slug === '') {
        $slug = 'item-' . (string)$itemId;
    }

    $exists = $existingRow !== null;

    if ($exists) {
        $stmt = $conn->prepare('UPDATE wiki_items SET slug = ?, name = ?, description = ?, type = ?, weaponType = ?, price = ? WHERE item_id = ?');
        $stmt->bind_param('sssssii', $slug, $name, $description, $type, $weaponType, $price, $itemId);
        $stmt->execute();
        return $itemId;
    }

    $stmt = $conn->prepare('INSERT INTO wiki_items (item_id, slug, name, description, type, weaponType, price) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssi', $itemId, $slug, $name, $description, $type, $weaponType, $price);
    $stmt->execute();
    return $itemId;
}

function upsertGameItem(mysqli $conn, array $item): int
{
    $itemId = (int)($item['item_id'] ?? $item['id'] ?? 0);
    $name = (string)($item['name'] ?? '');
    $type = (string)($item['type'] ?? '');
    $rarity = (string)($item['rarity'] ?? 'Common');
    $price = (int)($item['price'] ?? $item['Price'] ?? 0);
    $weaponType = (string)($item['weaponType'] ?? '');
    $hasIconInPayload = array_key_exists('iconPath', $item) || array_key_exists('imagePath', $item);
    $hasMeshInPayload = array_key_exists('meshPath', $item) || array_key_exists('mesh_path', $item);
    $iconPath = (string)($item['iconPath'] ?? $item['imagePath'] ?? '');
    $meshPath = (string)($item['meshPath'] ?? $item['mesh_path'] ?? '');
    $damageMultiplier = (int)($item['damageMultiplier'] ?? 1);

    $checkStmt = $conn->prepare('SELECT 1 FROM items WHERE item_id = ? LIMIT 1');
    $checkStmt->bind_param('i', $itemId);
    $checkStmt->execute();
    $exists = $checkStmt->get_result()->fetch_row() !== null;

    if ($exists) {
        if (!$hasIconInPayload || !$hasMeshInPayload) {
            $existingStmt = $conn->prepare('SELECT imagePath, mesh_path FROM items WHERE item_id = ? LIMIT 1');
            $existingStmt->bind_param('i', $itemId);
            $existingStmt->execute();
            $existing = $existingStmt->get_result()->fetch_assoc() ?: [];

            if (!$hasIconInPayload) {
                $iconPath = (string)($existing['imagePath'] ?? $iconPath);
            }
            if (!$hasMeshInPayload) {
                $meshPath = (string)($existing['mesh_path'] ?? $meshPath);
            }
        }

        $stmt = $conn->prepare('UPDATE items SET name = ?, type = ?, rarity = ?, price = ?, weaponType = ?, imagePath = ?, mesh_path = ?, damageMultiplier = ? WHERE item_id = ?');
        $stmt->bind_param('sssisssii', $name, $type, $rarity, $price, $weaponType, $iconPath, $meshPath, $damageMultiplier, $itemId);
        $stmt->execute();
        return $itemId;
    }

    if ($iconPath === '' || $meshPath === '') {
        throw new Exception('Missing iconPath/meshPath for new item_id ' . (string)$itemId);
    }

    $stmt = $conn->prepare('INSERT INTO items (name, type, rarity, item_id, price, weaponType, imagePath, mesh_path, damageMultiplier) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssiisssi', $name, $type, $rarity, $itemId, $price, $weaponType, $iconPath, $meshPath, $damageMultiplier);
    $stmt->execute();
    return $itemId;
}
