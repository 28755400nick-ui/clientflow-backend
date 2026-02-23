# 🖥️ TESTING LOCAL — CLIENTFLOW
> Todo lo que necesitas para correr el proyecto completo en tu máquina

---

## CREDENCIALES LOCALES

| Campo | Valor |
|-------|-------|
| **Email** | `admin@clientflow.com` |
| **Password** | `password123` |

---

## PASO 1 — BACKEND (Laravel)

### 1.1 — Primera vez (setup completo)

Ejecuta en orden desde `c:\Users\nica2\clientflow-api`:

```bash
# 1. Instalar dependencias
composer install

# 2. Copiar .env de ejemplo
cp .env.example .env

# 3. Generar APP_KEY
php artisan key:generate

# 4. El .env usa SQLite por defecto — crear el archivo
touch database/database.sqlite

# 5. Ejecutar migraciones + seeders
php artisan migrate --seed

# 6. Verificar que el usuario admin existe
php artisan tinker
>>> App\Models\User::where('email', 'admin@clientflow.com')->first()
# Ctrl+D para salir

# 7. Iniciar el servidor
php artisan serve
```

### 1.2 — Arranque rápido (a partir de ahora)

```bash
php artisan serve
```

El backend queda disponible en:

> **http://localhost:8000**

---

## PASO 2 — FRONTEND (Next.js)

### 2.1 — Verificar .env.local

El archivo `c:\Users\nica2\clientflow-front\.env.local` debe tener:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

### 2.2 — Primera vez

```bash
# Desde c:\Users\nica2\clientflow-front
npm install
npm run dev
```

### 2.3 — Arranque rápido

```bash
npm run dev
```

El frontend queda disponible en:

> **http://localhost:3000**

---

## 🔗 LINKS LOCALES PARA PROBAR TODO

| URL | Descripción |
|-----|-------------|
| **http://localhost:3000** | Aplicación completa (redirige a /login) |
| **http://localhost:3000/login** | Página de login |
| **http://localhost:3000/clients** | Dashboard de clientes (requiere auth) |

### API directa (para probar con curl o Postman)

| Método | URL | Descripción |
|--------|-----|-------------|
| POST | http://localhost:8000/api/login | Login |
| POST | http://localhost:8000/api/refresh | Refresh token |
| POST | http://localhost:8000/api/logout | Logout |
| GET | http://localhost:8000/api/me | Usuario actual |
| GET | http://localhost:8000/api/clients | Listar clientes |
| POST | http://localhost:8000/api/clients | Crear cliente |
| PUT | http://localhost:8000/api/clients/{id} | Editar cliente |
| DELETE | http://localhost:8000/api/clients/{id} | Eliminar cliente |
| GET | http://localhost:8000/api/clients/export | Exportar Excel |

---

## PASO 3 — PROBAR LA API CON CURL

### Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@clientflow.com","password":"password123"}'
```

Respuesta esperada:
```json
{
  "access_token": "eyJ0eXAiOi...",
  "refresh_token": "eyJ0eXAiOi...",
  "token_type": "Bearer",
  "expires_in": 900
}
```

### Listar clientes (guarda el access_token del paso anterior)

```bash
# Reemplaza TOKEN con el access_token del login
curl http://localhost:8000/api/clients \
  -H "Authorization: Bearer TOKEN"
```

### Crear cliente

```bash
curl -X POST http://localhost:8000/api/clients \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "first_name": "Juan",
    "last_name": "García",
    "phone": "+54 9 11 12345678",
    "email": "juan@example.com"
  }'
```

### Exportar Excel

```bash
curl http://localhost:8000/api/clients/export \
  -H "Authorization: Bearer TOKEN" \
  --output clientes.xlsx
```

---

## PASO 4 — POBLAR LA BASE DE DATOS CON DATOS DE PRUEBA

Si quieres tener datos de prueba sin crearlos manualmente:

```bash
# Desde c:\Users\nica2\clientflow-api
php artisan tinker

# Crear 50 clientes de prueba
>>> App\Models\Client::factory()->count(50)->create()

# Ctrl+D para salir
```

---

## PASO 5 — EJECUTAR TESTS

### Backend

```bash
# Desde c:\Users\nica2\clientflow-api
php artisan test

# Solo tests de autenticación
php artisan test --filter AuthTest

# Solo tests de clientes
php artisan test --filter ClientTest

# Solo unit tests de JWT
php artisan test --filter JwtServiceTest

# Con detalle (verbose)
php artisan test --verbose
```

### Frontend

```bash
# Instalar dependencias de test (solo la primera vez)
cd C:\Users\nica2\clientflow-front

npm install --save-dev \
  jest \
  jest-environment-jsdom \
  @testing-library/react \
  @testing-library/jest-dom \
  @testing-library/user-event \
  @types/jest \
  ts-jest

# Agregar script en package.json → "test": "jest"
# Luego:
npm test
```

---

## CHECKLIST DE VERIFICACIÓN

Antes de decir que todo funciona, verifica:

- [ ] `php artisan serve` corre sin errores
- [ ] `npm run dev` corre sin errores
- [ ] http://localhost:3000/login carga la pantalla de login
- [ ] Login con `admin@clientflow.com` / `password123` funciona
- [ ] La tabla de clientes carga (puede estar vacía)
- [ ] Puedes crear un cliente nuevo desde el modal
- [ ] El cliente aparece en la tabla
- [ ] Puedes editar el cliente
- [ ] Puedes eliminar el cliente (con confirmación)
- [ ] El filtro por nombre funciona
- [ ] El filtro por teléfono funciona
- [ ] El botón "Exportar Excel" descarga un .xlsx
- [ ] El logout limpia la sesión y redirige a /login
- [ ] Al recargar la página con sesión activa, sigue autenticado
- [ ] Al recargar la página sin sesión, redirige a /login
- [ ] Los tests del backend pasan (49 tests)

---

## TROUBLESHOOTING COMÚN

### Error: "No application key has been set"

```bash
php artisan key:generate
```

### Error: "SQLSTATE[HY000]: No such file or directory" (SQLite)

```bash
touch database/database.sqlite
php artisan migrate
```

### Error: "php_network_getaddresses" en el frontend

El backend no está corriendo. Inicia `php artisan serve` primero.

### Error de CORS en el navegador

El `.env` del backend tiene:
```env
CORS_ALLOWED_ORIGINS=http://localhost:3000
```
Si el frontend corre en otro puerto, actualiza esta variable.

### Error: "JWT_SECRET not set"

Si tu config tiene un JWT_SECRET requerido:
```bash
php artisan tinker
>>> echo base64_encode(random_bytes(32));
# Copia el resultado al .env: JWT_SECRET=resultado
php artisan config:clear
```

### La tabla de clientes no carga (error 401)

El token expiró (15 min). Cierra sesión y vuelve a loguearte.
El interceptor de Axios debería manejarlo automáticamente con el refresh token.

### Los tests fallan con "Class not found"

```bash
composer dump-autoload
```
