<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Sync-Token, X-Client-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");  
header("Content-Type: application/json");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}

require "db.php";
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/SyncWikiHelper.php';

use App\Log;

$logFile = "wikiSync";

function jsonFail(int $code, string $status, string $message = ""): void
{
    http_response_code($code);
    $payload = ["status" => $status];
    if ($message !== "") {
        $payload["message"] = $message;
    }
    echo json_encode($payload);
    exit;
}

$expectedToken = getenv("WIKI_SYNC_SECRET") ?: "";
$syncToken = $_SERVER["HTTP_X_SYNC_TOKEN"] ?? "";
if ($expectedToken !== "" && !hash_equals($expectedToken, $syncToken)) {
    Log::error("Blocked sync with invalid token", $logFile);
    jsonFail(401, "invalid_sync_token");
}

$raw = file_get_contents("php://input");
$data = json_decode($raw ?: "", true);
if (!is_array($data)) {
    jsonFail(400, "invalid_json");
}

$summary = [
    "biome_categories" => 0,
    "monster_categories" => 0,
    "item_categories" => 0,
    "items_game" => 0,
    "items" => 0,
    "biomes" => 0,
    "monsters" => 0,
    "spawns" => 0,
    "spawns_auto" => 0,
    "loots" => 0,
    "item_category_links" => 0,
    "deleted" => 0
];

