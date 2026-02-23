# 🚀 INFRAESTRUCTURA COMPLETA — CLIENTFLOW
> **Guía DevOps Senior | Sin Docker | Sin GitHub Actions | Sin Pipelines**
> Reproducible 1:1 — Ubuntu EC2 + RDS MySQL + Vercel + Namecheap + SSL

---

## ARQUITECTURA GENERAL

```
Internet
   │
   ├── cascavel.site  ──────────────► Vercel (Next.js frontend)
   │
   └── api.cascavel.site ───────────► EC2 Ubuntu (Nginx → PHP-FPM → Laravel)
                                              │
                                              └──► RDS MySQL (privado, sin IP pública)
```

---

## PASO 1 — CREAR LA INSTANCIA EC2

### 1.1 — Crear la instancia en AWS Console

Ve a: **AWS Console → EC2 → Launch Instance**

| Campo | Valor |
|-------|-------|
| Name | `clientflow-api` |
| AMI | Ubuntu Server 24.04 LTS (64-bit x86) |
| Instance Type | `t3.small` (2 vCPU, 2GB RAM) — mínimo recomendado para prod |
| Key Pair | Crea uno nuevo: `clientflow-key` → descarga el `.pem` |
| Storage | 20 GB gp3 |

### 1.2 — Configurar Security Group (Firewall)

Nombre: `clientflow-sg`

| Tipo | Protocolo | Puerto | Origen |
|------|-----------|--------|--------|
| SSH | TCP | 22 | Tu IP (solo tu IP, NO 0.0.0.0/0) |
| HTTP | TCP | 80 | 0.0.0.0/0 |
| HTTPS | TCP | 443 | 0.0.0.0/0 |

> ⚠️ **NUNCA** abras el puerto 22 a 0.0.0.0/0 en producción. Solo tu IP.

### 1.3 — Asignar IP Elástica (Elastic IP)

```
EC2 → Elastic IPs → Allocate Elastic IP address → Allocate
→ Actions → Associate Elastic IP → selecciona tu instancia
```

Anota la IP: `XX.XX.XX.XX` (la usarás para DNS y Nginx)

### 1.4 — Conectarse por SSH

```bash
# En tu máquina local (Mac/Linux)
chmod 400 clientflow-key.pem
ssh -i clientflow-key.pem ubuntu@XX.XX.XX.XX

# En Windows (PowerShell o Git Bash)
ssh -i clientflow-key.pem ubuntu@XX.XX.XX.XX
```

---

## PASO 2 — PREPARAR EL SERVIDOR UBUNTU

### 2.1 — Actualizar el sistema

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl wget unzip git
```

### 2.2 — Instalar PHP 8.2

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
  php8.2-mysql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl \
  php8.2-xml php8.2-bcmath php8.2-intl php8.2-readline php8.2-tokenizer
```

Verificar versión:
```bash
php --version
# PHP 8.2.x (cli)
```

### 2.3 — Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer --version
```

### 2.4 — Instalar Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 2.5 — Instalar MySQL Client (para conectar a RDS)

```bash
sudo apt install -y mysql-client
```

### 2.6 — Instalar extensiones para Excel export

```bash
sudo apt install -y php8.2-zip
# PhpSpreadsheet requiere zip y xml (ya instalados arriba)
```

---

## PASO 3 — CREAR BASE DE DATOS EN RDS

### 3.1 — Crear la instancia RDS

Ve a: **AWS Console → RDS → Create database**

| Campo | Valor |
|-------|-------|
| Engine | MySQL 8.0 |
| Template | Free tier (para empezar) → luego db.t3.micro |
| DB Identifier | `clientflow-db` |
| Master Username | `clientflow_admin` |
| Master Password | `TuPasswordSeguro123!` (guárdala bien) |
| DB Instance Class | `db.t3.micro` |
| Storage | 20 GB gp2, autoscaling desactivado por ahora |
| VPC | Misma VPC que tu EC2 |
| Public Access | **NO** (base de datos privada) |
| VPC Security Group | Crea uno nuevo: `clientflow-rds-sg` |

### 3.2 — Configurar Security Group de RDS

Security Group `clientflow-rds-sg`:

| Tipo | Protocolo | Puerto | Origen |
|------|-----------|--------|--------|
| MySQL/Aurora | TCP | 3306 | ID del Security Group de EC2 (`clientflow-sg`) |

> ✅ Solo EC2 puede hablar con RDS. Nadie más.

### 3.3 — Crear la base de datos

Una vez RDS esté disponible (tarda ~5 min), conéctate desde EC2:

```bash
# Desde EC2
mysql -h clientflow-db.XXXXXXXXXX.us-east-1.rds.amazonaws.com \
      -u clientflow_admin \
      -p'TuPasswordSeguro123!'
