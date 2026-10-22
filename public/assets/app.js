/* TechStore: lógica de la tienda. Habla con la API (/api) del mismo servidor, sin Vite ni npm. */

// La API exige la API key en cada petición: el navegador la manda sola, viene en un <meta> de la página.
const API_KEY = document.querySelector('meta[name="api-key"]')?.content ?? '';

/* El almacenamiento del navegador puede estar bloqueado: nunca debe romper la página. */
function storageGet(key) {
    try { return sessionStorage.getItem(key); } catch (e) { return null; }
}

function storageSet(key, value) {
    try { sessionStorage.setItem(key, value); } catch (e) { /* queda solo en memoria */ }
}

function storageRemove(key) {
    try { sessionStorage.removeItem(key); } catch (e) { /* nada que borrar */ }
}

function storageJson(key, fallback) {
    try { return JSON.parse(storageGet(key)) ?? fallback; } catch (e) { return fallback; }
}

// La sesión (JWT) y el carrito viven en sessionStorage: se borran al cerrar la pestaña.
let token = storageGet('token');
let currentUser = storageJson('usuario', null);
let cart = storageJson('carrito', []);
let productsList = [];
let adminProducts = [];
let adminSection = 'products';
let alertTimer = null;
let checkingOut = false;
const requestIds = {};

// Si el usuario cambia de filtro o de pestaña muy rápido, una respuesta lenta no debe pisar a la más nueva.
function nextRequest(name) {
    requestIds[name] = (requestIds[name] || 0) + 1;
    return requestIds[name];
}

function isStale(name, id) {
    return requestIds[name] !== id;
}

const statusLabels = {
    pendiente: { text: 'Pendiente', cls: 'badge-pending' },
    pagado: { text: 'Pagado', cls: 'badge-paid' },
    enviado: { text: 'Enviado', cls: 'badge-shipped' },
    cancelado: { text: 'Cancelado', cls: 'badge-cancelled' }
};