try {
    $conn->begin_transaction();

    $biomeCategoriesById = [];
    $monsterCategoriesById = [];
    $processedBiomeIds = [];

    foreach (($data["biome_categories"] ?? []) as $category) {
        upsertBySlug($conn, "wiki_biome_categories", $category);
        $summary["biome_categories"]++;
    }

    foreach (($data["monster_categories"] ?? []) as $category) {
        upsertBySlug($conn, "wiki_monster_categories", $category);
        $summary["monster_categories"]++;
    }

    foreach (($data["item_categories"] ?? []) as $category) {
        upsertBySlug($conn, "wiki_item_categories", $category);
        $summary["item_categories"]++;
    }

    foreach (($data['items'] ?? []) as $item) {
        upsertGameItem($conn, $item);
        $summary['items_game']++;
        upsertWikiItem($conn, $item);
        $summary['items']++;
    }

    foreach (($data["biomes"] ?? []) as $biome) {
        $slug = trim((string)($biome["slug"] ?? ""));
        $name = trim((string)($biome["name"] ?? ""));
        $description = (string)($biome["description"] ?? "");
        $levelMin = (int)($biome["level_min"] ?? 1);
        $levelMax = (int)($biome["level_max"] ?? 100);

        if ($slug === "" || $name === "") {
            throw new Exception("Biome requires slug and name");
        }

        $biomeId = getIdBySlug($conn, "wiki_biomes", $slug);
        if ($biomeId !== null) {
            $stmt = $conn->prepare("UPDATE wiki_biomes SET name=?, description=?, level_min=?, level_max=? WHERE id=?");
            $stmt->bind_param("ssiii", $name, $description, $levelMin, $levelMax, $biomeId);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO wiki_biomes (slug, name, description, level_min, level_max) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $slug, $name, $description, $levelMin, $levelMax);
            $stmt->execute();
            $biomeId = (int)$conn->insert_id;
        }

        if (isset($biome["categories"]) && is_array($biome["categories"])) {
            $del = $conn->prepare("DELETE FROM wiki_biome_category_map WHERE biome_id = ?");
            $del->bind_param("i", $biomeId);
            $del->execute();

            foreach ($biome["categories"] as $catSlug) {
                $catId = getIdBySlug($conn, "wiki_biome_categories", (string)$catSlug);
                if ($catId === null) {
                    continue;
                }
                $ins = $conn->prepare("INSERT IGNORE INTO wiki_biome_category_map (biome_id, category_id) VALUES (?, ?)");
                $ins->bind_param("ii", $biomeId, $catId);
                $ins->execute();
            }

            $biomeCategoriesById[$biomeId] = normalizeSlugList($biome["categories"]);
        } else {
            $biomeCategoriesById[$biomeId] = [];
        }

        $processedBiomeIds[] = $biomeId;

        $summary["biomes"]++;
    }

    foreach (($data["monsters"] ?? []) as $monster) {
        $slug = trim((string)($monster["slug"] ?? ""));
        $name = trim((string)($monster["name"] ?? ""));
        $description = (string)($monster["description"] ?? "");
        $levelMin = (int)($monster["level_min"] ?? 1);
        $levelMax = (int)($monster["level_max"] ?? 100);
        $difficulty = (string)($monster["difficulty"] ?? "normal");

        if ($slug === "" || $name === "") {
            throw new Exception("Monster requires slug and name");
        }

        upsertMonster($conn, $monster);

        $monsterId = getIdBySlug($conn, "wiki_monsters", $slug);
        if ($monsterId !== null) {
            $stmt = $conn->prepare("UPDATE wiki_monsters SET name=?, description=?, level_min=?, level_max=?, difficulty=? WHERE id=?");
            $stmt->bind_param("ssiisi", $name, $description, $levelMin, $levelMax, $difficulty, $monsterId);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO wiki_monsters (slug, name, description, level_min, level_max, difficulty) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiis", $slug, $name, $description, $levelMin, $levelMax, $difficulty);
            $stmt->execute();
            $monsterId = (int)$conn->insert_id;
        }

        if (isset($monster["categories"]) && is_array($monster["categories"])) {
            $del = $conn->prepare("DELETE FROM wiki_monster_category_map WHERE monster_id = ?");
            $del->bind_param("i", $monsterId);
            $del->execute();

            foreach ($monster["categories"] as $catSlug) {
                $catId = getIdBySlug($conn, "wiki_monster_categories", (string)$catSlug);
                if ($catId === null) {
                    continue;
                }
                $ins = $conn->prepare("INSERT IGNORE INTO wiki_monster_category_map (monster_id, category_id) VALUES (?, ?)");
                $ins->bind_param("ii", $monsterId, $catId);
                $ins->execute();
            }

            $monsterCategoriesById[$monsterId] = normalizeSlugList($monster["categories"]);
        } else {
            $monsterCategoriesById[$monsterId] = [];
        }

        $summary["monsters"]++;
    }

    $explicitSpawns = $data["spawns"] ?? [];
    $useCategorySpawnSync = (bool)($data["spawn_from_category_slugs"] ?? (is_array($explicitSpawns) && count($explicitSpawns) === 0));

    if ($useCategorySpawnSync) {
        foreach ($processedBiomeIds as $biomeId) {
            $del = $conn->prepare("DELETE FROM wiki_monster_spawns WHERE biome_id = ?");
            $del->bind_param("i", $biomeId);
            $del->execute();
        }

        foreach ($biomeCategoriesById as $biomeId => $biomeCatSlugs) {
            if (!$biomeCatSlugs) {
                continue;
            }

            foreach ($monsterCategoriesById as $monsterId => $monsterCatSlugs) {
                if (!$monsterCatSlugs) {
                    continue;
                }

                $shared = array_values(array_intersect($biomeCatSlugs, $monsterCatSlugs));
                if (!$shared) {
                    continue;
                }

                $notes = "auto:category_match:" . implode(',', $shared);
                $spawnRate = null;
                $stmt = $conn->prepare("INSERT INTO wiki_monster_spawns (monster_id, biome_id, spawn_rate, notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE spawn_rate = VALUES(spawn_rate), notes = VALUES(notes)");
                $stmt->bind_param("iids", $monsterId, $biomeId, $spawnRate, $notes);
                $stmt->execute();

                $summary["spawns"]++;
                $summary["spawns_auto"]++;
            }
        }
    }

    foreach ($explicitSpawns as $spawn) {
        $monsterId = getIdBySlug($conn, "wiki_monsters", (string)($spawn["monster_slug"] ?? ""));
        $biomeId = getIdBySlug($conn, "wiki_biomes", (string)($spawn["biome_slug"] ?? ""));
        if ($monsterId === null || $biomeId === null) {
            continue;
        }

        $spawnRate = isset($spawn["spawn_rate"]) ? (float)$spawn["spawn_rate"] : null;
        $notes = (string)($spawn["notes"] ?? "");

        $stmt = $conn->prepare("INSERT INTO wiki_monster_spawns (monster_id, biome_id, spawn_rate, notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE spawn_rate = VALUES(spawn_rate), notes = VALUES(notes)");
        $stmt->bind_param("iids", $monsterId, $biomeId, $spawnRate, $notes);
        $stmt->execute();
        $summary["spawns"]++;
    }

    foreach (($data["loots"] ?? []) as $loot) {
        $monsterId = getIdBySlug($conn, "wiki_monsters", (string)($loot["monster_slug"] ?? ""));
        $itemId = (int)($loot["item_id"] ?? 0);
        if ($monsterId === null || $itemId < 0) {
            continue;
        }

        upsertWikiItem($conn, [
            'item_id' => $itemId,
            'name' => (string)($loot['item_name'] ?? ''),
            'slug' => (string)($loot['item_slug'] ?? ''),
            'description' => (string)($loot['item_description'] ?? '')
        ]);

        $biomeSlug = (string)($loot["biome_slug"] ?? "");
        $biomeId = $biomeSlug !== "" ? getIdBySlug($conn, "wiki_biomes", $biomeSlug) : null;
        $dropRate = isset($loot["drop_rate"]) ? (float)$loot["drop_rate"] : null;
        $minQty = max(1, (int)($loot["min_qty"] ?? 1));
        $maxQty = max($minQty, (int)($loot["max_qty"] ?? 1));
        $notes = (string)($loot["notes"] ?? "");

        if ($biomeId === null) {
            $del = $conn->prepare("DELETE FROM wiki_monster_loot WHERE monster_id = ? AND item_id = ? AND biome_id IS NULL");
            $del->bind_param("ii", $monsterId, $itemId);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO wiki_monster_loot (monster_id, item_id, biome_id, drop_rate, min_qty, max_qty, notes) VALUES (?, ?, NULL, ?, ?, ?, ?)");
            $ins->bind_param("iiddds", $monsterId, $itemId, $dropRate, $minQty, $maxQty, $notes);
            $ins->execute();
        } else {
            $del = $conn->prepare("DELETE FROM wiki_monster_loot WHERE monster_id = ? AND item_id = ? AND biome_id = ?");
            $del->bind_param("iii", $monsterId, $itemId, $biomeId);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO wiki_monster_loot (monster_id, item_id, biome_id, drop_rate, min_qty, max_qty, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iiiddis", $monsterId, $itemId, $biomeId, $dropRate, $minQty, $maxQty, $notes);
            $ins->execute();
        }

        $summary["loots"]++;
    }

    foreach (($data["item_category_links"] ?? []) as $link) {
        $itemId = (int)($link["item_id"] ?? 0);
        if ($itemId < 0) {
            continue;
        }

        upsertWikiItem($conn, [
            'item_id' => $itemId,
            'name' => (string)($link['item_name'] ?? ''),
            'slug' => (string)($link['item_slug'] ?? '')
        ]);

        $slugs = [];
        if (isset($link["category_slug"])) {
            $slugs[] = (string)$link["category_slug"];
        }
        if (isset($link["category_slugs"]) && is_array($link["category_slugs"])) {
            foreach ($link["category_slugs"] as $slug) {
                $slugs[] = (string)$slug;
            }
        }

        $del = $conn->prepare("DELETE FROM wiki_item_category_map WHERE item_id = ?");
        $del->bind_param("i", $itemId);
        $del->execute();

        foreach ($slugs as $slug) {
            $catId = getIdBySlug($conn, "wiki_item_categories", $slug);
            if ($catId === null) {
                continue;
            }
            $ins = $conn->prepare("INSERT IGNORE INTO wiki_item_category_map (item_id, category_id) VALUES (?, ?)");
            $ins->bind_param("ii", $itemId, $catId);
            $ins->execute();
            $summary["item_category_links"]++;
        }
    }

    $deletes = $data["deletes"] ?? [];
    foreach (($deletes["biomes"] ?? []) as $slug) {
        $stmt = $conn->prepare("DELETE FROM wiki_biomes WHERE slug = ?");
        $slug = (string)$slug;
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $summary["deleted"] += $stmt->affected_rows > 0 ? 1 : 0;
    }

    foreach (($deletes["monsters"] ?? []) as $slug) {
        $stmt = $conn->prepare("DELETE FROM wiki_monsters WHERE slug = ?");
        $slug = (string)$slug;
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $summary["deleted"] += $stmt->affected_rows > 0 ? 1 : 0;
    }

    $conn->commit();

    Log::info("Wiki sync completed", $logFile);
    echo json_encode([
        "status" => "success",
        "summary" => $summary
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    Log::error("Wiki sync failed: " . $e->getMessage(), $logFile);
    jsonFail(500, "sync_failed", $e->getMessage());
}
