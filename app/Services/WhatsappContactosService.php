<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConversacionWhatsapp;
use App\Services\TenantManager;
use App\Services\WhatsappResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa contactos del WhatsApp del tenant hacia la tabla `clientes`.
 *
 * Funciona contra la API de TecnoByteApp (whatsapp-web.js wrapper).
 * Intenta varios endpoints conocidos y se queda con el que responda OK.
 */
class WhatsappContactosService
{
    /**
     * Obtiene la lista de contactos del WhatsApp activo del tenant actual.
     * Devuelve un array de ['nombre', 'telefono', 'foto'].
     */
    public function listarContactos(?int $connectionId = null): array
    {
        $resolver = app(WhatsappResolverService::class);
        $token = $resolver->token();
        $cred  = $resolver->credenciales();

        if (!$token) {
            throw new \RuntimeException('No hay token de WhatsApp activo. Verifica que la conexión esté escaneada.');
        }

        if (!$connectionId) {
            $ids = $resolver->connectionIdsValidos();
            $connectionId = $ids[0] ?? null;
        }

        if (!$connectionId) {
            throw new \RuntimeException('No hay connection_id válido para el tenant.');
        }

        $base = rtrim($cred['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422', '/');

        // Probar muchos endpoints — TecnoByteApp puede exponer cualquiera de ellos.
        // El que funcione gana; el resto cae en 404.
        $intentos = [
            // Patrón estándar whatsapp-web.js wrapper
            "/whatsapp/{$connectionId}/contacts",
            "/whatsapp/{$connectionId}/chats",
            "/whatsapp/{$connectionId}/getContacts",
            "/whatsapp/{$connectionId}/get-contacts",
            "/whatsapps/{$connectionId}/contacts",
            "/whatsapps/{$connectionId}/chats",
            // Con prefijo /api
            "/api/whatsapp/{$connectionId}/contacts",
            "/api/whatsapp/{$connectionId}/chats",
            "/api/whatsapps/{$connectionId}/contacts",
            "/api/whatsapps/{$connectionId}/chats",
            "/api/contacts/{$connectionId}",
            "/api/chats/{$connectionId}",
            // Con query string
            "/contacts?whatsappId={$connectionId}",
            "/contacts?id={$connectionId}",
            "/chats?whatsappId={$connectionId}",
            "/chats?id={$connectionId}",
            "/api/contacts?whatsappId={$connectionId}",
            "/api/chats?whatsappId={$connectionId}",
            // Variantes camelCase
            "/getContacts/{$connectionId}",
            "/getChats/{$connectionId}",
        ];

        $contactos = null;
        $ultimoError = null;

        foreach ($intentos as $path) {
            try {
                $resp = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(60)
                    ->get($base . $path);

                if ($resp->successful()) {
                    $body = $resp->json();
                    $contactos = $this->normalizarRespuesta($body);
                    if (!empty($contactos)) {
                        Log::info('📇 Contactos WhatsApp obtenidos', [
                            'endpoint' => $path,
                            'total'    => count($contactos),
                        ]);
                        break;
                    }
                } else {
                    $ultimoError = "[{$resp->status()}] {$path}: " . mb_substr($resp->body(), 0, 200);
                }
            } catch (\Throwable $e) {
                $ultimoError = "Excepción en {$path}: " . $e->getMessage();
            }
        }

        if ($contactos === null) {
            throw new \RuntimeException(
                "No se pudo obtener contactos de WhatsApp. Último error: " . ($ultimoError ?? 'desconocido')
            );
        }

        return $contactos;
    }

    /**
     * Diagnostica la API de TecnoByteApp probando muchos paths candidatos y
     * devolviendo lo que respondio cada uno. Sirve para descubrir cual es el
     * endpoint correcto sin depender de documentacion externa.
     */
    public function diagnosticarApi(): array
    {
        $resolver = app(WhatsappResolverService::class);
        $token = $resolver->token();
        $cred  = $resolver->credenciales();

        if (!$token) {
            throw new \RuntimeException('No hay token de WhatsApp activo.');
        }

        $ids = $resolver->connectionIdsValidos();
        $cid = $ids[0] ?? null;
        if (!$cid) {
            throw new \RuntimeException('No hay connection_id valido.');
        }

        $base = rtrim($cred['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422', '/');

        // Lista MUY amplia de candidatos. El que devuelva 200 con JSON gana.
        $paths = [
            // root level
            "/whatsapp/{$cid}",
            "/whatsapps/{$cid}",
            "/api/whatsapp/{$cid}",
            "/api/whatsapps/{$cid}",
            // contacts
            "/whatsapp/{$cid}/contacts",
            "/whatsapp/{$cid}/getContacts",
            "/whatsapp/{$cid}/get-contacts",
            "/whatsapps/{$cid}/contacts",
            "/api/whatsapp/{$cid}/contacts",
            "/api/whatsapps/{$cid}/contacts",
            "/api/contacts/{$cid}",
            "/contacts/{$cid}",
            "/contacts?whatsappId={$cid}",
            "/api/contacts?whatsappId={$cid}",
            // chats
            "/whatsapp/{$cid}/chats",
            "/whatsapp/{$cid}/getChats",
            "/whatsapps/{$cid}/chats",
            "/api/whatsapp/{$cid}/chats",
            "/api/whatsapps/{$cid}/chats",
            "/api/chats/{$cid}",
            "/chats/{$cid}",
            "/chats?whatsappId={$cid}",
            "/api/chats?whatsappId={$cid}",
            // messages
            "/whatsapp/{$cid}/messages",
            "/api/whatsapp/{$cid}/messages",
            "/messages?whatsappId={$cid}",
            "/api/messages?whatsappId={$cid}",
            // routes index (para descubrir lista)
            "/",
            "/api",
            "/api/",
            "/routes",
            "/healthcheck",
            "/whatsapp/",
        ];

        $resultados = [];
        foreach ($paths as $p) {
            $start = microtime(true);
            try {
                $resp = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(8)
                    ->get($base . $p);

                $body = (string) $resp->body();
                $resultados[] = [
                    'path'     => $p,
                    'status'   => $resp->status(),
                    'ms'       => (int) ((microtime(true) - $start) * 1000),
                    'len'      => strlen($body),
                    'preview'  => mb_substr(strip_tags($body), 0, 160),
                    'is_json'  => str_starts_with(trim($body), '{') || str_starts_with(trim($body), '['),
                ];
            } catch (\Throwable $e) {
                $resultados[] = [
                    'path' => $p, 'status' => 'EXC', 'ms' => 0, 'len' => 0,
                    'preview' => $e->getMessage(), 'is_json' => false,
                ];
            }
        }

        // Ordenar: 2xx primero, despues por status
        usort($resultados, function ($a, $b) {
            $sa = is_int($a['status']) ? $a['status'] : 999;
            $sb = is_int($b['status']) ? $b['status'] : 999;
            $ra = $sa >= 200 && $sa < 300 ? 0 : ($sa >= 400 && $sa < 500 ? 1 : 2);
            $rb = $sb >= 200 && $sb < 300 ? 0 : ($sb >= 400 && $sb < 500 ? 1 : 2);
            if ($ra !== $rb) return $ra <=> $rb;
            return $sa <=> $sb;
        });

        return [
            'base'           => $base,
            'connection_id'  => $cid,
            'total_probados' => count($paths),
            'exitosos'       => count(array_filter($resultados, fn ($r) => is_int($r['status']) && $r['status'] >= 200 && $r['status'] < 300)),
            'resultados'     => $resultados,
        ];
    }

    /**
     * Parsea un archivo VCF (vCard) o CSV y devuelve [{nombre, telefono, foto}].
     * Soporta los exports tipicos de:
     *   - Google Contacts (Google CSV)
     *   - iPhone / Android (.vcf con multiples vCards concatenadas)
     *   - WhatsApp Web (no exporta directo, pero usuario puede usar Google Contacts)
     */
    public function parsearArchivo(string $rutaAbsoluta, string $nombreOriginal): array
    {
        $contenido = @file_get_contents($rutaAbsoluta);
        if ($contenido === false || $contenido === '') return [];

        // Limpiar BOM
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if ($ext === 'vcf' || str_contains($contenido, 'BEGIN:VCARD')) {
            return $this->parsearVCF($contenido);
        }
        return $this->parsearCSV($contenido);
    }

    private function parsearVCF(string $contenido): array
    {
        // Normalizar saltos de linea + unfold (vCard permite continuar lineas con espacio inicial)
        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
        $contenido = preg_replace("/\n[ \t]/", '', $contenido);

        $cards = preg_split('/BEGIN:VCARD/i', $contenido);
        $result = [];

        foreach ($cards as $card) {
            if (trim($card) === '') continue;
            $card = strstr($card, 'END:VCARD', true) ?: $card;

            $nombre = '';
            $telefono = '';

            // FN (formatted name) o N (estructurado)
            if (preg_match('/^FN[^:]*:(.+)$/im', $card, $m)) {
                $nombre = trim($m[1]);
            } elseif (preg_match('/^N[^:]*:(.+)$/im', $card, $m)) {
                $partes = explode(';', $m[1]);
                $nombre = trim(($partes[1] ?? '') . ' ' . ($partes[0] ?? ''));
            }

            // TEL — primer numero (preferentemente CELL/MOBILE)
            $tels = [];
            if (preg_match_all('/^TEL[^:]*:(.+)$/im', $card, $mm)) {
                $tels = $mm[1];
            }
            // Decodificar QUOTED-PRINTABLE si aparece
            $tels = array_map(fn ($t) => quoted_printable_decode(trim($t)), $tels);

            if (empty($tels)) continue;
            $telefono = $tels[0];

            $result[] = [
                'nombre'   => $this->utf8Limpio($nombre),
                'telefono' => $telefono,
                'foto'     => null,
            ];
        }
        return $result;
    }

    private function parsearCSV(string $contenido): array
    {
        $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
        $lineas = array_filter(explode("\n", $contenido), fn ($l) => trim($l) !== '');
        if (empty($lineas)) return [];

        // Detectar separador (, o ;)
        $primera = reset($lineas);
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        $headers = str_getcsv($primera, $sep);
        $headers = array_map(fn ($h) => strtolower(trim($h, " \t\"'")), $headers);

        // Indices de columnas (Google Contacts: "Name", "Phone 1 - Value"; CRM: "nombre", "telefono")
        $idxNombre = $this->indiceCSV($headers, ['name', 'nombre', 'full name', 'first name', 'display name']);
        $idxTel    = $this->indiceCSV($headers, ['phone 1 - value', 'phone', 'telefono', 'teléfono', 'mobile', 'celular', 'phone number']);

        // Si no hay header reconocible, asumir que es nombre,telefono sin header
        $tieneHeaders = ($idxNombre !== null || $idxTel !== null);
        if (!$tieneHeaders) {
            $idxNombre = 0;
            $idxTel    = 1;
        }

        $result = [];
        foreach ($lineas as $i => $linea) {
            if ($tieneHeaders && $i === array_key_first($lineas)) continue;
            $cols = str_getcsv($linea, $sep);

            $tel    = trim($cols[$idxTel] ?? '', " \t\"'");
            $nombre = trim($cols[$idxNombre] ?? '', " \t\"'");

            if ($tel === '') continue;
            $result[] = [
                'nombre'   => $this->utf8Limpio($nombre),
                'telefono' => $tel,
                'foto'     => null,
            ];
        }
        return $result;
    }

    private function indiceCSV(array $headers, array $candidatos): ?int
    {
        foreach ($candidatos as $cand) {
            $idx = array_search($cand, $headers, true);
            if ($idx !== false) return $idx;
        }
        return null;
    }

    private function utf8Limpio(string $s): string
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) return $s;
        $det = mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252';
        return mb_convert_encoding($s, 'UTF-8', $det);
    }

