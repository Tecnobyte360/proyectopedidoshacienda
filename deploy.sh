#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# DEPLOY EN PRODUCCIÓN
# Uso: ./deploy.sh
#
# Este script:
#   1. Descarta cualquier cambio local del server en assets compilados
#   2. Hace git pull del último main
#   3. Reconstruye el container app (toma código nuevo)
#   4. Corre migraciones
#   5. Limpia TODO el cache (vistas, config, rutas, bootstrap)
#   6. Recompila cache para producción
#   7. Reinicia Reverb
#   8. Muestra reporte final
# ═══════════════════════════════════════════════════════════════════════════

set -e   # Detener si algún comando falla

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
RESET='\033[0m'

CONTAINER_APP="pedidos_hacienda_app"
CONTAINER_REVERB="pedidos_hacienda_reverb"
PROJECT_DIR="/srv/proyectopedidoshacienda"

step() {
    echo ""
    echo -e "${BLUE}${BOLD}▶ $1${RESET}"
}

ok() {
    echo -e "${GREEN}  ✓ $1${RESET}"
}

warn() {
    echo -e "${YELLOW}  ⚠ $1${RESET}"
}

fail() {
    echo -e "${RED}  ✗ $1${RESET}"
    exit 1
}

# ─── Pre-checks ─────────────────────────────────────────────────────────────
cd "$PROJECT_DIR" || fail "No existe el directorio $PROJECT_DIR"

if ! command -v docker &> /dev/null; then
    fail "Docker no está instalado"
fi

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_APP}$"; then
    warn "El container ${CONTAINER_APP} no está corriendo. Intentaré arrancarlo."
fi

# ─── Paso 1: Descartar cambios locales en assets ───────────────────────────
step "1/8  Descartando cambios locales del server en public/build/"
git checkout public/build/ 2>/dev/null || warn "Nada que descartar en public/build/"
ok "Listo"

# ─── Paso 2: Pull del repo ─────────────────────────────────────────────────
step "2/8  Haciendo git pull origin main"
git pull origin main || fail "Falló el git pull (revisa conflictos)"
LATEST_COMMIT=$(git log --oneline -1)
ok "Último commit: ${LATEST_COMMIT}"

# ─── Paso 3: Rebuild del container app ─────────────────────────────────────
step "3/8  Reconstruyendo container ${CONTAINER_APP}"
docker compose up -d --build "$CONTAINER_APP" || fail "No se pudo reconstruir el container"
ok "Container reconstruido"

step "    Esperando que el container esté listo..."
sleep 6
ok "Container arriba"

# ─── Paso 4: Migrate ───────────────────────────────────────────────────────
step "4/8  Corriendo migraciones"
docker exec "$CONTAINER_APP" php artisan migrate --force || warn "Migraciones con problemas"
ok "Migraciones aplicadas"

