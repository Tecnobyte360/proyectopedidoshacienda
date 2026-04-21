#!/bin/bash
###############################################################################
# tenant-subdomain-watcher.sh
#
# Watcher REACTIVO con inotifywait. Reacciona en ~1 segundo cuando Laravel
# deja un .conf.pending nuevo. Reemplaza al cron de aplicar-tenant-subdomain.sh.
#
# Instalación:
#   sudo apt-get install -y inotify-tools
#   sudo cp bin/tenant-subdomain-watcher.sh   /usr/local/bin/
#   sudo cp bin/aplicar-tenant-subdomain.sh   /usr/local/bin/
#   sudo cp bin/tenant-subdomain-watcher.service /etc/systemd/system/
#   sudo chmod +x /usr/local/bin/tenant-subdomain-watcher.sh
#   sudo chmod +x /usr/local/bin/aplicar-tenant-subdomain.sh
#   sudo systemctl daemon-reload
#   sudo systemctl enable --now tenant-subdomain-watcher
#   sudo systemctl status tenant-subdomain-watcher
###############################################################################

set -e

PENDING_DIR="/srv/proyectopedidoshacienda/storage/app/nginx-tenants"
APLICAR="/usr/local/bin/aplicar-tenant-subdomain.sh"

# Asegurar que la carpeta exista (si no, crearla con permisos amplios)
mkdir -p "$PENDING_DIR"

echo "[watcher] Iniciado. Vigilando $PENDING_DIR"

# Procesar pendientes que ya estén ahí al arrancar (por si Laravel escribió antes)
"$APLICAR" || true

# Loop reactivo: cada vez que se cierra un archivo escrito (close_write) o se mueve a este dir
inotifywait -m -e close_write,moved_to --format '%f' "$PENDING_DIR" | while read FILE; do
    case "$FILE" in
        *.conf.pending)
            echo "[watcher] Detectado nuevo: $FILE"
            # Pequeño delay para asegurar que el .conf también está escrito
            sleep 1
            "$APLICAR" || echo "[watcher] aplicar-tenant-subdomain.sh devolvió error"
            ;;
    esac
done
