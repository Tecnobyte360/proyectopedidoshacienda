# 🚀 Deploy en producción

## Primera vez (solo una vez en el server)

```bash
cd /srv/proyectopedidoshacienda
chmod +x deploy.sh health-check.sh
```

## Cada despliegue (workflow normal)

### 1️⃣ En tu local (Windows)

Después de hacer cambios:

```bash
cd /c/xampp/htdocs/laravel-websocket-demo
npm run build
git add -A
git commit -m "tu mensaje"
git push origin main
```

### 2️⃣ En el servidor

```bash
cd /srv/proyectopedidoshacienda
./deploy.sh
```

Eso es todo. El script hace:
1. Descarta cualquier cambio local en `public/build/`
2. `git pull origin main`
3. Rebuild del container app
4. Migraciones (`php artisan migrate --force`)
5. Limpia TODO cache (vistas compiladas, config, rutas, bootstrap)
6. Recompila cache para producción
7. Verifica `storage:link`
8. Reinicia Reverb
9. Reporte final con verificación

### 3️⃣ En el navegador

**Ctrl + Shift + R** (hard refresh) para limpiar caché del browser.
O modo incógnito (Ctrl+Shift+N) si quieres descartar 100% caché.

---

## 🔍 Si algo falla — health check

```bash
cd /srv/proyectopedidoshacienda
./health-check.sh
```

Te dice:
- Estado de los containers Docker
- Sincronía host ↔ container (clave para detectar Docker desactualizado)
- `.env` con las variables críticas configuradas
- Conteos en BD (productos, clientes, pedidos, zonas)
- Últimos 5 errores en logs de Laravel

---

## 🐛 Problemas comunes

### ⚠️ "Your local changes to public/build/manifest.json would be overwritten"

```bash
git checkout public/build/
git pull origin main
```

### ⚠️ El container tiene código viejo aunque hiciste `git pull`

Tu Dockerfile usa `COPY . .`, así que necesitas recrear:

```bash
docker compose stop pedidos_hacienda_app
docker compose rm -f pedidos_hacienda_app
docker compose up -d --build pedidos_hacienda_app
```

### ⚠️ Sidebar/topbar no se ven aunque el container está actualizado

Es caché de vistas compiladas:

```bash
docker exec pedidos_hacienda_app rm -rf storage/framework/views/*.php bootstrap/cache/*.php
docker exec pedidos_hacienda_app php artisan optimize:clear
docker exec pedidos_hacienda_app php artisan view:cache
```

Y en el navegador: **Ctrl+Shift+R** o modo incógnito.

### ⚠️ Las imágenes subidas no se ven (404)

Falta el storage:link:

```bash
docker exec pedidos_hacienda_app php artisan storage:link
```

### ⚠️ El bot no responde en tiempo real (no llegan pedidos al admin)

Reverb no está corriendo:

```bash
docker logs pedidos_hacienda_reverb --tail 30
docker restart pedidos_hacienda_reverb
```

### ⚠️ La API REST devuelve 500 "API_KEY no configurada"

Configura el `.env` del container:

```bash
docker exec pedidos_hacienda_app sh -c "grep API_KEY .env || echo 'API_KEY=$(openssl rand -hex 32)' >> .env"
docker exec pedidos_hacienda_app php artisan config:cache
```

---

## 📦 Estructura del sistema

| Container | Puerto | Función |
|---|---|---|
| `pedidos_hacienda_app` | 127.0.0.1:8088 | Laravel + PHP-FPM |
| `pedidos_hacienda_reverb` | 127.0.0.1:8092 | WebSockets (real-time) |
| `pedidos_hacienda_db` | 127.0.0.1:3311 | MySQL 8 |
| `vulcanos_nginx` | :8086 | Reverse proxy → app |

---

## 🔑 Variables de entorno mínimas

En el `.env` del container:

```
APP_URL=https://pedidosonline.tecnobyte360.com
APP_KEY=base64:...
OPENAI_API_KEY=sk-proj-...
API_KEY=...                         # Para API REST externa
BROADCAST_DRIVER=reverb
REVERB_APP_KEY=app-key
REVERB_HOST=pedidosonline.tecnobyte360.com
```
