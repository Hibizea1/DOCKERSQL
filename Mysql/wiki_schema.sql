-- Wiki V1 schema for DOCKERSQL

CREATE TABLE IF NOT EXISTS wiki_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wiki_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(140) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    excerpt VARCHAR(400) DEFAULT NULL,
    content_md MEDIUMTEXT NOT NULL,
    content_html MEDIUMTEXT DEFAULT NULL,
    status ENUM(
        'draft',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft',
    author_id INT DEFAULT NULL,
    published_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wiki_pages_status_updated (status, updated_at)
);

CREATE TABLE IF NOT EXISTS wiki_page_categories (
    page_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (page_id, category_id),
    CONSTRAINT fk_wiki_pc_page FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_pc_category FOREIGN KEY (category_id) REFERENCES wiki_categories (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wiki_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    content_md MEDIUMTEXT NOT NULL,
    edited_by INT DEFAULT NULL,
    change_note VARCHAR(255) DEFAULT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wiki_revisions_page FOREIGN KEY (page_id) REFERENCES wiki_pages (id) ON DELETE CASCADE,
    INDEX idx_wiki_revisions_page_time (page_id, edited_at)
);

INSERT IGNORE INTO
    wiki_categories (slug, name, description)
VALUES (
        'classes',
        'Classes',
        'Roles and archetypes available in game.'
    ),
    (
        'items',
        'Items',
        'Equipment, rarity and progression details.'
    ),
    (
        'combat',
        'Combat',
        'Damage, stats and battle systems.'
    ),
    (
        'lore',
        'Lore',
        'World and universe background.'
    );

INSERT IGNORE INTO
    wiki_pages (
        slug,
        title,
        excerpt,
        content_md,
        status,
        published_at
    )
VALUES (
        'starter-guide',
        'Starter Guide',
        'Understand progression, inventory and first combat loop.',
        '# Starter Guide\n\nWelcome to DOCKERSQL.\n\n## First steps\n- Create your character\n- Open your inventory\n- Equip your first weapon\n\n## Core stats\n- Health: survivability\n- Mana: spell resource\n- Stamina: action resource\n',
        'published',
        NOW()
    ),
    (
        'rarity-system',
        'Rarity System',
        'How rarity impacts stats and upgrade value.',
        '# Rarity System\n\nItem rarity controls base scaling.\n\n## Tiers\n- Common\n- Rare\n- Epic\n- Legendary\n',
        'published',
        NOW()
    ),
    (
        'combat-basics',
        'Combat Basics',
        'Damage flow, survivability and efficient build planning.',
        '# Combat Basics\n\nCombat uses your equipped items and character stats.\n\n## Tips\n- Keep stamina above 30%\n- Balance health and damage\n- Upgrade weapon level often\n',
        'published',
        NOW()
    );

INSERT IGNORE INTO
    wiki_page_categories (page_id, category_id)
SELECT p.id, c.id
FROM wiki_pages p
    JOIN wiki_categories c ON (
        (
            p.slug = 'starter-guide'
            AND c.slug IN ('classes', 'items')
        )
        OR (
            p.slug = 'rarity-system'
            AND c.slug = 'items'
        )
        OR (
            p.slug = 'combat-basics'
            AND c.slug = 'combat'
        )
    );

-- =========================
-- Wiki V2: Biomes, Monsters, Loot Sources
-- =========================

CREATE TABLE IF NOT EXISTS wiki_biomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    level_min INT DEFAULT 1,
    level_max INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wiki_monsters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    description VARCHAR(400) DEFAULT NULL,
    level_min INT DEFAULT 1,
    level_max INT DEFAULT 100,
    difficulty ENUM(
        'easy',
        'normal',
        'hard',
        'boss'
    ) NOT NULL DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wiki_items (
    item_id INT NOT NULL PRIMARY KEY,
    slug VARCHAR(140) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(400) DEFAULT NULL,
    type VARCHAR(120) DEFAULT NULL,
    weaponType VARCHAR(120) DEFAULT NULL,
    price INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wiki_items_name (name),
    INDEX idx_wiki_items_slug (slug)
);

SET
    @has_imagePath := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'wiki_items'
            AND COLUMN_NAME = 'imagePath'
    );

SET
    @drop_imagePath_sql := IF(
        @has_imagePath > 0,
        'ALTER TABLE wiki_items DROP COLUMN imagePath',
        'SELECT 1'
    );

PREPARE stmt_drop_imagePath FROM @drop_imagePath_sql;

EXECUTE stmt_drop_imagePath;

DEALLOCATE PREPARE stmt_drop_imagePath;

SET
    @has_meshPath := (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'wiki_items'
            AND COLUMN_NAME = 'meshPath'
    );

SET
    @drop_meshPath_sql := IF(
        @has_meshPath > 0,
        'ALTER TABLE wiki_items DROP COLUMN meshPath',
        'SELECT 1'
    );

