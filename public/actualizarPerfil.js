document.addEventListener("DOMContentLoaded", function(){

    const pass = document.getElementById("password");
    const confirmPass = document.getElementById("password_confirmation");
    const error = document.getElementById("password-error");

    if(!pass || !confirmPass) return;

    function checkPasswords(){

        if(confirmPass.value === ""){
        error.style.display = "none";
        confirmPass.style.borderColor = "";
        return;
        }

        if(pass.value !== confirmPass.value){
        error.style.display = "block";
        confirmPass.style.borderColor = "#ef4444";
        }else{
        error.style.display = "none";
        confirmPass.style.borderColor = "#10b981";
        }

    }

    pass.addEventListener("input", checkPasswords);
    confirmPass.addEventListener("input", checkPasswords);

});

document.getElementById("foto").addEventListener("change", function(){
    const name = this.files[0]?.name || "";
    document.getElementById("file-name").textContent = name;
});

async function saveProfile(){
    let formData = new FormData();
    const nombre = document.getElementById("nombre").value;
    const apellido_p = document.getElementById("apellido_p").value;
    const apellido_m = document.getElementById("apellido_m").value;
    const password = document.getElementById("password").value;
    const foto = document.getElementById("foto").files[0];

    formData.append("nombre",nombre);
    formData.append("apellido_p",apellido_p);
    formData.append("apellido_m",apellido_m);
    formData.append("password",password);
    if(foto) formData.append("foto",foto);
    

    await fetch("/api/perfil",{
        method:"POST",
        body:formData,
        headers:{
            "Authorization":"Bearer "+token
        }
    });

    alert("Perfil actualizado");
}
