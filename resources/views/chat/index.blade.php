<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>A&F Chat</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
</head>
<body class="chat-body">

  <div id="app-screen">

    <div class="topbar">
      <div class="topbar-logo">A&amp;F Chat</div>
      <div class="topbar-user">
        <span>{{ session('chat_user')['nombre'] ?? '' }}</span>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
          @csrf
          <button class="logout-btn" type="submit">Salir</button>
        </form>
      </div>
    </div>

    <div class="main">

      <div class="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-title">Conversaciones</div>
        </div>
        <div class="user-list" id="user-list"></div>
      </div>

      <div class="chat-area">
        <div class="chat-placeholder" id="chat-placeholder">
          <div class="chat-placeholder-icon">💬</div>
          <div class="chat-placeholder-text">Selecciona una conversación</div>
        </div>

        <div id="chat-panel" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
          <div class="chat-header" id="chat-header"></div>
          <div class="messages" id="messages"></div>
          <div class="chat-input-row">
            <textarea
              class="chat-input"
              id="chat-input"
              rows="1"
              placeholder="Escribe un mensaje..."
              onkeydown="handleInputKey(event)"
              oninput="autoResize(this)">
            </textarea>
            <button class="send-btn" onclick="sendMessage()">
              <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div id="toast"></div>

  <script>
    const CURRENT_USER_ID = {{ session('chat_user')['id_usuario'] ?? 'null' }};
  </script>
  <script src="{{ asset('chat.js') }}"></script>

</body>
</html>