PREPARE stmt_drop_meshPath FROM @drop_meshPath_sql;

EXECUTE stmt_drop_meshPath;

DEALLOCATE PREPARE stmt_drop_meshPath;

INSERT IGNORE INTO
    wiki_items (
        item_id,
        slug,
        name,
        description,
        type,
        weaponType,
        price
    )
SELECT i.Item_ID, LOWER(
        REPLACE (TRIM(i.name), ' ', '-')
    ), i.name, NULL, i.type, i.weaponType, i.Price
FROM items i;

CREATE TABLE IF NOT EXISTS wiki_monster_spawns (
    monster_id INT NOT NULL,
    biome_id INT NOT NULL,
    spawn_rate DECIMAL(5, 2) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (monster_id, biome_id),
    CONSTRAINT fk_wiki_spawn_monster FOREIGN KEY (monster_id) REFERENCES wiki_monsters (id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_spawn_biome FOREIGN KEY (biome_id) REFERENCES wiki_biomes (id) ON DELETE CASCADE,
    INDEX idx_wiki_spawn_biome (biome_id)
);

CREATE TABLE IF NOT EXISTS wiki_monster_loot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monster_id INT NOT NULL,
    item_id INT NOT NULL,
    biome_id INT DEFAULT NULL,
    drop_rate DECIMAL(5, 2) DEFAULT NULL,
    min_qty INT DEFAULT 1,
    max_qty INT DEFAULT 1,
    notes VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_wiki_loot_monster FOREIGN KEY (monster_id) REFERENCES wiki_monsters (id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_loot_biome FOREIGN KEY (biome_id) REFERENCES wiki_biomes (id) ON DELETE SET NULL,
    INDEX idx_wiki_loot_item (item_id),
    INDEX idx_wiki_loot_monster (monster_id),
    INDEX idx_wiki_loot_biome (biome_id)
);

CREATE TABLE IF NOT EXISTS wiki_biome_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wiki_monster_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wiki_item_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS wiki_biome_category_map (
    biome_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (biome_id, category_id),
    CONSTRAINT fk_wiki_biome_cat_biome FOREIGN KEY (biome_id) REFERENCES wiki_biomes (id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_biome_cat_category FOREIGN KEY (category_id) REFERENCES wiki_biome_categories (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wiki_monster_category_map (
    monster_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (monster_id, category_id),
    CONSTRAINT fk_wiki_monster_cat_monster FOREIGN KEY (monster_id) REFERENCES wiki_monsters (id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_monster_cat_category FOREIGN KEY (category_id) REFERENCES wiki_monster_categories (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wiki_item_category_map (
    item_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (item_id, category_id),
    CONSTRAINT fk_wiki_item_cat_category FOREIGN KEY (category_id) REFERENCES wiki_item_categories (id) ON DELETE CASCADE,
    INDEX idx_wiki_item_cat_item (item_id)
);

INSERT IGNORE INTO
    wiki_biomes (
        slug,
        name,
        description,
        level_min,
        level_max
    )
VALUES (
        'green-plains',
        'Green Plains',
        'Low-risk area with beginner monsters.',
        1,
        12
    ),
    (
        'ashen-caves',
        'Ashen Caves',
        'Tight caves with stronger ambushes.',
        10,
        25
    ),
    (
        'frost-ridge',
        'Frost Ridge',
        'Cold biome with elite enemies.',
        20,
        40
    ),
    (
        'obsidian-wastes',
        'Obsidian Wastes',
        'End-game biome with boss-grade threats.',
        35,
        60
    );

INSERT IGNORE INTO
    wiki_monsters (
        slug,
        name,
        description,
        level_min,
        level_max,
        difficulty
    )
VALUES (
        'slime-scout',
        'Slime Scout',
        'Small scouting slime with weak defenses.',
        1,
        8,
        'easy'
    ),
    (
        'cave-raider',
        'Cave Raider',
        'Aggressive humanoid found in cave systems.',
        9,
        20,
        'normal'
    ),
    (
        'frost-stalker',
        'Frost Stalker',
        'Fast monster roaming frozen cliffs.',
        18,
        35,
        'hard'
    ),
    (
        'obsidian-titan',
        'Obsidian Titan',
        'Rare giant boss tied to obsidian zones.',
        40,
        60,
        'boss'
    );

INSERT IGNORE INTO
    wiki_biome_categories (slug, name, description)
VALUES (
        'starter',
        'Starter',
        'Beginner-friendly biome category.'
    ),
    (
        'midgame',
        'Midgame',
        'Intermediate progression biomes.'
    ),
    (
        'endgame',
        'Endgame',
        'High-level and late progression biomes.'
    );

INSERT IGNORE INTO
    wiki_monster_categories (slug, name, description)
VALUES (
        'beast',
        'Beast',
        'Natural and savage creatures.'
    ),
    (
        'humanoid',
        'Humanoid',
        'Intelligent or semi-intelligent enemies.'
    ),
    (
        'elite',
        'Elite',
        'Stronger enemies and bosses.'
    );

INSERT IGNORE INTO
    wiki_item_categories (slug, name, description)
VALUES (
        'weapon',
        'Weapon',
        'All offensive equipment.'
    ),
    (
        'armor',
        'Armor',
        'Protective equipment pieces.'
    ),
    (
        'consumable',
        'Consumable',
        'Single-use items and utility drops.'
    );

INSERT IGNORE INTO
    wiki_biome_category_map (biome_id, category_id)
SELECT b.id, c.id
FROM
    wiki_biomes b
    JOIN wiki_biome_categories c ON (
        (
            b.slug = 'green-plains'
            AND c.slug = 'starter'
        )
        OR (
            b.slug IN ('ashen-caves', 'frost-ridge')
            AND c.slug = 'midgame'
        )
        OR (
            b.slug = 'obsidian-wastes'
            AND c.slug = 'endgame'
        )
    );

INSERT IGNORE INTO
    wiki_monster_category_map (monster_id, category_id)
SELECT m.id, c.id
FROM
    wiki_monsters m
    JOIN wiki_monster_categories c ON (
        (
            m.slug = 'slime-scout'
            AND c.slug = 'beast'
        )
        OR (
            m.slug = 'cave-raider'
            AND c.slug = 'humanoid'
        )
        OR (
            m.slug IN (
                'frost-stalker',
                'obsidian-titan'
            )
            AND c.slug = 'elite'
        )
    );

INSERT IGNORE INTO
    wiki_monster_spawns (
        monster_id,
        biome_id,
        spawn_rate,
        notes
    )
SELECT
    m.id,
    b.id,
    CASE
        WHEN m.slug = 'slime-scout' THEN 48.00
        WHEN m.slug = 'cave-raider' THEN 32.00
        WHEN m.slug = 'frost-stalker' THEN 24.00
        ELSE 5.00
    END AS spawn_rate,
    NULL
FROM wiki_monsters m
    JOIN wiki_biomes b ON (
        (
            m.slug = 'slime-scout'
            AND b.slug = 'green-plains'
        )
        OR (
            m.slug = 'cave-raider'
            AND b.slug IN ('green-plains', 'ashen-caves')
        )
        OR (
            m.slug = 'frost-stalker'
            AND b.slug IN ('frost-ridge', 'ashen-caves')
        )
        OR (
            m.slug = 'obsidian-titan'
            AND b.slug = 'obsidian-wastes'
        )
    );

-- Seed loot mapping from existing game items table (if rows exist).
INSERT IGNORE INTO
    wiki_monster_loot (
        monster_id,
        item_id,
        biome_id,
        drop_rate,
        min_qty,
        max_qty,
        notes
    )
SELECT
    m.id,
    i.Item_ID,
    b.id,
    CASE
        WHEN m.slug = 'obsidian-titan' THEN 12.00
        WHEN m.slug = 'frost-stalker' THEN 18.00
        WHEN m.slug = 'cave-raider' THEN 24.00
        ELSE 30.00
    END AS drop_rate,
    1,
    1,
    'Auto-seeded relation'
FROM
    wiki_monsters m
    JOIN wiki_biomes b ON (
        (
            m.slug = 'slime-scout'
            AND b.slug = 'green-plains'
        )
        OR (
            m.slug = 'cave-raider'
            AND b.slug = 'ashen-caves'
        )
        OR (
            m.slug = 'frost-stalker'
            AND b.slug = 'frost-ridge'
        )
        OR (
            m.slug = 'obsidian-titan'
            AND b.slug = 'obsidian-wastes'
        )
    )
    JOIN wiki_items i ON (
        (
            m.slug = 'slime-scout'
            AND i.item_id % 4 = 0
        )
        OR (
            m.slug = 'cave-raider'
            AND i.item_id % 4 = 1
        )
        OR (
            m.slug = 'frost-stalker'
            AND i.item_id % 4 = 2
        )
        OR (
            m.slug = 'obsidian-titan'
            AND i.item_id % 4 = 3
        )
    );

INSERT IGNORE INTO
    wiki_item_category_map (item_id, category_id)
SELECT i.item_id, c.id
FROM
    wiki_items i
    JOIN wiki_item_categories c ON (
        (
            i.type LIKE '%Weapon%'
            AND c.slug = 'weapon'
        )
        OR (
            (
                i.type LIKE '%Head%'
                OR i.type LIKE '%Chest%'
                OR i.type LIKE '%Pants%'
                OR i.type LIKE '%Boots%'
            )
            AND c.slug = 'armor'
        )
    );