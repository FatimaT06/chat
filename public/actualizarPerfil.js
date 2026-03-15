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
