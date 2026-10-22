# Tienda API — Seguridad de API con Laravel

Tarea de Seguridad Informática (UPQ). Mini tienda de aparatos electrónicos (**TechStore**): API REST en
**Laravel 13 + PostgreSQL** con una tienda web en Blade + `fetch()` (sin Vite ni npm): registro e inicio de sesión,
catálogo con búsqueda, carrito, "Mis pedidos" y panel de administración.

El proyecto se construye por etapas. Cada concepto de seguridad vive en su propia rama, se une a `master` con
`git merge --no-ff` y queda marcado con un tag, así que se puede viajar entre etapas con `git checkout <tag>`:

| Tag | Rama | Qué agrega |
|-----|------|------------|
| `v1-base` | `master` | API base y frontend, funcional pero **vulnerable a propósito** |
| `v2-api-key` | `01-api-key` | API key: header `X-API-KEY` obligatorio en toda la API |
| `v3-jwt` | `02-jwt` | JWT: login con token firmado, expiración y revocación en el logout |
| `v4-bola-roles` | `03-bola-roles` | BOLA/IDOR (`PedidoPolicy`), `user_id` tomado del token y roles (`rol:admin`) |
| `v5-rate-limiter` | `04-rate-limiter` | Rate limiter por usuario/API key (nunca por IP) con respuesta `429` en JSON y `Retry-After` |
| `v6-ui-tienda` | `05-ui-tienda` | Tienda web (TechStore) y API ampliada: registro, categorías, búsqueda, dirección de envío, "Mis pedidos" y panel admin |

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

Genera la API key que van a usar los clientes (header `X-API-KEY`) y ponla en el `.env`:

```bash
openssl rand -hex 24      # copia el resultado en API_KEY=
```

Genera también la llave con la que se firman los JWT (`JWT_SECRET`, mínimo 32 caracteres) y revisa `JWT_TTL`
(minutos de vida de cada token, 30 por omisión):

```bash
openssl rand -hex 32      # copia el resultado en JWT_SECRET=
```

Crea las tablas con datos de prueba y levanta el servidor:

```bash
php artisan migrate:fresh --seed
php artisan serve        # http://127.0.0.1:8000
```

La tienda web está en `http://127.0.0.1:8000/` y la API en `http://127.0.0.1:8000/api`.
Los ejemplos de abajo usan `curl` y `jq` (opcional, solo para ver el JSON bonito).

### Usuarios de prueba

Contraseña de todos: `password`.

| Email | Rol | Pedidos |
|-------|-----|---------|
| `admin@tienda.com` | `admin` | — |
| `cliente1@tienda.com` | `cliente` | 1 (enviado) y 2 (pendiente) |
| `cliente2@tienda.com` | `cliente` | 3 (pagado) y 4 (cancelado) |

Además hay 12 productos electrónicos en 7 categorías (ids 1 a 12). También se puede crear una cuenta nueva desde
"Regístrate aquí". `migrate:fresh --seed` deja todo como al inicio.

### Endpoints

| Método | Ruta | Qué hace | Acceso | Límite |
|--------|------|----------|--------|--------|
| POST | `/api/login` | Valida credenciales y regresa un JWT y el usuario | API key | `login`: 5/min por email + API key |
| POST | `/api/register` | Crea una cuenta de cliente y regresa un JWT | API key | `api`: 60/min |
| POST | `/api/logout` | Revoca el token que se usó en la petición | API key + JWT | `api`: 60/min |
| GET | `/api/perfil` | Datos del dueño del token | API key + JWT | `api`: 60/min |
| GET | `/api/productos?buscar=&categoria=` | Catálogo, con búsqueda y filtro por categoría | API key | `api`: 60/min |
| POST | `/api/productos` | Crea un producto | API key + JWT + rol `admin` | `api`: 60/min |
| PUT | `/api/productos/{id}` | Edita un producto | API key + JWT + rol `admin` | `api`: 60/min |
| DELETE | `/api/productos/{id}` | Elimina un producto (`409` si ya está en pedidos) | API key + JWT + rol `admin` | `api`: 60/min |
| GET | `/api/pedidos` | "Mis pedidos" (el admin recibe los de todos) | API key + JWT | `api`: 60/min |
| GET | `/api/pedidos/{id}` | Detalle de un pedido con sus productos | API key + JWT (solo el dueño o un admin) | `api`: 60/min |
| POST | `/api/pedidos` | Crea un pedido (`direccion_envio` + `items`) a nombre del dueño del token | API key + JWT | `pedidos`: 3/min por usuario |
| PATCH | `/api/pedidos/{id}/estado` | Cambia el estado del pedido | API key + JWT + rol `admin` | `api`: 60/min |
| GET | `/api/usuarios` | Lista de usuarios | API key + JWT + rol `admin` | `api`: 60/min |

El límite `api` es por usuario del token (o por API key si no hay token válido). Ningún límite usa la IP.

Las rutas de `routes/api.php` se registran a mano en `bootstrap/app.php` (no se usó `php artisan install:api`,
así que no hay Sanctum ni Passport).