```

```sql
CREATE DATABASE clientflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'clientflow_user'@'%' IDENTIFIED BY 'OtraPasswordSegura456!';
GRANT ALL PRIVILEGES ON clientflow.* TO 'clientflow_user'@'%';
FLUSH PRIVILEGES;
EXIT;
```

---

## PASO 4 — DEPLOY DE LARAVEL EN EC2

### 4.1 — Crear el directorio del proyecto

```bash
sudo mkdir -p /var/www/clientflow-api
sudo chown -R $USER:$USER /var/www/clientflow-api
```

### 4.2 — Subir el código al servidor

**Opción A: desde tu PC via SCP**
```bash
# En tu máquina local, dentro del proyecto
tar --exclude='.git' --exclude='vendor' --exclude='node_modules' \
    --exclude='storage/logs/*.log' \
    -czf clientflow-api.tar.gz .

scp -i clientflow-key.pem clientflow-api.tar.gz ubuntu@XX.XX.XX.XX:/tmp/
```

**Opción B: clonar desde GitHub (si el repo es privado, usa deploy key)**
```bash
# En EC2
cd /var/www/clientflow-api
git clone https://github.com/TU_USUARIO/clientflow-api.git .
```

### 4.3 — Extraer y configurar

```bash
# En EC2
cd /var/www/clientflow-api
tar -xzf /tmp/clientflow-api.tar.gz
```

### 4.4 — Instalar dependencias

```bash
cd /var/www/clientflow-api
composer install --no-dev --optimize-autoloader
```

> `--no-dev` omite paquetes de desarrollo.
> `--optimize-autoloader` genera el classmap para mejor rendimiento.

### 4.5 — Configurar permisos

```bash
sudo chown -R www-data:www-data /var/www/clientflow-api
sudo find /var/www/clientflow-api -type f -exec chmod 644 {} \;
sudo find /var/www/clientflow-api -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/clientflow-api/storage
sudo chmod -R 775 /var/www/clientflow-api/bootstrap/cache
sudo usermod -aG www-data ubuntu
```

### 4.6 — Configurar .env de producción

```bash
cp /var/www/clientflow-api/.env.example /var/www/clientflow-api/.env
nano /var/www/clientflow-api/.env
```

Contenido del `.env` de producción:

```env
APP_NAME="ClientFlow"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.cascavel.site

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=clientflow-db.XXXXXXXXXX.us-east-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=clientflow
DB_USERNAME=clientflow_user
DB_PASSWORD=OtraPasswordSegura456!

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database

JWT_SECRET=
JWT_ACCESS_TTL=15
JWT_REFRESH_TTL=7

CORS_ALLOWED_ORIGINS=https://cascavel.site,https://www.cascavel.site
```

### 4.7 — Generar keys y optimizar

```bash
cd /var/www/clientflow-api

# Generar APP_KEY
php artisan key:generate

# Generar JWT_SECRET
php artisan jwt:secret
# Si el comando no existe (implementación custom), genera manualmente:
# php -r "echo base64_encode(random_bytes(32));"
# Y pégalo en JWT_SECRET=

# Ejecutar migraciones
php artisan migrate --force

# Cachear configuración (IMPORTANTE en producción)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimizar autoloader
composer dump-autoload --optimize
```

---

## PASO 5 — CONFIGURAR NGINX

### 5.1 — Crear virtual host para la API

```bash
sudo nano /etc/nginx/sites-available/clientflow-api
```

Contenido:
```nginx
server {
    listen 80;
    server_name api.cascavel.site;
    root /var/www/clientflow-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    index index.php;

    charset utf-8;

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
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Logs
    access_log /var/log/nginx/clientflow-api.access.log;
    error_log  /var/log/nginx/clientflow-api.error.log;
}
```

### 5.2 — Activar el sitio

```bash
sudo ln -s /etc/nginx/sites-available/clientflow-api /etc/nginx/sites-enabled/
sudo nginx -t       # verificar sintaxis
sudo systemctl reload nginx
```

---

## PASO 6 — INSTALAR SSL CON CERTBOT

```bash
sudo apt install -y certbot python3-certbot-nginx

# Obtener certificado (el dominio ya debe apuntar a esta IP)
sudo certbot --nginx -d api.cascavel.site \
  --non-interactive --agree-tos \
  -m tu@email.com

