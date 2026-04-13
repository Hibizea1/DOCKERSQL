const inventoryPreviewInput = document.getElementById("inventoryPreview");
const darkModeInput = document.getElementById("notifications");
const saveButton = document.getElementById("saveSettings");

async function loadSettings() {
    try {
        const response = await window.AuthClient.authFetch('/php/get_parameter.php', {
            method: "GET"
        });

        if (!response.ok) {
            throw new Error("Erreur HTTP : " + response.status);
        }

        const result = await response.json();
        if (result.status !== "success" || !result.params) {
            throw new Error("Réponse invalide de get_parameter");
        }

        inventoryPreviewInput.checked = Number(result.params.inventorypreview) === 1;
        darkModeInput.checked = Number(result.params.darkMode) === 1;
    } catch (error) {
        console.error("Erreur chargement paramètres :", error);
    }
}

saveButton.addEventListener("click", async () => {
    const payload = {
        darkMode: darkModeInput.checked ? 1 : 0,
        inventorypreview: inventoryPreviewInput.checked ? 1 : 0
    };

    try {
        const response = await window.AuthClient.authFetch('/php/update_parameter.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok || result.status !== "success") {
            throw new Error(result.status || ("Erreur HTTP : " + response.status));
        }

        console.log("Paramètres sauvegardés :", result.params);
        alert("Paramètres sauvegardés !");
    } catch (error) {
        console.error("Erreur sauvegarde paramètres :", error);
        alert("Impossible de sauvegarder les paramètres");
    }
});

loadSettings();