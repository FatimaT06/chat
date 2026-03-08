// ──────────────────────────────────────────────
//  CHAT ENTRE USUARIOS
// ──────────────────────────────────────────────

let currentChatId = null;
let pollInterval  = null;
let lastMsgCount  = 0;

loadUsers();

async function api(method, path, body = null) {
  const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': token
  };
  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(path, opts);
  const data = await res.json();
  if (!res.ok) throw data;
  return data;
}

function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3000);
}

function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(str || ''));
  return d.innerHTML;
}

function avatarLetter(nombre) {
  return nombre ? nombre[0].toUpperCase() : '?';
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleTimeString('es', {
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d         = new Date(dateStr);
  const today     = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return 'Hoy';
  if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
  return d.toLocaleDateString('es', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  });
}

async function loadUsers() {
  try {
    const users = await api('GET', '/usuarios');
    renderUserList(Array.isArray(users) ? users : (users.data || []));
  } catch {
    toast('Error al cargar usuarios.');
  }
}

function renderUserList(users) {
  const list = document.getElementById('user-list');
  list.innerHTML = '';
  if (!users.length) {
    list.innerHTML =
      '<div style="padding:20px;text-align:center;color:var(--chat-muted);font-size:13px;">No hay otros usuarios</div>';
    return;
  }
  users.forEach(u => {
    const div      = document.createElement('div');
    div.className  = 'user-item';
    div.dataset.id = u.id_usuario;
    div.onclick    = () => openChat(u);
    div.innerHTML  = `
      <div class="avatar">
        ${avatarLetter(u.nombre)}
        <div class="avatar-dot"></div>
      </div>
      <div class="user-info">
        <div class="user-name">${escHtml(u.nombre + ' ' + u.apellido_p)}</div>
        <div class="user-preview" id="preview-${u.id_usuario}">Toca para chatear</div>
      </div>
    `;
    list.appendChild(div);
  });
}

async function openChat(user) {
  stopPolling();
  currentChatId = user.id_usuario;
  lastMsgCount  = 0;

  document.getElementById('ai-panel').style.display = 'none';
  document.getElementById('ai-sidebar-item').classList.remove('active');

  document.querySelectorAll('.user-item').forEach(el => {
    el.classList.toggle('active', el.dataset.id == user.id_usuario);
  });

  document.getElementById('chat-placeholder').style.display = 'none';
  const panel = document.getElementById('chat-panel');
  panel.style.display = 'flex';

  document.getElementById('chat-header').innerHTML = `
    <div class="avatar" style="width:36px;height:36px;font-size:14px">
      ${avatarLetter(user.nombre)}
    </div>
    <div>
      <div class="chat-header-name">${escHtml(user.nombre + ' ' + user.apellido_p)}</div>
      <div class="chat-header-status">● En línea</div>
    </div>
  `;

  document.getElementById('messages').innerHTML =
    '<div class="loading-msgs">Cargando...</div>';
  document.getElementById('chat-input').value = '';

  await fetchMessages();
  startPolling();
}

async function fetchMessages() {
  if (!currentChatId) return;
  try {
    const res  = await api('GET', `/chat/${currentChatId}`);
    const msgs = Array.isArray(res) ? res : (res.data || []);
    if (msgs.length !== lastMsgCount) {
      lastMsgCount = msgs.length;
      renderMessages(msgs);
    }
  } catch {
    document.getElementById('messages').innerHTML =
      '<div class="loading-msgs" style="color:red">Error al cargar mensajes</div>';
  }
}

function renderMessages(msgs) {
  const el       = document.getElementById('messages');
  const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 60;
  let html     = '';
  let lastDate = '';

  msgs.forEach(m => {
    const isMine    = m.id_emisor === CURRENT_USER_ID;
    const time      = m.fecha_envio || '';
    const dateLabel = formatDate(time);
    const body      = m.contenido_cifrado || '';

    if (dateLabel && dateLabel !== lastDate) {
      html += `<div class="date-sep">${dateLabel}</div>`;
      lastDate = dateLabel;
    }

    html += `
      <div class="msg ${isMine ? 'mine' : 'theirs'}">
        ${escHtml(body)}
        <span class="msg-time">${formatTime(time)}</span>
      </div>
    `;
  });

  el.innerHTML = html;
  if (atBottom) el.scrollTop = el.scrollHeight;
}

async function sendMessage() {
  const input = document.getElementById('chat-input');
  const text  = input.value.trim();
  if (!text || !currentChatId) return;
  input.value = '';
  try {
    await api('POST', `/chat/${currentChatId}`, { mensaje: text });
    await fetchMessages();
  } catch {
    toast('No se pudo enviar el mensaje.');
    input.value = text;
  }
}

function handleInputKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function startPolling() {
  pollInterval = setInterval(fetchMessages, 1000);
}

function stopPolling() {
  clearInterval(pollInterval);
  pollInterval = null;
}


// ──────────────────────────────────────────────
//  IA GEMINI
// ──────────────────────────────────────────────

const GEMINI_KEY = 'AIzaSyCh4C94k3B8NsJcNufcjNE0kZK0TFGBmAQ';
const GEMINI_URL = `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=${GEMINI_KEY}`;

let aiHistory = [];
let aiWaiting = false;
let lastCall  = 0;
const MIN_GAP = 5000;

function openAIChat() {
  stopPolling();
  currentChatId = null;

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

function handleAIKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendAIMessage();
  }
}

async function sendAIMessage() {
  const input = document.getElementById('ai-input');
  const text  = input.value.trim();
  if (!text || aiWaiting) return;

  pushBubble('user', text);
  setPreview(text);
  input.value = '';
  input.style.height = '';
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
  row.className = role === 'user' ? 'msg mine' : 'msg theirs';
  const bubble = document.createElement('div');
  bubble.className = 'msg-bubble';
  const timeString = new Date().toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit'
  });
  bubble.innerHTML = md(text) + `<span class="msg-time">${timeString}</span>`;
  row.appendChild(bubble);
  c.appendChild(row);
  c.scrollTop = c.scrollHeight;
  return row;
}

function pushTyping() {
  const c   = document.getElementById('ai-messages');
  const row = document.createElement('div');
  row.className = 'msg theirs';
  row.innerHTML = `
    <div class="msg-bubble">
      <div class="typing-dots">
        <span></span><span></span><span></span>
      </div>
    </div>
  `;
  c.appendChild(row);
  c.scrollTop = c.scrollHeight;
  return row;
}

function md(t) {
  return t
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
    .replace(/\*(.*?)\*/g,'<em>$1</em>')
    .replace(/`([^`]+)`/g,'<code style="background:rgba(255,255,255,0.12);padding:1px 5px;border-radius:4px;font-size:12px">$1</code>')
    .replace(/\n/g,'<br>');
}

function setPreview(t) {
  const s = t.replace(/\n/g,' ').slice(0, 46);
  document.getElementById('ai-preview').textContent =
    s.length < t.length ? s + '…' : s;
}

function setStatus(t) {
  document.getElementById('ai-status').textContent = t;
}

function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}