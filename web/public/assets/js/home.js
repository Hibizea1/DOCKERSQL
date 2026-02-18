let pseudo = sessionStorage.getItem("username");
console.log(pseudo);
console.log(sessionStorage.getItem("data"));

if (!pseudo) {
    window.location.href = "login.html";
}

document.getElementById("name").textContent = pseudo;
