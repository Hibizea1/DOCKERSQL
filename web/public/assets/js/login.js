// ---------------------------
// Variables
// ---------------------------
const loginForm = document.getElementById("loginForm");
const signupForm = document.getElementById("signupForm");
const toggleBtn = document.getElementById("toggleForm");
const formTitle = document.getElementById("form-title");

// ---------------------------
// Toggle connexion / inscription
// ---------------------------
toggleBtn.addEventListener("click", () => {
    if (loginForm.style.display !== "none") {
        loginForm.style.display = "none";
        signupForm.style.display = "block";
        formTitle.textContent = "Inscription";
        toggleBtn.textContent = "Déjà inscrit ? Connexion";
    } else {
        loginForm.style.display = "block";
        signupForm.style.display = "none";
        formTitle.textContent = "Connexion";
        toggleBtn.textContent = "Pas encore inscrit ? S'inscrire";
    }
});

// ---------------------------
// Connexion
// ---------------------------
loginForm.addEventListener("submit", async function(e) {
    e.preventDefault(); // empêche le rechargement de la page

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    try {
        const response = await fetch('/php/login_web.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();
        if (data.status === "success") {
            sessionStorage.setItem("username", data.username);
            sessionStorage.setItem("data", JSON.stringify(data));
            sessionStorage.setItem("access_token", data.access_token);
            sessionStorage.setItem("refresh_token", data.refresh_token);
            window.location.href = '/pages/home.html';
            console.log(data);
        } else {
            alert(data.message || "Erreur de connexion");
        }
    } catch (err) {
        console.error("Erreur connexion :", err);
        alert("Erreur de connexion");
    }
});

// ---------------------------
// Inscription
// ---------------------------
signupForm.addEventListener("submit", async function(e) {
    e.preventDefault();

    const username = document.getElementById("signup-pseudo").value;
    const email = document.getElementById("signup-email").value;
    const password = document.getElementById("signup-password").value;
    const password2 = document.getElementById("signup-password2").value;

    if (password !== password2) {
        alert("Les mots de passe ne correspondent pas !");
        return;
    }

    try {
        const response = await fetch('/php/create_account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });

        const data = await response.json();

        if (data.status === "success") {
            alert("Compte créé avec succès ! Vous pouvez maintenant vous connecter.");
            // Retour à la connexion
            loginForm.style.display = "block";
            signupForm.style.display = "none";
            formTitle.textContent = "Connexion";
            toggleBtn.textContent = "Pas encore inscrit ? S'inscrire";
        } else {
            alert(data || "Erreur lors de la création du compte");
            console.error("Erreur inscription :", data);
        }
    } catch (err) {
        console.error("Erreur inscription :", err);
        alert("Erreur lors de la création du compte");
    }
});