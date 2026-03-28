let currentChatId = null;
let pollTimeout = null;
let lastMsgCount = 0;
let pendingMessages = new Map();
let isFetching = false;
let lastFetchTime = 0;
let activeChatId = null;

// Variables para Gemini AI
const GEMINI_KEY = 'AIzaSyCHcPkPOlLFP10zbJOJfbaPc0KBrUI5jKo';
const GEMINI_URL = `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=${GEMINI_KEY}`;

let aiHistory = [];
let aiWaiting = false;
let lastCall  = 0;
const MIN_GAP = 5000;
let aiSelectedFile = null;

loadUsers();

// ── Helpers ────────────────────────────────────────────────────────────────

function isImageUrl(url) {
  if (!url) return false;
if (/\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?|$|\/)/i.test(url)) return true;
if (url.includes('cloudinary.com') && url.includes('/image/upload/')) return true;
if (url.includes('cloudinary.com') && !url.includes('/video/') && !url.includes('/raw/')) return true;
  return false;
}

function resolveFileUrl(archivo) {
  if (!archivo) return '';
  if (archivo.startsWith('http://') || archivo.startsWith('https://')) {
    return archivo; // URL completa de Cloudinary
  }
  return '/storage/' + archivo; // compatibilidad con archivos locales viejos
}

// ── Event listeners ────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  const aiFileInput = document.getElementById('ai-chat-file');
  if (aiFileInput) {
    aiFileInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (!file) return;
      aiSelectedFile = file;
      document.getElementById('ai-file-name').textContent = file.name;
      document.getElementById('ai-file-preview').style.display = 'flex';
    });
  }
});

const fileInput   = document.getElementById("chat-file");
const filePreview = document.getElementById("file-preview");
const fileName    = document.getElementById("file-name");

fileInput.addEventListener("change", function(){
  const file = this.files[0];
  if (!file) return;
  fileName.textContent    = file.name;
  filePreview.style.display = "flex";
});

// ── Funciones utilitarias ──────────────────────────────────────────────────

function removeFile() {
  document.getElementById("chat-file").value = "";
  document.getElementById("file-preview").style.display = "none";
}

function removeAIFile() {
  aiSelectedFile = null;
  const aiFileInput = document.getElementById('ai-chat-file');
  if (aiFileInput) aiFileInput.value = '';
  document.getElementById('ai-file-preview').style.display = 'none';
  document.getElementById('ai-file-name').textContent = '';
}

function clearFile() {
  const fi = document.getElementById("chat-file");
  const fp = document.getElementById("file-preview");
  const fn = document.getElementById("file-name");
  if (fi) fi.value = "";
  if (fp) fp.style.display = "none";
  if (fn) fn.textContent = "";
}

async function api(method, path, body = null) {
  const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': token
    }
  };
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

