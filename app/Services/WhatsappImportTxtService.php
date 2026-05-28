<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 📥 Importa exports .txt de WhatsApp Business app del celular a Kivox.
 *
 * Soporta los 2 formatos típicos:
 *   - Android: "12/03/24, 10:45 - Juan Pérez: Hola"
 *   - iOS:     "[12/03/24, 10:45:32] Juan Pérez: Hola"
 *
 * Maneja:
 *   - Mensajes multilínea (líneas siguientes sin patrón de fecha pertenecen al previo)
 *   - <Multimedia omitido> / <Media omitted> → tipo image|audio|video|document
 *   - Líneas de sistema (cifrado, llamada, cambios de grupo) → se ignoran
 *   - Detección automática de participantes
 */
class WhatsappImportTxtService
{
    /**
     * Línea de fecha — captura los dos formatos.
     * Grupos: [1]=fecha, [2]=hora, [3]=autor, [4]=mensaje
     */
    private const REGEX_LINEA = '/^\[?(\d{1,2}\/\d{1,2}\/\d{2,4}),?\s+(\d{1,2}:\d{2}(?::\d{2})?\s*(?:[ap]\.?\s*m\.?)?)\]?\s*[-–]?\s*([^:]+?):\s?(.*)$/iu';

    /**
     * Línea de sistema (sin autor real). Lo ignoramos.
     * Ej: "12/03/24, 10:45 - Los mensajes y las llamadas están cifrados..."
     */
    private const REGEX_SISTEMA = '/^\[?\d{1,2}\/\d{1,2}\/\d{2,4},?\s+\d{1,2}:\d{2}(?::\d{2})?\s*(?:[ap]\.?\s*m\.?)?\]?\s*[-–]?\s+(?!.*:)/iu';

    /**
     * Lee un archivo .txt y devuelve el resultado del parseo:
     *   ['participantes' => ['Juan', 'Yo'], 'mensajes' => [...], 'rango' => [from, to]]
     */
    public function parsearArchivo(string $rutaAbsoluta): array
    {
        $contenido = @file_get_contents($rutaAbsoluta);
        if ($contenido === false) {
            throw new \RuntimeException("No se pudo leer el archivo: {$rutaAbsoluta}");
        }
        return $this->parsearContenido($contenido);
    }

    public function parsearContenido(string $contenido): array
    {
        // Normalizar saltos de línea, quitar BOM
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);

