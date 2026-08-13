<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsappConfig;
use App\Models\MetaWhatsappPlantilla;
use App\Services\Meta\MetaWhatsappCloudService;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🟢 API pública (por tenant, con api_key) para que sistemas externos envíen
 * mensajes por WhatsApp a través de KIVOX. El tenant lo resuelve el middleware
 * `tenant.apikey` a partir de la api_key → el mensaje sale desde SU número.
 */
class WhatsappApiController extends Controller
{
    /**
     * GET /api/v1/whatsapp/plantillas
     * Lista las plantillas del tenant (para saber cuáles se pueden enviar).
     */
    public function plantillas(): JsonResponse
    {
        $tenantId = app(TenantManager::class)->id();

        $plantillas = MetaWhatsappPlantilla::where('tenant_id', $tenantId)
            ->orderBy('nombre')
            ->get(['nombre', 'idioma', 'categoria', 'estado', 'body_preview'])
            ->map(fn ($p) => [
                'nombre'      => $p->nombre,
                'idioma'      => $p->idioma,
                'categoria'   => $p->categoria,
                'estado'      => $p->estado,       // APPROVED / PENDING / REJECTED
                'vista_previa'=> $p->body_preview, // texto con {{1}}, {{2}}...
            ]);

        return response()->json(['ok' => true, 'data' => $plantillas]);
    }

    /**
     * POST /api/v1/whatsapp/plantilla
     * Envía una plantilla a un cliente específico.
     *
     * Body:
     *   telefono   (req)  ej "573001234567"
     *   plantilla  (req)  nombre EXACTO de la plantilla aprobada
     *   idioma     (opt)  ej "es_CO" (default: idioma de la plantilla / "es")
     *   variables  (opt)  arreglo posicional [ "Juan", "#1234", ... ] → {{1}},{{2}}...
     *   imagen_url (opt)  si la plantilla tiene header de imagen
     *   boton_url  (opt)  si la plantilla tiene botón URL dinámico
     */
    public function enviarPlantilla(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telefono'   => ['required', 'string', 'max:20'],
            'plantilla'  => ['required', 'string', 'max:120'],
            'idioma'     => ['nullable', 'string', 'max:10'],
            'variables'  => ['nullable', 'array'],
            'variables.*'=> ['nullable', 'string', 'max:1000'],
            'imagen_url' => ['nullable', 'url', 'max:2048'],
            'boton_url'  => ['nullable', 'string', 'max:512'],
        ]);

        $tenant   = app(TenantManager::class)->current();
        $tenantId = $tenant?->id;

        // El tenant debe tener WhatsApp (Meta) configurado y activo.
        $cfg = MetaWhatsappConfig::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('activo', true)->first();
        if (!$cfg) {
            return response()->json([
                'ok' => false,
                'message' => 'Este tenant no tiene WhatsApp (Meta) configurado.',
            ], 422);
        }

        // Idioma: el que manden, o el de la plantilla, o el default de la config.
        $idioma = $data['idioma'] ?? null;
        if (!$idioma) {
            $pl = MetaWhatsappPlantilla::where('tenant_id', $tenantId)
                ->where('nombre', $data['plantilla'])->first();
            $idioma = $pl?->idioma ?: ($cfg->default_lang ?: 'es');
        }

        $variables = array_map(
            fn ($v) => (string) $v,
            array_values($data['variables'] ?? [])
        );

        $telefono = preg_replace('/\D+/', '', $data['telefono']);
        if ($telefono === '') {
            return response()->json(['ok' => false, 'message' => 'Teléfono inválido.'], 422);
        }

        $ok = app(MetaWhatsappCloudService::class)->enviarPlantilla(
            telefono:        $telefono,
            plantilla:       $data['plantilla'],
            variables:       $variables,
            tenantId:        $tenantId,
            idioma:          $idioma,
            headerImagenUrl: $data['imagen_url'] ?? null,
            botonUrlParam:   $data['boton_url'] ?? null,
        );

        if (!$ok) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo enviar. Verifica que la plantilla exista, esté APROBADA y el idioma sea correcto.',
            ], 422);
        }

        return response()->json([
            'ok'        => true,
            'message'   => 'Plantilla enviada.',
            'telefono'  => $telefono,
            'plantilla' => $data['plantilla'],
            'idioma'    => $idioma,
        ]);
    }
}
