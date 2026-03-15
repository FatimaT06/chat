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
          <div class="user-avatar">A</div>
          <div class="user-info">
            <div class="user-name">Asistente IA</div>
            <div class="user-last-msg" id="ai-preview">Toca para chatear</div>
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
            <div class="user-avatar" style="width:36px;height:36px;font-size:13px;flex-shrink:0;">A</div>
            <div class="user-info" style="flex:1;">
              <div class="user-name">Asistente IA</div>
              <div class="user-last-msg" id="ai-status">Gemini Flash</div>
            </div>
          </div>
          <div class="messages" id="ai-messages"></div>
          <div class="chat-input-row">
            <textarea
              class="chat-input"
              id="ai-input"
              rows="1"
              placeholder="Escribe un mensaje..."
              onkeydown="handleAIKey(event)"
              oninput="autoResize(this)">
            </textarea>
            <button class="send-btn" id="ai-send-btn" onclick="sendAIMessage()">
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

  <script>
    const GEMINI_KEY = 'AIzaSyCHcPkPOlLFP10zbJOJfbaPc0KBrUI5jKo';
    const GEMINI_URL = `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=${GEMINI_KEY}`;

    let aiHistory = [];
    let aiWaiting = false;
    let lastCall  = 0;
    const MIN_GAP = 5000;

    function openAIChat() {
      document.getElementById('chat-placeholder').style.display = 'none';
      document.getElementById('chat-panel').style.display       = 'none';
      document.getElementById('ai-panel').style.display         = 'flex';

      document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
      document.getElementById('ai-sidebar-item').classList.add('active');
      document.getElementById('ai-input').focus();

      if (aiHistory.length === 0) {
        pushBubble('ai', '¡Hola! Soy tu asistente IA. ¿En qué puedo ayudarte?');
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      const orig = window.openChat;
      if (typeof orig === 'function') {
        window.openChat = function (...args) {
          document.getElementById('ai-panel').style.display = 'none';
          document.getElementById('ai-sidebar-item').classList.remove('active');
          orig.apply(this, args);
        };
      }
    });

    function handleAIKey(e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendAIMessage(); }
    }

    async function sendAIMessage() {
      const input = document.getElementById('ai-input');
      const text  = input.value.trim();
      if (!text || aiWaiting) return;

      pushBubble('user', text);
      setPreview(text);
      input.value = ''; input.style.height = '';
      aiHistory.push({ role: 'user', parts: [{ text }] });

      aiWaiting = true;
      document.getElementById('ai-send-btn').disabled = true;
      const typingEl = pushTyping();

      const wait = Math.max(0, MIN_GAP - (Date.now() - lastCall));
      if (wait > 0) {
        for (let i = Math.ceil(wait / 1000); i > 0; i--) {
          setStatus(`listo en ${i}s…`);
          await sleep(1000);
        }
      }
      setStatus('escribiendo…');

      try {
        const reply = await callGemini(aiHistory);
        aiHistory.push({ role: 'model', parts: [{ text: reply }] });
        typingEl.remove();
        pushBubble('ai', reply);
        setPreview(reply);
      } catch (err) {
        typingEl.remove();
        aiHistory.pop();
        pushBubble('ai', err.status === 429
          ? 'Límite alcanzado. Espera unos segundos e intenta de nuevo.'
          : 'Error al conectar con Gemini. Revisa tu conexión.');
      } finally {
        aiWaiting = false;
        document.getElementById('ai-send-btn').disabled = false;
        setStatus('Gemini Flash');
      }
    }

    async function callGemini(history, tries = 3, delay = 6000) {
      lastCall = Date.now();
      const res = await fetch(GEMINI_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contents: history })
      });
      if (res.status === 429 && tries > 0) {
        setStatus(`esperando cuota… (${tries})`);
        await sleep(delay);
        return callGemini(history, tries - 1, delay + 4000);
      }
      if (!res.ok) { const e = new Error(); e.status = res.status; throw e; }
      const d = await res.json();
      return d?.candidates?.[0]?.content?.parts?.[0]?.text ?? 'Sin respuesta. Intenta de nuevo.';
    }

    function pushBubble(role, text) {
      const c   = document.getElementById('ai-messages');
      const row = document.createElement('div');
      
      row.className = role === 'user' ? 'msg msg-self' : 'msg';
      
      const b = document.createElement('div');
      b.className = 'msg-bubble';
      
      const timeString = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      
      b.innerHTML = md(text) + `<div style="font-size: 0.7em; opacity: 0.7; margin-top: 4px;">${timeString}</div>`;
      
      row.appendChild(b);
      c.appendChild(row);
      c.scrollTop = c.scrollHeight;
      return row;
    }

    function pushTyping() {
      const c   = document.getElementById('ai-messages');
      const row = document.createElement('div');
      row.className = 'msg';
      row.innerHTML = `<div class="msg-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>`;
      c.appendChild(row);
      c.scrollTop = c.scrollHeight;
      return row;
    }

    function md(t) {
      return t
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
        .replace(/\*(.*?)\*/g,'<em>$1</em>')
        .replace(/`([^`]+)`/g,'<code style="background:rgba(255,255,255,0.12);padding:1px 5px;border-radius:4px;font-size:12px">$1</code>')
        .replace(/\n/g,'<br>');
    }

    function setPreview(t) {
      const s = t.replace(/\n/g,' ').slice(0,46);
      document.getElementById('ai-preview').textContent = s.length < t.length ? s+'…' : s;
    }
    function setStatus(t) { document.getElementById('ai-status').textContent = t; }
    function sleep(ms)    { return new Promise(r => setTimeout(r, ms)); }
  </script>

</body>
</html>