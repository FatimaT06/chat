let currentChatId = null;
let pollTimeout = null;
let lastMsgCount = 0;
let pendingMessages = new Map();
let isFetching = false;
let lastFetchTime = 0;
let activeChatId = null;

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
  
  const res = await fetch(path, opts);
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
  console.log(users);
  const list = document.getElementById('user-list');
  list.innerHTML = '';

  if (!users.length) {
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#666;font-size:13px;">No hay otros usuarios</div>';
    return;
  }

  users.forEach(u => {
    const div = document.createElement('div');
    div.className = 'user-item';
    div.dataset.id = u.id_usuario;
    div.onclick = () => openChat(u);
    div.innerHTML = `
      <div class="avatar">
        ${
          u.foto
          ? `<img src="/storage/${u.foto}" style="width:100%;height:100%;border-radius:50%">`
          : avatarLetter(u.nombre)
        }
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
  
  activeChatId = user.id_usuario;
  currentChatId = user.id_usuario;
  lastMsgCount = 0;
  isFetching = false;

  document.querySelectorAll('.user-item').forEach(el => {
    el.classList.toggle('active', el.dataset.id == user.id_usuario);
  });

  document.getElementById('chat-placeholder').style.display = 'none';
  const panel = document.getElementById('chat-panel');
  panel.style.display = 'flex';

  document.getElementById('chat-header').innerHTML = `
    <div class="avatar" style="width:36px;height:36px;font-size:14px">
      ${
        user.foto
        ? `<img src="/storage/${user.foto}" style="width:100%;height:100%;border-radius:50%">`
        : avatarLetter(user.nombre)
      }
    </div>
    <div>
      <div class="chat-header-name">${escHtml(user.nombre + ' ' + user.apellido_p)}</div>
      <div class="chat-header-status">Conectado</div>
    </div>`;

  document.getElementById('messages').innerHTML = '<div class="loading-msgs">Cargando mensajes...</div>';
  document.getElementById('chat-input').value = '';

  if (!pendingMessages.has(currentChatId)) {
    pendingMessages.set(currentChatId, []);
  }

  await fetchMessages();
  
  startPolling();
}

function stopPolling() {
  if (pollTimeout) {
    clearTimeout(pollTimeout);
    pollTimeout = null;
  }
}

function startPolling() {
  schedulePoll();
}

function schedulePoll() {
  if (!currentChatId) return;
  
  pollTimeout = setTimeout(async () => {
    await fetchMessages();
    schedulePoll();
  }, 3000);
}

async function fetchMessages() {
  if (isFetching || !currentChatId) return;
  
  const chatIdAtStart = currentChatId;
  
  try {
    isFetching = true;
    
    const res = await api('GET', `/chat/${chatIdAtStart}`);
    
    if (chatIdAtStart !== currentChatId) return;
    
    const msgs = Array.isArray(res) ? res : (res.data || []);

    const pending = pendingMessages.get(currentChatId) || [];
    
    if (pending.length > 0) {
      pending.forEach(pendingMsg => {
        if (!pendingMsg.element || !pendingMsg.element.parentNode) return;
        
        const confirmed = msgs.some(m => 
          m.id_emisor === CURRENT_USER_ID && 
          m.contenido_cifrado === pendingMsg.text &&
          Math.abs(new Date(m.fecha_envio) - new Date(pendingMsg.timestamp)) < 10000
        );

        if (confirmed) {
          const timeSpan = pendingMsg.element.querySelector('.msg-time');
          if (timeSpan) {
            timeSpan.innerHTML = formatTime(pendingMsg.timestamp) + ' ✓✓';
            pendingMsg.element.classList.remove('pending');
          }
          
          pendingMessages.set(currentChatId, pending.filter(p => p.tempId !== pendingMsg.tempId));
        }
      });
    }

    // Solo renderizar si hay mensajes nuevos
    if (msgs.length !== lastMsgCount) {
      lastMsgCount = msgs.length;
      renderMessages(msgs);
      
      // Actualizar preview
      if (msgs.length > 0) {
        const lastMsg = msgs[msgs.length - 1];
        const preview = document.getElementById(`preview-${currentChatId}`);
        if (preview) {
          preview.textContent = lastMsg.contenido_cifrado || '';
        }
      }
    }
  } catch (e) {
    // Solo mostrar error si sigue siendo el mismo chat
    if (chatIdAtStart === currentChatId) {
      console.error('Error fetching messages:', e);
    }
  } finally {
    // Solo resetear flag si sigue siendo el mismo chat
    if (chatIdAtStart === currentChatId) {
      isFetching = false;
    }
  }
}

function renderMessages(msgs) {
  const container = document.getElementById('messages');
  const atBottom  = container.scrollHeight - container.scrollTop - container.clientHeight < 60;

  let html     = '';
  let lastDate = '';

  msgs.forEach(m => {
    const isMine    = m.id_emisor === CURRENT_USER_ID;
    const time      = m.fecha_envio || '';
    const body      = m.contenido_cifrado || '';
    const dateLabel = formatDate(time);

    // Separador de fecha si cambió el día
    if (dateLabel && dateLabel !== lastDate) {
      html += `<div class="date-sep">${dateLabel}</div>`;
      lastDate = dateLabel;
    }

    html += `
      <div class="msg ${isMine ? 'mine' : 'theirs'}">
        ${escHtml(body)}
        <span class="msg-time">${formatTime(time)} ${isMine ? '✓✓' : ''}</span>
      </div>`;
  });

  const pending = pendingMessages.get(currentChatId) || [];

  if (!html && pending.length === 0) {
    html = '<div class="loading-msgs">No hay mensajes aún. ¡Escribe el primero!</div>';
  }

  container.innerHTML = html;
  if (atBottom) container.scrollTop = container.scrollHeight;
}

async function sendMessage() {
  const input = document.getElementById('chat-input');
  const text = input.value.trim();
  
  if (!text || !currentChatId) return;

  // Guardar referencia al chat actual
  const chatIdAtSend = currentChatId;

  // 1. LIMPIAR INPUT INMEDIATAMENTE
  input.value = '';
  input.style.height = 'auto';

  // 2. MOSTRAR MENSAJE INMEDIATAMENTE
  const container = document.getElementById('messages');
  
  const tempId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  const timestamp = new Date().toISOString();
  
  const msgDiv = document.createElement('div');
  msgDiv.className = 'msg mine pending';
  msgDiv.dataset.tempId = tempId;
  msgDiv.innerHTML = `
    ${escHtml(text)}
    <span class="msg-time">${formatTime(timestamp)} ✓</span>
  `;
  container.appendChild(msgDiv);
  container.scrollTop = container.scrollHeight;

  // Guardar pendiente
  if (!pendingMessages.has(currentChatId)) {
    pendingMessages.set(currentChatId, []);
  }
  
  pendingMessages.get(currentChatId).push({
    tempId: tempId,
    element: msgDiv,
    text: text,
    timestamp: timestamp
  });

  // 3. ENVIAR AL SERVIDOR
  try {
    await api('POST', `/chat/${currentChatId}`, { mensaje: text });
    
    // Si el chat sigue siendo el mismo
    if (chatIdAtSend === currentChatId) {
      // Actualizar preview
      const preview = document.getElementById(`preview-${currentChatId}`);
      if (preview) preview.textContent = text;
      
      // Verificar confirmación pronto
      setTimeout(() => {
        if (chatIdAtSend === currentChatId && !isFetching) {
          fetchMessages();
        }
      }, 800);
    }
  } catch (e) {
    // Solo mostrar error si sigue siendo el mismo chat
    if (chatIdAtSend === currentChatId) {
      toast('Error al enviar mensaje');
      msgDiv.classList.add('error');
      const timeSpan = msgDiv.querySelector('.msg-time');
      if (timeSpan) timeSpan.innerHTML += ' ⚠';
      
      // Eliminar de pendientes
      const pending = pendingMessages.get(currentChatId) || [];
      pendingMessages.set(currentChatId, pending.filter(p => p.tempId !== tempId));
    }
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
// Limpiar todo al cerrar
window.addEventListener('beforeunload', () => {
  stopPolling();
});
