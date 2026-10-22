<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tienda de electrónicos</title>
    <style>
        :root {
            --fondo: #f3f5f8;
            --tarjeta: #ffffff;
            --texto: #1b2533;
            --suave: #5d6b7c;
            --borde: #dbe1e8;
            --acento: #2457d6;
            --ok: #12724f;
            --error: #b42318;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; background: var(--fondo); color: var(--texto); }
        header { background: #14213d; color: #fff; padding: 14px 20px; display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; justify-content: space-between; }
        header h1 { margin: 0; font-size: 20px; }
        .etiqueta { background: #ffffff22; border: 1px solid #ffffff55; border-radius: 999px; padding: 2px 12px; font-size: 13px; }
        main { max-width: 1100px; margin: 20px auto; padding: 0 16px 32px; display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 440px), 1fr)); }
        .tarjeta { background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 10px; padding: 16px; }
        .ancho { grid-column: 1 / -1; }
        h2 { margin: 0 0 10px; font-size: 17px; }
        label { display: block; font-size: 13px; color: var(--suave); margin-bottom: 8px; }
        textarea { resize: vertical; font: 12px/1.4 ui-monospace, monospace; word-break: break-all; }
        input, textarea { width: 100%; padding: 7px 9px; border: 1px solid var(--borde); border-radius: 6px; font: inherit; color: var(--texto); background: #fff; }
        button { padding: 7px 14px; border: 0; border-radius: 6px; background: var(--acento); color: #fff; font: inherit; cursor: pointer; }
        button.secundario { background: #e8edf5; color: var(--texto); }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .fila { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .suave { color: var(--suave); font-size: 13px; margin: 8px 0 0; }
        .scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--borde); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--suave); }
        td small { display: block; color: var(--suave); }
        td input { width: 80px; }
        code { background: #eef1f6; border-radius: 4px; padding: 1px 5px; font-size: 13px; }
        #estado { font-weight: 600; margin-bottom: 8px; }
        #estado.ok { color: var(--ok); }
        #estado.error { color: var(--error); }
        pre { margin: 0; padding: 12px; background: #0f1724; color: #d7e3f4; border-radius: 8px; overflow: auto; max-height: 360px; font-size: 13px; }
    </style>
</head>
<body>
<header>
    <h1>Tienda de electrónicos</h1>
    <span class="etiqueta">v3-jwt · API key + JWT</span>
</header>

<main>
    <section class="tarjeta ancho">
        <h2>API key</h2>
        <label>Valor de <code>API_KEY</code> del servidor (se manda en el header <code>X-API-KEY</code> en cada petición)
            <input id="api-key" autocomplete="off" spellcheck="false" placeholder="Pega aquí la API key">
        </label>
        <p class="suave" style="margin-top: 0;">No viene incrustada en la página a propósito: cualquiera que abra el sitio la vería.</p>
    </section>

    <section class="tarjeta">
        <h2>Sesión</h2>
        <form id="form-login">
            <label>Email
                <input id="email" type="email" value="cliente1@tienda.com" required>
            </label>
            <label>Contraseña
                <input id="password" type="password" value="password" required>
            </label>
            <div class="fila">
                <button type="submit">Entrar</button>
                <button type="button" class="secundario" data-usuario="admin@tienda.com">admin</button>
                <button type="button" class="secundario" data-usuario="cliente1@tienda.com">cliente1</button>
                <button type="button" class="secundario" data-usuario="cliente2@tienda.com">cliente2</button>
            </div>
        </form>
        <div class="fila" style="margin-top: 8px;">
            <button type="button" class="secundario" id="btn-logout" disabled>Cerrar sesión</button>
        </div>
        <p class="suave" id="sesion-info">Sin sesión.</p>
    </section>

    <section class="tarjeta">
        <h2>Perfil</h2>
        <p class="suave" style="margin-top: 0;">Es el del dueño del token: ya no se manda ningún <code>user_id</code>.</p>
        <button type="button" id="btn-perfil">Ver mi perfil</button>
    </section>

    <section class="tarjeta ancho" id="tarjeta-token" hidden>
        <h2>Token JWT</h2>
        <label>Se manda en cada petición como <code>Authorization: Bearer &lt;token&gt;</code> (se guarda en <code>sessionStorage</code>)
            <textarea id="token-texto" rows="3" readonly></textarea>
        </label>
        <p class="suave" style="margin-top: 0;">Claims (decodificados aquí solo para mostrarlos; la firma la verifica el servidor):</p>
        <pre id="token-claims"></pre>
    </section>

    <section class="tarjeta ancho">
        <div class="fila" style="justify-content: space-between; margin-bottom: 8px;">
            <h2 style="margin: 0;">Productos</h2>
            <button type="button" class="secundario" id="btn-productos">Recargar</button>
        </div>
        <div class="scroll">
            <table id="tabla-productos">
                <thead>
                <tr><th>ID</th><th>Producto</th><th>Precio</th><th>Stock</th><th>Cantidad</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="tarjeta">
        <h2>Crear pedido</h2>
        <p class="suave" style="margin-top: 0;">Elige las cantidades en la tabla de productos.</p>
        <label>user_id (a nombre de quién se crea)
            <input id="pedido-user-id" type="number" min="1" value="1">
        </label>
        <button type="button" id="btn-pedido">Crear pedido</button>
    </section>

    <section class="tarjeta">
        <h2>Ver pedido por id</h2>
        <form id="form-ver-pedido">
            <label>ID del pedido
                <input id="ver-pedido-id" type="number" min="1" value="1" required>
            </label>
            <button type="submit">Consultar</button>
        </form>
    </section>

    <section class="tarjeta ancho">
        <h2>Crear producto</h2>
        <form id="form-producto">
            <div class="fila">
                <label style="flex: 2 1 200px;">Nombre
                    <input id="producto-nombre" required>
                </label>
                <label style="flex: 3 1 260px;">Descripción
                    <input id="producto-descripcion">
                </label>
                <label style="flex: 1 1 110px;">Precio
                    <input id="producto-precio" type="number" min="0" step="0.01" required>
                </label>
                <label style="flex: 1 1 90px;">Stock
                    <input id="producto-stock" type="number" min="0" step="1" value="10" required>
                </label>
            </div>
            <button type="submit">Crear producto</button>
        </form>
    </section>

    <section class="tarjeta ancho">
        <h2>Última respuesta de la API</h2>
        <div id="estado">Todavía no hay peticiones.</div>
        <pre id="salida">{}</pre>
    </section>
</main>

<script>
    const API = '/api';
    const $ = (id) => document.getElementById(id);
    const dinero = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

    // El almacenamiento del navegador puede estar bloqueado: nunca debe romper la página.
    function leer(tipo, clave) {
        try { return window[tipo].getItem(clave); } catch (e) { return null; }
    }

    function guardar(tipo, clave, valor) {
        try { window[tipo].setItem(clave, valor); } catch (e) { /* se queda solo en memoria */ }
    }

    function leerJson(tipo, clave) {
        try { return JSON.parse(leer(tipo, clave)); } catch (e) { return null; }
    }

    function borrar(tipo, clave) {
        try { window[tipo].removeItem(clave); } catch (e) { /* nada que borrar */ }
    }

    let usuario = leerJson('sessionStorage', 'usuario');
    let token = leer('sessionStorage', 'token');
    let apiKey = leer('localStorage', 'apiKey') ?? '';

    function cabeceras(conCuerpo) {
        const h = { 'Accept': 'application/json' };
        if (conCuerpo) h['Content-Type'] = 'application/json';
        if (apiKey) h['X-API-KEY'] = apiKey;
        if (token) h['Authorization'] = `Bearer ${token}`;
        return h;
    }

    function mostrar(texto, ok, datos) {
        $('estado').textContent = texto;
        $('estado').className = ok ? 'ok' : 'error';
        $('salida').textContent = datos === undefined ? '' : JSON.stringify(datos, null, 2);
    }

    // silencioso: no pisa el panel "Última respuesta" (se usa al recargar la tabla de productos)
    async function api(metodo, ruta, cuerpo, silencioso = false) {
        const opciones = { method: metodo, headers: cabeceras(cuerpo !== undefined) };
        if (cuerpo !== undefined) opciones.body = JSON.stringify(cuerpo);

        try {
            const res = await fetch(API + ruta, opciones);
            const datos = await res.json().catch(() => null);
            if (!silencioso) mostrar(`${metodo} ${API}${ruta} → ${res.status}`, res.ok, datos);
            return { ok: res.ok, status: res.status, datos };
        } catch (e) {
            mostrar(`${metodo} ${API}${ruta} → no se pudo conectar con la API`, false);
            return { ok: false, status: 0, datos: null };
        }
    }

    // Solo para mostrar los claims: decodificar NO verifica nada, eso lo hace el servidor.
    function claimsDe(jwt) {
        try {
            return JSON.parse(atob(jwt.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
        } catch (e) {
            return null;
        }
    }

    function pintarSesion() {
        const activa = Boolean(usuario && token);
        const claims = activa ? claimsDe(token) : null;

        $('btn-logout').disabled = !activa;
        $('tarjeta-token').hidden = !activa;

        if (!activa) {
            $('sesion-info').textContent = 'Sin sesión.';
            return;
        }

        const expira = claims?.exp ? ` · el token expira a las ${new Date(claims.exp * 1000).toLocaleTimeString()}` : '';
        $('sesion-info').textContent = `Sesión de ${usuario.name} (${usuario.email}) · id ${usuario.id} · rol ${usuario.rol}${expira}`;
        $('token-texto').value = token;
        $('token-claims').textContent = JSON.stringify(claims, null, 2);
        $('pedido-user-id').value = usuario.id;
    }

    function iniciarSesion(datos) {
        usuario = datos.user;
        token = datos.token;
        guardar('sessionStorage', 'usuario', JSON.stringify(usuario));
        guardar('sessionStorage', 'token', token);
        pintarSesion();
    }

    function limpiarSesion() {
        usuario = null;
        token = null;
        borrar('sessionStorage', 'usuario');
        borrar('sessionStorage', 'token');
        pintarSesion();
    }

    async function login(e) {
        e.preventDefault();
        const r = await api('POST', '/login', { email: $('email').value, password: $('password').value });
        if (r.ok) iniciarSesion(r.datos);
    }

    async function logout() {
        const r = await api('POST', '/logout');
        // 200: el token quedó revocado. 401: ya no servía (expirado o revocado). En ambos casos se limpia.
        if (r.ok || r.status === 401) limpiarSesion();
    }

    function celda(fila, texto) {
        const td = document.createElement('td');
        td.textContent = texto;
        fila.appendChild(td);
        return td;
    }

    function pintarProductos(productos) {
        const cuerpo = $('tabla-productos').querySelector('tbody');
        cuerpo.replaceChildren();

        for (const p of productos) {
            const fila = document.createElement('tr');
            fila.dataset.id = p.id;

            celda(fila, p.id);

            const nombre = celda(fila, p.nombre);
            const detalle = document.createElement('small');
            detalle.textContent = p.descripcion ?? '';
            nombre.appendChild(detalle);

            celda(fila, dinero.format(p.precio));
            celda(fila, p.stock);

            const cantidad = document.createElement('input');
            cantidad.type = 'number';
            cantidad.min = '0';
            cantidad.max = String(p.stock);
            cantidad.value = '0';
            celda(fila, '').appendChild(cantidad);

            cuerpo.appendChild(fila);
        }
    }

    async function cargarProductos(silencioso = false) {
        const r = await api('GET', '/productos', undefined, silencioso);
        if (r.ok) pintarProductos(r.datos);
    }

    async function crearPedido() {
        const items = [...document.querySelectorAll('#tabla-productos tbody tr')]
            .map((fila) => ({ producto_id: Number(fila.dataset.id), cantidad: Number(fila.querySelector('input').value) }))
            .filter((item) => item.cantidad > 0);

        if (items.length === 0) {
            mostrar('Elige al menos un producto con cantidad mayor a 0.', false);
            return;
        }

        const r = await api('POST', '/pedidos', { user_id: Number($('pedido-user-id').value), items });
        if (r.ok) {
            $('ver-pedido-id').value = r.datos.id;
            cargarProductos(true);
        }
    }

    function verPedido(e) {
        e.preventDefault();
        api('GET', `/pedidos/${encodeURIComponent($('ver-pedido-id').value)}`);
    }

    function verPerfil() {
        api('GET', '/perfil');
    }

    async function crearProducto(e) {
        e.preventDefault();
        const r = await api('POST', '/productos', {
            nombre: $('producto-nombre').value,
            descripcion: $('producto-descripcion').value || null,
            precio: Number($('producto-precio').value),
            stock: Number($('producto-stock').value),
        });
        if (r.ok) {
            $('form-producto').reset();
            cargarProductos(true);
        }
    }

    $('api-key').value = apiKey;
    $('api-key').addEventListener('input', (e) => {
        apiKey = e.target.value.trim();
        guardar('localStorage', 'apiKey', apiKey);
    });
    $('api-key').addEventListener('change', () => cargarProductos());

    $('form-login').addEventListener('submit', login);
    $('form-ver-pedido').addEventListener('submit', verPedido);
    $('form-producto').addEventListener('submit', crearProducto);
    $('btn-perfil').addEventListener('click', verPerfil);
    $('btn-logout').addEventListener('click', logout);
    $('btn-pedido').addEventListener('click', crearPedido);
    $('btn-productos').addEventListener('click', () => cargarProductos());

    document.querySelectorAll('[data-usuario]').forEach((boton) => {
        boton.addEventListener('click', () => {
            $('email').value = boton.dataset.usuario;
            $('password').value = 'password';
        });
    });

    pintarSesion();
    cargarProductos();
</script>
</body>
</html>