# Verificar renovación automática
sudo certbot renew --dry-run
```

Certbot modifica automáticamente tu config Nginx para redirigir HTTP → HTTPS.

Verificar el cron de renovación:
```bash
sudo systemctl status certbot.timer
# o
sudo crontab -l
```

---

## PASO 7 — CONFIGURAR DNS EN NAMECHEAP

Ve a: **Namecheap → Domain List → cascavel.site → Manage → Advanced DNS**

Agrega estos registros:

| Type | Host | Value | TTL |
|------|------|-------|-----|
| A Record | `@` | Vercel IP (lo obtienes en Vercel) | Automatic |
| CNAME | `www` | `cname.vercel-dns.com` | Automatic |
| A Record | `api` | `XX.XX.XX.XX` (Elastic IP de EC2) | Automatic |

> ⚠️ TTL en Automatic (300s) para que los cambios propaguen rápido.
> DNS puede tardar de 5 min a 48h en propagar globalmente.

---

## PASO 8 — DEPLOY EN VERCEL (FRONTEND)

### 8.1 — Instalar Vercel CLI en tu PC

```bash
npm i -g vercel
```

### 8.2 — Deploy desde el directorio del frontend

```bash
cd C:\Users\nica2\clientflow-front
vercel
```

Responde las preguntas:
- Set up and deploy? → Y
- Which scope? → Tu cuenta
- Link to existing project? → N (primera vez)
- Project name → `clientflow-front`
- In which directory is your code? → ./
- Override settings? → N

### 8.3 — Configurar variables de entorno en Vercel

```bash
vercel env add NEXT_PUBLIC_API_URL production
# Valor: https://api.cascavel.site/api
```

O desde el dashboard: **Vercel → Project → Settings → Environment Variables**

| Variable | Valor |
|----------|-------|
| `NEXT_PUBLIC_API_URL` | `https://api.cascavel.site/api` |

### 8.4 — Configurar dominio en Vercel

```
Vercel → Project → Settings → Domains
→ Add: cascavel.site
→ Add: www.cascavel.site
```

Vercel te dará las IPs/CNAME para configurar en Namecheap (ya hechos en Paso 7).

### 8.5 — Re-deploy con dominio correcto

```bash
vercel --prod
```

---

## PASO 9 — VERIFICACIÓN FINAL

### 9.1 — Verificar Laravel

```bash
# Desde EC2
curl -X POST https://api.cascavel.site/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password"}'
```

### 9.2 — Verificar SSL

```bash
curl -I https://api.cascavel.site
# Debe devolver HTTP/2 200 y el header: X-Frame-Options: SAMEORIGIN
```

### 9.3 — Verificar frontend

Abre en el navegador: `https://cascavel.site`

### 9.4 — Revisar logs

```bash
# Laravel logs
sudo tail -f /var/www/clientflow-api/storage/logs/laravel-$(date +%Y-%m-%d).log

# Nginx access log
sudo tail -f /var/log/nginx/clientflow-api.access.log

# Nginx error log
sudo tail -f /var/log/nginx/clientflow-api.error.log
```

---

## PASO 10 — SEGURIDAD Y HARDENING

### 10.1 — Deshabilitar password SSH (usar solo claves)

```bash
sudo nano /etc/ssh/sshd_config
```

Asegúrate de tener:
```
PasswordAuthentication no
PubkeyAuthentication yes
PermitRootLogin no
```

```bash
sudo systemctl restart sshd
```

### 10.2 — Instalar fail2ban

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 10.3 — Firewall UFW

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
sudo ufw status
```

### 10.4 — Ocultar versión de Nginx

```bash
sudo nano /etc/nginx/nginx.conf
```
Dentro del bloque `http {}`:
```nginx
server_tokens off;
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 10.5 — Configurar PHP-FPM para producción

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Valores clave:
```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php8.2-errors.log
max_execution_time = 30
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## PASO 11 — ACTUALIZACIONES DE CÓDIGO (PROCESO DE DEPLOY)

Cada vez que actualices el backend, ejecuta esto en EC2:

```bash
cd /var/www/clientflow-api

# 1. Subir código nuevo (via SCP o git pull)
# git pull origin main

# 2. Instalar dependencias nuevas
composer install --no-dev --optimize-autoloader

# 3. Ejecutar migraciones nuevas
php artisan migrate --force

# 4. Limpiar y reconstruir cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm

# 6. Verificar permisos
sudo chown -R www-data:www-data /var/www/clientflow-api/storage
sudo chmod -R 775 /var/www/clientflow-api/storage
```

---

## RESUMEN DE COSTOS ESTIMADOS (AWS)

| Servicio | Tier | Costo mensual aprox. |
|----------|------|---------------------|
| EC2 t3.small | On-Demand | ~$15-18/mes |
| RDS db.t3.micro | On-Demand | ~$15/mes |
| Elastic IP | En uso | Gratis |
| RDS Storage (20GB) | gp2 | ~$2/mes |
| **Total** | | **~$32-35/mes** |

> Para reducir costos: usa Reserved Instances (1 año) → 40% de ahorro.
