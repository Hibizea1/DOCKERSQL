const categoriesBox = document.getElementById("wikiCategories");
const cardsBox = document.getElementById("wikiCards");
const articleBox = document.getElementById("wikiArticle");
const articleTitle = document.getElementById("wikiArticleTitle");
const articleMeta = document.getElementById("wikiArticleMeta");
const articleContent = document.getElementById("wikiArticleContent");
const wikiSearchInput = document.getElementById("wikiSearch");
const wikiSearchBtn = document.getElementById("wikiSearchBtn");
const wikiItemsCatalog = document.getElementById("wikiItemsCatalog");
const wikiCatalogTitle = document.getElementById("wikiCatalogTitle");
const wikiItemsList = document.getElementById("wikiItemsList");

let currentCategory = "";
let currentSearch = "";
let currentMode = "pages";
let liveSearchTimer = null;
let currentSlugFilter = "";

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function toPascalCase(value) {
    const input = String(value || "").trim();
    if (!input) {
        return "";
    }

    const normalized = input
        .replace(/[_-]+/g, " ")
        .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
        .replace(/\s+/g, " ")
        .trim();

    return normalized
        .split(" ")
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join(" ");
}

function categoriesToArray(categories) {
    if (!categories) {
        return [];
    }

    return String(categories)
        .split(",")
        .map((c) => c.trim())
        .filter((c) => c.length > 0);
}

function renderCategoryChips(categories) {
    const arr = categoriesToArray(categories);
    if (!arr.length) {
        return "";
    }

    const chips = arr
        .map((label) => `<span class="wiki-chip">${escapeHtml(label)}</span>`)
        .join("");

    return `<div class="wiki-card-categories">${chips}</div>`;
}

function splitCsv(value) {
    if (!value) {
        return [];
    }

    return String(value)
        .split(",")
        .map((v) => v.trim())
        .filter((v) => v.length > 0);
}