### Despliegue en una VM Ubuntu con Cloudflare Tunnel

Los pasos exactos (paquetes, PostgreSQL, `.env` con todas las variables, `cloudflared`) están en
[`docs/DEPLOY.md`](docs/DEPLOY.md). Con el túnel, todas las peticiones llegan a Laravel desde `127.0.0.1`: por eso el
rate limiter usa el usuario o la API key y nunca la IP, y `bootstrap/app.php` confía en `X-Forwarded-Proto/Host`
(`trustProxies(at: '*')`) para generar enlaces `https`.

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

Estos comandos son de la etapa 1 (`git checkout v1-base`). Desde la etapa 2 hay que agregar
`-H 'X-API-KEY: TU_API_KEY'` a cada uno, si no responden `401`.

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

Haz `git checkout v1-base` (ese tag trae la pantalla técnica de la etapa 1; la tienda actual está en `master`). Al
terminar, `git checkout master`.

1. Abre `http://127.0.0.1:8000/`: se ve la lista de productos y la etiqueta **v1-base · sin seguridad**.
2. Entra como `cliente1` y consulta el pedido `1` (suyo) con "Ver pedido por id".
3. Cambia el id a `3` (de `cliente2`): **se ve completo**. Eso es BOLA/IDOR.
4. En "Crear pedido" cambia el `user_id` a `3`: el pedido se crea a nombre de otra persona.
5. Crea un producto desde el formulario aunque seas un simple cliente: devuelve `201`.
6. Corre el bucle de 100 peticiones del punto 5: todas responden `200`, nadie frena nada.

En las siguientes etapas se repiten estos mismos pasos y cada uno empieza a fallar con `401`, `403` o `429`.

---

## Etapa 2 — API key (`v2-api-key`, rama `01-api-key`)

### Qué ataque previene

El acceso anónimo a la API: bots, scrapers y cualquier programa que no sea un cliente autorizado ya no llegan a
ningún endpoint; sin la llave reciben `401` antes de que se ejecute cualquier lógica.

Una API key identifica a la **aplicación cliente**, no a la persona. No sustituye al login (eso es el JWT de la
etapa 3) y tiene límites que conviene decir en la exposición:

- Es un secreto compartido: si se filtra, vale para todos. Se rota cambiando `API_KEY` en el `.env`.
- En un navegador no puede ser secreta. La tienda web la manda sola: va en un `<meta name="api-key">` de la página.
  Sale de `API_KEY` en el `.env` (con `config('services.api_key')`; no está escrita en ningún archivo del repo) y
  cualquiera que abra la tienda la ve en "ver código fuente". Por eso la API key **identifica a la aplicación, no es
  un secreto ni una contraseña**: frena a bots y clientes que no la conocen, pero la seguridad real la dan el JWT,
  los roles y el rate limiter. (En el tag `v2-api-key` la pantalla tenía un campo para pegarla.)
- Mejora futura: un **BFF** (*backend for frontend*): rutas propias de la web que llaman a la API por dentro, con la
  llave guardada solo en el servidor, para que nunca llegue al navegador.

### Cómo funciona

1. El cliente manda el header `X-API-KEY: <llave>`.
2. El middleware `ValidateApiKey` lo compara con `config('services.api_key')` (que sale de `API_KEY` en el `.env`)
   con `hash_equals`, una comparación en tiempo constante que no filtra la llave por tiempos de respuesta.
3. Falta o no coincide: `401` en JSON. Si el servidor no tiene `API_KEY` configurada: `500`. Falla cerrado, nunca
   deja pasar a nadie por omisión.
4. Se registra en el grupo `api` de `bootstrap/app.php`, así que protege **todas** las rutas `/api`, también las
   que se agreguen después, y corre antes que cualquier otro middleware del grupo.

### Archivos que se tocaron

- `app/Http/Middleware/ValidateApiKey.php` (nuevo): el middleware.
- `bootstrap/app.php`: `$middleware->api(prepend: [ValidateApiKey::class])`.
- `config/services.php`: `'api_key' => env('API_KEY')`.
- `.env` y `.env.example`: variable `API_KEY`.
- `resources/views/tienda.blade.php`: el frontend manda el header `X-API-KEY` en cada `fetch()`.

### Pruebas con curl (falla vs. pasa)

Reemplaza `TU_API_KEY` por el valor de `API_KEY` de tu `.env`.

```bash
# FALLA: sin API key  ->  401 {"message":"API key ausente o inválida."}
curl -i http://127.0.0.1:8000/api/productos

# FALLA: API key incorrecta  ->  401
curl -i -H 'X-API-KEY: clave-incorrecta' http://127.0.0.1:8000/api/productos

# FALLA: el login tampoco se salva  ->  401
curl -i -X POST http://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"cliente1@tienda.com","password":"password"}'

# PASA: API key correcta  ->  200 con la lista de productos
curl -i -H 'X-API-KEY: TU_API_KEY' http://127.0.0.1:8000/api/productos

# PASA: login con la key  ->  200 con el usuario
curl -s -X POST http://127.0.0.1:8000/api/login \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"email":"cliente1@tienda.com","password":"password"}' | jq
```