        // Algunos exports usan espacio no-rompible U+202F entre hora y AM/PM
        $contenido = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $contenido);

        $lineas = explode("\n", $contenido);

        $mensajes = [];
        $actual = null;
        $participantes = [];

        foreach ($lineas as $linea) {
            // Si matchea una nueva línea de mensaje
            if (preg_match(self::REGEX_LINEA, $linea, $m)) {
                // Cerrar el mensaje anterior
                if ($actual !== null) {
                    $mensajes[] = $actual;
                }

                $autor = trim($m[3]);
                // Algunas líneas de sistema matchean el regex pero el "autor" en realidad
                // es algo como "Te uniste a este grupo". Filtramos heurístico.
                if ($this->esLineaDeSistema($autor, $m[4] ?? '')) {
                    $actual = null;
                    continue;
                }

                $fecha = $this->parsearFecha($m[1], $m[2]);
                $contenidoMsg = trim($m[4] ?? '');

                $tipo = 'text';
                $meta = [];

                // Detectar multimedia omitida
                if ($this->esMultimedia($contenidoMsg, $tipo, $meta)) {
                    // tipo y meta ya seteados por referencia
                }

                $actual = [
                    'autor'     => $autor,
                    'fecha'     => $fecha,
                    'tipo'      => $tipo,
                    'contenido' => $contenidoMsg,
                    'meta'      => $meta,
                ];

                if (!in_array($autor, $participantes, true)) {
                    $participantes[] = $autor;
                }
            } else {
                // Línea de continuación del mensaje anterior
                if ($actual !== null && trim($linea) !== '') {
                    $actual['contenido'] .= "\n" . $linea;
                }
            }
        }

        if ($actual !== null) {
            $mensajes[] = $actual;
        }

        // Determinar rango temporal
        $rango = ['from' => null, 'to' => null];
        if (!empty($mensajes)) {
            $rango['from'] = $mensajes[0]['fecha'];
            $rango['to']   = end($mensajes)['fecha'];
        }

        return [
            'participantes' => $participantes,
            'mensajes'      => $mensajes,
            'total'         => count($mensajes),
            'rango'         => $rango,
        ];
    }

    /**
     * Importa el resultado parseado a la BD del tenant.
     *
     * @param array  $parseado  Resultado de parsearContenido()
     * @param int    $tenantId
     * @param string $autorYo   Nombre EXACTO del autor que representa al merchant ("yo")
     * @param string $telefonoCliente Teléfono normalizado del cliente (solo dígitos)
     * @param string $nombreCliente   Nombre a usar si hay que crear cliente nuevo
     * @param ?int   $connectionId    Conexión TecnoByteApp asociada (opcional)
     * @param string $fuente          Etiqueta de fuente_importacion
     */
    public function importar(
        array $parseado,
        int $tenantId,
        string $autorYo,
        string $telefonoCliente,
        string $nombreCliente = '',
        ?int $connectionId = null,
        string $fuente = 'wa_export'
    ): array {
        $tel = preg_replace('/\D+/', '', $telefonoCliente);
        if (!$tel || strlen($tel) < 7) {
            throw new \InvalidArgumentException('Teléfono de cliente inválido.');
        }

        // 1) Upsert cliente
        $cliente = Cliente::where('tenant_id', $tenantId)
            ->where('telefono_normalizado', $tel)
            ->first();

        $creadoCliente = false;
        if (!$cliente) {
            $cliente = Cliente::create([
                'tenant_id'            => $tenantId,
                'nombre'               => trim($nombreCliente) ?: 'Cliente',
                'pais_codigo'          => '+57',
                'telefono'             => substr($tel, -10),
                'telefono_normalizado' => $tel,
                'canal_origen'         => 'whatsapp_import_txt',
                'activo'               => true,
            ]);
            $creadoCliente = true;
        } elseif ($nombreCliente && (empty($cliente->nombre) || $cliente->nombre === 'Cliente')) {
            $cliente->update(['nombre' => trim($nombreCliente)]);
        }

        // 2) Upsert conversación
        $conv = ConversacionWhatsapp::where('tenant_id', $tenantId)
            ->where('telefono_normalizado', $tel)
            ->first();

        $creadaConv = false;
        if (!$conv) {
            $conv = ConversacionWhatsapp::create([
                'tenant_id'            => $tenantId,
                'cliente_id'           => $cliente->id,
                'telefono_normalizado' => $tel,
                'canal'                => 'whatsapp',
                'connection_id'        => $connectionId,
                'estado'               => 'activa',
                'no_leidos'            => 0,
            ]);
            $creadaConv = true;
        } elseif (!$conv->cliente_id) {
            $conv->update(['cliente_id' => $cliente->id]);
        }

        // 3) Insertar mensajes (omitir duplicados por hash contenido+fecha)
        // Como los exports NO traen IDs externos, usamos un hash determinístico
        // como external_id para soportar re-importar el mismo archivo idempotente.
        $existentes = DB::table('mensajes_whatsapp')
            ->where('conversacion_id', $conv->id)
            ->where('importado_historico', true)
            ->whereNotNull('mensaje_externo_id')
            ->pluck('mensaje_externo_id')
            ->flip()
            ->toArray();

        $insertados = 0;
        $omitidos   = 0;
        $primer = null;
        $ultimo = null;
        $rows = [];
        $now  = now();

        foreach ($parseado['mensajes'] as $m) {
            $esMio = ($m['autor'] === $autorYo);
            $rol   = $esMio ? 'assistant' : 'user';

            $extId = 'imp:' . md5($m['fecha'] . '|' . $m['autor'] . '|' . mb_substr($m['contenido'], 0, 200));
            if (isset($existentes[$extId])) {
                $omitidos++;
                continue;
            }

            $fecha = $m['fecha'] ?: $now;
            $meta = array_merge($m['meta'] ?? [], [
                'autor_original' => $m['autor'],
                'importado_at'   => $now->toIso8601String(),
            ]);

            $rows[] = [
                'conversacion_id'     => $conv->id,
                'rol'                 => $rol,
                'tipo'                => $m['tipo'] ?? 'text',
                'contenido'           => mb_substr((string) $m['contenido'], 0, 65000),
                'meta'                => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'mensaje_externo_id'  => $extId,
                'importado_historico' => true,
                'fuente_importacion'  => $fuente,
                'created_at'          => $fecha,
                'updated_at'          => $fecha,
            ];

            $insertados++;
            if ($primer === null || $fecha < $primer) $primer = $fecha;
            if ($ultimo === null || $fecha > $ultimo) $ultimo = $fecha;

            // Insertar por lotes de 500 para no saturar memoria
            if (count($rows) >= 500) {
                DB::table('mensajes_whatsapp')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('mensajes_whatsapp')->insert($rows);
        }

        // 4) Actualizar contadores y rango de la conversación
        if ($insertados > 0) {
            $update = [
                'total_mensajes' => DB::raw("COALESCE(total_mensajes,0) + {$insertados}"),
            ];
            if ($primer && (!$conv->primer_mensaje_at || $primer < $conv->primer_mensaje_at)) {
                $update['primer_mensaje_at'] = $primer;
            }
            if ($ultimo && (!$conv->ultimo_mensaje_at || $ultimo > $conv->ultimo_mensaje_at)) {
                $update['ultimo_mensaje_at'] = $ultimo;
            }
            $conv->update($update);
        }

        Log::info('📥 Histórico WhatsApp importado', [
            'tenant_id'      => $tenantId,
            'cliente_id'     => $cliente->id,
            'conv_id'        => $conv->id,
            'insertados'     => $insertados,
            'omitidos'       => $omitidos,
            'autor_yo'       => $autorYo,
            'fuente'         => $fuente,
        ]);

        return [
            'cliente_id'      => $cliente->id,
            'cliente_creado'  => $creadoCliente,
            'conversacion_id' => $conv->id,
            'conv_creada'     => $creadaConv,
            'insertados'      => $insertados,
            'omitidos'        => $omitidos,
            'primer'          => $primer,
            'ultimo'          => $ultimo,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function parsearFecha(string $fecha, string $hora): string
    {
        // Limpieza
        $hora = trim(strtolower($hora));
        $hora = str_replace(['a. m.', 'a.m.', 'a m'], 'am', $hora);
        $hora = str_replace(['p. m.', 'p.m.', 'p m'], 'pm', $hora);

        // Intentar formatos comunes (en orden de probabilidad para LATAM)
        $candidatos = [
            'd/m/y H:i:s',  'd/m/y H:i',
            'd/m/Y H:i:s',  'd/m/Y H:i',
            'd/m/y g:i a',  'd/m/y g:i:s a',
            'd/m/Y g:i a',  'd/m/Y g:i:s a',
            'm/d/y H:i:s',  'm/d/y H:i',
            'm/d/Y H:i:s',  'm/d/Y H:i',
            'm/d/y g:i a',  'm/d/y g:i:s a',
            'm/d/Y g:i a',  'm/d/Y g:i:s a',
        ];

        $str = "{$fecha} {$hora}";
        foreach ($candidatos as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $str);
                if ($c && $c->year >= 2010 && $c->year <= 2100) {
                    return $c->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Fallback: strtotime
        $ts = strtotime($str);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }

        return now()->format('Y-m-d H:i:s');
    }

    private function esLineaDeSistema(string $autor, string $contenido): bool
    {
        $autorLower = mb_strtolower($autor);
        $patrones = [
            'los mensajes y las llamadas',
            'messages and calls are end-to-end',
            'creó este grupo',
            'created group',
            'añadió',
            'added',
            'cambió el ícono',
            'changed the group',
            'se unió',
            'joined using',
        ];
        foreach ($patrones as $p) {
            if (str_contains($autorLower, $p)) return true;
        }
        // Si el "autor" es muy largo (> 60 chars) probablemente es una línea de sistema
        // mal parseada (no había ':' real, era un texto que casualmente tenía ':').
        if (mb_strlen($autor) > 60) return true;

        return false;
    }

    /**
     * Detecta si el contenido es un placeholder de multimedia y setea tipo+meta.
     */
    private function esMultimedia(string $contenido, string &$tipo, array &$meta): bool
    {
        $c = mb_strtolower($contenido);

        if (preg_match('/<(?:multimedia omitido|media omitted|imagen omitida|image omitted|video omitido|video omitted|audio omitido|audio omitted|documento omitido|document omitted|sticker omitido|sticker omitted|gif omitido)>/i', $contenido)) {
            if (str_contains($c, 'imagen') || str_contains($c, 'image')) {
                $tipo = 'image';
            } elseif (str_contains($c, 'video')) {
                $tipo = 'video';
            } elseif (str_contains($c, 'audio')) {
                $tipo = 'audio';
            } elseif (str_contains($c, 'documento') || str_contains($c, 'document')) {
                $tipo = 'document';
            } elseif (str_contains($c, 'sticker')) {
                $tipo = 'sticker';
            } else {
                $tipo = 'media';
            }
            $meta['media_placeholder'] = true;
            return true;
        }

        // iOS: <adjunto: nombre.jpg>  o  "documento.pdf (archivo adjunto)"
        if (preg_match('/<adjunto:\s*([^>]+)>/iu', $contenido, $mm)
            || preg_match('/^(.+?)\s*\(archivo adjunto\)$/iu', $contenido, $mm)) {
            $nombre = trim($mm[1]);
            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            $tipo = match (true) {
                in_array($ext, ['jpg','jpeg','png','webp','gif']) => 'image',
                in_array($ext, ['mp4','mov','3gp','avi'])         => 'video',
                in_array($ext, ['opus','ogg','mp3','m4a','wav'])  => 'audio',
                in_array($ext, ['pdf','docx','xlsx','txt'])       => 'document',
                default                                             => 'media',
            };
            $meta['media_nombre'] = $nombre;
            return true;
        }

        return false;
    }
}
