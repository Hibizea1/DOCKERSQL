document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // empêche le rechargement de la page

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    const response = await fetch('/php/login_web.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
    });

    const data = await response.json();
    if (data["status"] === "success") {
        sessionStorage.setItem("username", data.character);
        sessionStorage.setItem("data", data);
        sessionStorage.setItem("access_token", data.access_token);
        sessionStorage.setItem("refresh_token", data.refresh_token);
        window.location.href = '/pages/home.html';
        console.log(data);
        alert(data["username"]);
    }

});
