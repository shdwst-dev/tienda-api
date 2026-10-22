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
        #estado { font-weight: 600; margin-bottom: 8px; }
        #estado.ok { color: var(--ok); }
        #estado.error { color: var(--error); }
        pre { margin: 0; padding: 12px; background: #0f1724; color: #d7e3f4; border-radius: 8px; overflow: auto; max-height: 360px; font-size: 13px; }
    </style>
</head>
<body>
<header>
    <h1>Tienda de electrónicos</h1>
    <span class="etiqueta">v1-base · sin seguridad</span>
</header>

<main>
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
        <p class="suave" id="sesion-info">Sin sesión.</p>
    </section>

    <section class="tarjeta">
        <h2>Perfil</h2>
        <label>user_id
            <input id="perfil-user-id" type="number" min="1" value="1">
        </label>
        <button type="button" id="btn-perfil">Ver perfil</button>
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

    let usuario = JSON.parse(sessionStorage.getItem('usuario') || 'null');

    function cabeceras(conCuerpo) {
        const h = { 'Accept': 'application/json' };
        if (conCuerpo) h['Content-Type'] = 'application/json';
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

    function pintarSesion() {
        $('sesion-info').textContent = usuario
            ? `Sesión de ${usuario.name} (${usuario.email}) · id ${usuario.id} · rol ${usuario.rol}`
            : 'Sin sesión.';
        if (usuario) {
            $('perfil-user-id').value = usuario.id;
            $('pedido-user-id').value = usuario.id;
        }
    }

    async function login(e) {
        e.preventDefault();
        const r = await api('POST', '/login', { email: $('email').value, password: $('password').value });
        if (!r.ok) return;

        usuario = r.datos.user;
        sessionStorage.setItem('usuario', JSON.stringify(usuario));
        pintarSesion();
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
        api('GET', `/perfil?user_id=${encodeURIComponent($('perfil-user-id').value)}`);
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

    $('form-login').addEventListener('submit', login);
    $('form-ver-pedido').addEventListener('submit', verPedido);
    $('form-producto').addEventListener('submit', crearProducto);
    $('btn-perfil').addEventListener('click', verPerfil);
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