La llave se revisa **antes** que todo lo demás: pedir un pedido que no existe responde `401` sin llave y `404` con
llave, así que sin la llave ni siquiera se puede "tantear" qué hay en la API.

### Cómo demostrarlo en vivo

1. En Postman, carpeta "1 · API key": `Productos · sin API key` da `401`, `API key incorrecta` da `401` y con la llave
   correcta da `200`.
2. Abre la tienda: carga sola porque el navegador manda la llave. Muestra "ver código fuente": ahí está el
   `<meta name="api-key">`. Explica por qué la API key identifica a la app y no es un secreto.
3. Muestra el código: una línea en `bootstrap/app.php` y los 15 renglones de `ValidateApiKey`.

---

## Etapa 3 — JWT (`v3-jwt`, rama `02-jwt`)

### Qué ataque previene

- **Acceso sin autenticar** a las rutas privadas: sin un token válido responden `401`. La API key identifica a la
  *aplicación*; el JWT identifica a la *persona*.
- **Falsificar o modificar el token** (cambiar `sub` o `rol` para hacerse pasar por el admin): va firmado con HS256 y
  `JWT_SECRET`, si se altera un solo carácter la firma deja de coincidir y responde `401`.
- **Reusar un token viejo o robado**: cada token vence (`exp`) y se puede revocar antes con el logout.
- **Ataques al algoritmo** (`alg: none`, cambiar a otro algoritmo): el algoritmo lo fija el servidor, no el header
  del token.

Lo que **todavía no** resuelve: un cliente autenticado puede ver pedidos de otros y crear pedidos con el `user_id`
de otra persona, y cualquier usuario con token puede crear productos. Saber *quién* eres no es lo mismo que decidir
*qué puedes hacer*: eso se arregla en la etapa 4.

### Cómo funciona

1. `POST /api/login` valida email y contraseña y regresa un JWT con estos claims:

   | Claim | Valor |
   |-------|-------|
   | `sub` | id del usuario (como texto, como pide el estándar) |
   | `rol` | `cliente` o `admin` (informativo) |
   | `iat` | momento en que se emitió |
   | `exp` | `iat` + `JWT_TTL` minutos (30 por omisión) |
   | `jti` | uuid único por token |

2. El cliente lo manda en cada petición: `Authorization: Bearer <token>`.
3. El middleware `AuthenticateJwt` revisa en orden: que haya token; firma y vigencia (`JWT::decode` con HS256 fijo);
   que existan `sub`, `jti` y `exp`; que el `jti` no esté en la lista negra; y que el usuario siga existiendo.
   Cualquier fallo es un `401` con su mensaje: `Falta el token`, `Token expirado.`, `Token inválido.` o `Token revocado.`
4. `POST /api/logout` mete el `jti` en una lista negra en la caché (`CACHE_STORE=database`) hasta la hora en que el
   token iba a expirar. Cada login genera un `jti` nuevo, así que se revoca **solo esa sesión**: las demás sesiones
   del mismo usuario siguen vivas.
5. `GET /api/perfil` ya no recibe `user_id`: usa al usuario del token.
6. El frontend guarda el token en `sessionStorage` (se borra al cerrar la pestaña) y lo manda en cada `fetch()`.
   Todo lo que viene de la API se escapa antes de pintarlo (`escapeHtml`) para que un XSS no pueda robar el token.

Detalles que conviene mencionar:

- `firebase/php-jwt` acepta tokens sin `exp`; el middleware los rechaza porque `exp` es obligatorio.
- El middleware se ejecuta antes del route model binding: sin token, pedir un pedido que no existe responde `401`,
  no `404` (así no se puede "tantear" qué pedidos existen).
- `php artisan cache:clear` (y `migrate:fresh`) vacía también la lista negra: los tokens revocados vuelven a valer
  hasta su `exp`.

### Archivos que se tocaron

- `composer.json` y `composer.lock`: `firebase/php-jwt`.
- `config/jwt.php` (nuevo): `JWT_SECRET` y `JWT_TTL`.
- `app/Services/JwtService.php` (nuevo): emite, decodifica, revoca y consulta la lista negra.
- `app/Http/Middleware/AuthenticateJwt.php` (nuevo): el middleware.
- `app/Http/Controllers/AuthController.php`: `login` regresa el token, nuevo `logout`, `perfil` usa el token.
- `routes/api.php`: grupo `jwt` para todo menos `POST /login` y `GET /productos`.
- `bootstrap/app.php`: alias `jwt`.
- `.env` y `.env.example`: `JWT_SECRET` y `JWT_TTL`.
- `resources/views/tienda.blade.php`: guarda el token, lo manda como `Bearer`, botón de logout y tarjeta con los claims.

### Pruebas con curl (falla vs. pasa)

Reemplaza `TU_API_KEY` por el valor de `API_KEY` de tu `.env`.

