# Despliegue en Ubuntu Server 24.04 con Cloudflare Tunnel

La app corre con `php artisan serve` en `127.0.0.1:8000` y `cloudflared` la publica en una URL
`https://xxxx.trycloudflare.com`. No se abre ningún puerto en la VM. Todas las peticiones llegan a Laravel desde
`127.0.0.1`; por eso el rate limiter usa el usuario o la API key (nunca la IP) y `bootstrap/app.php` confía en
`X-Forwarded-Proto/Host` (`trustProxies(at: '*')`) para generar enlaces `https`.

Comandos pensados para un usuario normal con `sudo`.

## 1. Paquetes

Ubuntu 24.04 trae PHP 8.3 y PostgreSQL 16 (Laravel 13 pide PHP 8.3 o superior).

```bash
sudo apt update
sudo apt install -y git curl unzip openssl \
  php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  composer postgresql

php -v && composer -V && psql --version
php -m | grep -E 'pdo_pgsql|mbstring|xml|curl|zip'   # deben salir las 5
```

## 2. PostgreSQL: usuario y base

```bash
sudo -u postgres psql <<'SQL'
CREATE USER tienda WITH PASSWORD 'cambia-esta-password';
CREATE DATABASE tienda_api OWNER tienda;
SQL

# prueba de conexión
PGPASSWORD='cambia-esta-password' psql -h 127.0.0.1 -U tienda -d tienda_api -c 'select 1'
```

## 3. Código y dependencias

```bash
git clone <URL-DEL-REPOSITORIO> tienda-api
cd tienda-api
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## 4. `.env`

Estas son **todas** las variables que hay que revisar. Las demás de `.env.example` pueden quedarse como vienen.
Edita `.env` a mano o con `sed` (no agregues líneas duplicadas al final: se queda con la primera):

```dotenv
APP_NAME="TechStore"
APP_ENV=production
APP_KEY=base64:...              # ya lo llenó php artisan key:generate
APP_DEBUG=false                 # con true los errores 500 muestran trace y rutas del servidor
APP_URL=http://localhost:8000
APP_LOCALE=es                   # mensajes de validación en español
APP_FALLBACK_LOCALE=en

LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tienda_api
DB_USERNAME=tienda
DB_PASSWORD=cambia-esta-password

SESSION_DRIVER=database
CACHE_STORE=database            # contadores del rate limiter y lista negra de JWT (tabla "cache")
QUEUE_CONNECTION=database       # no se usa, pero la tabla "jobs" ya la crea la migración

API_KEY=ejemplo-cambia-esta-llave                # genera la tuya con: openssl rand -hex 24
JWT_SECRET=ejemplo-cambia-este-secreto-de-32-o-mas-caracteres   # genera uno con: openssl rand -hex 32
JWT_TTL=30                      # minutos de vida del token
```

Con `sed`, generando las llaves en el momento (copia `API_KEY` para dársela a quien pruebe con Postman):

```bash
set_env() { sed -i "s|^#\? \?$1=.*|$1=$2|" .env; }
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_LOCALE es
set_env DB_CONNECTION pgsql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 5432
set_env DB_DATABASE tienda_api
set_env DB_USERNAME tienda
set_env DB_PASSWORD 'cambia-esta-password'
set_env CACHE_STORE database
set_env API_KEY "$(openssl rand -hex 24)"
set_env JWT_SECRET "$(openssl rand -hex 32)"
set_env JWT_TTL 30

grep -E '^(APP_ENV|APP_DEBUG|APP_LOCALE|DB_|CACHE_STORE|API_KEY|JWT_TTL)' .env   # revisa (no imprimas JWT_SECRET en público)
```

> Las líneas `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` vienen comentadas (`# DB_HOST=...`) en
> `.env.example`; la función `set_env` también las descomenta.

## 5. Base de datos y arranque

```bash
php artisan migrate:fresh --seed --force     # crea las tablas y los datos de ejemplo (borra lo que hubiera)
php artisan serve --host=127.0.0.1 --port=8000
```

Prueba rápida desde otra terminal de la VM (`<API_KEY>` es la de tu `.env`):

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/                                        # 200 (la tienda)
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/api/productos                           # 401 (sin API key)
curl -s -o /dev/null -w '%{http_code}\n' -H 'X-API-KEY: <API_KEY>' http://127.0.0.1:8000/api/productos # 200
```

Usuarios de ejemplo (contraseña `password`): `admin@tienda.com`, `cliente1@tienda.com`, `cliente2@tienda.com`.

## 6. cloudflared

```bash
# amd64 (en ARM usa cloudflared-linux-arm64.deb)
curl -fL -o cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb
cloudflared --version
```

Con `php artisan serve` corriendo, en **otra terminal**:

```bash
cloudflared tunnel --url http://localhost:8000
```

Imprime una URL como `https://algo-aleatorio.trycloudflare.com`: esa es la dirección pública de la tienda.

- Abre la URL en el navegador: debe cargar la tienda con su CSS y JS (si se viera sin estilos, revisa que
  `bootstrap/app.php` tenga `trustProxies(at: '*')`).
- Para Postman: `base_url` = esa URL (sin `/` al final) y `api_key` = la `API_KEY` de la VM. Importa
  `docs/tienda-api.postman_collection.json`.
- La URL cambia cada vez que reinicias `cloudflared` (túnel rápido, sin cuenta). Para mantener ambos procesos al
  cerrar la sesión SSH usa `tmux` (`sudo apt install -y tmux`; una ventana para `php artisan serve` y otra para
  `cloudflared`).

## 7. Operación

| Qué | Comando |
|-----|---------|
| Reiniciar los datos de ejemplo | `php artisan migrate:fresh --seed --force` |
| Reiniciar los contadores del rate limiter (y la lista negra de JWT) | `php artisan cache:clear` |
| Ver errores | `tail -f storage/logs/laravel.log` |
| Cambiar la API key o el secreto JWT | editar `.env` (`php artisan serve` se reinicia solo); los tokens emitidos antes dejan de valer si cambia `JWT_SECRET` |

Problemas frecuentes:

- **500 en todo**: falta `APP_KEY` (`php artisan key:generate`) o no se puede escribir en `storage/` y `bootstrap/cache/`
  (`chmod -R u+rwX storage bootstrap/cache`).
- **`could not find driver`**: falta `php8.3-pgsql`.
- **`La API key no está configurada en el servidor`** / **`JWT_SECRET no está configurado`**: revisa `API_KEY` y
  `JWT_SECRET` (mínimo 32 caracteres) en `.env`.
- **Todo da 429**: `php artisan cache:clear`.
