# 📞 Kivox IVR — Asterisk + integración Kivox

Sistema IVR propio que reemplaza el desvío de llamadas del celular Claro a un menú automatizado.

## Arquitectura

```
Celular Claro de Hacienda (+57 320 XXX XXXX)
   ↓ (desvío con código MMI)
Número DID virtual (Twilio / Voxbeam / DIDWW)
   ↓ (SIP trunk)
VPS con Asterisk (este repo)
   ↓ (AGI → HTTPS)
API Kivox (consulta pedidos, registra llamada, etc.)
```

## Deploy en VPS

### 1. Provisión del servidor

Cualquier VPS Ubuntu 22.04 con IP pública:
- **Mínimo**: 2 vCPU / 4 GB RAM / 40 GB disco
- **Recomendado**: Hetzner CX22 (€5/mes) o DigitalOcean Droplet ($12/mes)

### 2. Abrir puertos en firewall

```bash
sudo ufw allow 5060/udp     # SIP signaling
sudo ufw allow 5060/tcp
sudo ufw allow 10000:20000/udp  # RTP media
sudo ufw allow 22/tcp       # SSH
sudo ufw enable
```

### 3. Instalar Docker

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker
```

### 4. Clonar y configurar

```bash
git clone https://github.com/Tecnobyte360/proyectopedidoshacienda.git
cd proyectopedidoshacienda/ivr

# Editar credenciales reales:
nano config/pjsip.conf
# - Reemplazar TU_USUARIO_TWILIO y TU_PASSWORD_TWILIO
# - Cambiar external_media_address por IP pública del VPS

nano config/extensions.conf
# - Reemplazar KIVOX_API_KEY con la API key real de Kivox

# Variables para los AGI scripts:
cat > .env <<EOF
KIVOX_API_URL=https://admin.kivox.co/api/v1
KIVOX_API_KEY=tu-api-key-real
OPENAI_API_KEY=sk-...
EOF
```

### 5. Levantar Asterisk

```bash
docker compose up -d
docker compose logs -f asterisk
```

### 6. Verificar conexión SIP

```bash
docker exec -it kivox_ivr asterisk -rvvv
# Dentro de la consola:
pjsip show registrations
pjsip show endpoints
```

Debes ver `twilio-out` con estado `Registered`.

### 7. Activar desvío en celular Claro

Desde el marcador del celular de Hacienda:
- Desvío incondicional: `**21*+1XXXXXXXXXX#` (número Twilio en formato E.164)
- Solo cuando no contesta: `**61*+1XXXXXXXXXX*11*15#` (15 seg)
- Cancelar: `##002#`

## Generar audios TTS

```bash
# Desde tu PC con OPENAI_API_KEY en env:
./scripts/generar_audios.sh
# Sube los WAVs al VPS:
scp sounds/*.wav root@vps:/srv/kivox-ivr/sounds/
```

O usar el helper `agi_say()` que genera y cachea audios on-the-fly.

## Monitoreo

- Logs Asterisk: `docker compose logs -f`
- Logs AGI: `tail -f logs/agi.log`
- Panel Kivox: https://admin.kivox.co/ivr

## Costos estimados (500 llamadas/mes × 2 min)

| Item | Costo |
|---|---|
| Twilio número Colombia | USD $1.50/mes |
| Twilio minutos entrantes (1000 min) | USD $13/mes |
| OpenAI TTS-1-HD (50 audios únicos × 200 chars) | USD $0.15/mes |
| VPS Hetzner CX22 | €5/mes (~USD $5.50) |
| **TOTAL** | **~USD $20/mes** |

## Troubleshooting

- **"No SIP registration"** → revisar firewall + credenciales en `pjsip.conf`
- **"Audio en una sola dirección"** → `external_media_address` mal configurado o NAT
- **"AGI script no encontrado"** → permisos de ejecución: `chmod +x agi/*.php`
- **"Llamadas no llegan al IVR"** → revisar contexto `from-twilio` y match del DID en extensions.conf