```bash
# Login: guarda el token en una variable
#   bash / zsh:
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/login \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"email":"cliente1@tienda.com","password":"password"}' | jq -r .token)
#   fish:
set TOKEN (curl -s -X POST http://127.0.0.1:8000/api/login \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"email":"cliente1@tienda.com","password":"password"}' | jq -r .token)

# FALLA: ruta privada sin token  ->  401 "Falta el token ..."
curl -i -H 'X-API-KEY: TU_API_KEY' http://127.0.0.1:8000/api/perfil

# FALLA: token falsificado (dice ser el admin, firma inventada)  ->  401 "Token inválido."
curl -i -H 'X-API-KEY: TU_API_KEY' \
  -H 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIiwicm9sIjoiYWRtaW4ifQ.firma-falsa' \
  http://127.0.0.1:8000/api/perfil

# FALLA: crear un pedido sin token  ->  401
curl -i -X POST http://127.0.0.1:8000/api/pedidos \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"direccion_envio":"Av. Universidad 123, Querétaro","items":[{"producto_id":6,"cantidad":1}]}'

# PASA: con el token  ->  200 con el usuario DEL TOKEN (aunque pidas ?user_id=1 lo ignora)
curl -s -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" \
  'http://127.0.0.1:8000/api/perfil?user_id=1' | jq

# PASA: crear un pedido con el token  ->  201
curl -s -X POST http://127.0.0.1:8000/api/pedidos \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"direccion_envio":"Av. Universidad 123, Querétaro","items":[{"producto_id":6,"cantidad":1}]}' | jq

# Logout  ->  200, el jti del token entra a la lista negra
curl -s -X POST -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" \
  http://127.0.0.1:8000/api/logout | jq

# FALLA: el mismo token después del logout  ->  401 "Token revocado."
curl -i -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/perfil
```

Token expirado: pon `JWT_TTL=1` en el `.env` (el servidor se reinicia solo al cambiar el `.env`), haz login, espera
61 segundos y repite el `GET /api/perfil`: `401 "Token expirado."`.

### Cómo demostrarlo en vivo

1. En la tienda, sin sesión solo existe la pantalla de inicio de sesión: no se ve nada del catálogo ni de pedidos.
   Entra como `cliente1` y se abre el catálogo.
2. En Postman, carpeta "2 · JWT", en orden: `Login cliente1` (guarda el token), `Perfil · token válido` (`200`),
   `Perfil · sin token` (`401 Falta el token`), `Perfil · token falsificado` (`401 Token inválido`). Pega el token en
   jwt.io para mostrar `sub`, `rol`, `iat`, `exp` y `jti`.
3. `Logout` (`200`) y luego `Perfil · token revocado` (`401 Token revocado`). En la tienda, "Cerrar sesión" hace lo
   mismo: revoca el token en el servidor. Para ver la lista negra: `select key from cache where key like '%jwt:revocado%';`.
4. Entra con el mismo usuario en otra ventana: su sesión no se afecta por el logout de la primera (revocación por sesión).
5. Código para mostrar: `AuthenticateJwt` (los pasos en orden) y `JwtService`.

---

## Etapa 4 — BOLA/IDOR y roles (`v4-bola-roles`, rama `03-bola-roles`)

### Qué ataque previene