    /**
     * Importa directamente un array de contactos ya parseado.
     */
    public function importarLista(array $contactos, bool $actualizarExistentes = false, bool $importarConversaciones = true): array
    {
        return $this->importarContactosArray($contactos, $actualizarExistentes, $importarConversaciones, 'archivo');
    }

    /**
     * Lee contactos de las conversaciones locales del tenant. Estos son los
     * clientes que YA escribieron al bot, no toda la libreta de WhatsApp.
     * Sirve como fallback cuando la API de TecnoByteApp no expone /contacts.
     */
    public function listarContactosLocal(): array
    {
        $tm = app(TenantManager::class);
        $tenantId = (method_exists($tm, 'id') ? $tm->id() : null)
            ?? $tm->current()?->id;

        if (!$tenantId) return [];

        // Tomar telefonos unicos de conversaciones del tenant + intentar
        // sacar pushname del primer mensaje del usuario en meta JSON.
        $rows = DB::table('conversaciones_whatsapp as c')
            ->where('c.tenant_id', $tenantId)
            ->whereNotNull('c.telefono_normalizado')
            ->where('c.telefono_normalizado', '!=', '')
            ->select('c.telefono_normalizado', 'c.id')
            ->orderBy('c.id', 'desc')
            ->get();

        $vistos = [];
        $result = [];
        foreach ($rows as $r) {
            $tel = preg_replace('/\D+/', '', (string) $r->telefono_normalizado);
            if (!$tel || isset($vistos[$tel])) continue;
            $vistos[$tel] = true;

            // Buscar pushname en meta del primer mensaje user de esa conversacion
            $nombre = '';
            $msg = DB::table('mensajes_whatsapp')
                ->where('conversacion_id', $r->id)
                ->where('rol', 'user')
                ->whereNotNull('meta')
                ->orderBy('id')
                ->limit(1)
                ->value('meta');

            if ($msg) {
                $meta = is_string($msg) ? json_decode($msg, true) : $msg;
                if (is_array($meta)) {
                    $nombre = $meta['pushname']
                        ?? $meta['notify']
                        ?? $meta['name']
                        ?? $meta['from_name']
                        ?? '';
                }
            }

            $result[] = [
                'nombre'   => trim((string) $nombre),
                'telefono' => $tel,
                'foto'     => null,
            ];
        }

        return $result;
    }