function slugifyName(value) {
    return String(value || "")
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function parseRefToken(token) {
    const raw = String(token || "").trim();
    if (!raw) {
        return null;
    }

    const idx = raw.indexOf("::");
    if (idx === -1) {
        return {
            slug: slugifyName(raw),
            label: toPascalCase(raw)
        };
    }

    const slug = raw.slice(0, idx).trim();
    const label = raw.slice(idx + 2).trim();
    return {
        slug: slug || slugifyName(label),
        label: toPascalCase(label || slug)
    };
}

function renderClickableCsv(value, target) {
    const items = splitCsv(value)
        .map(parseRefToken)
        .filter(Boolean);

    if (!items.length) {
        return "None";
    }

    return items
        .map((entry) => {
            const encodedSlug = encodeURIComponent(entry.slug || "");
            const encodedLabel = encodeURIComponent(entry.label || "");
            return `<a href="#" class="wiki-inline-link" data-wiki-target="${target}" data-wiki-slug="${encodedSlug}" data-wiki-label="${encodedLabel}">${escapeHtml(entry.label || entry.slug)}</a>`;
        })
        .join(", ");
}

async function fetchJson(url) {
    const response = await fetch(url);
    const data = await response.json();

    if (!response.ok || data.status !== "success") {
        throw new Error(data.status || "request_failed");
    }

    return data;
}

function setActiveCategoryButton(key) {
    const buttons = categoriesBox.querySelectorAll(".wiki-category-item");
    buttons.forEach((btn) => {
        btn.classList.toggle("active", btn.dataset.key === key);
    });
}

function cancelPendingLiveSearch() {
    if (liveSearchTimer) {
        clearTimeout(liveSearchTimer);
        liveSearchTimer = null;
    }
}

function showPagesMode() {
    wikiItemsCatalog.style.display = "none";
    cardsBox.style.display = "grid";
}

function showCatalogMode(title) {
    wikiCatalogTitle.textContent = title;
    wikiItemsCatalog.style.display = "block";
    cardsBox.style.display = "none";
    articleBox.style.display = "none";
}

function addDedicatedDataCategories() {
    const subtitle = document.createElement("p");
    subtitle.className = "wiki-category-subtitle";
    subtitle.textContent = "Donnees du jeu";
    categoriesBox.appendChild(subtitle);

    const dedicated = [
        { key: "data:biomes", label: "Biomes and Spawn" },
        { key: "data:monsters", label: "Monsters and Loot" },
        { key: "data:items", label: "Items Loot Sources" }
    ];

    dedicated.forEach((entry) => {
        const btn = document.createElement("button");
        btn.className = "wiki-category-item wiki-category-item-data";
        btn.dataset.key = entry.key;
        btn.textContent = entry.label;

        btn.addEventListener("click", async () => {
            cancelPendingLiveSearch();
            currentSearch = wikiSearchInput.value.trim();
            currentSlugFilter = "";
            currentCategory = "";
            currentMode = entry.key;
            setActiveCategoryButton(entry.key);

            if (entry.key === "data:biomes") {
                showCatalogMode("Biomes and Spawn");
            } else if (entry.key === "data:monsters") {
                showCatalogMode("Monsters and Loot");
            } else {
                showCatalogMode("Items Loot Sources");
            }

            runCurrentSearch();
        });

        categoriesBox.appendChild(btn);
    });
}

async function loadCategories() {
    const data = await fetchJson("/php/wiki_categories.php");
    categoriesBox.innerHTML = "";

    const allBtn = document.createElement("button");
    allBtn.className = "wiki-category-item active";
    allBtn.dataset.key = "page:all";
    allBtn.textContent = "Toutes les categories";
    allBtn.addEventListener("click", () => {
        cancelPendingLiveSearch();
        currentSearch = wikiSearchInput.value.trim();
        currentSlugFilter = "";
        currentCategory = "";
        currentMode = "pages";
        setActiveCategoryButton("page:all");
        showPagesMode();
        runCurrentSearch();
    });
    categoriesBox.appendChild(allBtn);

    data.categories.forEach((category) => {
        const btn = document.createElement("button");
        btn.className = "wiki-category-item";
        btn.dataset.slug = category.slug;
        btn.dataset.key = `page:${category.slug}`;
        btn.textContent = `${category.name} (${category.page_count})`;
        btn.title = category.description || "";

        btn.addEventListener("click", () => {
            cancelPendingLiveSearch();
            currentSearch = wikiSearchInput.value.trim();
            currentSlugFilter = "";
            currentCategory = category.slug;
            currentMode = "pages";
            setActiveCategoryButton(`page:${category.slug}`);
            showPagesMode();
            runCurrentSearch();
        });

        categoriesBox.appendChild(btn);
    });

    addDedicatedDataCategories();
}

async function openPage(slug) {
    const data = await fetchJson(`/php/wiki_page.php?slug=${encodeURIComponent(slug)}`);
    const page = data.page;

    articleTitle.textContent = page.title;
    articleMeta.textContent = `Categories: ${page.categories || "None"} | Updated: ${page.updated_at}`;
    articleContent.textContent = page.content_md || page.content_html || "";
    articleBox.style.display = "block";

    const url = new URL(window.location.href);
    url.searchParams.set("slug", slug);
    window.history.replaceState({}, "", url.toString());
}

async function loadPages() {
    const params = new URLSearchParams();
    if (currentSearch) params.set("q", currentSearch);
    if (currentCategory) params.set("category", currentCategory);

    const data = await fetchJson(`/php/wiki_list.php?${params.toString()}`);
    cardsBox.innerHTML = "";

    if (!data.pages.length) {
        cardsBox.innerHTML = "<p>Aucune page trouvee.</p>";
        articleBox.style.display = "none";
        return;
    }

    data.pages.forEach((page) => {
        const card = document.createElement("article");
        card.className = "wiki-card";
        card.innerHTML = `
            <h3>${escapeHtml(page.title)}</h3>
            ${renderCategoryChips(page.categories)}
            <p>${escapeHtml(page.excerpt || "Aucun resume")}</p>
        `;
        card.addEventListener("click", () => {
            openPage(page.slug);
        });
        cardsBox.appendChild(card);
    });

    const urlParams = new URLSearchParams(window.location.search);
    const slugFromUrl = urlParams.get("slug");
    if (slugFromUrl) {
        openPage(slugFromUrl);
    }
}

function renderEntityCatalog(entries, options) {
    wikiItemsList.innerHTML = "";

    if (!entries.length) {
        wikiItemsList.innerHTML = "<p>Aucun resultat trouve.</p>";
        return;
    }

    entries.forEach((entry) => {
        const row = document.createElement("article");
        row.className = "wiki-item-row";

        const title = options.titleFor(entry);
        const details = (typeof options.detailHtmlFor === "function")
            ? options.detailHtmlFor(entry)
            : options.detailFor(entry)
                .map((line) => `<p>${escapeHtml(line)}</p>`)
                .join("");

        row.innerHTML = `
            <button class="wiki-item-head" type="button">
                <img src="${escapeHtml(options.iconPath)}" alt="${escapeHtml(title)}">
                <span class="wiki-item-title">${escapeHtml(toPascalCase(title))}</span>
                <span class="wiki-item-toggle">▶</span>
            </button>
            <div class="wiki-item-detail">
                ${renderCategoryChips(options.categoriesFor(entry))}
                ${details}
            </div>
        `;

        const btn = row.querySelector(".wiki-item-head");
        btn.addEventListener("click", () => {
            row.classList.toggle("open");
        });

        wikiItemsList.appendChild(row);
    });
}

async function loadBiomesCatalog() {
    const params = new URLSearchParams();
    const query = currentSlugFilter || currentSearch;
    if (query) {
        params.set("q", query);
    }

    const data = await fetchJson(`/php/wiki_biomes.php?${params.toString()}`);
    renderEntityCatalog(data.biomes || [], {
        iconPath: "../assets/img/slots/head.png",
        titleFor: (biome) => biome.name,
        detailHtmlFor: (biome) => `
            <p>${escapeHtml(`Description: ${biome.description || "No description"}`)}</p>
            <p>${escapeHtml(`Level: ${biome.level_min}-${biome.level_max}`)}</p>
            <p>Spawned monsters: ${renderClickableCsv(biome.spawn_monster_refs || biome.spawn_monster_refs_by_category || biome.spawn_monster_refs_by_loot || biome.spawn_monsters || biome.spawn_monsters_by_category || biome.spawn_monsters_by_loot, "monster")}</p>
            <p>${escapeHtml(`Loot entries: ${biome.loot_entries}`)}</p>
        `,
        categoriesFor: (biome) => biome.categories
    });
}

async function loadMonstersCatalog() {
    const params = new URLSearchParams();
    const query = currentSlugFilter || currentSearch;
    if (query) {
        params.set("q", query);
    }

    const data = await fetchJson(`/php/wiki_monsters.php?${params.toString()}`);
    renderEntityCatalog(data.monsters || [], {
        iconPath: "../assets/img/slots/weapon.png",
        titleFor: (monster) => monster.name,
        detailHtmlFor: (monster) => `
            <p>${escapeHtml(`Description: ${monster.description || "No description"}`)}</p>
            <p>${escapeHtml(`Difficulty: ${monster.difficulty}`)}</p>
            <p>${escapeHtml(`Level: ${monster.level_min}-${monster.level_max}`)}</p>
            <p>Spawn biomes: ${renderClickableCsv(monster.spawn_biome_refs || monster.spawn_biome_refs_by_category || monster.spawn_biome_refs_by_loot || monster.spawn_biomes || monster.spawn_biomes_by_category || monster.spawn_biomes_by_loot, "biome")}</p>
            <p>Loot items: ${renderClickableCsv(monster.loot_item_refs || monster.loot_items, "item")}</p>
        `,
        categoriesFor: (monster) => monster.categories
    });
}

function buildItemDetailHtml(item) {
    const droppedBy = renderClickableCsv(item.dropped_by_refs || item.dropped_by, "monster");
    return `
        <p>${escapeHtml(`Type: ${item.type || "Unknown"}`)}</p>
        <p>${escapeHtml(`Weapon type: ${item.weaponType || "None"}`)}</p>
        <p>${escapeHtml(`Price: ${item.price ?? item.Price ?? "Unknown"}`)}</p>
        <p>Dropped by: ${droppedBy}</p>
    `;
}

function renderItemCatalog(items) {
    wikiItemsList.innerHTML = "";

    if (!items.length) {
        wikiItemsList.innerHTML = "<p>Aucun item trouve.</p>";
        return;
    }

    items.forEach((item) => {
        const row = document.createElement("article");
        row.className = "wiki-item-row";

        const imagePath = `../assets/img/items/${item.name}.png`;
        const details = buildItemDetailHtml(item);

        row.innerHTML = `
            <button class="wiki-item-head" type="button">
                <img src="${escapeHtml(imagePath)}" alt="${escapeHtml(item.name)}" onerror="this.onerror=null;this.src='../assets/img/slots/weapon.png';">
                <span class="wiki-item-title">${escapeHtml(toPascalCase(item.name))}</span>
                <span class="wiki-item-toggle">▶</span>
            </button>
            <div class="wiki-item-detail">
                ${renderCategoryChips(item.categories)}
                ${details}
            </div>
        `;

        const btn = row.querySelector(".wiki-item-head");
        btn.addEventListener("click", () => {
            row.classList.toggle("open");
        });

        wikiItemsList.appendChild(row);
    });
}

async function loadItemsCatalog() {
    const params = new URLSearchParams();
    const query = currentSlugFilter || currentSearch;
    if (query) {
        params.set("q", query);
    }

    const data = await fetchJson(`/php/wiki_item_sources.php?${params.toString()}`);
    renderItemCatalog(data.items || []);
}

function runCurrentSearch() {
    currentSearch = wikiSearchInput.value.trim();
    currentSlugFilter = "";

    if (currentMode === "data:items") {
        loadItemsCatalog();
        return;
    }

    if (currentMode === "data:monsters") {
        loadMonstersCatalog();
        return;
    }

    if (currentMode === "data:biomes") {
        loadBiomesCatalog();
        return;
    }

    loadPages();
}

async function applyInitialRouteFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const item = (params.get("item") || "").trim();
    const slug = (params.get("slug") || "").trim();
    const view = (params.get("view") || "").trim().toLowerCase();

    if (view === "item") {
        currentMode = "data:items";
        currentCategory = "";
        currentSlugFilter = slug;
        currentSearch = item || slug;
        wikiSearchInput.value = currentSearch;
        setActiveCategoryButton("data:items");
        showCatalogMode("Items Loot Sources");
        await loadItemsCatalog();
        return true;
    }

    if (view === "monster") {
        currentMode = "data:monsters";
        currentCategory = "";
        currentSlugFilter = slug;
        currentSearch = item || slug;
        wikiSearchInput.value = currentSearch;
        setActiveCategoryButton("data:monsters");
        showCatalogMode("Monsters and Loot");
        await loadMonstersCatalog();
        return true;
    }

    if (view === "biome") {
        currentMode = "data:biomes";
        currentCategory = "";
        currentSlugFilter = slug;
        currentSearch = item || slug;
        wikiSearchInput.value = currentSearch;
        setActiveCategoryButton("data:biomes");
        showCatalogMode("Biomes and Spawn");
        await loadBiomesCatalog();
        return true;
    }

    return false;
}

