#!/bin/bash
# 🤖 AGI: conversacion_ia.sh
#
# Loop de conversación con IA:
#   1. POST inicio → recibe saludo y lo reproduce
#   2. Loop:
#      a. Graba audio del cliente con detección de silencio
#      b. POST audio → recibe respuesta IA
#      c. Reproduce respuesta
#      d. Si "colgar" → sale del loop
#
# Args AGI estándar (via stdin como variables agi_*)

# Leer variables AGI desde stdin hasta línea vacía
while read line; do
  [ "$line" = "" ] && break
  case "$line" in
    agi_uniqueid:*)  UNIQUEID=$(echo "$line" | cut -d: -f2- | tr -d ' \r') ;;
    agi_callerid:*)  CALLERID=$(echo "$line" | cut -d: -f2- | tr -d ' \r') ;;
  esac
done

API_URL="${KIVOX_API_URL:-https://admin.kivox.co/api/v1}"
API_KEY="${KIVOX_API_KEY:-cambia-esto}"

agi_exec() {
  echo "$1"
  read RESP
}

agi_log() {
  echo "VERBOSE \"[IA] $1\" 1"
  read _
}

# 1. Iniciar conversación + reproducir saludo
agi_log "Iniciando conversación con IA para $CALLERID"

INIT=$(curl -sS -m 8 -X POST \
  -H "X-API-KEY: $API_KEY" -H "Content-Type: application/json" \
  -d "{\"unique_id\":\"$UNIQUEID\",\"caller_id\":\"$CALLERID\"}" \
  "$API_URL/ivr/conversacion/iniciar")

SALUDO_FILE=$(echo "$INIT" | jq -r '.audio_filename // empty')
if [ -z "$SALUDO_FILE" ]; then
  agi_log "ERROR iniciando conversación"
  agi_exec "EXEC Playback custom/asesor_no_disponible"
  exit 0
fi

# Descargar el saludo y reproducirlo
curl -sS -m 10 "$API_URL/ivr/audio/$SALUDO_FILE" -o "/var/lib/asterisk/sounds/custom/${SALUDO_FILE}.wav"
agi_exec "STREAM FILE custom/${SALUDO_FILE} \"\""

# 2. Loop de conversación (máx 15 turnos)
for turno in $(seq 1 15); do
  agi_log "Turno $turno — grabando audio del cliente"
  REC_FILE="/tmp/kivox-ivr/turno_${UNIQUEID//[^a-zA-Z0-9]/_}_t${turno}"

  # ⚡ OPTIMIZADO: silencio 0.8s + timeout 8s (era 2s/10s)
  mkdir -p /tmp/kivox-ivr
  agi_exec "RECORD FILE $REC_FILE wav # 8000 0 s=1"

  # Verificar que el archivo se creó
  if [ ! -f "${REC_FILE}.wav" ]; then
    agi_log "No se grabó audio, saliendo"
    break
  fi

  agi_log "Subiendo audio del turno $turno"
  RESP=$(curl -sS -m 30 -X POST \
    -H "X-API-KEY: $API_KEY" \
    -F "unique_id=$UNIQUEID" \
    -F "audio=@${REC_FILE}.wav" \
    "$API_URL/ivr/conversacion/turno")

  rm -f "${REC_FILE}.wav"

  AUDIO_NAME=$(echo "$RESP" | jq -r '.audio_filename // empty')
  COLGAR=$(echo "$RESP" | jq -r '.colgar // false')
  RESP_TEXT=$(echo "$RESP" | jq -r '.respuesta // empty')

  if [ -z "$AUDIO_NAME" ]; then
    agi_log "ERROR procesando turno: $RESP"
    break
  fi

  agi_log "IA respondió: $RESP_TEXT"

  # Descargar audio respuesta y reproducirlo
  curl -sS -m 15 "$API_URL/ivr/audio/$AUDIO_NAME" -o "/var/lib/asterisk/sounds/custom/${AUDIO_NAME}.wav"
  agi_exec "STREAM FILE custom/${AUDIO_NAME} \"\""

  if [ "$COLGAR" = "true" ]; then
    agi_log "IA decidió colgar"
    break
  fi
done

agi_log "Conversación terminada"
exit 0
