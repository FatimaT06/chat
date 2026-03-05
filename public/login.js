const API_BASE = 'http://127.0.0.1:8000';

if (localStorage.getItem('chat_token')) {
  window.location.href = 'chat.html';
}

function showError(msg) {
  const el = document.getElementById('error-box');
  el.textContent = msg;
  el.style.display = 'block';
}

function setLoading(on) {
  document.getElementById('btn-login').disabled = on;
  document.getElementById('btn-text').textContent = on ? 'Entrando...' : 'Iniciar sesión';
  document.getElementById('spinner').style.display = on ? 'block' : 'none';
}

async function doLogin() {
  document.getElementById('error-box').style.display = 'none';

  const correo   = document.getElementById('correo').value.trim();
  const password = document.getElementById('password').value;

  if (!correo || !password) { showError('Completa todos los campos.'); return; }

  setLoading(true);
  try {
    const res = await fetch(`${API_BASE}/api/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ correo, password })
    });

    const data = await res.json();
    if (!res.ok) throw data;

    localStorage.setItem('chat_token', data.token);
    localStorage.setItem('chat_user', JSON.stringify(data.user));
    window.location.href = 'chat.html';

  } catch (e) {
    const msg = e.errors
      ? Object.values(e.errors).flat().join(' ')
      : (e.message || 'Credenciales incorrectas.');
    showError(msg);
  } finally {
    setLoading(false);
  }
}