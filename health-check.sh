#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# HEALTH CHECK DE PRODUCCIÓN
# Uso: ./health-check.sh
#
# Diagnostica el estado del sistema:
#   - Containers Docker
#   - Sincronía host ↔ container
#   - Configuración crítica (.env, storage:link)
#   - Estado de Reverb
#   - Logs recientes
# ═══════════════════════════════════════════════════════════════════════════

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
RESET='\033[0m'

CONTAINER_APP="pedidos_hacienda_app"
CONTAINER_REVERB="pedidos_hacienda_reverb"
CONTAINER_DB="pedidos_hacienda_db"
PROJECT_DIR="/srv/proyectopedidoshacienda"

ok()   { echo -e "  ${GREEN}✓${RESET} $1"; }
warn() { echo -e "  ${YELLOW}⚠${RESET} $1"; }
err()  { echo -e "  ${RED}✗${RESET} $1"; }
hdr()  { echo ""; echo -e "${BLUE}${BOLD}━━ $1 ━━${RESET}"; }

cd "$PROJECT_DIR" 2>/dev/null || { err "No existe $PROJECT_DIR"; exit 1; }

# ─── Containers ─────────────────────────────────────────────────────────────
hdr "1. Containers Docker"

for c in "$CONTAINER_APP" "$CONTAINER_REVERB" "$CONTAINER_DB"; do
    if docker ps --format '{{.Names}}' | grep -q "^${c}$"; then
        STATUS=$(docker ps --filter "name=^${c}$" --format '{{.Status}}')
        ok "${c} → ${STATUS}"
    else
        err "${c} NO está corriendo"
    fi
done

# ─── Git ────────────────────────────────────────────────────────────────────
hdr "2. Git en el host"

CURRENT_BRANCH=$(git branch --show-current 2>/dev/null)
LATEST_COMMIT=$(git log --oneline -1 2>/dev/null)
ok "Branch: ${CURRENT_BRANCH}"
ok "Commit: ${LATEST_COMMIT}"

UNTRACKED=$(git status --short 2>/dev/null | wc -l)
if [ "$UNTRACKED" -gt 0 ]; then
    warn "Hay ${UNTRACKED} cambio(s) local(es) sin commit:"
    git status --short
else
    ok "Sin cambios locales pendientes"
fi

# ─── Sincronía host ↔ container ─────────────────────────────────────────────
hdr "3. Sincronía host ↔ container app"

CHECKS=(
    "md:pl-64|resources/views/livewire/layouts/app.blade.php|Layout responsive"
    "registrarStore|resources/views/livewire/layouts/app.blade.php|Alpine store"
    "BotPromptService|app/Services/BotPromptService.php|Servicio prompt"
    "encontrarOCrearPorTelefono|app/Models/Cliente.php|Modelo Cliente"
    "info_empresa|app/Models/ConfiguracionBot.php|Config bot info_empresa"
)

for check in "${CHECKS[@]}"; do
    IFS='|' read -r needle file label <<< "$check"

    HOST_COUNT=$(grep -c "$needle" "$file" 2>/dev/null || echo 0)
    CONT_COUNT=$(docker exec "$CONTAINER_APP" grep -c "$needle" "$file" 2>/dev/null || echo 0)

    if [ "$HOST_COUNT" -eq "$CONT_COUNT" ] && [ "$HOST_COUNT" -gt 0 ]; then
        ok "${label} (host: ${HOST_COUNT}, container: ${CONT_COUNT})"
    elif [ "$HOST_COUNT" -gt 0 ] && [ "$CONT_COUNT" -eq 0 ]; then
        err "${label} → HOST tiene, CONTAINER NO. ¡Hay que hacer rebuild!"
    else
        warn "${label} → host: ${HOST_COUNT}, container: ${CONT_COUNT}"
    fi
done

# ─── Configuración crítica ──────────────────────────────────────────────────
hdr "4. Configuración crítica"

# storage:link
if docker exec "$CONTAINER_APP" test -L public/storage 2>/dev/null; then
    ok "storage:link existe"
else
    err "storage:link NO existe — corre: docker exec $CONTAINER_APP php artisan storage:link"
fi

# .env keys importantes
for key in "APP_URL" "APP_KEY" "OPENAI_API_KEY" "API_KEY" "BROADCAST_DRIVER" "REVERB_APP_KEY"; do
    VAL=$(docker exec "$CONTAINER_APP" sh -c "grep ^${key}= .env 2>/dev/null | cut -d= -f2- | head -c 30")
    if [ -n "$VAL" ]; then
        ok "${key} configurado (${VAL}...)"
    else
        warn "${key} NO está en .env"
    fi
done

# ─── BD ─────────────────────────────────────────────────────────────────────
hdr "5. Datos en BD"

PRODUCTOS=$(docker exec "$CONTAINER_APP" php artisan tinker --execute="echo App\Models\Producto::count();" 2>/dev/null | tail -1)
CLIENTES=$(docker exec "$CONTAINER_APP" php artisan tinker --execute="echo App\Models\Cliente::count();" 2>/dev/null | tail -1)
PEDIDOS=$(docker exec "$CONTAINER_APP" php artisan tinker --execute="echo App\Models\Pedido::count();" 2>/dev/null | tail -1)
ZONAS=$(docker exec "$CONTAINER_APP" php artisan tinker --execute="echo App\Models\ZonaCobertura::count();" 2>/dev/null | tail -1)

ok "Productos: ${PRODUCTOS}"
ok "Clientes:  ${CLIENTES}"
ok "Pedidos:   ${PEDIDOS}"
ok "Zonas:     ${ZONAS}"

# ─── Logs recientes ─────────────────────────────────────────────────────────
hdr "6. Errores recientes en Laravel (últimos 5)"

ERRORS=$(docker exec "$CONTAINER_APP" tail -200 storage/logs/laravel.log 2>/dev/null | grep "local.ERROR" | tail -5)
if [ -z "$ERRORS" ]; then
    ok "Sin errores recientes"
else
    echo "$ERRORS" | head -c 800
    echo ""
fi

# ─── Resumen ────────────────────────────────────────────────────────────────
hdr "Resumen"
echo ""
echo "  📋 Comandos útiles:"
echo "     ./deploy.sh            → Deploy completo de cambios nuevos"
echo "     ./health-check.sh      → Este chequeo"
echo "     docker logs $CONTAINER_APP --tail 50"
echo "     docker exec $CONTAINER_APP php artisan tinker"
echo ""