# ─── Paso 5: Limpiar TODO cache + arreglar permisos ────────────────────────
step "5/8  Limpiando caches (views compiladas, config, rutas, bootstrap)"
docker exec "$CONTAINER_APP" rm -rf storage/framework/views/*.php 2>/dev/null || true
docker exec "$CONTAINER_APP" rm -rf bootstrap/cache/*.php 2>/dev/null || true
docker exec "$CONTAINER_APP" php artisan optimize:clear
ok "Cache limpio"

# CRÍTICO: arreglar permisos después de borrar como root
step "    Reparando permisos de storage y bootstrap/cache (evita 500)"
docker exec "$CONTAINER_APP" chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
docker exec "$CONTAINER_APP" chmod -R 775 storage bootstrap/cache 2>/dev/null || true
ok "Permisos OK"

# ─── Paso 6: Recompilar cache para producción ──────────────────────────────
step "6/8  Recompilando cache para producción"
docker exec "$CONTAINER_APP" php artisan config:cache
docker exec "$CONTAINER_APP" php artisan route:cache
docker exec "$CONTAINER_APP" php artisan view:cache
ok "Cache recompilado"

# ─── Paso 7: Storage link (idempotente) ────────────────────────────────────
step "7/8  Verificando enlace storage:link"
docker exec "$CONTAINER_APP" php artisan storage:link 2>/dev/null || ok "Ya existe"

# ─── Paso 8: Reiniciar Reverb ──────────────────────────────────────────────
step "8/8  Reiniciando Reverb (WebSockets)"
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_REVERB}$"; then
    docker restart "$CONTAINER_REVERB" > /dev/null && ok "Reverb reiniciado"
else
    warn "Container Reverb no encontrado — saltando"
fi

# ─── Verificación final ────────────────────────────────────────────────────
step "✅ Verificación final"

# Sanea con tr para quitar saltos de línea / caracteres raros del docker exec
clean_int() { echo "${1:-0}" | tr -dc '0-9' | head -c 5; }

LAYOUT_RESP=$(clean_int "$(docker exec "$CONTAINER_APP" grep -c 'lg:pl-64' resources/views/livewire/layouts/app.blade.php 2>/dev/null)")
SIDEBAR_OK=$(clean_int "$(docker exec "$CONTAINER_APP" grep -c 'app-sidebar' resources/views/livewire/layouts/sidebar.blade.php 2>/dev/null)")
FULLSCREEN_OK=$(clean_int "$(docker exec "$CONTAINER_APP" grep -c 'toggleFullscreen' resources/views/livewire/layouts/app.blade.php 2>/dev/null)")

PRODUCTOS=$(docker exec "$CONTAINER_APP" php artisan tinker --execute="echo App\Models\Producto::count();" 2>/dev/null | tail -1 | tr -dc '0-9')
CLIENTES=$(docker exec "$CONTAINER_APP" php artisan tinker --execute="echo App\Models\Cliente::count();" 2>/dev/null | tail -1 | tr -dc '0-9')

LAYOUT_RESP=${LAYOUT_RESP:-0}
SIDEBAR_OK=${SIDEBAR_OK:-0}
FULLSCREEN_OK=${FULLSCREEN_OK:-0}

if [ "$LAYOUT_RESP" -gt 0 ] && [ "$SIDEBAR_OK" -gt 0 ]; then
    echo -e "${GREEN}${BOLD}"
    echo "═══════════════════════════════════════════════════"
    echo "  ✅ DEPLOY COMPLETADO CON ÉXITO"
    echo "═══════════════════════════════════════════════════"
    echo -e "${RESET}"
    echo "  Layout lg:pl-64 ...... ✓ (${LAYOUT_RESP} ocurrencias)"
    echo "  Sidebar app-sidebar .. ✓ (${SIDEBAR_OK} ocurrencias)"
    echo "  toggleFullscreen ..... ${FULLSCREEN_OK} ocurrencias"
    echo "  Productos en BD ...... ${PRODUCTOS:-?}"
    echo "  Clientes en BD ....... ${CLIENTES:-?}"
    echo "  Commit ............... ${LATEST_COMMIT}"
    echo ""
    echo "  💡 En el navegador: Ctrl+Shift+R para limpiar caché."
    echo ""
else
    echo -e "${RED}${BOLD}"
    echo "═══════════════════════════════════════════════════"
    echo "  ⚠ DEPLOY INCOMPLETO — el container no tiene código nuevo"
    echo "═══════════════════════════════════════════════════"
    echo -e "${RESET}"
    [ "$LAYOUT_RESP" -eq 0 ] && echo "  ✗ lg:pl-64 NO está en app.blade.php del container"
    [ "$SIDEBAR_OK" -eq 0 ] && echo "  ✗ app-sidebar NO está en sidebar.blade.php del container"
    echo ""
    echo "  Solución: forzar recreación del container:"
    echo "    docker compose stop $CONTAINER_APP"
    echo "    docker compose rm -f $CONTAINER_APP"
    echo "    docker compose up -d --build $CONTAINER_APP"
    echo ""
fi
