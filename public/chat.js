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

function avatarLetter(nombre) { return nombre ? nombre[0].toUpperCase() : '?'; }

function formatTime(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  const d         = new Date(dateStr);
  const today     = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString())     return 'Hoy';
  if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
  return d.toLocaleDateString('es', { day: '2-digit', month: 'long', year: 'numeric' });
}

async function loadUsers() {
  try {
    const users = await api('GET', '/usuarios');
    renderUserList(Array.isArray(users) ? users : (users.data || []));
  } catch (e) {
    toast('Error al cargar usuarios.');
  }
}

function renderUserList(users) {
  const list = document.getElementById('user-list');
  list.innerHTML = '';

  if (!users.length) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--chat-muted);font-size:13px;">No hay otros usuarios</div>';
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
      </div>`;
    list.appendChild(div);
  });
}

async function openChat(user) {
  stopPolling();
  currentChatId = user.id_usuario;
  lastMsgCount  = 0;

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
    </div>`;

  document.getElementById('messages').innerHTML = '<div class="loading-msgs">Cargando...</div>';
  document.getElementById('chat-input').value   = '';

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
  } catch (e) {
    document.getElementById('messages').innerHTML =
      '<div class="loading-msgs" style="color:var(--error)">Error al cargar mensajes</div>';
  }
}

function renderMessages(msgs) {
  const el       = document.getElementById('messages');
  const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 60;
  let html       = '';
  let lastDate   = '';

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
      </div>`;
  });

  el.innerHTML = html ||
    '<div class="loading-msgs">Sin mensajes aún. ¡Sé el primero en escribir!</div>';

  if (atBottom) el.scrollTop = el.scrollHeight;
}

async function sendMessage() {
  const input = document.getElementById('chat-input');
  const text  = input.value.trim();
  if (!text || !currentChatId) return;

  input.value        = '';
  input.style.height = 'auto';

  try {
    await api('POST', `/chat/${currentChatId}`, { mensaje: text });
    await fetchMessages();
    const preview = document.getElementById(`preview-${currentChatId}`);
    if (preview) preview.textContent = text;
  } catch (e) {
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

function startPolling() { pollInterval = setInterval(fetchMessages, 1000); }
function stopPolling()  { clearInterval(pollInterval); pollInterval = null; }