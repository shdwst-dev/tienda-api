<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- La API exige la API key en cada petición; el navegador la manda sola desde aquí (ver app.js). --}}
    <meta name="api-key" content="{{ config('services.api_key') }}">
    <title>TechStore - Tienda en Línea</title>
    <link rel="stylesheet" href="/assets/styles.css?v={{ filemtime(public_path('assets/styles.css')) }}">
</head>
<body>

<!-- Barra de navegación (oculta hasta que el usuario inicie sesión) -->
<nav id="main-navbar" class="navbar" style="display: none;">
    <div class="navbar-container">
        <a class="brand" onclick="showView('catalog')">TechStore</a>

        <div class="nav-links">
            <button id="nav-catalog" class="nav-link active" onclick="showView('catalog')">Catálogo</button>
            <button id="nav-cart" class="nav-link" onclick="showView('cart')">Carrito (<span id="cart-count">0</span>)</button>
            <button id="nav-orders" class="nav-link" onclick="showView('orders')">Mis pedidos</button>
            <button id="nav-admin" class="nav-link" style="display: none;" onclick="showView('admin')">Administración</button>

            <div class="user-menu">
                <span id="current-user-name" class="user-name"></span>
                <button class="btn btn-secondary btn-sm" onclick="logout()">Cerrar sesión</button>
            </div>
        </div>
    </div>
</nav>

<main class="container">
    <div id="global-alert" class="alert" role="alert"></div>

    <!-- VISTA: INICIAR SESIÓN (vista inicial por defecto) -->
    <section id="view-login" class="view-section active">
        <div class="auth-wrapper card">
            <div class="auth-header">
                <h1>TechStore</h1>
                <p>Inicia sesión para acceder a la tienda</p>
            </div>
            <form onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label for="login-email">Correo electrónico</label>
                    <input id="login-email" type="email" class="form-control" autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Contraseña</label>
                    <input id="login-password" type="password" class="form-control" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Iniciar sesión</button>
            </form>
            <p class="auth-switch">
                ¿No tienes cuenta? <a href="#" onclick="showView('register'); return false;">Regístrate aquí</a>
            </p>
        </div>
    </section>

    <!-- VISTA: REGISTRO -->
    <section id="view-register" class="view-section">
        <div class="auth-wrapper card">
            <div class="auth-header">
                <h1>TechStore</h1>
                <p>Crea una cuenta para continuar</p>
            </div>
            <form onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label for="reg-name">Nombre completo</label>
                    <input id="reg-name" type="text" class="form-control" autocomplete="name" maxlength="255" required>
                </div>
                <div class="form-group">
                    <label for="reg-email">Correo electrónico</label>
                    <input id="reg-email" type="email" class="form-control" autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label for="reg-password">Contraseña</label>
                    <input id="reg-password" type="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="72" required>
                    <small class="field-hint">Mínimo 8 caracteres.</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Crear cuenta</button>
            </form>
            <p class="auth-switch">
                ¿Ya tienes cuenta? <a href="#" onclick="showView('login'); return false;">Inicia sesión</a>
            </p>
        </div>
    </section>

    <!-- VISTA: CATÁLOGO -->
    <section id="view-catalog" class="view-section">
        <div class="page-header">
            <h1 class="page-title">Catálogo de Productos</h1>
        </div>

        <form class="filters-bar" onsubmit="event.preventDefault(); loadCatalog();">
            <input id="search-input" type="text" class="form-control" maxlength="100" placeholder="Buscar por nombre o descripción...">
            <select id="category-select" class="form-select">
                <option value="">Todas las categorías</option>
            </select>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <button type="button" class="btn btn-secondary" onclick="resetFilters()">Limpiar</button>
        </form>

        <div id="products-grid" class="products-grid"></div>
    </section>

    <!-- VISTA: CARRITO -->
    <section id="view-cart" class="view-section">
        <div class="page-header">
            <h1 class="page-title">Carrito de Compras</h1>
        </div>

        <div class="card">
            <div id="cart-empty" class="muted">No hay productos en tu carrito.</div>

            <div id="cart-content" style="display: none;">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Precio unitario</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="cart-tbody"></tbody>
                    </table>
                </div>

                <div class="cart-footer">
                    <div class="cart-address">
                        <label for="shipping-address">Dirección de envío</label>
                        <input id="shipping-address" type="text" class="form-control" maxlength="255" autocomplete="street-address" placeholder="Calle, número, colonia y ciudad">
                    </div>
                    <div class="cart-summary">
                        <div class="cart-total">
                            Total: <span id="cart-total">$0.00</span>
                        </div>
                        <button class="btn btn-secondary" onclick="clearCart()">Vaciar carrito</button>
                        <button id="checkout-btn" class="btn btn-primary" onclick="checkout()">Confirmar pedido</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- VISTA: MIS PEDIDOS -->
    <section id="view-orders" class="view-section">
        <div class="page-header">
            <h1 class="page-title">Mis Pedidos</h1>
        </div>
        <div id="orders-container"></div>
    </section>

    <!-- VISTA: ADMINISTRACIÓN (solo visible para administradores) -->
    <section id="view-admin" class="view-section">
        <div class="page-header">
            <h1 class="page-title">Panel de Administración</h1>
        </div>

        <div class="sub-nav">
            <button id="admin-tab-products" class="nav-link active" onclick="showAdminSection('products')">Productos</button>
            <button id="admin-tab-orders" class="nav-link" onclick="showAdminSection('orders')">Pedidos de clientes</button>
            <button id="admin-tab-users" class="nav-link" onclick="showAdminSection('users')">Usuarios</button>
        </div>

        <!-- Admin: Productos -->
        <div id="admin-sec-products">
            <div class="card">
                <h3 id="product-form-title" class="card-title">Agregar producto</h3>
                <form onsubmit="saveProduct(event)" class="product-form">
                    <input id="prod-id" type="hidden">
                    <div>
                        <label for="prod-name">Nombre</label>
                        <input id="prod-name" type="text" class="form-control" maxlength="255" required>
                    </div>
                    <div>
                        <label for="prod-category">Categoría</label>
                        <input id="prod-category" type="text" class="form-control" list="category-options" maxlength="100" required>
                        <datalist id="category-options"></datalist>
                    </div>
                    <div>
                        <label for="prod-price">Precio ($)</label>
                        <input id="prod-price" type="number" step="0.01" min="0.01" class="form-control" required>
                    </div>
                    <div>
                        <label for="prod-stock">Stock</label>
                        <input id="prod-stock" type="number" min="0" step="1" class="form-control" required>
                    </div>
                    <div class="full-row">
                        <label for="prod-description">Descripción</label>
                        <input id="prod-description" type="text" class="form-control" maxlength="2000" required>
                    </div>
                    <div class="full-row form-actions">
                        <button type="submit" class="btn btn-primary">Guardar producto</button>
                        <button type="button" id="prod-cancel-btn" class="btn btn-secondary" style="display: none;" onclick="resetProductForm()">Cancelar edición</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody id="admin-products-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Admin: todos los pedidos -->
        <div id="admin-sec-orders" style="display: none;">
            <div class="card">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Dirección</th>
                            <th>Artículos</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                        </thead>
                        <tbody id="admin-orders-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Admin: usuarios -->
        <div id="admin-sec-users" style="display: none;">
            <div class="card">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo electrónico</th>
                            <th>Rol</th>
                            <th>Fecha de registro</th>
                        </tr>
                        </thead>
                        <tbody id="admin-users-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<script src="/assets/app.js?v={{ filemtime(public_path('assets/app.js')) }}"></script>
</body>
</html>
