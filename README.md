# Tienda API — Seguridad de API con Laravel

Tarea de Seguridad Informática (UPQ). Mini tienda de aparatos electrónicos: API REST en **Laravel 13 + PostgreSQL**
con un frontend simple en Blade + `fetch()` (sin Vite ni npm).

El proyecto se construye por etapas. Cada concepto de seguridad vive en su propia rama, se une a `master` con
`git merge --no-ff` y queda marcado con un tag, así que se puede viajar entre etapas con `git checkout <tag>`:

| Tag | Rama | Qué agrega |
|-----|------|------------|
| `v1-base` | `master` | API base y frontend, funcional pero **vulnerable a propósito** |

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configura PostgreSQL en el `.env` (la base `tienda_api` debe existir):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tienda_api
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
CACHE_STORE=database
```

Crea las tablas con datos de prueba y levanta el servidor:

```bash
php artisan migrate:fresh --seed
php artisan serve        # http://127.0.0.1:8000
```

El frontend está en `http://127.0.0.1:8000/` y la API en `http://127.0.0.1:8000/api`.
Los ejemplos de abajo usan `curl` y `jq` (opcional, solo para ver el JSON bonito).

### Usuarios de prueba

Contraseña de todos: `password`.

| Email | Rol | Pedidos |
|-------|-----|---------|
| `admin@tienda.com` | `admin` | — |
| `cliente1@tienda.com` | `cliente` | 1 y 2 |
| `cliente2@tienda.com` | `cliente` | 3 y 4 |

Además hay 8 productos electrónicos (ids 1 a 8). `migrate:fresh --seed` deja todo como al inicio.

### Endpoints

| Método | Ruta | Qué hace | Acceso |
|--------|------|----------|--------|
| POST | `/api/login` | Valida credenciales y regresa el usuario | Público |
| GET | `/api/perfil?user_id=X` | Datos del usuario `X` | Público |
| GET | `/api/productos` | Lista de productos | Público |
| POST | `/api/productos` | Crea un producto | Público |
| GET | `/api/pedidos/{id}` | Detalle de un pedido con sus productos | Público |
| POST | `/api/pedidos` | Crea un pedido (`user_id` + `items`) | Público |

Las rutas de `routes/api.php` se registran a mano en `bootstrap/app.php` (no se usó `php artisan install:api`,
así que no hay Sanctum ni Passport).

---

## Etapa 1 — API base, vulnerable a propósito (`v1-base`)

Es el punto de partida: todo funciona, pero **nada está protegido**. Sirve para mostrar el "antes" de cada concepto.

### Qué está mal (a propósito)

| Problema | Dónde | Qué permite |
|----------|-------|-------------|
| BOLA / IDOR | `GET /api/pedidos/{id}` | Ver el pedido de cualquier persona solo cambiando el id |
| Perfil sin dueño | `GET /api/perfil?user_id=X` | Ver los datos de cualquier usuario |
| Sin control de roles | `POST /api/productos` | Cualquiera (incluso sin cuenta) crea productos |
| Confía en el cliente | `POST /api/pedidos` | Crear pedidos a nombre de otro usuario con `user_id` en el body |
| Sin autenticación | todos | `login` solo valida credenciales, no hay sesión ni token |
| Sin límite de peticiones | todos | Fuerza bruta al login, scraping, spam de pedidos |

### Archivos

- `bootstrap/app.php`: registra `routes/api.php` y devuelve los errores de `/api` como JSON corto (sin trace aunque `APP_DEBUG=true`).
- `routes/api.php`, `routes/web.php`: rutas de la API y la ruta `/` del frontend.
- `app/Http/Controllers/AuthController.php`, `ProductoController.php`, `PedidoController.php`.
- `app/Models/`: `User` (con `rol`), `Producto`, `Pedido`, `PedidoProducto`.
- `database/migrations/`: `productos`, `pedidos`, `pedido_productos` y la columna `rol` (`cliente` o `admin`) en `users`.
- `database/seeders/`: usuarios, productos y 2 pedidos por cliente.
- `resources/views/tienda.blade.php`: el frontend completo (HTML + CSS + JS en una sola vista).

### Pruebas con curl (aquí los ataques FUNCIONAN)

```bash
# 1) IDOR: ver el pedido 3 (de cliente2) sin autenticarse  ->  200 con todo el pedido
curl -s http://127.0.0.1:8000/api/pedidos/3 | jq

# 2) Perfil ajeno: datos del admin (id 1)  ->  200
curl -s 'http://127.0.0.1:8000/api/perfil?user_id=1' | jq

# 3) Crear un producto sin ser admin ni tener cuenta  ->  201
curl -s -X POST http://127.0.0.1:8000/api/productos \
  -H 'Content-Type: application/json' \
  -d '{"nombre":"Producto pirata","descripcion":"lo cree sin permiso","precio":1,"stock":999}' | jq

# 4) Pedido a nombre de otro usuario (user_id 3 = cliente2)  ->  201
curl -s -X POST http://127.0.0.1:8000/api/pedidos \
  -H 'Content-Type: application/json' \
  -d '{"user_id":3,"items":[{"producto_id":6,"cantidad":1}]}' | jq

# 5) Sin límite de peticiones: 100 seguidas  ->  100 respuestas 200
curl -s -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1:8000/api/productos?[1-100]' | sort | uniq -c
```

### Cómo demostrarlo en vivo

1. Abre `http://127.0.0.1:8000/`: se ve la lista de productos y la etiqueta **v1-base · sin seguridad**.
2. Entra como `cliente1` y consulta el pedido `1` (suyo) con "Ver pedido por id".
3. Cambia el id a `3` (de `cliente2`): **se ve completo**. Eso es BOLA/IDOR.
4. En "Crear pedido" cambia el `user_id` a `3`: el pedido se crea a nombre de otra persona.
5. Crea un producto desde el formulario aunque seas un simple cliente: devuelve `201`.
6. Corre el bucle de 100 peticiones del punto 5: todas responden `200`, nadie frena nada.

En las siguientes etapas se repiten estos mismos pasos y cada uno empieza a fallar con `401`, `403` o `429`.
