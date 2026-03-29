<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>A&F Chat — Iniciar sesión</title>
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body>
<div class="page-center">
  <div class="wrapper wrapper-login">
    <div class="panel-right" style="width:100%">
      <div class="form-header">
        <h1>A&amp;F Chat</h1>
        <p>Bienvenido de vuelta — <a href="{{ route('register') }}">¿No tienes cuenta? Regístrate</a></p>
      </div>

      @if($errors->any())
        <div class="error-box" style="display:block">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('login.post') }}" id="login-form">
        @csrf
        <div class="field">
          <label>Correo electrónico</label>
          <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" value="{{ old('correo') }}" required />
        </div>

        <div class="field">
          <label>Contraseña</label>
          <input type="password" id="password" name="password" placeholder="Contraseña" required />
        </div>

        <button class="btn" type="submit">Iniciar sesión</button>
      </form>

      <div style="text-align:center;">
        <button class="btn" id="btn-biometric-login">Iniciar sesión con huella/cara</button>
      </div>
    </div>
  </div>
</div>

<script src="{{ asset('login.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const correoInput = document.getElementById('correo');
    document.getElementById('btn-biometric-login').addEventListener('click', async () => {
        const correo = correoInput.value.trim();
        if (!correo) return alert('Ingresa tu correo');

        if (!window.PublicKeyCredential) return alert('Tu navegador no soporta WebAuthn');

        const publicKey = { challenge: new Uint8Array(32), timeout: 60000, userVerification: "preferred" };

        try {
            const credential = await navigator.credentials.get({ publicKey });
            const credentialJson = { id: credential.id, type: credential.type, rawId: Array.from(new Uint8Array(credential.rawId)) };
            const resp = await fetch("{{ route('biometria.login') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta.content },
                body: JSON.stringify({ correo, credential: JSON.stringify(credentialJson) })
            });

            const data = await resp.json();
            if (data.success) window.location.href = "{{ route('chat') }}";
            else alert(data.message || 'Biometría no reconocida');

        } catch (err) {
            alert("Error al registrar biometría: " + err.message);
        }
    });
});
</script>
</body>
</html>