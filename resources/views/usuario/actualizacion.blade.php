<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Actualizar Usuario</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body class="chat-body">
    <div id="app-screen">
        <div class="topbar">
        <div class="topbar-logo">A&amp;F Chat</div>
        <div class="topbar-user">
            <span>{{ session('chat_user')['nombre'] ?? '' }}</span>
            <a href="{{ route('chat') }}" class="config-btn">Chat</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button class="logout-btn" type="submit">Salir</button>
            </form>
        </div>
    </div>
    <div class="usuario-config">
       <div class="chat-area">

  <div style="max-width:600px; margin:40px auto; width:100%;">

    <div class="form-header">
      <h1>Actualizar Usuario</h1>
      <p>Modifica tu información de perfil</p>
    </div>

    <div style="display:flex; justify-content:center; margin:25px 0;">
      @if(session('chat_user')['foto'] ?? false)
        <img src="{{ asset('storage/' . session('chat_user')['foto']) }}"
        style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);">
      @else
        <div style="
          width:100px;
          height:100px;
          border-radius:50%;
          background:rgba(0,206,200,0.2);
          display:flex;
          align-items:center;
          justify-content:center;
          font-size:32px;
          color:var(--accent);
          font-weight:600;">
          {{ strtoupper(substr(session('chat_user')['nombre'] ?? 'U',0,1)) }}
        </div>
      @endif
    </div>

    <form method="POST" action="{{ route('usuario.update') }}" enctype="multipart/form-data">
      @csrf

      <div class="field">
        <label>Nombre</label>
        <input type="text" name="nombre"
        value="{{ session('chat_user')['nombre'] ?? '' }}">
      </div>

      <div class="field">
        <label>Apellido Paterno</label>
        <input type="text" name="apellido_p"
        value="{{ session('chat_user')['apellido_p'] ?? '' }}">
      </div>

      <div class="field">
        <label>Apellido Materno</label>
        <input type="text" name="apellido_m"
        value="{{ session('chat_user')['apellido_m'] ?? '' }}">
      </div>

      <div class="field">
        <label>Cambiar foto</label>
        <label class="file-btn">
            Seleccionar imagen
            <input type="file" name="foto" id="foto" accept="image/*">
        </label>
        <div id="file-name" style="font-size:12px;margin-top:6px;color:var(--muted);"></div>
      </div>

      <div style="margin:25px 0 10px; font-size:13px; color:var(--muted); font-weight:600;">
        Cambiar contraseña
      </div>

      <div class="field">
        <label>Nueva contraseña</label>
        <input type="password" name="password">
      </div>

      <div class="field">
        <label>Confirmar contraseña</label>
        <input type="password" name="password_confirmation">
      </div>

      <button class="btn" type="submit">
        Actualizar información
      </button>

    </form>

  </div>

</div>

    </div>
    <script>
        document.getElementById("foto").addEventListener("change", function(){
            const name = this.files[0]?.name || "";
            document.getElementById("file-name").textContent = name;
        });
    </script>

</body>
</html> 
    