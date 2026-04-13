const urlParams = new URLSearchParams(window.location.search);
const usernameFromUrl = (urlParams.get("user") || "").trim();
const usernameFromSession = (sessionStorage.getItem("username") || "").trim();
const username = usernameFromUrl || usernameFromSession;

const equippedItems = {
    head: null,
    chest: null,
    pants: null,
    boots: null,
    weapon: null
};

// Récupérer les infos du joueur
async function getPlayerData() {
    try {
        const response = await fetch(`/php/get_player.php?user=${encodeURIComponent(username)}`);
        const data = await response.json();

        if (data.status !== "success") {
            alert("Impossible de récupérer les infos du joueur !");
            return;
        }

        // Affichage pseudo et level
        document.getElementById("name").textContent = "Username : " + data.username;
        document.getElementById("level").textContent = "Level : " + data.character[0].level;

        const character = data.character && data.character[0] ? data.character[0] : {};
        const readStat = (...keys) => {
            for (const key of keys) {
                if (character[key] !== undefined && character[key] !== null) {
                    return character[key];
                }
            }
            return "-";
        };

        document.getElementById("stat-level").textContent = readStat("level");
        document.getElementById("stat-xp").textContent = readStat("xp");
        document.getElementById("stat-gold").textContent = readStat("gold");
        document.getElementById("stat-health").textContent = readStat("health");
        document.getElementById("stat-mana").textContent = readStat("mana");
        document.getElementById("stat-stamina").textContent = readStat("stamina");
        document.getElementById("stat-strength").textContent = readStat("strenght");
        document.getElementById("stat-intelligence").textContent = readStat("intelligence");

        // Inventaire
        const table = document.getElementById("inventories");
        const body = table.querySelector("tbody");
        body.innerHTML = "";
        data.inventories.forEach(item => {
            const row = body.insertRow();
            const nameCell = row.insertCell(0);
            nameCell.appendChild(createItemWikiLink(item.name));
            row.insertCell(1).textContent = item.rarity;
            row.insertCell(2).textContent = item.lvl;
            row.insertCell(3).textContent = item.price;
            row.insertCell(4).textContent = safeType(item.equipment_type);
            row.insertCell(5).textContent = safeType(item.weapon_type);
        });

        data.equipment.forEach((item) => {
            placeItemInSlot(item);
        });

        bindSlotInteractions();
        showItemInfo(equippedItems.head);

    } catch (err) {
        console.error("Erreur fetch player :", err);
    }
}

function formatName(name) {
  return name.replace(/([A-Z])/g, " $1").replace(/^./, c => c.toUpperCase());
}

function wikiItemUrl(name) {
        return `/pages/wiki.html?view=item&item=${encodeURIComponent(name || "")}`;
}

function createItemWikiLink(name) {
        const link = document.createElement("a");
        link.className = "item-wiki-link";
        link.href = wikiItemUrl(name);
        link.textContent = formatName(name || "Unknown");
        return link;
}

function safeType(typeString) {
    if (!typeString || !typeString.includes("::")) return "None";
    return typeString.split("::")[1];
}

// Place les items dans les slots
function placeItemInSlot(item) {
    const type = item ? safeType(item.equipment_type) : null;
    const iconPath = item ? "../assets/img/items/" + item.name + ".png" : null;
    const slotMap = {
        Head: "head",
        Chest: "chest",
        Pants: "pants",
        Boots: "boots",
        Weapon: "weapon"
    };

    const slotKey = slotMap[type] || null;
    if (!slotKey) {
        return;
    }

    setSlotImage("slot-" + slotKey, iconPath, "../assets/img/slots/" + slotKey + ".png");
    equippedItems[slotKey] = item;

    const mobileLabel = document.getElementById("mobile-slot-" + slotKey);
    if (mobileLabel) {
        mobileLabel.innerHTML = "";
        if (item && item.name) {
            const mobileLink = createItemWikiLink(item.name);
            mobileLink.addEventListener("click", (event) => {
                event.stopPropagation();
            });
            mobileLabel.appendChild(mobileLink);
        } else {
            mobileLabel.textContent = "None";
        }
    }
}

// Update de l'image avec fallback si l'image item n'existe pas
function setSlotImage(id, pathItem, pathDefault) {
    const img = document.getElementById(id);
    if (!img) return;

    if (pathItem) {
        img.src = pathItem;
        img.onerror = () => {
            img.src = pathDefault;
        };
    } else {
        img.src = pathDefault;
    }
}