// ── Usuarios ───────────────────────────────────────────────────────────────

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
    list.innerHTML = '<div style="padding:20px;text-align:center;color:#666;font-size:13px;">No hay otros usuarios</div>';
    return;
  }

  users.forEach(u => {
    const div = document.createElement('div');
    div.className   = 'user-item';
    div.dataset.id  = u.id_usuario;
    div.onclick     = () => openChat(u);

    // La foto puede ser URL de Cloudinary o ruta local
    const fotoUrl = u.foto
      ? (u.foto.startsWith('http') ? u.foto : `/storage/${u.foto}`)
      : null;

    div.innerHTML = `
      <div class="avatar">
        ${fotoUrl
          ? `<img src="${fotoUrl}" style="width:100%;height:100%;border-radius:50%">`
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

// ── Chat ───────────────────────────────────────────────────────────────────

async function openChat(user) {
  stopPolling();
  clearFile();
  activeChatId  = user.id_usuario;
  currentChatId = user.id_usuario;
  lastMsgCount  = 0;
  isFetching    = false;

  document.querySelectorAll('.user-item').forEach(el => {
    el.classList.toggle('active', el.dataset.id == user.id_usuario);
  });

  document.getElementById('chat-placeholder').style.display = 'none';
  document.getElementById('chat-panel').style.display       = 'flex';

  const fotoUrl = user.foto
    ? (user.foto.startsWith('http') ? user.foto : `/storage/${user.foto}`)
    : null;

  document.getElementById('chat-header').innerHTML = `
    <div class="avatar" style="width:36px;height:36px;font-size:14px">
      ${fotoUrl
        ? `<img src="${fotoUrl}" style="width:100%;height:100%;border-radius:50%">`
        : avatarLetter(user.nombre)
      }
    </div>
    <div>
      <div class="chat-header-name">${escHtml(user.nombre + ' ' + user.apellido_p)}</div>
      <div class="chat-header-status">Conectado</div>
    </div>`;

  document.getElementById('messages').innerHTML = '<div class="loading-msgs">Cargando mensajes...</div>';
  document.getElementById('chat-input').value   = '';

  if (!pendingMessages.has(currentChatId)) pendingMessages.set(currentChatId, []);

  await fetchMessages();
  startPolling();
}

function stopPolling() {
  if (pollTimeout) { clearTimeout(pollTimeout); pollTimeout = null; }
}

function startPolling() { schedulePoll(); }

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
    const res  = await api('GET', `/chat/${chatIdAtStart}`);
    if (chatIdAtStart !== currentChatId) return;

    const msgs    = Array.isArray(res) ? res : (res.data || []);
    const pending = pendingMessages.get(currentChatId) || [];

    if (pending.length > 0) {
      pending.forEach(pm => {
        if (!pm.element?.parentNode) return;
        const confirmed = msgs.some(m =>
          m.id_emisor === CURRENT_USER_ID &&
          m.contenido_cifrado === pm.text &&
          Math.abs(new Date(m.fecha_envio) - new Date(pm.timestamp)) < 10000
        );
        if (confirmed) {
          const timeSpan = pm.element.querySelector('.msg-time');
          if (timeSpan) { timeSpan.innerHTML = formatTime(pm.timestamp) + ' ✓✓'; pm.element.classList.remove('pending'); }
          pendingMessages.set(currentChatId, pending.filter(p => p.tempId !== pm.tempId));
        }
      });
    }

    if (msgs.length !== lastMsgCount) {
      lastMsgCount = msgs.length;
      renderMessages(msgs);
      if (msgs.length > 0) {
        const last    = msgs[msgs.length - 1];
        const preview = document.getElementById(`preview-${currentChatId}`);
        if (preview) preview.textContent = last.contenido_cifrado || 'Archivo';
      }
    }
  } catch (e) {
    if (chatIdAtStart === currentChatId) console.error('Error fetching messages:', e);
  } finally {
    if (chatIdAtStart === currentChatId) isFetching = false;
  }
}

// ── RENDER MESSAGES — Compatible con Cloudinary y rutas locales legacy ──────
function renderMessages(msgs) {
  const container = document.getElementById('messages');
  const atBottom  = container.scrollHeight - container.scrollTop - container.clientHeight < 60;

  let html     = '';
  let lastDate = '';

  msgs.forEach(m => {
    const isMine    = m.id_emisor === CURRENT_USER_ID;
    const time      = m.fecha_envio || '';
    const dateLabel = formatDate(time);

    if (dateLabel && dateLabel !== lastDate) {
      html     += `<div class="date-sep">${dateLabel}</div>`;
      lastDate  = dateLabel;
    }

    let content = '';
    if (m.contenido_cifrado) content += escHtml(m.contenido_cifrado);

    if (m.archivo) {
      const url      = resolveFileUrl(m.archivo);
      const fileName = m.archivo.split('/').pop().split('?')[0];
      const ext      = fileName.split('.').pop().toLowerCase();

      if (isImageUrl(m.archivo)) {
        content += `
          <div class="msg-img" style="margin-top:${m.contenido_cifrado ? '8px' : '0'}">
            <img src="${url}"
                 loading="lazy"
                 onclick="window.open('${url}','_blank')"
                 style="max-width:260px;min-width:120px;width:100%;border-radius:12px;display:block;cursor:zoom-in;object-fit:cover;"
                 onerror="this.parentElement.innerHTML='<span style=\'opacity:.6;font-size:12px\'>No se pudo cargar</span>'"
            >
          </div>`;
      } else {
        const fileIconSvg = `<svg width="32" height="38" viewBox="0 0 32 38" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="32" height="38" rx="4" fill="rgba(255,255,255,0.25)"/>
          <path d="M6 10h13l7 7v17a2 2 0 01-2 2H6a2 2 0 01-2-2V12a2 2 0 012-2z" fill="rgba(255,255,255,0.9)"/>
          <path d="M19 10l7 7h-5a2 2 0 01-2-2v-5z" fill="rgba(255,255,255,0.5)"/>
          <rect x="8" y="20" width="16" height="1.5" rx="1" fill="rgba(0,150,140,0.5)"/>
          <rect x="8" y="24" width="12" height="1.5" rx="1" fill="rgba(0,150,140,0.5)"/>
          <rect x="8" y="28" width="14" height="1.5" rx="1" fill="rgba(0,150,140,0.5)"/>
        </svg>`;
        content += `
          <a href="${url}" target="_blank" download style="text-decoration:none;">
            <div style="display:flex;align-items:center;gap:12px;background:rgba(0,0,0,0.12);border-radius:12px;padding:10px 14px;margin-top:${m.contenido_cifrado ? '8px' : '0'};min-width:200px;max-width:280px;cursor:pointer;">
              <div style="flex-shrink:0;">${fileIconSvg}</div>
              <div style="flex:1;overflow:hidden;">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:inherit;opacity:0.95;">${escHtml(fileName)}</div>
                <div style="font-size:11px;opacity:0.65;margin-top:3px;text-transform:uppercase;letter-spacing:0.5px;">${ext}</div>
              </div>
              <div style="flex-shrink:0;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.7">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
              </div>
            </div>
          </a>`;
      }
    }

    html += `
      <div class="msg ${isMine ? 'mine' : 'theirs'}">
        ${content}
        <span class="msg-time">${formatTime(time)} ${isMine ? '✓✓' : ''}</span>
      </div>`;
  });

  if (!html && !(pendingMessages.get(currentChatId) || []).length) {
    html = '<div class="loading-msgs">No hay mensajes aún. ¡Escribe el primero!</div>';
  }

  container.innerHTML = html;
  if (atBottom) container.scrollTop = container.scrollHeight;
}

// ── Enviar mensaje ─────────────────────────────────────────────────────────

async function sendMessage() {
  const input = document.getElementById('chat-input');
  const text  = input.value.trim();
  const file  = document.getElementById('chat-file').files[0];

  if ((!text && !file) || !currentChatId) return;

  input.value        = '';
  input.style.height = 'auto';

  const chatIdAtSend = currentChatId;
  const container    = document.getElementById('messages');
  const tempId       = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  const timestamp    = new Date().toISOString();

  const msgDiv       = document.createElement('div');
  msgDiv.className   = 'msg mine pending';
  msgDiv.dataset.tempId = tempId;

  let content = '';
  if (text) content += escHtml(text);

  if (file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (['jpg','jpeg','png','gif','webp','bmp'].includes(ext)) {
      const previewUrl = URL.createObjectURL(file);
      content += `<div class="msg-img" style="margin-top:${text ? '8px' : '0'}"><img src="${previewUrl}" style="max-width:260px;min-width:120px;width:100%;border-radius:12px;display:block;object-fit:cover;"></div>`;
    } else {
      const fileIconSvg2 = `<svg width="32" height="38" viewBox="0 0 32 38" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="38" rx="4" fill="rgba(255,255,255,0.25)"/>
        <path d="M6 10h13l7 7v17a2 2 0 01-2 2H6a2 2 0 01-2-2V12a2 2 0 012-2z" fill="rgba(255,255,255,0.9)"/>
        <path d="M19 10l7 7h-5a2 2 0 01-2-2v-5z" fill="rgba(255,255,255,0.5)"/>
        <rect x="8" y="20" width="16" height="1.5" rx="1" fill="rgba(0,150,140,0.5)"/>
        <rect x="8" y="24" width="12" height="1.5" rx="1" fill="rgba(0,150,140,0.5)"/>
        <rect x="8" y="28" width="14" height="1.5" rx="1" fill="rgba(0,150,140,0.5)"/>
      </svg>`;
      content += `<div style="display:flex;align-items:center;gap:12px;background:rgba(0,0,0,0.12);border-radius:12px;padding:10px 14px;margin-top:${text ? '8px' : '0'};min-width:200px;max-width:280px;">
        <div style="flex-shrink:0;">${fileIconSvg2}</div>
        <div style="flex:1;overflow:hidden;">
          <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:inherit;opacity:0.95;">${escHtml(file.name)}</div>
          <div style="font-size:11px;opacity:0.65;margin-top:3px;text-transform:uppercase;letter-spacing:0.5px;">${ext}</div>
        </div>
        <div style="flex-shrink:0;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.7">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
        </div>
      </div>`;
    }
  }

  msgDiv.innerHTML = `${content}<span class="msg-time">${formatTime(timestamp)} ✓</span>`;
  container.appendChild(msgDiv);
  container.scrollTop = container.scrollHeight;

  if (!pendingMessages.has(currentChatId)) pendingMessages.set(currentChatId, []);
  pendingMessages.get(currentChatId).push({ tempId, element: msgDiv, text, timestamp });

  try {
    const formData = new FormData();
    if (text) formData.append("mensaje", text);
    if (file) formData.append("archivo", file);

    const res = await fetch(`/chat/${currentChatId}`, {
      method: "POST",
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: formData
    });

    if (!res.ok) { const err = await res.text(); throw new Error(err); }

    clearFile();

    if (chatIdAtSend === currentChatId) {
      const preview = document.getElementById(`preview-${currentChatId}`);
      if (preview) preview.textContent = text || 'Archivo';
      setTimeout(() => { if (chatIdAtSend === currentChatId && !isFetching) fetchMessages(); }, 800);
    }
  } catch (e) {
    if (chatIdAtSend === currentChatId) {
      toast('Error al enviar mensaje');
      msgDiv.classList.add('error');
      const timeSpan = msgDiv.querySelector('.msg-time');
      if (timeSpan) timeSpan.innerHTML += ' ⚠';
      clearFile();
      const pending = pendingMessages.get(currentChatId) || [];
      pendingMessages.set(currentChatId, pending.filter(p => p.tempId !== tempId));
    }
  }
}

function handleInputKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── IA (Gemini) ────────────────────────────────────────────────────────────

function openAIChat() {
  document.getElementById('chat-placeholder').style.display = 'none';
  document.getElementById('chat-panel').style.display       = 'none';
  document.getElementById('ai-panel').style.display         = 'flex';
  document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
  document.getElementById('ai-sidebar-item').classList.add('active');
  document.getElementById('ai-input').focus();
  removeAIFile();
  if (aiHistory.length === 0) pushBubble('ai', '¡Hola! Soy tu asistente IA. ¿En qué puedo ayudarte?');
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

async function readFileContent(file) {
  return new Promise((resolve) => {
    const reader = new FileReader();
    if (file.type.startsWith('image/')) {
      reader.onload = () => resolve({ type: 'image', name: file.name, data: reader.result, mime: file.type });
      reader.readAsDataURL(file);
    } else if (file.type.startsWith('text/') || /\.(txt|js|html|css|json|md)$/.test(file.name)) {
      reader.onload = () => resolve({ type: 'text', name: file.name, content: reader.result });
      reader.readAsText(file);
    } else if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
      resolve({ type: 'pdf', name: file.name, size: file.size });
    } else {
      resolve({ type: 'other', name: file.name, size: file.size, mime: file.type });
    }
  });
}

async function sendAIMessage() {
  const input = document.getElementById('ai-input');
  const text  = input.value.trim();
  const file  = aiSelectedFile;

  if ((!text && !file) || aiWaiting) return;

  let fileInfo = '';

  if (file) {
    try {
      const fd = await readFileContent(file);
      switch (fd.type) {
        case 'text':  fileInfo = `📄 Archivo: ${file.name}\nContenido:\n${fd.content}\n\n`; pushBubble('user', `📄 ${file.name}\n${text || ''}`); break;
        case 'image': fileInfo = `🖼️ Imagen: ${file.name}\n`;  pushBubble('user', `🖼️ ${file.name}\n${text || ''}`); break;
        case 'pdf':   fileInfo = `📑 PDF: ${file.name} (${(fd.size/1024).toFixed(1)} KB)\n`; pushBubble('user', `📑 ${file.name}\n${text || ''}`); break;
        default:      fileInfo = `📎 Archivo: ${file.name} (${(fd.size/1024).toFixed(1)} KB)\n`; pushBubble('user', `📎 ${file.name}\n${text || ''}`);
      }
    } catch { fileInfo = `📎 Archivo: ${file.name}\n`; pushBubble('user', `📎 ${file.name}\n${text || ''}`); }
  } else {
    pushBubble('user', text);
  }

  const fullPrompt = fileInfo + (text || '');
  setPreview(text || file.name);
  input.value        = '';
  input.style.height = 'auto';
  removeAIFile();

  aiHistory.push({ role: 'user', parts: [{ text: fullPrompt }] });
  aiWaiting = true;
  document.getElementById('ai-send-btn').disabled = true;
  const typingEl = pushTyping();

  const wait = Math.max(0, MIN_GAP - (Date.now() - lastCall));
  if (wait > 0) { for (let i = Math.ceil(wait/1000); i > 0; i--) { setStatus(`listo en ${i}s…`); await sleep(1000); } }
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
  lastCall    = Date.now();
  const res   = await fetch(GEMINI_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ contents: history })
  });
  if (res.status === 429 && tries > 0) { setStatus(`esperando cuota… (${tries})`); await sleep(delay); return callGemini(history, tries-1, delay+4000); }
  if (!res.ok) { const e = new Error(); e.status = res.status; throw e; }
  const d = await res.json();
  return d?.candidates?.[0]?.content?.parts?.[0]?.text ?? 'Sin respuesta. Intenta de nuevo.';
}

function pushBubble(role, text) {
  const c   = document.getElementById('ai-messages');
  const row = document.createElement('div');
  row.className = role === 'user' ? 'msg mine' : 'msg theirs';
  const t   = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  row.innerHTML = `${md(text)}<span class="msg-time">${t} ${role === 'user' ? '✓✓' : ''}</span>`;
  c.appendChild(row);
  c.scrollTop = c.scrollHeight;
  return row;
}

function pushTyping() {
  const c   = document.getElementById('ai-messages');
  const row = document.createElement('div');
  row.className = 'msg theirs';
  row.innerHTML = `<div class="typing-dots"><span></span><span></span><span></span></div>`;
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

window.addEventListener('beforeunload', () => stopPolling());