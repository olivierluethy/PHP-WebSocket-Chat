// Clientside Validierung
window.addEventListener("load", function() {

    document.getElementById("login").addEventListener("submit", function(evt) {

        var errors = false;
        var warnings = document.querySelectorAll(".warning");
        if (warnings != null) {
            warnings.forEach(element => {
                element.remove();
            });
        }

        if (document.querySelector('#loginUser') != null) {
            if (document.querySelector('#loginUser').value.trim() === '') {
                document.getElementById('loginUserError').innerHTML = "Bitte gib einen Username ein.";
                errors = true;
            } else {
                document.getElementById('loginUserError').innerHTML = "";
            }
        }

        if (document.querySelector('#loginPass') != null) {
            if (document.querySelector('#loginPass').value.trim() === '') {
                document.getElementById('loginPassError').innerHTML = "Bitte gib ein Passwort ein.";
                errors = true;
            } else {
                document.getElementById('loginPassError').innerHTML = "";
            }
        }

        if (errors) {
            evt.preventDefault();
        }

    });
});