function bindSlotInteractions() {
    const allSlotButtons = document.querySelectorAll("[data-slot]");
    allSlotButtons.forEach((slotButton) => {
        slotButton.addEventListener("click", () => {
            const slotKey = slotButton.getAttribute("data-slot");
            if (!slotKey) {
                return;
            }

            allSlotButtons.forEach((button) => {
                const sameSlot = button.getAttribute("data-slot") === slotKey;
                button.classList.toggle("active", sameSlot);
            });

            showItemInfo(equippedItems[slotKey]);
        });
    });
}



// ==========================
//   PANEL D’INFO À DROITE
// ==========================
function showItemInfo(item) {

    if (!item) {
        document.getElementById("info-name").textContent = "Name : ";
        document.getElementById("info-rarity").textContent = "Rarity : ";
        document.getElementById("info-level").textContent = "Level : ";
        document.getElementById("info-desc").textContent = "Description : Aucun item équipé.";
        return;
    }

    const infoName = document.getElementById("info-name");
    infoName.textContent = "Name : ";
    infoName.appendChild(createItemWikiLink(item.name));
    document.getElementById("info-rarity").textContent = "Rarity : " + item.rarity;
    document.getElementById("info-level").textContent = "Level : " + item.lvl;
    document.getElementById("info-desc").textContent =
        "Description : " + (item.description || "Aucune description.");
}

const searchInput = document.getElementById("searchInput");
const searchButton = document.getElementById("searchButton");
const searchResults = document.getElementById("searchResults");
const menuToggleButton = document.getElementById("menuToggleButton");
const profileMenuDropdown = document.getElementById("profileMenuDropdown");
const goHomeButton = document.getElementById("goHomeButton");
const goProfileButton = document.getElementById("goProfileButton");
const goSettingsButton = document.getElementById("goSettingsButton");
const goWikiButton = document.getElementById("goWikiButton");
const goLoginButton = document.getElementById("goLoginButton");

searchInput.addEventListener("input", async () => {
    const query = searchInput.value.trim();

    if(query === "") {
        searchResults.style.display = "none";
        return;
    }

    try {
        const response = await fetch(`/php/search_pseudo.php?username=${encodeURIComponent(query)}`);
        const data = await response.json();

        searchResults.innerHTML = "";

        if(data.status !== "success" || data.users.length === 0) {
            searchResults.innerHTML = "<div class='resultItem'>Aucun résultat</div>";
        } else {
            data.users.forEach(user => {
                const div = document.createElement("div");
                div.className = "resultItem";
                div.textContent = user.username;
                div.addEventListener("click", () => {
                    window.location.href = `/pages/profilViewer.html?user=${encodeURIComponent(user.username)}`;
                });
                searchResults.appendChild(div);
            });
        }

        searchResults.style.display = "block";

    } catch (err) {
        console.error("Erreur recherche :", err);
    }
});

// Cacher le panel si clic en dehors
document.addEventListener("click", (e) => {
    if(!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = "none";
    }
});

if (searchButton) {
    searchButton.addEventListener("click", () => {
        const query = searchInput.value.trim();
        if (query) {
            window.location.href = `/pages/profilViewer.html?user=${encodeURIComponent(query)}`;
        }
    });
}

function setupTopMenuDropdown() {
    if (!menuToggleButton || !profileMenuDropdown) {
        return;
    }

    const setOpen = (isOpen) => {
        profileMenuDropdown.classList.toggle("show", isOpen);
        profileMenuDropdown.style.display = isOpen ? "block" : "none";
        menuToggleButton.setAttribute("aria-expanded", String(isOpen));
    };

    setOpen(false);

    menuToggleButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        setOpen(!profileMenuDropdown.classList.contains("show"));
    });

    document.addEventListener("click", (event) => {
        if (!profileMenuDropdown.contains(event.target) && !menuToggleButton.contains(event.target)) {
            setOpen(false);
        }
    });

    if (goHomeButton) {
        goHomeButton.addEventListener("click", () => {
            window.location.href = "/pages/home.html";
        });
    }

    if (goProfileButton) {
        goProfileButton.addEventListener("click", () => {
            window.location.href = "/pages/profil.html";
        });
    }

    if (goSettingsButton) {
        goSettingsButton.addEventListener("click", () => {
            window.location.href = "/pages/settings.html";
        });
    }

    if (goWikiButton) {
        goWikiButton.addEventListener("click", () => {
            window.location.href = "/pages/wiki.html";
        });
    }

    if (goLoginButton) {
        goLoginButton.addEventListener("click", () => {
            window.location.href = "/pages/login.html";
        });
    }
}

setupTopMenuDropdown();
getPlayerData();