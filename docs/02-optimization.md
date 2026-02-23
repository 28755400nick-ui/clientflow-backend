# ⚡ OPTIMIZACIÓN SENIOR — CLIENTFLOW
> Performance + Seguridad + Escalabilidad

---

## 1. OPTIMIZACIONES APLICADAS EN EL CÓDIGO

### 1.1 — Índices MySQL (migration aplicada)

**Archivo:** `database/migrations/2026_02_21_000001_add_performance_indexes.php`

| Índice | Tabla | Columnas | Por qué |
|--------|-------|----------|---------|
| `idx_clients_name` | clients | first_name, last_name | Búsqueda por nombre (LIKE) |
| `idx_clients_deleted_at` | clients | deleted_at | Soft-delete (WHERE deleted_at IS NULL) — aparece en CADA query |
| `idx_clients_created_at` | clients | created_at | ORDER BY en paginación |
| `idx_refresh_tokens_expires_at` | refresh_tokens | expires_at | Limpieza de tokens expirados |

**Para aplicar:**
```bash
php artisan migrate
```

### 1.2 — ClientController: select() explícito

En vez de `SELECT *` (incluye `deleted_at` que es irrelevante en la respuesta),
ahora usamos columnas exactas. Reduce el payload de red y el trabajo del ORM.

**Antes:** `Client::query()` → trae todas las columnas
**Ahora:** `->select(['id', 'first_name', 'last_name', 'phone', 'email', 'created_at', 'updated_at'])`

### 1.3 — per_page limitado a máximo 100

```php
$perPage = min((int) $request->input('per_page', 10), 100);
```

Sin este límite, un cliente malicioso puede pedir `per_page=1000000` y tumbar el servidor.

### 1.4 — CORS de producción (config/cors.php)

- Solo métodos necesarios (no `*`)
- Solo headers necesarios (no `*`)
- Múltiples orígenes desde una sola variable de entorno
- Preflight cache de 1 hora (evita OPTIONS requests repetidos)

### 1.5 — Limpieza automática de tokens (Scheduled Command)

**Archivo:** `app/Console/Commands/CleanExpiredRefreshTokens.php`
**Schedule:** `routes/console.php` → diario a las 02:00 AM

Sin esto, la tabla `refresh_tokens` crece indefinidamente.

**Para ejecutar manualmente:**
```bash
php artisan tokens:clean
```

**Para activar el scheduler en producción (EC2):**
```bash
# Agregar a crontab del servidor
crontab -e

# Agregar esta línea:
* * * * * cd /var/www/clientflow-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## 2. OPTIMIZACIONES DE LARAVEL (PRODUCCIÓN)

### 2.1 — Cache de configuración, rutas y vistas

```bash
# Cachear toda la configuración (arrays → PHP serializado)
php artisan config:cache

# Cachear rutas (evita parsear routes/api.php en cada request)
php artisan route:cache

# Cachear vistas Blade (no usadas en API, pero buena práctica)
php artisan view:cache

# Optimizar autoloader de Composer
composer dump-autoload --optimize
```

> ⚠️ Después de cambiar `.env`, SIEMPRE ejecuta `php artisan config:cache`
> de lo contrario los cambios no aplican.

### 2.2 — Queue Worker (si usas jobs)

```bash
# Iniciar queue worker (en background)
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &

