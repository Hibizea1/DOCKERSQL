let token = sessionStorage.getItem("access_token");


document.getElementById("saveSettings").addEventListener("click", async () => {
    
    // Tes variables à envoyer
    const darkMode = 1;
    const inventoryPreview = 0;
    const userId = 42;

    const data = {
        darkMode: darkMode,
        inventoryPreview: inventoryPreview,
        userId: userId
    };

    try {
        const response = await fetch('/php/update_parameter.php', {
            method: "POST",
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error("Erreur HTTP : " + response.status);
        }

        const result = await response.json();
        console.log("Réponse du serveur :", result);

    } catch (error) {
        console.error("Erreur lors de la requête :", error);
    }
});