function formatCurrency(amount) {
    return '$' + Number(amount).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatDate(value, withTime = true) {
    const options = withTime
        ? { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }
        : { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(value).toLocaleDateString('es-MX', options);
}

function showAlert(message, type = 'error', ms = 4500) {
    const box = document.getElementById('global-alert');
    box.textContent = message;
    box.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    box.style.display = 'block';
    clearTimeout(alertTimer);
    alertTimer = setTimeout(() => {
        box.style.display = 'none';
    }, ms);
}

function hideAlert() {
    clearTimeout(alertTimer);
    document.getElementById('global-alert').style.display = 'none';
}

function isAuthenticated() {
    return Boolean(token && currentUser);
}

function isAdmin() {
    return isAuthenticated() && currentUser.rol === 'admin';
}

function updateNav() {
    const navbar = document.getElementById('main-navbar');
    const navAdmin = document.getElementById('nav-admin');
    const userName = document.getElementById('current-user-name');

    if (isAuthenticated()) {
        navbar.style.display = 'block';
        userName.textContent = currentUser.name;
        navAdmin.style.display = isAdmin() ? 'inline-block' : 'none';
    } else {
        navbar.style.display = 'none';
    }

    const totalItems = cart.reduce((acc, item) => acc + item.quantity, 0);
    document.getElementById('cart-count').textContent = totalItems;
}

function showView(viewName) {
    // Sin sesión solo se puede ver el login y el registro.
    if (!isAuthenticated() && viewName !== 'login' && viewName !== 'register') {
        viewName = 'login';
    }

    // Con sesión iniciada, login y registro mandan al catálogo.
    if (isAuthenticated() && (viewName === 'login' || viewName === 'register')) {
        viewName = 'catalog';
    }

    // La pestaña de administración es solo comodidad: quien decide es el servidor.
    if (viewName === 'admin' && !isAdmin()) {
        viewName = 'catalog';
    }

    document.querySelectorAll('.view-section').forEach(sec => sec.classList.remove('active'));
    const target = document.getElementById('view-' + viewName);
    if (target) {
        target.classList.add('active');
    }

    ['catalog', 'cart', 'orders', 'admin'].forEach(nav => {
        const btn = document.getElementById('nav-' + nav);
        if (btn) {
            btn.classList.toggle('active', nav === viewName);
        }
    });

    if (viewName === 'catalog') loadCatalog();
    if (viewName === 'cart') renderCart();
    if (viewName === 'orders') loadMyOrders();
    if (viewName === 'admin') showAdminSection(adminSection);
}

/* ================= COMUNICACIÓN CON LA API ================= */
async function apiFetch(path, options = {}) {
    const { skipAuthHandling = false, ...fetchOptions } = options;
    const headers = {
        'Accept': 'application/json',
        'X-API-KEY': API_KEY,
        ...(fetchOptions.headers || {})
    };

    if (fetchOptions.body && typeof fetchOptions.body === 'object') {
        headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(fetchOptions.body);
    }

    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    let response;
    try {
        response = await fetch('/api' + path, { ...fetchOptions, headers });
    } catch (e) {
        return { ok: false, status: 0, data: { message: 'No se pudo conectar con la tienda. Revisa tu conexión e intenta de nuevo.' } };
    }

    const data = await response.json().catch(() => ({}));
    const isAuthRoute = path === '/login' || path === '/register';

    // Sesión vencida o cerrada desde otro lado: se vuelve al inicio de sesión.
    if (response.status === 401 && token && !isAuthRoute && !skipAuthHandling && !String(data.message || '').startsWith('API key')) {
        clearSession();
        showView('login');
        showAlert('Tu sesión terminó. Inicia sesión de nuevo.');
        return { ok: false, status: 401, data, handled: true };
    }

    return { ok: response.ok, status: response.status, data };
}

// Mensaje para el usuario: nunca se muestran textos técnicos del servidor.
function errorMessage(res, fallback) {
    const data = res.data || {};

    if (data.errors) {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }

    if (res.status >= 500) return fallback;
    return data.message || fallback;
}

function showError(res, fallback) {
    // Con "demasiadas peticiones" el aviso dura más: trae los segundos que faltan.
    showAlert(errorMessage(res, fallback), 'error', res.status === 429 ? 9000 : 4500);
}

/* ================= CATÁLOGO ================= */
function updateCategories(list) {
    const names = [...new Set(list.map(p => p.categoria))].sort((a, b) => a.localeCompare(b, 'es'));
    const select = document.getElementById('category-select');
    const current = select.value;

    select.replaceChildren(new Option('Todas las categorías', ''));
    names.forEach(name => select.appendChild(new Option(name, name)));
    select.value = names.includes(current) ? current : '';

    const datalist = document.getElementById('category-options');
    datalist.replaceChildren(...names.map(name => new Option(name)));
}

async function loadCatalog() {
    if (!isAuthenticated()) return;

    const search = document.getElementById('search-input').value.trim();
    const category = document.getElementById('category-select').value;
    const params = new URLSearchParams();
    if (search) params.set('buscar', search);
    if (category) params.set('categoria', category);

    const query = params.toString() ? '?' + params.toString() : '';
    const requestId = nextRequest('catalog');
    const res = await apiFetch('/productos' + query);
    const grid = document.getElementById('products-grid');

    if (res.handled || isStale('catalog', requestId)) return;

    if (!res.ok || !Array.isArray(res.data)) {
        grid.innerHTML = '<p class="empty-state">No se pudieron cargar los productos.</p>';
        return;
    }

    productsList = res.data;

    // Sin filtros se tiene el catálogo completo: de ahí salen las categorías disponibles.
    if (!search && !category) updateCategories(productsList);

    if (productsList.length === 0) {
        grid.innerHTML = '<p class="empty-state">No se encontraron productos con esos filtros.</p>';
        return;
    }

    grid.innerHTML = productsList.map(p => `
        <div class="product-card">
            <div>
                <div class="product-category">${escapeHtml(p.categoria)}</div>
                <h3 class="product-title">${escapeHtml(p.nombre)}</h3>
                <p class="product-desc">${escapeHtml(p.descripcion)}</p>
            </div>
            <div class="product-footer">
                <div>
                    <span class="product-price">${formatCurrency(p.precio)}</span>
                    <span class="product-stock">${p.stock > 0 ? p.stock + ' disponibles' : 'Agotado'}</span>
                </div>
                <button class="btn btn-primary btn-sm" ${p.stock <= 0 ? 'disabled' : ''} onclick="addToCart(${Number(p.id)})">
                    Agregar
                </button>
            </div>
        </div>
    `).join('');
}

function resetFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('category-select').value = '';
    loadCatalog();
}

/* ================= CARRITO ================= */
function saveCart() {
    storageSet('carrito', JSON.stringify(cart));
    updateNav();
}

function addToCart(productId) {
    const product = productsList.find(p => p.id === productId);
    if (!product) return;

    const existing = cart.find(item => item.productId === productId);
    if (existing) {
        if (existing.quantity >= Math.min(product.stock, 100)) {
            showAlert('No hay más unidades disponibles.');
            return;
        }
        existing.quantity += 1;
        existing.stock = product.stock;
    } else {
        cart.push({
            productId: product.id,
            name: product.nombre,
            price: Number(product.precio),
            stock: product.stock,
            quantity: 1
        });
    }

    saveCart();
    showAlert('Producto agregado al carrito.', 'success', 2500);
}

function changeCartQty(productId, delta) {
    const item = cart.find(i => i.productId === productId);
    if (!item) return;

    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        cart = cart.filter(i => i.productId !== productId);
    } else if (newQty <= Math.min(item.stock, 100)) {
        item.quantity = newQty;
    }
    saveCart();
    renderCart();
}

