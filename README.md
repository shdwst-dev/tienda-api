# Tienda API — Seguridad de API con Laravel

Tarea de Seguridad Informática (UPQ). Mini tienda de aparatos electrónicos: API REST en **Laravel 13 + PostgreSQL**
con un frontend simple en Blade + `fetch()` (sin Vite ni npm).

El proyecto se construye por etapas. Cada concepto de seguridad vive en su propia rama, se une a `master` con
`git merge --no-ff` y queda marcado con un tag, así que se puede viajar entre etapas con `git checkout <tag>`:

| Tag | Rama | Qué agrega |
|-----|------|------------|
| `v1-base` | `master` | API base y frontend, funcional pero **vulnerable a propósito** |
| `v2-api-key` | `01-api-key` | API key: header `X-API-KEY` obligatorio en toda la API |
| `v3-jwt` | `02-jwt` | JWT: login con token firmado, expiración y revocación en el logout |

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
| POST | `/api/login` | Valida credenciales y regresa un JWT y el usuario | API key |
| POST | `/api/logout` | Revoca el token que se usó en la petición | API key + JWT |
| GET | `/api/perfil` | Datos del dueño del token | API key + JWT |
| GET | `/api/productos` | Lista de productos | API key |
| POST | `/api/productos` | Crea un producto | API key + JWT |
| GET | `/api/pedidos/{id}` | Detalle de un pedido con sus productos | API key + JWT |
| POST | `/api/pedidos` | Crea un pedido (`user_id` + `items`) | API key + JWT |

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
- En un navegador no puede ser secreta (DevTools la muestra). Por eso el frontend **no** la trae incrustada en el
  HTML (cualquiera que abra el sitio la vería): se pega a mano en el campo "API key" y queda en `localStorage`.

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
- `resources/views/tienda.blade.php`: campo "API key" y header `X-API-KEY` en cada `fetch()`.

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

1. Abre el frontend sin llave: la tabla de productos no carga y el panel muestra `401 API key ausente o inválida`.
2. Escribe una llave equivocada en el campo "API key": sigue el `401`.
3. Pega la correcta: aparecen los productos y todo lo demás vuelve a funcionar.
4. En Postman: `GET /api/productos` sin el header da `401`; con `X-API-KEY` da `200`.
5. Muestra el código: una línea en `bootstrap/app.php` y los 15 renglones de `ValidateApiKey`.

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
   Pinta los datos de la API con `textContent`, nunca con `innerHTML`, para que un XSS no pueda robar el token.

Detalles que conviene mencionar:

- `firebase/php-jwt` acepta tokens sin `exp`; el middleware los rechaza porque `exp` es obligatorio.
- El middleware implementa `AuthenticatesRequests`, así Laravel lo ejecuta antes del rate limiter y del route model
  binding: sin token, pedir un pedido que no existe responde `401`, no `404`.
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
  -d '{"user_id":2,"items":[{"producto_id":6,"cantidad":1}]}'

# PASA: con el token  ->  200 con el usuario DEL TOKEN (aunque pidas ?user_id=1 lo ignora)
curl -s -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" \
  'http://127.0.0.1:8000/api/perfil?user_id=1' | jq

# PASA: crear un pedido con el token  ->  201
curl -s -X POST http://127.0.0.1:8000/api/pedidos \
  -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"user_id":2,"items":[{"producto_id":6,"cantidad":1}]}' | jq

# Logout  ->  200, el jti del token entra a la lista negra
curl -s -X POST -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" \
  http://127.0.0.1:8000/api/logout | jq

# FALLA: el mismo token después del logout  ->  401 "Token revocado."
curl -i -H 'X-API-KEY: TU_API_KEY' -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/perfil
```

Token expirado: pon `JWT_TTL=1` en el `.env` (el servidor se reinicia solo al cambiar el `.env`), haz login, espera
61 segundos y repite el `GET /api/perfil`: `401 "Token expirado."`.

### Cómo demostrarlo en vivo

1. En el frontend, sin sesión: "Ver mi perfil" y "Consultar" pedido responden `401 Falta el token`. La lista de
   productos sí carga porque `GET /api/productos` es pública.
2. Entra como `cliente1`: aparece la tarjeta **Token JWT** con los claims. Explica `sub`, `rol`, `iat`, `exp` y `jti`.
3. "Ver mi perfil" responde con el usuario del token; "Crear pedido" responde `201`.
4. Copia el token a Postman: úsalo en `GET /api/perfil` (`200`), cámbiale un carácter (`401 Token inválido`) y
   quítale el header (`401 Falta el token`).
5. Haz logout en el frontend y vuelve a mandar el token copiado desde Postman: `401 Token revocado`. Para ver la
   lista negra: `select key from cache where key like '%jwt:revocado%';`.
6. Entra en otra pestaña con el mismo usuario: su sesión no se afecta por el logout de la primera (revocación por sesión).
7. Código para mostrar: `AuthenticateJwt` (los pasos en orden) y `JwtService`.
8. Cierra con el hueco que queda: con el token de `cliente1`, `GET /api/pedidos/3` (de `cliente2`) todavía responde `200`.
