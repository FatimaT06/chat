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

        {{-- Mensajes de éxito/error --}}
        @if(session('success'))
          <div style="background:rgba(16,185,129,0.15);border:1px solid #10b981;border-radius:8px;padding:10px 16px;margin-bottom:16px;color:#10b981;font-size:13px;">
            {{ session('success') }}
          </div>
        @endif
        @if(session('error'))
          <div style="background:rgba(239,68,68,0.15);border:1px solid #ef4444;border-radius:8px;padding:10px 16px;margin-bottom:16px;color:#ef4444;font-size:13px;">
            {{ session('error') }}
          </div>
        @endif

        {{-- Foto de perfil actual --}}
        <div style="display:flex; justify-content:center; margin:25px 0;">
          @php
            $foto = session('chat_user')['foto'] ?? null;
            $fotoUrl = $foto
              ? (str_starts_with($foto, 'http') ? $foto : asset('storage/' . $foto))
              : null;
          @endphp

          @if($fotoUrl)
            <img id="foto-preview"
                src="{{ $fotoUrl }}"
                style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);">
          @else
            <div id="foto-preview-placeholder" style="
              width:100px;height:100px;border-radius:50%;
              background:rgba(0,206,200,0.2);
              display:flex;align-items:center;justify-content:center;
              font-size:32px;color:var(--accent);font-weight:600;">
              {{ strtoupper(substr(session('chat_user')['nombre'] ?? 'U', 0, 1)) }}
            </div>
            <img id="foto-preview" src="" style="display:none;width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);">
          @endif
        </div>

        <form method="POST" action="{{ route('usuario.update') }}" enctype="multipart/form-data">
          @csrf

          <div class="field">
            <label>Nombre</label>
            <input type="text" name="nombre" value="{{ session('chat_user')['nombre'] ?? '' }}">
          </div>

          <div class="field">
            <label>Apellido Paterno</label>
            <input type="text" name="apellido_p" value="{{ session('chat_user')['apellido_p'] ?? '' }}">
          </div>

          <div class="field">
            <label>Apellido Materno</label>
            <input type="text" name="apellido_m" value="{{ session('chat_user')['apellido_m'] ?? '' }}">
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
            <input type="password" id="password" name="password" minlength="8">
          </div>

          <div class="field">
            <label>Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" minlength="8">
            <div id="password-error" style="color:#ef4444;font-size:12px;margin-top:4px;display:none;">
              Las contraseñas no coinciden
            </div>
          </div>

          <button class="btn" type="submit">
            Actualizar información
          </button>

        </form>

        {{-- Registro de biometría --}}
        <div style="margin-top:20px; text-align:center;">
          <button class="btn" id="btn-register-biometric">Registrar biometría</button>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const correo = "{{ session('chat_user')['correo'] ?? '' }}";

    const btnRegister = document.getElementById('btn-register-biometric');

    btnRegister.addEventListener('click', async () => {
        if (!correo) { alert('No se detecta correo del usuario'); return; }
        if (!window.PublicKeyCredential) { alert('Tu navegador no soporta WebAuthn'); return; }

        try {
            const challengeResp = await fetch("{{ route('biometria.challenge') }}");
            const challengeData = await challengeResp.json();

            const publicKey = {
                challenge: Uint8Array.from(atob(challengeData.challenge), c => c.charCodeAt(0)),
                rp: { name: "A&F Chat" },
                user: { id: Uint8Array.from(correo, c => c.charCodeAt(0)), name: correo, displayName: correo },
                pubKeyCredParams: [{ type: "public-key", alg: -7 }, { type: "public-key", alg: -257 }],
                timeout: 60000,
                authenticatorSelection: { userVerification: "preferred" },
                attestation: "none"
            };

            const credential = await navigator.credentials.create({ publicKey });

            const credentialJson = {
                id: credential.id,
                type: credential.type,
                rawId: Array.from(new Uint8Array(credential.rawId))
            };

            const resp = await fetch("{{ route('biometria.save') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                body: JSON.stringify({ correo, credential: JSON.stringify(credentialJson) })
            });

            const data = await resp.json();
            alert(data.message || 'Biometría registrada');

        } catch (err) {
            alert("Error al registrar biometría: " + err.message);
        }
    });
});
</script>

<script src="{{ asset('actualizarPerfil.js') }}"></script>
</body>
</html>
