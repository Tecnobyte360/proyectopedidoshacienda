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

        // ✅ Confirmado por diagnostico: el endpoint real es /contacts?whatsappId=
        $intentos = [
            "/contacts?whatsappId={$connectionId}",
            // Mantener fallbacks por si en otra version del wrapper cambia
            "/api/contacts?whatsappId={$connectionId}",
            "/whatsapp/{$connectionId}/contacts",
            "/api/whatsapp/{$connectionId}/contacts",
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
    public function importar(?int $connectionId = null, bool $actualizarExistentes = false, bool $importarConversaciones = true, bool $bajarFotos = false): array
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

        // Patron observado: TecnoByteApp usa /resource?whatsappId=ID
        $intentos = [
            "/messages?whatsappId={$connectionId}&chatId={$chatId}",
            "/messages?whatsappId={$connectionId}&number={$telefono}",
            "/messages?whatsappId={$connectionId}&contactNumber={$telefono}",
            "/chats?whatsappId={$connectionId}&number={$telefono}",
            "/api/messages?whatsappId={$connectionId}&chatId={$chatId}",
            "/whatsapp/{$connectionId}/messages?chatId={$chatId}",
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

            // Distintos shapes posibles. En TecnoByteApp el campo `name` suele
            // ser el telefono internacional (ej "521998655477") y `pushname` /
            // `verifiedName` el nombre real.
            $tel = $item['number']
                ?? $item['phone']
                ?? $item['phoneNumber']
                ?? $item['wa_id']
                ?? $item['waId']
                ?? $item['contactNumber']
                ?? $item['id']['user']
                ?? null;

            // Si no encontramos un campo explicito de telefono y `name` es
            // todo digitos (o digitos + algunos chars), usar name como tel.
            $rawName = $item['name'] ?? $item['displayName'] ?? '';
            if (!$tel && $rawName !== '' && preg_match('/^\+?\d{7,}$/', preg_replace('/\s+/', '', (string) $rawName))) {
                $tel = $rawName;
            }

            // Para el nombre real, preferir pushname/verifiedName/notify/short
            $nombre = $item['pushname']
                ?? $item['verifiedName']
                ?? $item['notify']
                ?? $item['shortName']
                ?? $item['short']
                ?? '';

            // Si seguimos sin nombre y `name` NO es un telefono, usarlo.
            if (!$nombre && $rawName !== '' && !preg_match('/^\+?\d+$/', preg_replace('/\s+/', '', (string) $rawName))) {
                $nombre = $rawName;
            }

            $foto = $item['profilePicUrl']
                ?? $item['contactProfilePic']
                ?? $item['profile_pic_url']
                ?? $item['avatar']
                ?? $item['picture']
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

    // ════════════════════════════════════════════════════════════════════════
    // SINCRONIZACION COMPLETA DE HISTORIAL via /tickets (Whaticket-style API)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Sincroniza el HISTORIAL COMPLETO del WhatsApp del tenant:
     *   - Recorre todos los tickets (chats) paginando
     *   - Por cada ticket: upsert Cliente (con foto), upsert Conversacion,
     *     baja todos los mensajes y los inserta como MensajeWhatsapp
     *
     * Es el metodo "matar todo de un golpe": deja la plataforma con la base
     * de clientes + conversaciones + mensajes igual a lo que esta en el celular.
     */
    public function sincronizarHistorialCompleto(?int $connectionId = null, int $maxTickets = 1000, int $maxMensajesPorTicket = 500): array
    {
        $resolver = app(WhatsappResolverService::class);
        $token = $resolver->token();
        if (!$token) throw new \RuntimeException('No hay token de WhatsApp activo.');

        if (!$connectionId) {
            $ids = $resolver->connectionIdsValidos();
            $connectionId = $ids[0] ?? null;
        }
        if (!$connectionId) throw new \RuntimeException('No hay connection_id valido.');

        $tm = app(TenantManager::class);
        $tenant = method_exists($tm, 'current') ? $tm->current() : null;
        $tenantId = $tenant?->id ?? (method_exists($tm, 'id') ? $tm->id() : null);
        if (!$tenantId) throw new \RuntimeException('No hay tenant activo.');

        $cred = $resolver->credenciales();
        $base = rtrim($cred['api_base_url'] ?? 'https://wa-api.tecnobyteapp.com:1422', '/');

        $stats = [
            'tickets_procesados' => 0,
            'clientes_creados'   => 0,
            'clientes_actualizados' => 0,
            'conv_creadas'       => 0,
            'conv_actualizadas'  => 0,
            'mensajes_imp'       => 0,
            'errores'            => 0,
        ];

        $page = 1;
        $limit = 100;
        $vistos = 0;

        while ($vistos < $maxTickets) {
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(60)
                ->get("{$base}/tickets", [
                    'whatsappId' => $connectionId,
                    'pageNumber' => $page,
                    'limit'      => $limit,
                    'showAll'    => 'true',
                ]);

            if (!$resp->successful()) {
                Log::warning('Fallo /tickets en sync historial', [
                    'page' => $page, 'status' => $resp->status(),
                ]);
                break;
            }

            $body = $resp->json();
            $tickets = $body['tickets'] ?? [];
            $hasMore = $body['hasMore'] ?? false;

            if (empty($tickets)) break;

            foreach ($tickets as $t) {
                $vistos++;
                if ($vistos > $maxTickets) break 2;

                try {
                    $procesado = $this->procesarTicket($t, $tenantId, $connectionId, $token, $base, $maxMensajesPorTicket);
                    foreach ($procesado as $k => $v) {
                        if (isset($stats[$k])) $stats[$k] += $v;
                    }
                    $stats['tickets_procesados']++;
                } catch (\Throwable $e) {
                    $stats['errores']++;
                    Log::warning('Error procesando ticket en sync', [
                        'ticket_id' => $t['id'] ?? null,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            if (!$hasMore) break;
            $page++;
        }

        return $stats;
    }

    /**
     * Procesa un ticket individual: upsert cliente, conversacion y mensajes.
     */
    private function procesarTicket(array $ticket, int $tenantId, int $connectionId, string $token, string $base, int $maxMensajes): array
    {
        $stats = [
            'clientes_creados' => 0, 'clientes_actualizados' => 0,
            'conv_creadas' => 0, 'conv_actualizadas' => 0, 'mensajes_imp' => 0,
        ];

        $contact = $ticket['contact'] ?? [];
        if (($contact['isGroup'] ?? false) === true) return $stats; // saltar grupos

        $numero = (string) ($contact['number'] ?? '');
        $tel = preg_replace('/\D+/', '', $numero);
        if (!$tel || strlen($tel) < 7) return $stats;

        $nombre = trim((string) ($contact['name'] ?? ''));
        // Si el "nombre" es solo digitos (es el telefono), dejarlo vacio
        if (preg_match('/^\d+$/', $nombre)) $nombre = '';
        $foto = $contact['profilePicUrl'] ?? null;

        // 1) UPSERT cliente
        $cliente = Cliente::where('tenant_id', $tenantId)
            ->where('telefono_normalizado', $tel)
            ->first();

        if (!$cliente) {
            $cliente = Cliente::create([
                'tenant_id'            => $tenantId,
                'nombre'               => $nombre ?: 'Cliente',
                'pais_codigo'          => '+57',
                'telefono'             => substr($tel, -10),
                'telefono_normalizado' => $tel,
                'canal_origen'         => 'whatsapp_sync',
                'profile_pic_url'      => $foto,
                'activo'               => true,
            ]);
            $stats['clientes_creados']++;
        } else {
            // Actualizar foto/nombre si vienen y faltaban
            $cambios = [];
            if ($foto && empty($cliente->profile_pic_url)) {
                $cambios['profile_pic_url'] = $foto;
            }
            if ($nombre && (empty($cliente->nombre) || $cliente->nombre === 'Cliente')) {
                $cambios['nombre'] = $nombre;
            }
            if (!empty($cambios)) {
                $cliente->update($cambios);
                $stats['clientes_actualizados']++;
            }
        }

        // 2) UPSERT conversacion
        $conv = ConversacionWhatsapp::where('tenant_id', $tenantId)
            ->where('telefono_normalizado', $tel)
            ->first();

        if (!$conv) {
            $conv = ConversacionWhatsapp::create([
                'tenant_id'            => $tenantId,
                'cliente_id'           => $cliente->id,
                'telefono_normalizado' => $tel,
                'canal'                => 'whatsapp',
                'connection_id'        => $connectionId,
                'estado'               => ConversacionWhatsapp::ESTADO_ACTIVA,
                'no_leidos'            => (int) ($ticket['unreadMessages'] ?? 0),
            ]);
            $stats['conv_creadas']++;
        } else {
            $cambios = [];
            if (!$conv->cliente_id) $cambios['cliente_id'] = $cliente->id;
            if (!$conv->connection_id) $cambios['connection_id'] = $connectionId;
            if (!empty($cambios)) {
                $conv->update($cambios);
                $stats['conv_actualizadas']++;
            }
        }

        // 3) BAJAR mensajes del ticket
        $msgs = $this->listarMensajesTicket($base, $token, (int) $ticket['id'], $maxMensajes);
        if (empty($msgs)) return $stats;

        // IDs externos ya existentes para no duplicar
        $existentes = DB::table('mensajes_whatsapp')
            ->where('conversacion_id', $conv->id)
            ->whereNotNull('mensaje_externo_id')
            ->pluck('mensaje_externo_id')
            ->flip()
            ->toArray();

        $primer = null;
        $ultimo = null;
        $insertados = 0;

        foreach ($msgs as $m) {
            $extId = $m['external_id'] ?? null;
            if ($extId && isset($existentes[$extId])) continue;

            DB::table('mensajes_whatsapp')->insert([
                'conversacion_id'    => $conv->id,
                'rol'                => $m['rol'],
                'tipo'               => $m['tipo'] ?? 'text',
                'contenido'          => mb_substr((string) ($m['contenido'] ?? ''), 0, 65000),
                'mensaje_externo_id' => $extId,
                'meta'               => json_encode($m['meta'] ?? []),
                'created_at'         => $m['fecha'] ?? now(),
                'updated_at'         => $m['fecha'] ?? now(),
            ]);
            $insertados++;

            $f = $m['fecha'] ?? null;
            if ($f) {
                if ($primer === null || $f < $primer) $primer = $f;
                if ($ultimo === null || $f > $ultimo) $ultimo = $f;
            }
        }

        if ($insertados > 0) {
            $stats['mensajes_imp'] += $insertados;

            $update = ['total_mensajes' => DB::raw("COALESCE(total_mensajes,0) + {$insertados}")];
            if ($primer && (!$conv->primer_mensaje_at || $primer < $conv->primer_mensaje_at)) {
                $update['primer_mensaje_at'] = $primer;
            }
            if ($ultimo && (!$conv->ultimo_mensaje_at || $ultimo > $conv->ultimo_mensaje_at)) {
                $update['ultimo_mensaje_at'] = $ultimo;
            }
            $conv->update($update);
        }

        return $stats;
    }

    /**
     * Baja mensajes de un ticket via /messages/{ticketId} con paginacion.
     */
    private function listarMensajesTicket(string $base, string $token, int $ticketId, int $maxMensajes = 500): array
    {
        $out = [];
        $page = 1;
        $limit = 100;

        while (count($out) < $maxMensajes) {
            // Patron Whaticket: /messages/{ticketId}?pageNumber=N
            $resp = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(30)
                ->get("{$base}/messages/{$ticketId}", [
                    'pageNumber' => $page,
                    'limit'      => $limit,
                ]);

            if (!$resp->successful()) break;

            $body = $resp->json();
            $items = $body['messages'] ?? $body['data'] ?? [];
            if (!is_array($items) || empty($items)) break;

            foreach ($items as $m) {
                if (!is_array($m)) continue;
                $fromMe = $m['fromMe'] ?? false;
                $contenido = $m['body'] ?? $m['text'] ?? '';
                $ts = $m['timestamp'] ?? $m['createdAt'] ?? null;
                $fecha = $ts ? date('Y-m-d H:i:s', is_numeric($ts) ? (int) $ts : strtotime((string) $ts)) : now();

                $out[] = [
                    'rol'         => $fromMe ? 'assistant' : 'user',
                    'tipo'        => $m['mediaType'] ?? $m['type'] ?? 'text',
                    'contenido'   => is_string($contenido) ? $contenido : json_encode($contenido),
                    'fecha'       => $fecha,
                    'external_id' => isset($m['id']) ? (string) $m['id'] : null,
                    'meta'        => [
                        'ack'       => $m['ack'] ?? null,
                        'mediaType' => $m['mediaType'] ?? null,
                        'mediaUrl'  => $m['mediaUrl'] ?? null,
                    ],
                ];
                if (count($out) >= $maxMensajes) break 2;
            }

            $hasMore = $body['hasMore'] ?? (count($items) >= $limit);
            if (!$hasMore) break;
            $page++;
        }

        // Ordenar de mas viejo a mas nuevo
        usort($out, fn ($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));
        return $out;
    }
}
