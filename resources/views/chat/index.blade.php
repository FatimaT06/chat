<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>A&F Chat</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('styles.css') }}">
  <style>
    .typing-dots { display:flex; gap:5px; align-items:center; }
    .typing-dots span {
      width:7px; height:7px; border-radius:50%;
      background:currentColor; opacity:.4;
      animation:tdot 1.2s infinite;
    }
    .typing-dots span:nth-child(2){ animation-delay:.2s; }
    .typing-dots span:nth-child(3){ animation-delay:.4s; }
    @keyframes tdot{
      0%,60%,100%{ transform:translateY(0);    opacity:.35; }
      30%        { transform:translateY(-5px); opacity:1;   }
    }
  </style>
</head>
<body class="chat-body">

  <div id="app-screen">

    <div class="topbar">
      <div class="topbar-logo">A&amp;F Chat</div>
      <div class="topbar-user">
        <span>{{ session('chat_user')['nombre'] ?? '' }}</span>
        <a href="{{ route('usuario.configuracion') }}" class="config-btn">Configuración</a>
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

        <div class="user-item" id="ai-sidebar-item" onclick="openAIChat()">
          <div class="avatar">
            <img src="/storage/foto/chat.webp" alt="IA" style="width:100%; height:100%; border-radius:50%; object-fit: cover;">
          </div>
          <div class="user-info">
            <div class="user-name">Asistente IA</div>
            <div class="user-preview" id="ai-preview">Toca para chatear</div>
          </div>
        </div>

        <div class="user-list" id="user-list"></div>
      </div>

      <div class="chat-area">
        <div class="chat-placeholder" id="chat-placeholder">
          <div class="chat-placeholder-icon"></div>
          <div class="chat-placeholder-text">Selecciona una conversación</div>
        </div>

        <div id="chat-panel" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
          <div class="chat-header" id="chat-header"></div>
          <div class="messages" id="messages"></div>
          <div class="chat-input-container">
            <div id="file-preview" class="file-preview" style="display:none;">
              <span class="file-preview-icon">
                <img src="{{ asset('storage/foto/clip.png') }}" style="width:15px; height:15px; filter:invert(1);">
              </span>
              <span id="file-name"></span>
              <button type="button" class="remove-file" onclick="removeFile()">✕</button>
            </div>
            <div class="chat-input-row">
              <textarea
                class="chat-input"
                id="chat-input"
                rows="1"
                placeholder="Escribe un mensaje..."
                onkeydown="handleInputKey(event)"
                oninput="autoResize(this)">
              </textarea>
              <label class="file-icon" for="chat-file">
                <img src="{{ asset('storage/foto/clip.png') }}" style="width:18px; height:23px; filter:invert(1);">
                <input type="file" id="chat-file">
              </label>
              <button class="send-btn" onclick="sendMessage()">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div id="ai-panel" style="display:none; flex-direction:column; flex:1; overflow:hidden;">
          <div class="chat-header" id="ai-header">
            <div class="avatar">
              <img src="/storage/foto/chat.webp" alt="IA" style="width:100%; height:100%; border-radius:50%; object-fit: cover;">
            </div>
            <div class="user-info" style="flex:1;">
              <div class="user-name">Asistente IA</div>
              <div class="user-last-msg" id="ai-status">Gemini Flash</div>
            </div>
          </div>
          <div class="messages" id="ai-messages"></div>
          <div class="chat-input-container">
            <!-- Preview de archivo para IA -->
            <div id="ai-file-preview" class="file-preview" style="display:none;">
              <span class="file-preview-icon">
                <img src="{{ asset('storage/foto/clip.png') }}" style="width:15px; height:15px; filter:invert(1);">
              </span>
              <span id="ai-file-name"></span>
              <button type="button" class="remove-file" onclick="removeAIFile()">✕</button>
            </div>
            
            <div class="chat-input-row">
              <textarea
                class="chat-input"
                id="ai-input"
                rows="1"
                placeholder="Escribe un mensaje..."
                onkeydown="handleAIKey(event)"
                oninput="autoResize(this)">
              </textarea>
              
              <label class="file-icon" for="ai-chat-file">
                <img src="{{ asset('storage/foto/clip.png') }}" style="width:18px; height:23px; filter:invert(1);">
                <input type="file" id="ai-chat-file">
              </label>
              
              <button class="send-btn" id="ai-send-btn" onclick="sendAIMessage()">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
              </button>
            </div>
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