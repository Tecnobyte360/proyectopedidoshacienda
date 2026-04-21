#!/bin/bash
###############################################################################
# aplicar-tenant-subdomain.sh
#
# Watcher que corre EN EL HOST (no en el contenedor Docker).
# Lee los .conf que Laravel deja en storage/app/nginx-tenants/, los aplica
# a /etc/nginx/sites-enabled/, valida nginx, recarga, y corre certbot.
#
# Instalación:
#   sudo cp bin/aplicar-tenant-subdomain.sh /usr/local/bin/
#   sudo chmod +x /usr/local/bin/aplicar-tenant-subdomain.sh
#
# Cron (cada minuto):
#   sudo crontab -e
#   * * * * * /usr/local/bin/aplicar-tenant-subdomain.sh >> /var/log/aplicar-tenant.log 2>&1
#
# Variables (ajusta a tu setup):
###############################################################################

set -e

# Carpeta donde Laravel deja los .conf + .conf.pending
PENDING_DIR="/srv/proyectopedidoshacienda/storage/app/nginx-tenants"

# Carpeta de Nginx donde se aplican
NGINX_DIR="/etc/nginx/sites-enabled"

# Log
LOG_TAG="[aplicar-tenant]"

# ---- nada más que tocar ----

if [ ! -d "$PENDING_DIR" ]; then
    exit 0
fi

# Sólo procesar archivos .conf.pending (marcador de "listo para aplicar")
shopt -s nullglob
for FLAG in "$PENDING_DIR"/*.conf.pending; do
    CONF="${FLAG%.pending}"
    DOMINIO=$(basename "$CONF" .conf)

    if [ ! -f "$CONF" ]; then
        echo "$LOG_TAG falta el .conf de $FLAG, borro flag"
        rm -f "$FLAG"
        continue
    fi

    echo "$LOG_TAG === Procesando $DOMINIO ==="

    # Leer email y no_ssl del JSON
    EMAIL=$(grep -oP '"email"\s*:\s*"\K[^"]+' "$FLAG" || echo "")
    NO_SSL=$(grep -oP '"no_ssl"\s*:\s*\K(true|false)' "$FLAG" || echo "false")

    if [ -z "$EMAIL" ]; then
        EMAIL="comercial@tecnobyte360.com"
    fi

    # 1. Copiar a sites-enabled
    cp "$CONF" "$NGINX_DIR/$DOMINIO.conf"

    # 2. nginx -t
    if ! nginx -t 2>/dev/null; then
        echo "$LOG_TAG ❌ nginx -t falló para $DOMINIO. Quito el conf."
        rm -f "$NGINX_DIR/$DOMINIO.conf"
        mv "$FLAG" "$FLAG.error"
        continue
    fi

    # 3. Reload
    systemctl reload nginx
    echo "$LOG_TAG ✓ Nginx recargado con $DOMINIO"

    # 4. Certbot
    if [ "$NO_SSL" != "true" ]; then
        echo "$LOG_TAG 🔒 Generando SSL para $DOMINIO con email $EMAIL"
        if certbot --nginx -d "$DOMINIO" \
                   --non-interactive --agree-tos \
                   --email "$EMAIL" --redirect 2>&1; then
            echo "$LOG_TAG ✓ SSL OK para $DOMINIO"
        else
            echo "$LOG_TAG ⚠️ Certbot falló para $DOMINIO (revisa manualmente)"
        fi
    else
        echo "$LOG_TAG ⏭️ no_ssl=true, saltando certbot"
    fi

    # 5. Marcar como hecho
    mv "$FLAG" "$FLAG.done"
    echo "$LOG_TAG ✅ $DOMINIO completado"
done

exit 0