function removeFromCart(productId) {
    cart = cart.filter(i => i.productId !== productId);
    saveCart();
    renderCart();
}

function clearCart() {
    cart = [];
    saveCart();
    renderCart();
}

function renderCart() {
    const emptyEl = document.getElementById('cart-empty');
    const contentEl = document.getElementById('cart-content');
    const tbody = document.getElementById('cart-tbody');
    const totalEl = document.getElementById('cart-total');

    if (cart.length === 0) {
        emptyEl.style.display = 'block';
        contentEl.style.display = 'none';
        return;
    }

    emptyEl.style.display = 'none';
    contentEl.style.display = 'block';

    let total = 0;
    tbody.innerHTML = cart.map(item => {
        const subtotal = item.price * item.quantity;
        total += subtotal;
        return `
            <tr>
                <td>${escapeHtml(item.name)}</td>
                <td>${formatCurrency(item.price)}</td>
                <td>
                    <button class="qty-btn" onclick="changeCartQty(${Number(item.productId)}, -1)" aria-label="Quitar una pieza">-</button>
                    <span class="qty-value">${item.quantity}</span>
                    <button class="qty-btn" onclick="changeCartQty(${Number(item.productId)}, 1)" aria-label="Agregar una pieza">+</button>
                </td>
                <td><strong>${formatCurrency(subtotal)}</strong></td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="removeFromCart(${Number(item.productId)})">Quitar</button>
                </td>
            </tr>
        `;
    }).join('');

    totalEl.textContent = formatCurrency(total);
}

async function checkout() {
    if (checkingOut) return;

    const addressInput = document.getElementById('shipping-address');
    const shippingAddress = addressInput.value.trim();
    if (shippingAddress.length < 5) {
        showAlert('Ingresa una dirección de envío.');
        return;
    }

    checkingOut = true;
    const button = document.getElementById('checkout-btn');
    button.disabled = true;

    const items = cart.map(i => ({ producto_id: i.productId, cantidad: i.quantity }));
    const res = await apiFetch('/pedidos', {
        method: 'POST',
        body: { direccion_envio: shippingAddress, items }
    });

    checkingOut = false;
    button.disabled = false;

    if (res.handled) return;

    if (!res.ok) {
        showError(res, 'No se pudo procesar el pedido.');
        return;
    }

    clearCart();
    addressInput.value = '';
    showAlert('Tu pedido se ha registrado correctamente.', 'success');
    showView('orders');
}