- **BOLA / IDOR** (*Broken Object Level Authorization*, #1 del OWASP API Security Top 10): el atacante ya tiene un
  token válido, pero cambia el id de un objeto en la URL (`/api/pedidos/3`) o un campo del body (`user_id`) para leer
  o crear datos de **otra persona**. Autenticarse (etapa 3) no basta: hay que comprobar, en cada acceso, que el
  objeto pertenezca a quien lo pide.
- **Escalada de privilegios** (*BFLA*, *Broken Function Level Authorization*): un cliente usando una función de
  administrador, como crear productos.

### Cómo funciona

1. `PedidoPolicy::view`: el admin puede ver cualquier pedido; un cliente solo si `pedido->user_id` es el suyo. Si no,
   `403` con el mensaje "No tienes permiso para ver este pedido.".
2. `PedidoController::show` llama a `Gate::authorize('view', $pedido)`. El route model binding ya cargó el pedido, así
   que la comprobación se hace sobre el objeto real, no sobre el id que mandó el cliente.
3. `PedidoController::store` **ignora** el `user_id` del body: el pedido se crea siempre a nombre de
   `$request->user()`, el dueño del token.
4. El middleware `RequireRole` (`rol:admin`) protege `POST /api/productos`: cualquier otro rol recibe `403`. Lee el
   rol del usuario en la **base de datos**, no del claim `rol` del token, así que un cambio de rol (por ejemplo,
   degradar a un admin) surte efecto de inmediato aunque su token siga vigente, y un claim inventado no sirve de nada.
5. Códigos: `401` = no sé quién eres (falta o falla el token), `403` = sé quién eres pero no puedes hacer esto,
   `404` = no existe.

Notas que conviene mencionar:

- Un `403` confirma que el pedido existe. Para no revelarlo se puede responder `404` con `Response::denyAsNotFound()`
  en la policy; aquí se deja `403` porque es lo que se quiere demostrar.
- Los ids secuenciales (1, 2, 3...) facilitan la enumeración. Usar UUID ayuda, pero **no** sustituye a la policy.
- La columna `rol` no está en el `#[Fillable]` de `User`, así que no se puede asignar por mass assignment.

### Archivos que se tocaron

- `app/Policies/PedidoPolicy.php` (nuevo): `view`. Laravel la descubre sola por el nombre.
- `app/Http/Middleware/RequireRole.php` (nuevo): `rol:admin`.
- `app/Http/Controllers/PedidoController.php`: `Gate::authorize` en `show`; `store` usa el usuario del token.
- `routes/api.php`: `->middleware('rol:admin')` en `POST /productos`.
- `bootstrap/app.php`: alias `rol`.
- `resources/views/tienda.blade.php`: se quitó el campo `user_id` de "Crear pedido".

### Pruebas con curl (falla vs. pasa)

Reemplaza `TU_API_KEY` por el valor de `API_KEY` de tu `.env`. Primero guarda un token por cada usuario:

```bash
# bash / zsh
tienda_login() { curl -s -X POST http://127.0.0.1:8000/api/login -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' -d "{\"email\":\"$1\",\"password\":\"password\"}" | jq -r .token; }
TOKEN1=$(tienda_login cliente1@tienda.com); TOKEN2=$(tienda_login cliente2@tienda.com); ADMIN=$(tienda_login admin@tienda.com)
```

```fish
# fish
function tienda_login; curl -s -X POST http://127.0.0.1:8000/api/login -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' -d "{\"email\":\"$argv[1]\",\"password\":\"password\"}" | jq -r .token; end
set TOKEN1 (tienda_login cliente1@tienda.com); set TOKEN2 (tienda_login cliente2@tienda.com); set ADMIN (tienda_login admin@tienda.com)
```

```bash
# FALLA (BOLA bloqueado): cliente1 pide el pedido 3, que es de cliente2  ->  403
curl -i -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" http://127.0.0.1:8000/api/pedidos/3

# FALLA: cliente2 pide el pedido 1, que es de cliente1  ->  403
curl -i -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN2" http://127.0.0.1:8000/api/pedidos/1

# PASA: cliente1 pide su propio pedido 1  ->  200
curl -s -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" http://127.0.0.1:8000/api/pedidos/1 | jq

# PASA: el admin puede ver el pedido 3  ->  200
curl -s -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $ADMIN" http://127.0.0.1:8000/api/pedidos/3 | jq

# FALLA: un cliente intenta crear un producto  ->  403 "No tienes permiso para esta acción."
curl -i -X POST http://127.0.0.1:8000/api/productos \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" -H 'Content-Type: application/json' \
  -d '{"nombre":"Webcam HD","descripcion":"1080p","categoria":"Periféricos","precio":599,"stock":12}'

# PASA: el admin crea el producto  ->  201
curl -s -X POST http://127.0.0.1:8000/api/productos \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $ADMIN" -H 'Content-Type: application/json' \
  -d '{"nombre":"Webcam HD","descripcion":"1080p","categoria":"Periféricos","precio":599,"stock":12}' | jq

# Ataque neutralizado: cliente1 manda user_id 3 en el body, pero el pedido queda a su nombre  ->  {"id":5,"user_id":2}
curl -s -X POST http://127.0.0.1:8000/api/pedidos \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" -H 'Content-Type: application/json' \
  -d '{"user_id":3,"direccion_envio":"Av. Universidad 123, Querétaro","items":[{"producto_id":6,"cantidad":1}]}' | jq -c '{id, user_id}'
```

### Cómo demostrarlo en vivo

1. En la tienda entra como `cliente1`: "Mis pedidos" muestra solo sus 2 pedidos y no existe la pestaña
   Administración. Cierra sesión y entra como `admin`: aparece **Administración** con los pedidos de todos los clientes
   y la lista de usuarios. Aunque la pestaña esté oculta para los clientes, quien decide es el servidor.
2. En Postman, carpeta "3 · BOLA/IDOR y roles", en orden: `Pedido 1 · propio` (`200`), `Pedido 3 · de otro usuario`
   (**`403`**; en la etapa 1 mostraba el pedido completo de otra persona), `Crear pedido` con `user_id` ajeno en el
   body (`201`, queda a nombre del token), `Crear producto · cliente` (`403`), y con el admin: `Pedido 3` (`200`) y
   `Crear producto` (`201`).
3. Código para mostrar: `PedidoPolicy`, la línea `Gate::authorize('view', $pedido)` y `RequireRole`.
4. Extra: el rol sale de la base de datos. Con un token de admin vigente, ejecuta
   `update users set rol = 'cliente' where email = 'admin@tienda.com';` y repite `POST /api/productos`: ahora da
   `403`. Para dejarlo como estaba: `update users set rol = 'admin' where email = 'admin@tienda.com';`.

---

## Etapa 5 — Rate limiter (`v5-rate-limiter`, rama `04-rate-limiter`)

### Qué ataque previene

- **Fuerza bruta y credential stuffing** contra `POST /api/login`: sin límite, un script prueba miles de contraseñas
  por minuto. Con el límite solo tiene 5 intentos por minuto por cuenta.
- **Abuso de recursos, scraping y DoS a nivel de aplicación** (*Unrestricted Resource Consumption*, API4 del OWASP API
  Security Top 10): 60 peticiones por minuto por usuario.
- **Spam de operaciones costosas**: cada pedido toca inventario y base de datos, así que solo se permiten 3 por minuto
  por usuario.
- Un cliente que se descontrola no afecta a los demás: cada usuario tiene su propio contador.

### Por qué la llave NO es la IP

La API se expone con Cloudflare Tunnel (`cloudflared tunnel --url http://localhost:8000`), así que **todas las
peticiones llegan a Laravel desde `127.0.0.1`**. Un límite por IP metería a todos los usuarios en el mismo contador y
uno solo bloquearía a los demás. Por eso las llaves son:

| Limitador | Límite | Llave | Dónde se aplica |
|-----------|--------|-------|-----------------|
| `login` | 5 por minuto | email + API key | `POST /api/login` |
| `api` | 60 por minuto | usuario del token; si no hay token válido, la API key | todas las demás rutas |
| `pedidos` | 3 por minuto | usuario del token | `POST /api/pedidos` |

Cada ruta usa **un solo** limitador: `login` y `pedidos` sustituyen al general con `withoutMiddleware('throttle:api')`.

### Cómo funciona

1. Los tres limitadores se definen con `RateLimiter::for(...)` en `AppServiceProvider::boot()`. Cada uno regresa
   `Limit::perMinute(N)->by(llave)`. El general se aplica a toda la API con `$middleware->throttleApi()`.
2. Al pasarse del límite responde `429` en **JSON** con el header `Retry-After` (`->response(...)` de `Limit`):

   ```http
   HTTP/1.1 429 Too Many Requests
   Retry-After: 41
   X-RateLimit-Limit: 5
   X-RateLimit-Remaining: 0

   {"message":"Demasiados intentos de inicio de sesión. Intenta de nuevo en 41 segundos.","retry_after":41}
   ```

   Las respuestas normales también traen `X-RateLimit-Limit` y `X-RateLimit-Remaining`.
3. "Usuario del token" sale de decodificar el JWT **verificando firma y vigencia** (`JwtService::usuarioDeToken`). Un
   token falso no sirve para gastar la cuota de otra persona; cae en el contador de la API key.
4. Orden de los middleware (`bootstrap/app.php`): **API key → rate limiter → JWT → route model binding → rol**.
   - La API key va primero: una llave falsa se rechaza sin crear contadores en la caché.
   - El límite va antes del JWT: también cuentan las peticiones sin token o con token malo (por API key), no solo las
     autenticadas. Por eso `GET /api/perfil` sin token da `401` y, pasadas 60, `429`.
5. Los contadores viven en la caché (`CACHE_STORE=database`, tabla `cache`). `php artisan cache:clear` los reinicia
   (también vacía la lista negra de JWT).

### Limitaciones que conviene conocer

- Todo el tráfico **anónimo** comparte un solo contador (la API key): un cliente anónimo ruidoso puede dejar sin
  respuesta a los demás anónimos hasta por un minuto. Quien manda un token válido no se ve afectado.
- El límite de login es **por cuenta**: quien prueba muchos emails distintos tiene 5 intentos por cada uno.
- El rate limiter de Laravel revisa y luego cuenta: con peticiones simultáneas pueden colarse unas pocas de más
  (en una prueba con 100 peticiones en paralelo y límite 60 pasaron 62).
- Para limitar por la IP real detrás de Cloudflare habría que confiar en `CF-Connecting-IP` (con `TrustProxies`).
  No se hace porque el requisito es no usar la IP.

### Archivos que se tocaron

- `app/Providers/AppServiceProvider.php`: los tres limitadores y la respuesta `429` en JSON.
- `routes/api.php`: `throttle:login`, `throttle:pedidos` y `withoutMiddleware('throttle:api')` en esas dos rutas.
- `bootstrap/app.php`: `throttleApi()` y el orden de los middleware (`appendToPriorityList`).
- `app/Services/JwtService.php`: `usuarioDeToken()`, la llave por usuario del limitador.
- `app/Http/Middleware/AuthenticateJwt.php`: ya no implementa `AuthenticatesRequests`; el orden se declara en `bootstrap/app.php`.
- `resources/views/tienda.blade.php`: avisos con el mensaje del `429` (pantalla técnica de esa etapa; la tienda actual
  muestra el mismo mensaje en un aviso).
- `docs/tienda-api.postman_collection.json` (nuevo): colección de Postman.

### Pruebas con curl (falla vs. pasa)

Reemplaza `TU_API_KEY` por tu `API_KEY`. Primero guarda `TOKEN1` y `TOKEN2` como en la etapa 4 y **después** reinicia
los contadores con `php artisan cache:clear`, para que esos logins de preparación no cuenten. El `?[1-6]` es un rango
de `curl` que repite la petición 6 veces (sirve igual en bash, zsh y fish).

```bash
# FALLA: 6 logins seguidos con contraseña incorrecta  ->  401 x5 y 429 x1
curl -s -o /dev/null -w '%{http_code}\n' -X POST 'http://127.0.0.1:8000/api/login?[1-6]' \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"email":"cliente1@tienda.com","password":"incorrecta"}'

# FALLA: aunque ahora la contraseña sea correcta, cliente1 sigue bloqueado un minuto  ->  429 con Retry-After
curl -i -X POST http://127.0.0.1:8000/api/login \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"email":"cliente1@tienda.com","password":"password"}'

# PASA: otro usuario, desde la misma IP, entra sin problema  ->  200
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://127.0.0.1:8000/api/login \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"email":"cliente2@tienda.com","password":"password"}'

# FALLA: 4 pedidos seguidos de cliente1  ->  201 201 201 429
curl -s -o /dev/null -w '%{http_code}\n' -X POST 'http://127.0.0.1:8000/api/pedidos?[1-4]' \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" -H 'Content-Type: application/json' \
  -d '{"direccion_envio":"Av. Universidad 123, Querétaro","items":[{"producto_id":6,"cantidad":1}]}'

# PASA: cliente2 (misma IP) todavía puede crear su pedido  ->  201
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://127.0.0.1:8000/api/pedidos \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN2" -H 'Content-Type: application/json' \
  -d '{"direccion_envio":"Av. Universidad 123, Querétaro","items":[{"producto_id":6,"cantidad":1}]}'

# FALLA: 65 peticiones seguidas de cliente1 a la API  ->  60 x 200 y 5 x 429
curl -s -o /dev/null -w '%{http_code}\n' -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" \
  'http://127.0.0.1:8000/api/productos?[1-65]' | sort | uniq -c

# FALLA: sin token también cuenta (por API key): 62 peticiones a una ruta privada  ->  60 x 401 y 2 x 429
curl -s -o /dev/null -w '%{http_code}\n' -H 'X-API-KEY: TU_API_KEY' \
  'http://127.0.0.1:8000/api/perfil?[1-62]' | sort | uniq -c
```

### Colección de Postman

`docs/tienda-api.postman_collection.json` trae todos los endpoints en 5 carpetas (una por concepto y una para la
tienda) y cada request incluye tests que verifican el código esperado (`401`, `403`, `429`...).

1. Postman → **Import** → el archivo.
2. En las variables de la colección: `base_url` (por omisión `http://127.0.0.1:8000`; con el túnel, la URL
   `https://....trycloudflare.com` sin `/` al final) y `api_key` (la que viene es solo un ejemplo: pon la de tu `.env`).
   `token`, `user_id` y `producto_id` se llenan solas.
3. **Run collection** en orden: todo debe salir en verde. Los 3 requests de la carpeta "4 · Rate limiter" son para
   repetirlos (Runner con 6, 4 y 61 iteraciones) hasta ver el `429`.

### Cómo demostrarlo en vivo

1. Antes de empezar: `php artisan cache:clear` para dejar los contadores en cero.
2. En la pantalla de inicio de sesión escribe la contraseña mal **6 veces** seguidas: del sexto intento aparece el
   aviso "Demasiados intentos de inicio de sesión. Intenta de nuevo en N segundos."
3. Entra como `cliente1`, agrega algo al carrito y confirma el pedido **4 veces** seguidas: el cuarto avisa "Demasiados
   pedidos seguidos..." y el carrito no se pierde.
4. **No usa la IP**: con `cliente1` ya bloqueado, abre otra ventana (incógnito) y entra como `cliente2`: puede comprar
   aunque toda la conexión llegue desde `127.0.0.1` por el túnel.
5. En Postman, carpeta "4 · Rate limiter" (Runner con 6, 4 y 61 iteraciones): `429` en JSON con el header `Retry-After`.
6. Los contadores están en la base: `select key, expiration from cache;` (las llaves van hasheadas). `php artisan cache:clear` los borra.
7. Código para mostrar: `AppServiceProvider` (los tres limitadores) y `routes/api.php` (qué ruta usa cuál).

---

## Etapa 6 — Tienda web y API ampliada (`v6-ui-tienda`, rama `05-ui-tienda`)

La pantalla técnica se sustituyó por una tienda normal (**TechStore**), sin términos técnicos a la vista: sin API key,
token, JSON ni códigos HTTP. Los errores salen como avisos en español ("No tienes permiso para ver este pedido",
"Demasiadas peticiones, intenta de nuevo en 40 segundos").

### Pantallas

Inicio de sesión y registro · Catálogo (búsqueda y categorías) · Carrito (cantidades, dirección de envío, total) ·
Mis pedidos (estado Pendiente, Pagado, Enviado o Cancelado) · Administración, solo admin: Productos (agregar, editar,
eliminar), Pedidos de clientes (cambiar el estado) y Usuarios.

### Cómo está hecha

- `resources/views/tienda.blade.php` (HTML), `public/assets/styles.css` y `public/assets/app.js`: sin Vite ni npm.
  El `<meta name="api-key">` lleva la API key desde el `.env` (ver la etapa 2 sobre por qué no es un secreto).
- El token JWT vive en `sessionStorage` y se revoca en el servidor al cerrar sesión. Si vence, la tienda vuelve al
  inicio de sesión con un aviso.
- Todo texto que viene de la API se escapa antes de pintarlo (`escapeHtml`): un nombre, una descripción o una dirección
  con HTML o `<script>` se ve como texto y no se ejecuta.
- Los mensajes de validación están en español (`lang/es`, `APP_LOCALE=es`).

### Endpoints nuevos y cómo se protegen

| Endpoint | Protección |
|----------|------------|
| `POST /api/register` | API key + límite `api`. El rol **siempre** es `cliente`: un `"rol":"admin"` en el body se ignora. Email en minúsculas y único; contraseña de 8 a 72 caracteres |
| `GET /api/productos?buscar=&categoria=` | API key. Los `%` y `_` de la búsqueda se escapan; todo va con parámetros enlazados (sin SQL injection) |
| `GET /api/pedidos` | API key + JWT. El filtro por dueño va **dentro de la consulta**: un cliente solo recibe lo suyo; el admin recibe todo, con el cliente |
| `POST /api/productos`, `PUT` y `DELETE /api/productos/{id}`, `PATCH /api/pedidos/{id}/estado`, `GET /api/usuarios` | API key + JWT + `rol:admin`. El rol se revisa **antes** del route model binding: un cliente recibe `403` exista o no el id (no se puede "tantear") |
| `DELETE /api/productos/{id}` | `409` si el producto ya está en pedidos (no se pierde el historial) |
| `PATCH /api/pedidos/{id}/estado` | Solo `pendiente`, `pagado`, `enviado` o `cancelado`. Al cancelar, las piezas regresan al inventario y un pedido cancelado no se reactiva |
| `POST /api/pedidos` | Ahora exige `direccion_envio` (5 a 255 caracteres) |

El orden de los middleware queda: API key → rate limiter → JWT → rol → route model binding.

### Pruebas con curl (falla vs. pasa)

Reemplaza `TU_API_KEY`; `TOKEN1` (cliente1) y `ADMIN` se obtienen como en la etapa 4.

```bash
# PASA: registro; el "rol":"admin" del body se ignora  ->  201 con "rol":"cliente"
curl -s -X POST http://127.0.0.1:8000/api/register \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"name":"Ana López","email":"ana@correo.com","password":"secreta123","rol":"admin"}' | jq -c '.user | {email, rol}'

# FALLA: el mismo email otra vez  ->  422 "Ya existe un registro con ese correo electrónico."
curl -s -X POST http://127.0.0.1:8000/api/register \
  -H 'X-API-KEY: TU_API_KEY' -H 'Content-Type: application/json' \
  -d '{"name":"Ana","email":"ANA@correo.com","password":"secreta123"}' | jq

# PASA: búsqueda y categoría  ->  2 productos
curl -s -G http://127.0.0.1:8000/api/productos -H 'X-API-KEY: TU_API_KEY' \
  --data-urlencode 'buscar=bluetooth' --data-urlencode 'categoria=Audio' | jq length

# PASA: "Mis pedidos" de cliente1 solo trae los suyos  ->  [2,1]
curl -s -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" http://127.0.0.1:8000/api/pedidos | jq -c '[.[].id]'

# FALLA: un cliente pide la lista de usuarios  ->  403
curl -i -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" http://127.0.0.1:8000/api/usuarios

# PASA: el admin cambia el estado de un pedido  ->  200
curl -s -X PATCH http://127.0.0.1:8000/api/pedidos/2/estado \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $ADMIN" -H 'Content-Type: application/json' \
  -d '{"estado":"pagado"}' | jq -c '{id, estado}'

# FALLA: un cliente intenta lo mismo (aunque el pedido no exista)  ->  403
curl -i -X PATCH http://127.0.0.1:8000/api/pedidos/99999/estado \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN1" -H 'Content-Type: application/json' \
  -d '{"estado":"pagado"}'

# FALLA: borrar un producto que ya está en pedidos  ->  409
curl -i -X DELETE http://127.0.0.1:8000/api/productos/6 -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $ADMIN"
```

### Cómo demostrarlo en vivo

1. Abre la tienda: solo se ve el inicio de sesión. Pulsa "Regístrate aquí", crea una cuenta y entra al catálogo.
2. Busca "bluetooth", filtra por categoría y agrega productos al carrito. Confirma el pedido con una dirección: aparece
   en "Mis pedidos" como Pendiente.
3. Cierra sesión y entra como `admin`: **Administración** → Productos (crea, edita y elimina uno), Pedidos de clientes
   (cambia el estado; al cancelar, el stock regresa) y Usuarios.
4. Seguridad: en Postman, carpeta "5 · Tienda", muestra que un cliente recibe `403` en `/api/usuarios` y que el
   registro ignora `"rol":"admin"`.
5. XSS: crea un producto con nombre `<img src=x onerror=alert(1)>`: en el catálogo se ve como texto, no se ejecuta.
