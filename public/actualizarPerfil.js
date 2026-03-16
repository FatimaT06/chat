document.addEventListener("DOMContentLoaded", function(){

    const pass        = document.getElementById("password");
    const confirmPass = document.getElementById("password_confirmation");
    const error       = document.getElementById("password-error");

    if (!pass || !confirmPass) return;

    function checkPasswords(){
        if (confirmPass.value === "") {
            error.style.display      = "none";
            confirmPass.style.borderColor = "";
            return;
        }
        if (pass.value !== confirmPass.value) {
            error.style.display           = "block";
            confirmPass.style.borderColor = "#ef4444";
        } else {
            error.style.display           = "none";
            confirmPass.style.borderColor = "#10b981";
        }
    }

    pass.addEventListener("input", checkPasswords);
    confirmPass.addEventListener("input", checkPasswords);

    // Preview de la foto seleccionada antes de subir
    const fotoInput = document.getElementById("foto");
    if (fotoInput) {
        fotoInput.addEventListener("change", function(){
            const file = this.files[0];
            if (!file) return;

            // Mostrar nombre
            const nameEl = document.getElementById("file-name");
            if (nameEl) nameEl.textContent = file.name;

            // Preview de imagen
            const preview = document.getElementById("foto-preview");
            if (preview) {
                const url = URL.createObjectURL(file);
                preview.src   = url;
                preview.style.display = "block";
            }
        });
    }

});