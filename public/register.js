const API_BASE = 'http://127.0.0.1:8000';

if (localStorage.getItem('chat_token')) {
    window.location.href = 'chat.html';
}

function showError(msg) {
    document.getElementById('success-box').style.display = 'none';
    const el = document.getElementById('error-box');
    el.textContent = msg;
    el.style.display = 'block';
}

function showSuccess(msg) {
    document.getElementById('error-box').style.display = 'none';
    const el = document.getElementById('success-box');
    el.innerHTML = msg;
    el.style.display = 'block';
}

function setLoading(on) {
    document.getElementById('btn-register').disabled = on;
    document.getElementById('btn-text').textContent = on ? 'Creando cuenta...' : 'Crear cuenta';
    document.getElementById('spinner').style.display = on ? 'block' : 'none';
}

async function doRegister() {
    document.getElementById('error-box').style.display = 'none';
    document.getElementById('success-box').style.display = 'none';

    const nombre           = document.getElementById('nombre').value.trim();
    const apellido_p       = document.getElementById('apellido_p').value.trim();
    const apellido_m       = document.getElementById('apellido_m').value.trim();
    const fecha_nacimiento = document.getElementById('fecha_nacimiento').value;
    const correo           = document.getElementById('correo').value.trim();

    if (!nombre || !apellido_p || !apellido_m || !fecha_nacimiento || !correo) {
        showError('Por favor completa todos los campos.');
        return;
    }

    setLoading(true);
    try {
        const res = await fetch(`${API_BASE}/api/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ nombre, apellido_p, apellido_m, fecha_nacimiento, correo })
        });

        const data = await res.json();
        if (!res.ok) throw data;

        localStorage.setItem('chat_token', data.token);
        localStorage.setItem('chat_user', JSON.stringify(data.user));

        showSuccess(`✅ Cuenta creada. Revisa tu correo <strong>${correo}</strong> para ver tu contraseña.`);
        setTimeout(() => { window.location.href = 'chat.html'; }, 3000);

    } catch (e) {
        const msg = e.errors
        ? Object.values(e.errors).flat().join(' ')
        : (e.message || 'Error al crear la cuenta.');
        showError(msg);
    } finally {
        setLoading(false);
    }
}