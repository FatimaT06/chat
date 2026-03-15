let currentChatId = null;
let pollTimeout = null;
let lastMsgCount = 0;
let pendingMessages = new Map();
let isFetching = false;
let lastFetchTime = 0;
let activeChatId = null;

loadUsers();

const fileInput = document.getElementById("chat-file");
const filePreview = document.getElementById("file-preview");
const fileName = document.getElementById("file-name");

fileInput.addEventListener("change", function(){

  const file = this.files[0];
  if(!file) return;

  fileName.textContent = file.name;
  filePreview.style.display = "flex";

});

function removeFile(){

  const fileInput = document.getElementById("chat-file");
  const filePreview = document.getElementById("file-preview");

  fileInput.value = "";
  filePreview.style.display = "none";

}

function clearFile(){

  const fileInput = document.getElementById("chat-file");
  const filePreview = document.getElementById("file-preview");
  const fileName = document.getElementById("file-name");

  if(fileInput) fileInput.value = "";

  if(filePreview) filePreview.style.display = "none";

  if(fileName) fileName.textContent = "";

}


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
  clearFile();
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

    if (msgs.length !== lastMsgCount) {
      lastMsgCount = msgs.length;
      renderMessages(msgs);
      
      if (msgs.length > 0) {
        const lastMsg = msgs[msgs.length - 1];
        const preview = document.getElementById(`preview-${currentChatId}`);
        if (preview) {
          preview.textContent = lastMsg.contenido_cifrado || 'Archivo';
        }
      }
    }
  } catch (e) {
    if (chatIdAtStart === currentChatId) {
      console.error('Error fetching messages:', e);
    }
  } finally {
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
    const dateLabel = formatDate(time);

    let content = '';
    if (m.contenido_cifrado) {
      content += escHtml(m.contenido_cifrado);
    }
    if (dateLabel && dateLabel !== lastDate) {
      html += `<div class="date-sep">${dateLabel}</div>`;
      lastDate = dateLabel;
    }
    if (m.archivo) {

      const ext = m.archivo.split('.').pop().toLowerCase();
      const url = "/storage/" + m.archivo;

      if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
        content += `
          <div class="msg-img">
            <img src="${url}" style="max-width:200px;border-radius:8px;margin-top:5px;">
          </div>
        `;
      } else {
        content += `
        <div class="msg-file">
          <div class="file-icon">
            <img src="/storage/foto/clip.png" style="width:15px; height:15px; filter:invert(1);">
          </div>
          <div class="file-info">
            <div class="file-name">${m.archivo.split('/').pop()}</div>
            <a href="${url}" target="_blank" class="file-download" style="color:var(--primary); text-decoration:underline;">Descargar</a>
          </div>
        </div>`;
      }
    }
    html += `
    <div class="msg ${isMine ? 'mine' : 'theirs'}">
      ${content}
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
  const file = document.getElementById('chat-file').files[0];

  if ((!text && !file) || !currentChatId) return;

  input.value = '';
  input.style.height = 'auto';

  const chatIdAtSend = currentChatId;

  const container = document.getElementById('messages');

  const tempId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  const timestamp = new Date().toISOString();

  const msgDiv = document.createElement('div');
  msgDiv.className = 'msg mine pending';
  msgDiv.dataset.tempId = tempId;

  let content = '';

  if (text) {
    content += escHtml(text);
  }

  if (file) {

    const ext = file.name.split('.').pop().toLowerCase();

    if (['jpg','jpeg','png','gif','webp'].includes(ext)) {

      const preview = URL.createObjectURL(file);

      content += `
      <div class="msg-img">
        <img src="${preview}" style="max-width:200px;border-radius:8px;">
      </div>
      `;

    } else {

      content += `
      <div class="msg-file">
        <img src="/storage/foto/clip.png" style="width:15px;height:15px;filter:invert(1);">
        ${file.name}
      </div>
      `;

    }
  }

  msgDiv.innerHTML = `
  ${content}
  <span class="msg-time">${formatTime(timestamp)} ✓</span>
  `;

  container.appendChild(msgDiv);
  container.scrollTop = container.scrollHeight;

  if (!pendingMessages.has(currentChatId)) {
    pendingMessages.set(currentChatId, []);
  }

  pendingMessages.get(currentChatId).push({
    tempId: tempId,
    element: msgDiv,
    text: text,
    timestamp: timestamp
  });

  try {

  
    const formData = new FormData();

    if (text) {
      formData.append("mensaje", text);
    }

    if (file) {
      formData.append("archivo", file);
    }

    const res = await fetch(`/chat/${currentChatId}`, {
  method: "POST",
  headers: {
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  },
  body: formData
});

if(!res.ok){
  const err = await res.text();
  console.error("SERVER ERROR:", err);
  throw new Error(err);
}


    clearFile();

    if (chatIdAtSend === currentChatId) {

      const preview = document.getElementById(`preview-${currentChatId}`);
      if (preview) preview.textContent = text;

      setTimeout(() => {
        if (chatIdAtSend === currentChatId && !isFetching) {
          fetchMessages();
        }
      }, 800);
    }

  } catch (e) {

    if (chatIdAtSend === currentChatId) {

      toast('Error al enviar mensaje');
      msgDiv.classList.add('error');

      const timeSpan = msgDiv.querySelector('.msg-time');
      if (timeSpan) timeSpan.innerHTML += ' ⚠';

      clearFile();

      const pending = pendingMessages.get(currentChatId) || [];
      pendingMessages.set(
        currentChatId,
        pending.filter(p => p.tempId !== tempId)
      );
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

document.getElementById("chat-file").addEventListener("change", function(){
  const name = this.files[0]?.name || "";
  document.getElementById("file-name").textContent = name;
});
// Limpiar todo al cerrar
window.addEventListener('beforeunload', () => {
  stopPolling();
});