# Mejor en producción: usar supervisor
```

**Configurar Supervisor para el worker:**
```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/clientflow-worker.conf
```

```ini
[program:clientflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/clientflow-api/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/clientflow-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start clientflow-worker:*
```

---

## 3. OPTIMIZACIÓN DE NGINX (PRODUCCIÓN)

Reemplaza el bloque `server` de Nginx con esta versión optimizada:

```nginx
# /etc/nginx/sites-available/clientflow-api

# Limitar request body (evita ataques por payload enorme)
client_max_body_size 10M;

# Definir zona de rate limiting a nivel Nginx (complementa Laravel throttle)
limit_req_zone $binary_remote_addr zone=api:10m rate=30r/m;

server {
    listen 443 ssl http2;
    server_name api.cascavel.site;
    root /var/www/clientflow-api/public;

    # SSL (Certbot lo genera automáticamente)
    ssl_certificate     /etc/letsencrypt/live/api.cascavel.site/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.cascavel.site/privkey.pem;

    # TLS moderno (solo TLS 1.2 y 1.3)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:MozSSL:10m;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=63072000" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    index index.php;
    charset utf-8;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types application/json application/javascript text/css text/plain;

    # Rate limiting Nginx
    location /api/ {
        limit_req zone=api burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;

        # Timeouts
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout    60s;
        fastcgi_read_timeout    60s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/clientflow-api.access.log;
    error_log  /var/log/nginx/clientflow-api.error.log;
}

# Redirigir HTTP → HTTPS
server {
    listen 80;
    server_name api.cascavel.site;
    return 301 https://$host$request_uri;
}
```

---

## 4. OPTIMIZACIÓN DE RDS MySQL

### 4.1 — Parameter Group (AWS Console)

Ve a: **RDS → Parameter Groups → Create**

| Parámetro | Valor | Por qué |
|-----------|-------|---------|
| `innodb_buffer_pool_size` | `{DBInstanceClassMemory*3/4}` | Usa 75% RAM para caché InnoDB |
| `slow_query_log` | `1` | Activa log de queries lentas |
| `long_query_time` | `1` | Queries > 1 segundo van al slow log |
| `log_queries_not_using_indexes` | `1` | Detecta queries sin índice |
| `max_connections` | `100` | Limita conexiones (ajustar según instancia) |
| `innodb_flush_log_at_trx_commit` | `2` | Mejor rendimiento (pequeño riesgo en crash) |

### 4.2 — Ver queries lentas

```sql
-- Conectado a MySQL en RDS
SHOW VARIABLES LIKE 'slow_query_log%';
SHOW VARIABLES LIKE 'long_query_time';

-- Ver los peores queries
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;
```

### 4.3 — Explain de queries críticos

```sql
-- Verificar que el índice de email se usa
EXPLAIN SELECT * FROM clients WHERE email = 'test@example.com';

-- Verificar índice de nombre
EXPLAIN SELECT * FROM clients WHERE first_name LIKE 'Juan%' AND deleted_at IS NULL;

-- Verificar paginación
EXPLAIN SELECT * FROM clients WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 10;
```

---

## 5. RATE LIMITING AVANZADO (LARAVEL)

El throttle actual en Laravel es básico. Aquí está la versión avanzada para producción:

### 5.1 — Configurar throttle en AppServiceProvider

Edita `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Login: 5 intentos por minuto por IP
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)
            ->by($request->ip())
            ->response(function () {
                return response()->json([
                    'message' => 'Demasiados intentos. Espera 1 minuto.',
                ], 429);
            });
    });

    // API general: 60 requests por minuto por usuario autenticado
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip());
    });

    // Export: 10 por hora (el export carga todos los datos)
    RateLimiter::for('export', function (Request $request) {
        return Limit::perHour(10)
            ->by($request->ip());
    });
}
```

### 5.2 — Aplicar en routes/api.php

```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:5,1');

Route::middleware(['jwt'])->group(function () {
    Route::get('/clients/export', [ClientController::class, 'export'])
        ->middleware('throttle:export');

    Route::get('/clients', [ClientController::class, 'index']);
    // ...
});
```

---

## 6. SEGURIDAD JWT — MEJORAS

### 6.1 — Rotar el secret periódicamente

```bash
# En EC2, genera un nuevo secret
php -r "echo base64_encode(random_bytes(32));"
# Copia el resultado

# Actualiza el .env
nano /var/www/clientflow-api/.env
# JWT_SECRET=nuevo_secret_aqui

# Limpia la cache y reinicia
php artisan config:cache
sudo systemctl restart php8.2-fpm
```

> ⚠️ Rotar el secret invalida TODOS los tokens existentes.
> Los usuarios necesitarán loguearse de nuevo.

### 6.2 — Validar el `iss` del token

En `JwtService::decodeToken()` agrega validación del issuer:
```php
// Ya está en el payload: 'iss' => config('app.url')
// La librería firebase/jwt puede validar esto automáticamente:
JWT::decode($token, new Key($this->secretKey, $this->algorithm));
// Agrega validación adicional si lo requieres
```

---

## 7. ESCALABILIDAD A 100K CLIENTES

### Para cuando la carga crezca:

| Escenario | Solución |
|-----------|---------|
| +50k clientes en tabla | Agregar paginación cursor-based en vez de offset |
| Alto tráfico en API | Load Balancer (ALB) + múltiples EC2 |
| Búsquedas lentas | Full-text search con MySQL FULLTEXT index |
| Cache de resultados | Redis + Laravel Cache (GET /clients cacheado 30s) |
| Tokens JWT en Redis | Mover refresh_tokens de MySQL a Redis (más rápido) |
| RDS saturada | RDS Read Replica para queries de lectura |

### Cursor-based pagination (para tablas enormes):

```php
// En vez de OFFSET (lento con millones de filas):
$clients = Client::where('id', '>', $lastId)
    ->orderBy('id')
    ->limit(10)
    ->get();
```

---

## 8. MONITOREO Y LOGS

### 8.1 — Configurar log diario (ya está en .env)

```env
LOG_CHANNEL=daily
LOG_LEVEL=error  # Solo errores en producción (no debug/info)
```

### 8.2 — Ver logs en EC2

```bash
# Log de hoy
tail -f /var/www/clientflow-api/storage/logs/laravel-$(date +%Y-%m-%d).log

# Últimas 100 líneas
tail -100 /var/www/clientflow-api/storage/logs/laravel-$(date +%Y-%m-%d).log

# Buscar errores específicos
grep -i "error\|exception" /var/www/clientflow-api/storage/logs/laravel-$(date +%Y-%m-%d).log
```

### 8.3 — Métricas básicas del servidor

```bash
# CPU y RAM en tiempo real
htop

# Espacio en disco
df -h

# Conexiones activas a Nginx
ss -tn | grep :443 | wc -l

# Estado de PHP-FPM
sudo systemctl status php8.2-fpm

# Procesos de PHP activos
ps aux | grep php
```
