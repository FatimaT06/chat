<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>A&F Chat — Iniciar sesion</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body>
<div class="page-center">
    <div class="wrapper wrapper-login">
        <div class="panel-right" style="width:100%">

        <div class="form-header">
          <h1>A&amp;F Chat</h1>
          <p>Bienvenido de vuelta — <a href="{{ route('register') }}">¿No tienes cuenta? Registrate</a></p>
        </div>

        @if($errors->any())
          <div class="error-box" style="display:block">
            {{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
          @csrf

          <div class="field">
            <label>Correo electronico</label>
            <input type="email" name="correo" placeholder="ejemplo@correo.com"
              value="{{ old('correo') }}" required />
          </div>

          <div class="field">
            <label>Contraseña</label>
            <input type="password" name="password" placeholder="Contraseña" required />
          </div>

          <button class="btn" type="submit">Iniciar sesion</button>
        </form>

      </div>
    </div>
  </div>

</body>
</html>