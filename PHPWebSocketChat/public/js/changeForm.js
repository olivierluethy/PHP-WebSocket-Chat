var login = document.getElementById("login");
var register = document.getElementById("register");

function changeToLogin() {
    register.style.display = "none";
    login.style.display = "block";
}

function changeToRegister() {
    register.style.display = "block";
    login.style.display = "none";
}