/* ================= MIS PEDIDOS ================= */
async function loadMyOrders() {
    const container = document.getElementById('orders-container');
    const requestId = nextRequest('orders');
    const res = await apiFetch('/pedidos');

    if (res.handled || isStale('orders', requestId)) return;

    if (!res.ok || !Array.isArray(res.data)) {
        container.innerHTML = '<div class="card">No se pudieron cargar tus pedidos.</div>';
        return;
    }

    // Un administrador recibe los pedidos de todos: aquí solo se muestran los suyos.
    const orders = res.data.filter(order => order.user_id === currentUser.id);

    if (orders.length === 0) {
        container.innerHTML = '<div class="card muted">Aún no has realizado ningún pedido.</div>';
        return;
    }

    container.innerHTML = orders.map(order => {
        const st = statusLabels[order.estado] || { text: order.estado, cls: 'badge-pending' };

        return `
            <div class="card">
                <div class="order-head">
                    <div>
                        <strong>Pedido #${Number(order.id)}</strong>
                        <span class="order-date">${escapeHtml(formatDate(order.created_at))}</span>
                    </div>
                    <span class="badge ${st.cls}">${escapeHtml(st.text)}</span>
                </div>
                <p class="order-address">
                    <strong>Dirección de envío:</strong> ${escapeHtml(order.direccion_envio || 'No registrada')}
                </p>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${order.items.map(i => `
                                <tr>
                                    <td>${escapeHtml(i.producto?.nombre)}</td>
                                    <td>${Number(i.cantidad)}</td>
                                    <td>${formatCurrency(i.precio_unitario)}</td>
                                    <td>${formatCurrency(i.cantidad * Number(i.precio_unitario))}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="order-total">
                    <strong>Total: ${formatCurrency(order.total)}</strong>
                </div>
            </div>
        `;
    }).join('');
}

/* ================= AUTENTICACIÓN ================= */
function startSession(data) {
    hideAlert();
    token = data.token;
    currentUser = data.user;
    storageSet('token', token);
    storageSet('usuario', JSON.stringify(currentUser));
}

function clearSession() {
    token = null;
    currentUser = null;
    cart = [];
    storageRemove('token');
    storageRemove('usuario');
    storageRemove('carrito');
    updateNav();
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;

    const res = await apiFetch('/login', {
        method: 'POST',
        body: { email, password }
    });

    if (!res.ok) {
        showError(res, 'No se pudo iniciar sesión. Intenta de nuevo.');
        return;
    }

    startSession(res.data);
    document.getElementById('login-password').value = '';
    updateNav();
    showView('catalog');
}

async function handleRegister(e) {
    e.preventDefault();
    const name = document.getElementById('reg-name').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;

    const res = await apiFetch('/register', {
        method: 'POST',
        body: { name, email, password }
    });

    if (!res.ok) {
        showError(res, 'No se pudo completar el registro.');
        return;
    }

    startSession(res.data);
    document.getElementById('reg-name').value = '';
    document.getElementById('reg-email').value = '';
    document.getElementById('reg-password').value = '';

    updateNav();
    showAlert('Cuenta creada correctamente.', 'success');
    showView('catalog');
}

async function logout() {
    // Se revoca el token en el servidor; si falla (por ejemplo ya venció) igual se cierra la sesión aquí.
    if (token) {
        await apiFetch('/logout', { method: 'POST', skipAuthHandling: true });
    }
    clearSession();
    showView('login');
}

/* ================= ADMINISTRACIÓN ================= */
function showAdminSection(section) {
    adminSection = section;

    ['products', 'orders', 'users'].forEach(sec => {
        document.getElementById('admin-sec-' + sec).style.display = sec === section ? 'block' : 'none';
        document.getElementById('admin-tab-' + sec).classList.toggle('active', sec === section);
    });

    if (section === 'products') {
        loadAdminProducts();
    } else if (section === 'orders') {
        loadAdminOrders();
    } else if (section === 'users') {
        loadAdminUsers();
    }
}

async function loadAdminProducts() {
    const tbody = document.getElementById('admin-products-tbody');
    const requestId = nextRequest('adminProducts');
    const res = await apiFetch('/productos');

    if (res.handled || isStale('adminProducts', requestId)) return;

    if (!res.ok || !Array.isArray(res.data)) {
        tbody.innerHTML = '<tr><td colspan="6">No se pudieron cargar los productos.</td></tr>';
        return;
    }

    adminProducts = res.data;
    updateCategories(adminProducts);
    renderAdminProducts();
}

function renderAdminProducts() {
    const tbody = document.getElementById('admin-products-tbody');
    tbody.innerHTML = adminProducts.map(p => `
        <tr>
            <td>${Number(p.id)}</td>
            <td>${escapeHtml(p.nombre)}</td>
            <td>${escapeHtml(p.categoria)}</td>
            <td>${formatCurrency(p.precio)}</td>
            <td>${Number(p.stock)}</td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="editProduct(${Number(p.id)})">Editar</button>
                <button class="btn btn-danger btn-sm" onclick="deleteProduct(${Number(p.id)})">Eliminar</button>
            </td>
        </tr>
    `).join('');
}

function editProduct(id) {
    const p = adminProducts.find(item => item.id === id);
    if (!p) return;

    document.getElementById('product-form-title').textContent = 'Editar producto #' + p.id;
    document.getElementById('prod-id').value = p.id;
    document.getElementById('prod-name').value = p.nombre;
    document.getElementById('prod-category').value = p.categoria;
    document.getElementById('prod-price').value = p.precio;
    document.getElementById('prod-stock').value = p.stock;
    document.getElementById('prod-description').value = p.descripcion ?? '';
    document.getElementById('prod-cancel-btn').style.display = 'inline-block';
    document.getElementById('prod-name').focus();
}

function resetProductForm() {
    document.getElementById('product-form-title').textContent = 'Agregar producto';
    document.getElementById('prod-id').value = '';
    document.getElementById('prod-name').value = '';
    document.getElementById('prod-category').value = '';
    document.getElementById('prod-price').value = '';
    document.getElementById('prod-stock').value = '';
    document.getElementById('prod-description').value = '';
    document.getElementById('prod-cancel-btn').style.display = 'none';
}

async function saveProduct(e) {
    e.preventDefault();
    const id = document.getElementById('prod-id').value;
    const payload = {
        nombre: document.getElementById('prod-name').value.trim(),
        categoria: document.getElementById('prod-category').value.trim(),
        precio: parseFloat(document.getElementById('prod-price').value),
        stock: parseInt(document.getElementById('prod-stock').value, 10),
        descripcion: document.getElementById('prod-description').value.trim()
    };

    const method = id ? 'PUT' : 'POST';
    const path = id ? `/productos/${encodeURIComponent(id)}` : '/productos';

    const res = await apiFetch(path, { method, body: payload });

    if (res.handled) return;

    if (!res.ok) {
        showError(res, 'No se pudo guardar el producto.');
        return;
    }

    resetProductForm();
    showAlert('Producto guardado correctamente.', 'success');
    await loadAdminProducts();
}

async function deleteProduct(id) {
    if (!confirm('¿Seguro que deseas eliminar este producto?')) return;

    const res = await apiFetch(`/productos/${encodeURIComponent(id)}`, { method: 'DELETE' });

    if (res.handled) return;

    if (!res.ok) {
        showError(res, 'No se pudo eliminar el producto.');
        return;
    }

    showAlert('Producto eliminado.', 'success');
    await loadAdminProducts();
}

async function loadAdminOrders() {
    const tbody = document.getElementById('admin-orders-tbody');
    const requestId = nextRequest('adminOrders');
    const res = await apiFetch('/pedidos');

    if (res.handled || isStale('adminOrders', requestId)) return;

    if (!res.ok || !Array.isArray(res.data)) {
        tbody.innerHTML = '<tr><td colspan="6">No se pudieron cargar los pedidos.</td></tr>';
        return;
    }

    if (res.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="muted">Todavía no hay pedidos.</td></tr>';
        return;
    }

    tbody.innerHTML = res.data.map(o => `
        <tr>
            <td>#${Number(o.id)}</td>
            <td>${escapeHtml(o.user?.name)}<br><small>${escapeHtml(o.user?.email)}</small></td>
            <td>${escapeHtml(o.direccion_envio || 'No registrada')}</td>
            <td>${o.items.map(i => `${Number(i.cantidad)}x ${escapeHtml(i.producto?.nombre)}`).join('<br>')}</td>
            <td><strong>${formatCurrency(o.total)}</strong></td>
            <td>
                <select class="form-select status-select" ${o.estado === 'cancelado' ? 'disabled' : ''} onchange="changeOrderStatus(${Number(o.id)}, this.value)">
                    ${Object.entries(statusLabels).map(([value, label]) =>
                        `<option value="${value}" ${o.estado === value ? 'selected' : ''}>${label.text}</option>`).join('')}
                </select>
            </td>
        </tr>
    `).join('');
}

async function changeOrderStatus(orderId, status) {
    const res = await apiFetch(`/pedidos/${encodeURIComponent(orderId)}/estado`, {
        method: 'PATCH',
        body: { estado: status }
    });

    if (res.handled) return;

    if (res.ok) {
        showAlert('Estado del pedido actualizado.', 'success', 2500);
    } else {
        showError(res, 'No se pudo actualizar el estado.');
    }

    // Se recarga para que la tabla quede igual que la base (si se canceló, el selector se bloquea).
    loadAdminOrders();
}

async function loadAdminUsers() {
    const tbody = document.getElementById('admin-users-tbody');
    const requestId = nextRequest('adminUsers');
    const res = await apiFetch('/usuarios');

    if (res.handled || isStale('adminUsers', requestId)) return;

    if (!res.ok || !Array.isArray(res.data)) {
        tbody.innerHTML = '<tr><td colspan="5">No se pudieron cargar los usuarios.</td></tr>';
        return;
    }

    tbody.innerHTML = res.data.map(u => `
        <tr>
            <td>${Number(u.id)}</td>
            <td>${escapeHtml(u.name)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td>${u.rol === 'admin' ? 'Administrador' : 'Cliente'}</td>
            <td>${escapeHtml(formatDate(u.created_at, false))}</td>
        </tr>
    `).join('');
}

// Inicialización: si no hay sesión activa completa, se muestra únicamente el login.
if (!isAuthenticated()) clearSession();
updateNav();
showView(isAuthenticated() ? 'catalog' : 'login');