    /**
     * Importa los contactos al tenant actual y devuelve el conteo.
     * Si la API no expone endpoint de contactos, hace fallback a las
     * conversaciones locales del tenant.
     * Skip = ya existe (no sobreescribe). Update = actualiza nombre/foto si vacios.
     */
    public function importar(?int $connectionId = null, bool $actualizarExistentes = false, bool $importarConversaciones = true): array
    {
        $fuente = 'api';
        try {
            $contactos = $this->listarContactos($connectionId);
        } catch (\Throwable $e) {
            Log::warning('API de contactos no disponible, usando fallback local', [
                'error' => $e->getMessage(),
            ]);
            $contactos = $this->listarContactosLocal();
            $fuente = 'local';

            if (empty($contactos)) {
                throw new \RuntimeException(
                    "No se pudieron obtener contactos. La API no expone endpoint de contactos "
                    . "y tampoco hay conversaciones locales registradas. "
                    . "Detalle API: " . $e->getMessage()
                );
            }
        }

        return $this->importarContactosArray($contactos, $actualizarExistentes, $importarConversaciones, $fuente, $connectionId);
    }

    /**
     * Logica comun de importacion: dado un array de contactos parseados,
     * los crea/actualiza en la tabla clientes y vincula conversaciones.
     */
    private function importarContactosArray(array $contactos, bool $actualizarExistentes, bool $importarConversaciones, string $fuente, ?int $connectionId = null): array
    {
        $tm = app(TenantManager::class);
        $tenant = method_exists($tm, 'current') ? $tm->current() : null;
        $tenantId = $tenant?->id ?? (method_exists($tm, 'id') ? $tm->id() : null);

        if (!$connectionId) {
            $ids = app(WhatsappResolverService::class)->connectionIdsValidos();
            $connectionId = $ids[0] ?? null;
        }

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $errores = 0;
        $convVinculadas = 0;
        $convCreadas = 0;
        $msgsImportados = 0;

        foreach ($contactos as $c) {
            $tel = preg_replace('/\D+/', '', (string) ($c['telefono'] ?? ''));
            if (!$tel || strlen($tel) < 7) {
                $omitidos++;
                continue;
            }

            try {
                $existente = Cliente::where('telefono_normalizado', $tel)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($existente) {
                    if ($importarConversaciones) {
                        $stats = $this->procesarConversacion(
                            $tenantId,
                            $existente->id,
                            $tel,
                            $connectionId
                        );
                        $convVinculadas += $stats['vinculadas'];
                        $convCreadas    += $stats['creadas'];
                        $msgsImportados += $stats['mensajes'];
                    }
                    if ($actualizarExistentes) {
                        $cambios = [];
                        if (empty($existente->nombre) || $existente->nombre === 'Cliente') {
                            $cambios['nombre'] = $c['nombre'] ?: $existente->nombre;
                        }
                        if (empty($existente->profile_pic_url) && !empty($c['foto'])) {
                            $cambios['profile_pic_url'] = $c['foto'];
                        }
                        if (!empty($cambios)) {
                            $existente->update($cambios);
                            $actualizados++;
                        } else {
                            $omitidos++;
                        }
                    } else {
                        $omitidos++;
                    }
                    continue;
                }

                $cliente = Cliente::create([
                    'tenant_id'            => $tenantId,
                    'nombre'               => $c['nombre'] ?: 'Cliente',
                    'pais_codigo'          => '+57',
                    'telefono'             => substr($tel, -10),
                    'telefono_normalizado' => $tel,
                    'canal_origen'         => 'whatsapp_import',
                    'profile_pic_url'      => $c['foto'] ?? null,
                    'activo'               => true,
                ]);
                $creados++;

                // Vincular conversaciones huerfanas + crear/importar mensajes
                if ($importarConversaciones) {
                    $stats = $this->procesarConversacion(
                        $tenantId,
                        $cliente->id,
                        $tel,
                        $connectionId
                    );
                    $convVinculadas += $stats['vinculadas'];
                    $convCreadas    += $stats['creadas'];
                    $msgsImportados += $stats['mensajes'];
                }
            } catch (\Throwable $e) {
                $errores++;
                Log::warning('Error importando contacto', [
                    'tel'   => $tel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total'             => count($contactos),
            'creados'           => $creados,
            'actualizados'      => $actualizados,
            'omitidos'          => $omitidos,
            'errores'           => $errores,
            'fuente'            => $fuente,
            'conv_vinculadas'   => $convVinculadas,
            'conv_creadas'      => $convCreadas,
            'mensajes_imp'      => $msgsImportados,
        ];
    }

    /**
     * Vincula conversaciones huerfanas (cliente_id NULL con mismo telefono) al
     * cliente, y opcionalmente intenta bajar el historial de mensajes desde la API.
     */
    private function procesarConversacion(int $tenantId, int $clienteId, string $tel, ?int $connectionId): array
    {
        $vinculadas = 0;
        $creadas    = 0;
        $mensajes   = 0;

        // 1) Vincular conversaciones existentes con cliente_id NULL
        try {
            $vinculadas = ConversacionWhatsapp::where('tenant_id', $tenantId)
                ->where('telefono_normalizado', $tel)
                ->whereNull('cliente_id')
                ->update(['cliente_id' => $clienteId]);
        } catch (\Throwable $e) {
            Log::warning('Error vinculando conversacion', [
                'tel' => $tel, 'error' => $e->getMessage(),
            ]);
        }

        // 2) Si no existe ninguna conversacion para este telefono, intentar
        //    bajar historial desde la API y crear conversacion + mensajes.
        $existeConv = ConversacionWhatsapp::where('tenant_id', $tenantId)
            ->where('telefono_normalizado', $tel)
            ->exists();

        if (!$existeConv && $connectionId) {
            $msgs = $this->bajarMensajesAPI($connectionId, $tel);
            if (!empty($msgs)) {
                try {
                    $conv = ConversacionWhatsapp::create([
                        'tenant_id'            => $tenantId,
                        'cliente_id'           => $clienteId,
                        'telefono_normalizado' => $tel,
                        'canal'                => 'whatsapp',
                        'connection_id'        => $connectionId,
                        'estado'               => ConversacionWhatsapp::ESTADO_ACTIVA,
                        'no_leidos'            => 0,
                        'total_mensajes'       => count($msgs),
                        'primer_mensaje_at'    => $msgs[0]['fecha'] ?? now(),
                        'ultimo_mensaje_at'    => end($msgs)['fecha'] ?? now(),
                    ]);
                    $creadas++;

                    foreach ($msgs as $m) {
                        DB::table('mensajes_whatsapp')->insert([
                            'conversacion_id'    => $conv->id,
                            'rol'                => $m['rol'],
                            'tipo'               => $m['tipo'] ?? 'text',
                            'contenido'          => mb_substr((string) ($m['contenido'] ?? ''), 0, 65000),
                            'mensaje_externo_id' => $m['external_id'] ?? null,
                            'meta'               => json_encode($m['meta'] ?? []),
                            'created_at'         => $m['fecha'] ?? now(),
                            'updated_at'         => $m['fecha'] ?? now(),
                        ]);
                        $mensajes++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Error creando conversacion importada', [
                        'tel' => $tel, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['vinculadas' => $vinculadas, 'creadas' => $creadas, 'mensajes' => $mensajes];
    }

    /**
     * Best-effort: prueba endpoints de mensajes en TecnoByteApp y devuelve un
     * array uniforme [{rol, contenido, fecha, tipo, external_id, meta}].
     * Si ninguno responde, devuelve [].
     */
    private function bajarMensajesAPI(int $connectionId, string $telefono): array
    {
        $resolver = app(WhatsappResolverService::class);
        $token = $resolver->token();
        if (!$token) return [];

        $cred = $resolver->credenciales();
        $base = rtrim($cred['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422', '/');

        // El "chat id" en whatsapp-web.js suele ser numero@c.us
        $chatId = $telefono . '@c.us';

        $intentos = [
            "/whatsapp/{$connectionId}/messages?chatId={$chatId}",
            "/whatsapp/{$connectionId}/chat/{$chatId}/messages",
            "/api/whatsapp/{$connectionId}/messages?chatId={$chatId}",
            "/api/messages?whatsappId={$connectionId}&chatId={$chatId}",
            "/messages?whatsappId={$connectionId}&number={$telefono}",
        ];

        foreach ($intentos as $path) {
            try {
                $resp = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(20)
                    ->get($base . $path);

                if (!$resp->successful()) continue;

                $body = $resp->json();
                $items = $body['messages'] ?? $body['data'] ?? $body;
                if (!is_array($items) || empty($items)) continue;

                $out = [];
                foreach ($items as $m) {
                    if (!is_array($m)) continue;
                    $fromMe = $m['fromMe'] ?? $m['from_me'] ?? false;
                    $body   = $m['body'] ?? $m['text'] ?? $m['content'] ?? $m['message'] ?? '';
                    $ts     = $m['timestamp'] ?? $m['ts'] ?? $m['date'] ?? null;
                    $fecha  = $ts ? date('Y-m-d H:i:s', is_numeric($ts) ? (int) $ts : strtotime((string) $ts)) : now();

                    $out[] = [
                        'rol'         => $fromMe ? 'assistant' : 'user',
                        'tipo'        => $m['type'] ?? 'text',
                        'contenido'   => is_string($body) ? $body : json_encode($body),
                        'fecha'       => $fecha,
                        'external_id' => $m['id'] ?? $m['_serialized'] ?? null,
                        'meta'        => $m,
                    ];
                }
                return $out;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    /**
     * Normaliza la respuesta de la API (que puede venir en distintos formatos)
     * a un array uniforme [{nombre, telefono, foto}].
     */
    private function normalizarRespuesta($body): array
    {
        // La API puede devolver: ['contacts' => [...]], ['data' => [...]], o directo [...]
        $items = [];

        if (is_array($body)) {
            $items = $body['contacts'] ?? $body['data'] ?? $body['chats'] ?? $body;
        }

        if (!is_array($items) || empty($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;

            // Distintos shapes posibles
            $tel = $item['number']
                ?? $item['phone']
                ?? $item['phoneNumber']
                ?? $item['wa_id']
                ?? $item['id']['user']
                ?? null;

            $nombre = $item['name']
                ?? $item['pushname']
                ?? $item['verifiedName']
                ?? $item['notify']
                ?? '';

            $foto = $item['profilePicUrl']
                ?? $item['profile_pic_url']
                ?? $item['avatar']
                ?? null;

            // Filtrar contactos que no son personas (grupos, broadcasts, etc.)
            if (!$tel) continue;
            if (str_contains((string) $tel, '@g.us')) continue;     // grupos
            if (str_contains((string) $tel, 'status@')) continue;   // status

            // Limpiar el teléfono
            $telLimpio = preg_replace('/\D+/', '', (string) $tel);
            if (!$telLimpio) continue;

            $result[] = [
                'nombre'   => trim((string) $nombre),
                'telefono' => $telLimpio,
                'foto'     => $foto,
            ];
        }

        return $result;
    }
}
