// Récupération des infos stockées
let pseudo = sessionStorage.getItem("username");
let token = sessionStorage.getItem("access_token");

if (!pseudo || !token) {
    window.location.href = "login.html"; // redirection si pas loggé
}
// Objet contenant les objets équipés
let equippedItems = {
    Head: null,
    Chest: null,
    Legs: null,
    Weapon: null,
    Accessory: null
};


// Affichage du pseudo
//TEXT
document.getElementById("name").textContent = "Username : " + pseudo;

//BUTTON
document.getElementById("logOutButton").addEventListener("click", logoutUser);
document.getElementById("profileBtn").addEventListener("click", function () {
    document.getElementById("profileDropdown").classList.toggle("show");
});
window.addEventListener("click", function (e) {
    if (!e.target.matches('#profileBtn')) {
        const dropdown = document.getElementById("profileDropdown");
        if (dropdown.classList.contains("show")) {
            dropdown.classList.remove("show");
        }
    }
});
document.getElementById("slot-head").addEventListener("click", () => showItemInfo(equippedItems.helmet));
document.getElementById("slot-chest").addEventListener("click", () => showItemInfo(equippedItems.Chest));
document.getElementById("slot-legs").addEventListener("click", () => showItemInfo(equippedItems.Legs));
document.getElementById("slot-boots").addEventListener("click", () => showItemInfo(equippedItems.boots));
document.getElementById("slot-weapon").addEventListener("click", () => showItemInfo(equippedItems.weapon));
document.addEventListener("click", (e) => {
    if(!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = "none";
    }
});

const searchInput = document.getElementById("searchInput");
const searchResults = document.getElementById("searchResults");


//SeachBar
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
                    window.location.href = `/pages/profil.html?user=${encodeURIComponent(user.username)}`;
                });
                searchResults.appendChild(div);
            });
        }
        
        searchResults.style.display = "block";
        
    } catch (err) {
        console.error("Erreur recherche :", err);
    }
});




function logoutUser() {
  localStorage.removeItem("token");   // supprime le token
  sessionStorage.clear();             // si tu utilises sessionStorage
  window.location.href = "login.html";
}
// ===============================
//   RÉCUPÉRATION DES DONNÉES
// ===============================
async function getCharacterData() {
    try {
        const response = await fetch('/php/get_character.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            }
        });

        const text = await response.text();
        const data = JSON.parse(text);

        if (data.status === "success") {

            // Affichage du niveau
            document.getElementById("level").textContent =
                "Level : " + data.character[0].level;

            const table = document.getElementById("inventories");

            data.inventories.forEach(item => {

                const row = table.insertRow();
                row.insertCell(0).textContent = formatName(item.name);
                row.insertCell(1).textContent = item.rarity;
                row.insertCell(2).textContent = item.lvl;
                row.insertCell(3).textContent = item.price;
                row.insertCell(4).textContent = safeType(item.equipment_type);
                row.insertCell(5).textContent = safeType(item.weapon_type);
            });

            data.Equipment.forEach(item =>{
                placeItemInSlot(item);
            });

            // Sauvegarde
            sessionStorage.setItem("character", JSON.stringify(data.character));

        } else {
            alert("Impossible de récupérer les infos du personnage !");
        }

    } catch (error) {
        console.error("Erreur fetch personnage :", error);
    }
}

// ==========================================
//   PLACE AUTO L'ITEM DANS LE BON SLOT
// ==========================================
// Mettre un item dans le slot (ou placeholder si pas équipé)
function placeItemInSlot(item) {
    
    let type = item ? safeType(item.equipment_type) : null;
    let iconPath = item ? "../assets/img/items/" + item.name.replace(/\s+/g, "_") + ".png" : null;
    
    switch(type) {
        
        case "Head":
            setSlotImage("slot-head", iconPath, "../assets/img/slots/head.png");
            equippedItems.Head = item;
            break;
            
            case "Chest":
                setSlotImage("slot-chest", iconPath, "../assets/img/slots/chest.png");
                equippedItems.Chest = item;
                break;
                
                case "Pants":
                    setSlotImage("slot-pants", iconPath, "../assets/img/slots/pants.png");
                    equippedItems.pants = item;
                    break;
                    
                    case "Boots":
                        setSlotImage("slot-boots", iconPath, "../assets/img/slots/boots.png");
                        equippedItems.boots = item;
                        break;
                        
                        case "Weapon":
                            setSlotImage("slot-weapon", iconPath, "../assets/img/slots/weapon.png");
                            equippedItems.weapon = item;
                            break;
                            
                            default:
                                // Aucun item -> laisse l'image par défaut
                                break;
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



// ==========================
//   UPDATE IMAGE DU SLOT
// ==========================
function setSlotImage(id, path) {
    const img = document.getElementById(id);
    if (!img) {
        console.warn("Slot introuvable : " + id);
        return;
    }
    img.src = path;
    
    img.onerror = () => {
        img.src = "../assets/img/placeholder.png"; // fallback
    };
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
    
    document.getElementById("info-name").textContent = "Name : " + item.name;
    document.getElementById("info-rarity").textContent = "Rarity : " + item.rarity;
    document.getElementById("info-level").textContent = "Level : " + item.lvl;
    document.getElementById("info-desc").textContent =
    "Description : " + (item.description || "Aucune description.");
}




// Cacher le panel si clic en dehors

// Lancement
getCharacterData();

function formatName(name) {
  return name.replace(/([A-Z])/g, " $1").replace(/^./, c => c.toUpperCase());
}

// =========================================
//   Extraction sécurisée du type ("A::B")
// =========================================
function safeType(typeString) {
    if (!typeString || !typeString.includes("::")) return "None";
    return typeString.split("::")[1];
}