# Tienda API — Seguridad de API con Laravel

Tarea de Seguridad Informática (UPQ). Tienda de aparatos electrónicos (TechStore): API REST en Laravel 13 +
PostgreSQL y una tienda web en Blade + `fetch()` (sin Vite ni npm).

Este README resume **qué se cambió en cada uno de los 4 pasos** para proteger la API. Cada paso es una rama que se unió
a `master` con `git merge --no-ff` y quedó marcada con un tag (`v1-base` es el punto de partida, vulnerable a propósito):

| Paso | Rama | Tag |
|------|------|-----|
| 1. API key | `01-api-key` | `v2-api-key` |
| 2. JWT | `02-jwt` | `v3-jwt` |
| 3. BOLA/IDOR y roles | `03-bola-roles` | `v4-bola-roles` |
| 4. Rate limiter | `04-rate-limiter` | `v5-rate-limiter` |

Después se agregó la tienda web y los endpoints que usa (`05-ui-tienda`, `v6-ui-tienda`).

## Cómo correrla

```bash
composer install
cp .env.example .env && php artisan key:generate
# en .env: datos de PostgreSQL, API_KEY (openssl rand -hex 24) y JWT_SECRET (openssl rand -hex 32)
php artisan migrate:fresh --seed
php artisan serve                      # tienda en http://127.0.0.1:8000
```

Usuarios de prueba (contraseña `password`): `admin@tienda.com`, `cliente1@tienda.com` (pedidos 1 y 2),
`cliente2@tienda.com` (pedidos 3 y 4). Colección de Postman: `docs/tienda-api.postman_collection.json`.
Despliegue en Ubuntu con Cloudflare Tunnel: `docs/DEPLOY.md`.

---

## Paso 1 — API key

**Protege contra:** clientes anónimos y bots que no conocen la llave: sin ella toda la API responde `401`.

**Qué se cambió**
- `app/Http/Middleware/ValidateApiKey.php` (nuevo): compara el header `X-API-KEY` con `config('services.api_key')` usando
  `hash_equals` (tiempo constante). Falta o es incorrecta: `401`. Si el servidor no tiene `API_KEY`: `500` (nunca deja
  pasar por omisión).
- `bootstrap/app.php`: `$middleware->api(prepend: [ValidateApiKey::class])`. Va en el grupo `api`, así protege **todas**
  las rutas `/api` y corre antes que cualquier otro middleware.
- `config/services.php`: `'api_key' => env('API_KEY')`.
- `.env` y `.env.example`: variable `API_KEY`. La llave no está escrita en ningún archivo del repo.
- Frontend: manda `X-API-KEY` en cada `fetch()`. La toma de un `<meta name="api-key">` que se llena desde el `.env`.
  Como cualquiera puede verla en "ver código fuente", la API key **identifica a la aplicación, no es un secreto**: la
  seguridad real la dan los pasos 2, 3 y 4. Mejora futura: un BFF para que la llave nunca llegue al navegador.

## Paso 2 — JWT

**Protege contra:** acceso sin autenticar, tokens falsificados o modificados, y reuso de tokens viejos o robados.

**Qué se cambió**
- `composer require firebase/php-jwt` (sin Sanctum ni Passport).
- `config/jwt.php` (nuevo) y `.env`: `JWT_SECRET` (mínimo 32 caracteres) y `JWT_TTL` (minutos, 30 por omisión).
- `app/Services/JwtService.php` (nuevo):
  - Emite el token HS256 con `sub`, `rol`, `iat`, `exp` y `jti` (uuid único).
  - Decodifica con el algoritmo fijado en el servidor (`alg: none` o cambiar de algoritmo no sirve).
  - Revoca: mete el `jti` en una lista negra en la caché (`CACHE_STORE=database`) hasta que el token expire.
- `app/Http/Middleware/AuthenticateJwt.php` (nuevo): lee `Authorization: Bearer`, valida firma y vigencia, exige `sub`,
  `jti` y `exp`, revisa la lista negra y que el usuario exista. Token ausente, expirado, inválido o revocado: `401`.
- `app/Http/Controllers/AuthController.php`: `login` (y `register`) regresan el token; `logout` revoca el `jti` de
  **esa** sesión; `perfil` usa al usuario del token (ya no recibe `user_id`).
- `routes/api.php` y `bootstrap/app.php`: alias `jwt` y grupo `jwt` para todo menos `POST /login`, `POST /register` y
  `GET /productos`.
- Frontend: guarda el token en `sessionStorage` y lo manda como `Authorization: Bearer`.

## Paso 3 — BOLA/IDOR y roles

**Protege contra:** que un usuario con token válido vea o cree datos de **otra persona** cambiando un id o el
`user_id`, y que un cliente use funciones de administrador.

**Qué se cambió**
- `app/Policies/PedidoPolicy.php` (nuevo): `view` deja pasar al dueño del pedido o a un admin; otro usuario recibe `403`.
- `PedidoController`:
  - `show` llama a `Gate::authorize('view', $pedido)`.
  - `index` (Mis pedidos) filtra por dueño dentro de la consulta; el admin recibe todos.
  - `store` **ignora** el `user_id` del body: el pedido se crea siempre a nombre del dueño del token.
- `app/Http/Middleware/RequireRole.php` (nuevo, alias `rol`): `->middleware('rol:admin')` protege crear, editar y
  eliminar productos, cambiar el estado de pedidos y listar usuarios. Un cliente recibe `403`. El rol se lee de la
  **base de datos**, no del claim `rol` del token, así que un token con el claim cambiado no sirve.
- `bootstrap/app.php`: el rol se revisa **antes** del route model binding, para que un cliente reciba `403` exista o no
  el id (no puede "tantear" qué ids existen).
- Roles: columna `rol` (`cliente` o `admin`) en `users`, sin paquetes. `rol` no es asignable en masa y el registro
  siempre crea `cliente` aunque el body diga `"rol":"admin"`.

## Paso 4 — Rate limiter

**Protege contra:** fuerza bruta al login, abuso y scraping de la API, y spam de pedidos.

**Qué se cambió**
- `app/Providers/AppServiceProvider.php`: tres limitadores con `RateLimiter::for`, **ninguno usa la IP** (con
  Cloudflare Tunnel todas las peticiones llegan desde `127.0.0.1`):

  | Limitador | Límite | Llave | Dónde |
  |-----------|--------|-------|-------|
  | `login` | 5 por minuto | email + API key | `POST /api/login` |
  | `api` | 60 por minuto | usuario del token; sin token válido, la API key | el resto de la API |
  | `pedidos` | 3 por minuto | usuario del token | `POST /api/pedidos` |

  Al pasarse responde `429` en JSON (`message` y `retry_after`) con el header `Retry-After`.
- `app/Services/JwtService.php`: `usuarioDeToken()` saca el usuario del JWT verificando firma y vigencia, así un token
  falso no gasta la cuota de otra persona.
- `routes/api.php`: `throttle:login` y `throttle:pedidos` en esas rutas, con `withoutMiddleware('throttle:api')`
  (cada ruta usa un solo limitador).
- `bootstrap/app.php`: `throttleApi()` para el límite general y el orden final de middleware:
  API key → rate limiter → JWT → rol → route model binding. También `trustProxies(at: '*')` para que Laravel use
  `https` detrás de Cloudflare.
- Los contadores viven en la caché (`CACHE_STORE=database`): `php artisan cache:clear` los reinicia.
