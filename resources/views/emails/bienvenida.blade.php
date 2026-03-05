<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #7b5ea7;
            font-size: 24px;
        }
        p {
            color: #444;
            line-height: 1.6;
        }
        .credenciales {
            background: #f0ebf8;
            border-left: 4px solid #7b5ea7;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 24px 0;
        }
        .credenciales p {
            margin: 6px 0;
            font-size: 15px;
        }
        .credenciales strong {
            color: #7b5ea7;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>¡Hola, {{ $nombre }}!</h1>
        <p>Tu cuenta en <strong>A&F Chat</strong> ha sido creada exitosamente.</p>
        <p>Aquí están tus credenciales de acceso:</p>

        <div class="credenciales">
            <p><strong>Correo:</strong> {{ $correo }}</p>
            <p><strong>Contraseña:</strong> {{ $password }}</p>
        </div>

        <p>Por seguridad, te recomendamos guardar esta información en un lugar seguro.</p>

        <div class="footer">
            Este correo fue generado automáticamente. Por favor no respondas a este mensaje.
        </div>
    </div>
</body>
</html>