wikiSearchBtn.addEventListener("click", runCurrentSearch);

wikiSearchInput.addEventListener("input", () => {
    if (liveSearchTimer) {
        clearTimeout(liveSearchTimer);
    }

    liveSearchTimer = setTimeout(() => {
        runCurrentSearch();
    }, 180);
});

wikiSearchInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
        event.preventDefault();
        runCurrentSearch();
    }
});

wikiItemsList.addEventListener("click", (event) => {
    const link = event.target.closest(".wiki-inline-link");
    if (!link) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const target = link.dataset.wikiTarget || "";
    const slug = decodeURIComponent(link.dataset.wikiSlug || "").trim();
    const label = decodeURIComponent(link.dataset.wikiLabel || "").trim();
    if (!slug && !label) {
        return;
    }

    const nextSearch = label || slug;
    const nextSlug = slug || slugifyName(label);

    if (target === "monster") {
        currentMode = "data:monsters";
        currentCategory = "";
        currentSlugFilter = nextSlug;
        currentSearch = nextSearch;
        wikiSearchInput.value = nextSearch;
        setActiveCategoryButton("data:monsters");
        showCatalogMode("Monsters and Loot");
        loadMonstersCatalog();

        const url = new URL(window.location.href);
        url.searchParams.set("view", "monster");
        url.searchParams.set("slug", nextSlug);
        url.searchParams.set("item", nextSearch);
        window.history.replaceState({}, "", url.toString());
        return;
    }

    if (target === "biome") {
        currentMode = "data:biomes";
        currentCategory = "";
        currentSlugFilter = nextSlug;
        currentSearch = nextSearch;
        wikiSearchInput.value = nextSearch;
        setActiveCategoryButton("data:biomes");
        showCatalogMode("Biomes and Spawn");
        loadBiomesCatalog();

        const url = new URL(window.location.href);
        url.searchParams.set("view", "biome");
        url.searchParams.set("slug", nextSlug);
        url.searchParams.set("item", nextSearch);
        window.history.replaceState({}, "", url.toString());
        return;
    }

    if (target === "item") {
        currentMode = "data:items";
        currentCategory = "";
        currentSlugFilter = nextSlug;
        currentSearch = nextSearch;
        wikiSearchInput.value = nextSearch;
        setActiveCategoryButton("data:items");
        showCatalogMode("Items Loot Sources");
        loadItemsCatalog();

        const url = new URL(window.location.href);
        url.searchParams.set("view", "item");
        url.searchParams.set("slug", nextSlug);
        url.searchParams.set("item", nextSearch);
        window.history.replaceState({}, "", url.toString());
    }
});

(async function initWiki() {
    try {
        await loadCategories();

        const redirected = await applyInitialRouteFromUrl();
        if (redirected) {
            return;
        }

        setActiveCategoryButton("page:all");
        showPagesMode();
        await loadPages();
    } catch (error) {
        cardsBox.innerHTML = "<p>Impossible de charger le wiki pour le moment.</p>";
        console.error("Wiki init error:", error);
    }
})();
