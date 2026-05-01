<?php

namespace App\Services;

use App\Models\Cliente;
use App\Services\TenantManager;
use App\Services\WhatsappResolverService;
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

        // Probar varios endpoints — TecnoByteApp puede exponer cualquiera de ellos.
        $intentos = [
            "/whatsapp/{$connectionId}/contacts",
            "/whatsapp/contacts?id={$connectionId}",
            "/api/contacts?whatsappId={$connectionId}",
            "/api/whatsapp/{$connectionId}/contacts",
            "/chats?whatsappId={$connectionId}",
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
     * Importa los contactos al tenant actual y devuelve el conteo.
     * Skip = ya existe (no sobreescribe). Update = actualiza nombre/foto si vacios.
     */
    public function importar(?int $connectionId = null, bool $actualizarExistentes = false): array
    {
        $contactos = $this->listarContactos($connectionId);

        $tm = app(TenantManager::class);
        $tenant = method_exists($tm, 'current') ? $tm->current() : null;
        $tenantId = $tenant?->id ?? (method_exists($tm, 'id') ? $tm->id() : null);
        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $errores = 0;

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

                Cliente::create([
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
            } catch (\Throwable $e) {
                $errores++;
                Log::warning('Error importando contacto', [
                    'tel'   => $tel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total'        => count($contactos),
            'creados'      => $creados,
            'actualizados' => $actualizados,
            'omitidos'     => $omitidos,
            'errores'      => $errores,
        ];
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
