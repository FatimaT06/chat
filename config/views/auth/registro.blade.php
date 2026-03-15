<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>A&F Chat — Registro</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body>
<div class="page-center">
    <div class="wrapper wrapper-login">
      <div class="panel-right" style="width:100%">

        <div class="form-header">
          <h1>A&amp;F Chat</h1>
          <p>Crea tu cuenta — <a href="{{ route('login') }}">¿Ya tienes cuenta? Inicia sesion</a></p>
        </div>

        @if($errors->any())
          <div class="error-box" style="display:block">
            {{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('register.post') }}">
          @csrf

          <div class="form-grid">

            <div class="field">
              <label>Nombre</label>
              <input type="text" name="nombre" placeholder="Nombre"
                value="{{ old('nombre') }}" required />
            </div>

            <div class="field">
              <label>Apellido paterno</label>
              <input type="text" name="apellido_p" placeholder="Apellido paterno"
                value="{{ old('apellido_p') }}" required />
            </div>

            <div class="field">
              <label>Apellido materno</label>
              <input type="text" name="apellido_m" placeholder="Apellido materno"
                value="{{ old('apellido_m') }}" required />
            </div>

            <div class="field">
              <label>Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento"
                value="{{ old('fecha_nacimiento') }}"  max="2020-12-31" required />
            </div>

            <div class="field full">
              <label>Correo electronico</label>
              <input type="email" name="correo" placeholder="ejemplo@correo.com"
                value="{{ old('correo') }}" required />
            </div>

            <div class="info-note">
              Tu contraseña sera enviada a tu correo.
            </div>

            <button class="btn" type="submit">Crear cuenta</button>

          </div>
        </form>

      </div>
    </div>
  </div>

</body>
</html>