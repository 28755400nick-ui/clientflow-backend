# ClientFlow API

Backend de ClientFlow — sistema de gestión de clientes construido con Laravel 12, MySQL y Docker.

> Proyecto de portafolio — Arquitectura distribuida Full Stack con CI/CD automatizado.

## Stack

- **PHP** 8.2
- **Laravel** 12
- **MySQL** 8.0
- **Docker** + Docker Compose
- **Nginx** (reverse proxy)
- **JWT** autenticación (access token 15min + refresh token 7 días)

## Arquitectura

```
Internet
   ↓ HTTPS
Nginx (host) — api.cascavel.site
   ↓ proxy → localhost:8080
Nginx (container)
   ↓ fastcgi
PHP-FPM (container)
   ↓
MySQL (container)
```

---

## Levantar el proyecto localmente con Docker

### Requisitos

- Docker y Docker Compose instalados
- Archivo `.env` configurado (ver `.env.example`)

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/28755400nick-ui/clientflow-backend.git
cd clientflow-backend

# 2. Copiar el archivo de entorno
cp .env.example .env

# 3. Configurar las variables en .env
#    - DB_HOST=db  (nombre del servicio MySQL en Docker)
#    - JWT_SECRET=genera uno con: openssl rand -hex 32

# 4. Levantar los containers
docker compose up -d --build

# 5. Esperar ~20 segundos para que MySQL inicie, luego:
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

La API estará disponible en `http://localhost:8080/api`

### Credenciales por defecto

| Campo | Valor |
|-------|-------|
| Email | admin@clientflow.com |
| Password | password123 |

### Comandos útiles

```bash
# Ver logs
docker compose logs -f

# Ejecutar comandos de artisan
docker compose exec app php artisan <comando>

# Detener containers
docker compose down

# Detener y eliminar volúmenes (borra la BD)
docker compose down -v
```

---

## Pipeline de despliegue (CI/CD)

### Cómo funciona

Cada `git push` a la rama `main` dispara automáticamente el pipeline de GitHub Actions:

```
git push origin main
       ↓
GitHub Actions (.github/workflows/deploy.yml)
       ↓ SSH al servidor EC2
deploy.sh
       ↓
git pull + docker compose up --build
       ↓
Nueva versión en producción
```

### Configuración requerida en GitHub

En el repositorio → **Settings** → **Secrets and variables** → **Actions**:

| Secret | Descripción |
|--------|-------------|
| `EC2_HOST` | IP pública del servidor EC2 |
| `EC2_USER` | Usuario SSH (ubuntu) |
| `EC2_SSH_KEY` | Contenido del archivo `.pem` |

### Ver el estado del pipeline

GitHub → repositorio → pestaña **Actions** → selecciona el workflow más reciente.

---

## Variables de entorno requeridas

```env
APP_ENV=production
APP_KEY=                    # php artisan key:generate
DB_HOST=db                  # "db" en Docker, IP/hostname en producción directa
DB_DATABASE=clientflow
DB_USERNAME=clientflow_user
DB_PASSWORD=
JWT_SECRET=                 # openssl rand -hex 32
CORS_ALLOWED_ORIGINS=https://cascavel.site,https://www.cascavel.site
```
