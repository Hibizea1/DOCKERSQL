<?php

function InsertIntoTable($FTableName, $data)
{
    require "db.php";

    $columns = GetTableColumns($FTableName);

    $filteredData = array_intersect_key($data, array_flip($columns));

    if (empty($filteredData)) {
        throw new Exception("No valid data to insert");
    }

    $fields = implode(', ', array_keys($filteredData));
    $placeholders = implode(', ', array_fill(0, count($filteredData), '?'));

    $sql = "INSERT INTO `$FTableName` ($fields) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    $types = '';
    $values = [];

    foreach ($filteredData as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $value;
    }

    $stmt->bind_param($types, ...$values);

    return $stmt->execute();
}

function GetTableColumns(string $table): array
{
    require "db.php";

    $result = $conn->query("DESCRIBE `$table`");

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    return $columns;
}

function GetUserId(mysqli $conn, string $username): ?int
{
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return $row ? (int)$row['id'] : null;
}

function GetCharacterFromUserId(mysqli $conn,string $user_id): array
{
    $stmt = $conn->prepare("SELECT * FROM characters WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $characters = $result->fetch_all(MYSQLI_ASSOC);

    return $characters ?: [];
}

function UpdateCharacter(mysqli $conn, int $user_id, $data)
{
    $stmt = $conn->prepare("UPDATE characters SET level = ?, xp = ?, gold = ?,stamina = ?,strenght=?,mana=?,health=?,intelligence=? WHERE user_id = ?");
    $stmt->bind_param("iiiiiiiii", $data["level"], $data["xp"], $data["gold"], $data["stamina"],$data["strengh"],$data["mana"],$data["health"],$data["intelligence"],$user_id);
    return $stmt->execute();
}

function CheckUser(mysqli $conn, int $user_id): bool
{
    $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->bind_param("i", $user_id);
    $checkUser->execute();
    $checkUser->store_result();

    if ($checkUser->num_rows === 0) {
        return true;
    }else {
        return false;
    }
}

function GetAllItemsFromUserId(mysqli $conn, string $user_id): array
{
    $characters = GetCharacterFromUserId($conn, $user_id);

    if (!$characters || !isset($characters[0]["id"])) {
        return ["No Character"];
    }

    $character_id = (int)$characters[0]["id"];
    // var_dump($character_id);

    $stmt = $conn->prepare("
        SELECT * FROM inventories WHERE character_id = ?
    ");
    $stmt->bind_param("i", $character_id);
    $stmt->execute();

    $inventories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // var_dump($inventories);
    $items = [];

    foreach ($inventories as $itemInstance) {

        $itemStmt = $conn->prepare("
            SELECT * FROM instance_items WHERE id = ?
        ");
        $itemStmt->bind_param("i", $itemInstance["item_id"]);
        $itemStmt->execute();

        $itemData = $itemStmt->get_result()->fetch_assoc();
        
        // var_dump($itemData);
        if (!$itemData) {
            continue;
        }

        $items[] = [
            "item_id"      => (int)$itemData["item_id"],
            "rarity"       => (int)$itemData["rarity"],
            "lvl"          => (int)$itemData["lvl"],
        ];
    }

    return $items;
}

function GetAllEquipmentFromUserId(mysqli $conn, string $user_id): array
{
    $characters = GetCharacterFromUserId($conn, $user_id);

    if (!$characters || !isset($characters[0]["id"])) {
        return ["No Character"];
    }

    $character_id = (int)$characters[0]["id"];
    // var_dump($character_id);

    $stmt = $conn->prepare("
        SELECT * FROM equipment WHERE characters_id = ?
    ");
    $stmt->bind_param("i", $character_id);
    $stmt->execute();

    $equipment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // var_dump($inventories);
    $items = [];

    foreach ($equipment as $itemInstance) {

        $itemStmt = $conn->prepare("
            SELECT * FROM instance_items WHERE id = ?
        ");
        $itemStmt->bind_param("i", $itemInstance["item_id"]);
        $itemStmt->execute();

        $itemData = $itemStmt->get_result()->fetch_assoc();
        
        // var_dump($itemData);
        if (!$itemData) {
            continue;
        }

        $items[] = [
            "item_id"      => (int)$itemData["item_id"],
            "rarity"       => (int)$itemData["rarity"],
            "lvl"          => (int)$itemData["lvl"],
        ];
    }

    return $items;
}

function GetAllItemsFromInventoryAndUserID(mysqli $conn, string $user_id): array
{
    $characters = GetCharacterFromUserId($conn, $user_id);

    if (!$characters || !isset($characters[0]["id"])) {
        return ["No Character"];
    }

    $character_id = (int)$characters[0]["id"];
    // var_dump($character_id);

    $stmt = $conn->prepare("
        SELECT * FROM inventories WHERE character_id = ?
    ");
    $stmt->bind_param("i", $character_id);
    $stmt->execute();

    $inventories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // var_dump($inventories);
    $items = [];

    foreach ($inventories as $itemInstance) {

        $itemStmt = $conn->prepare("
            SELECT * FROM instance_items WHERE id = ?
        ");
        $itemStmt->bind_param("i", $itemInstance["item_id"]);
        $itemStmt->execute();

        $itemInstanceData = $itemStmt->get_result()->fetch_assoc();
        
        $itemStmt = $conn->prepare("
            SELECT * FROM items WHERE Item_ID = ?
        ");
        $itemStmt->bind_param("i", $itemInstanceData["item_id"]);
        $itemStmt->execute();

        $itemData = $itemStmt->get_result()->fetch_assoc();
        
        if (!$itemInstanceData) {
            continue;
        }

        $items[] = [
            "item_id"      => (int)$itemInstanceData["item_id"],
            "rarity"       => (int)$itemInstanceData["rarity"],
            "lvl"          => (int)$itemInstanceData["lvl"],
            "equipment_type" => $itemData["type"],
            "name" => $itemData["name"],
            "weapon_type" => $itemData["weaponType"],
            "price" => $itemData["Price"]
        ];
    }

    return $items;
}

function GetAllItemsFromEquipmentAndUserID(mysqli $conn, string $user_id): array
{
    $characters = GetCharacterFromUserId($conn, $user_id);

    if (!$characters || !isset($characters[0]["id"])) {
        return ["No Character"];
    }

    $character_id = (int)$characters[0]["id"];
    // var_dump($character_id);

    $stmt = $conn->prepare("
        SELECT * FROM equipment WHERE characters_id = ?
    ");
    $stmt->bind_param("i", $character_id);
    $stmt->execute();

    $inventories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // var_dump($inventories);
    $items = [];

    foreach ($inventories as $itemInstance) {

        $itemStmt = $conn->prepare("
            SELECT * FROM instance_items WHERE id = ?
        ");
        $itemStmt->bind_param("i", $itemInstance["item_id"]);
        $itemStmt->execute();

        $itemInstanceData = $itemStmt->get_result()->fetch_assoc();
        
        $itemStmt = $conn->prepare("
            SELECT * FROM items WHERE Item_ID = ?
        ");
        $itemStmt->bind_param("i", $itemInstanceData["item_id"]);
        $itemStmt->execute();

        $itemData = $itemStmt->get_result()->fetch_assoc();
        
        if (!$itemInstanceData) {
            continue;
        }

        $items[] = [
            "item_id"      => (int)$itemInstanceData["item_id"],
            "rarity"       => (int)$itemInstanceData["rarity"],
            "lvl"          => (int)$itemInstanceData["lvl"],
            "equipment_type" => $itemData["type"],
            "name" => $itemData["name"],
            "weapon_type" => $itemData["weaponType"],
            "price" => $itemData["Price"]
        ];
    }

    return $items;
}