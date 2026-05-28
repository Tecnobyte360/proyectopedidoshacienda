<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Services\TenantManager;
use App\Services\WhatsappImportTxtService;
use App\Services\WhatsappResolverService;
use Livewire\Component;

/**
 * 📥 Importador de exports .txt de WhatsApp Business app.
 *
 * Flujo:
 *   1. (Super-admin) elige tenant. Resto usa tenant actual.
 *   2. Pega contenido del .txt o lo carga con un input file (lo lee en cliente
 *      con FileReader y manda el texto plano por wire:model).
 *   3. Le damos click a "Analizar" → parsea, muestra participantes + total.
 *   4. Elige cuál autor eres "tú" (el merchant) + teléfono cliente + nombre.
 *   5. Click "Importar a Kivox" → guarda en BD.
 */
class ImportarHistorialWa extends Component
{
    /** Texto crudo del export .txt (puede venir de paste o de file reader) */
    public string $textoExport = '';

    /** Nombre del archivo subido (sólo info) */
    public string $nombreArchivo = '';

    /** Slug del tenant destino (sólo super-admin lo puede cambiar) */
    public string $tenantSlug = '';

    /** Autor que representa al merchant ("yo") — se elige tras analizar */
    public string $autorYo = '';

    /** Teléfono del cliente (solo dígitos) */
    public string $telefonoCliente = '';
    public string $paisCodigo      = '+57';

    /** Nombre del cliente (opcional — se busca/crea) */
    public string $nombreCliente = '';

    /** Resultado del análisis (cache en sesión sería ideal, aquí en memoria) */
    public array $analisis = [];

    /** Resultado de la importación */
    public array $resultado = [];

    /** UI flags */
    public bool $analizando  = false;
    public bool $importando  = false;
    public string $error     = '';

    public function mount(): void
    {
        $tm = app(TenantManager::class);
        $tenant = method_exists($tm, 'current') ? $tm->current() : null;
        $this->tenantSlug = $tenant?->slug ?? '';
    }

    public function analizar(): void
    {
        $this->resetExceptUi();
        $this->error = '';

        if (trim($this->textoExport) === '') {
            $this->error = 'Pega el contenido del .txt o sube el archivo.';
            return;
        }

        $this->analizando = true;

        try {
            $svc = app(WhatsappImportTxtService::class);
            $this->analisis = $svc->parsearContenido($this->textoExport);

            if (empty($this->analisis['mensajes'])) {
                $this->error = 'No se detectaron mensajes. ¿Es realmente un export de WhatsApp?';
                $this->analisis = [];
                return;
            }

            // Heurística: si solo hay 2 participantes, asume el segundo es "yo"
            // (el otro suele ser el cliente). El usuario puede cambiarlo.
            if (count($this->analisis['participantes']) === 2 && empty($this->autorYo)) {
                $this->autorYo = $this->analisis['participantes'][1];
            }
        } catch (\Throwable $e) {
            $this->error = 'Error al analizar: ' . $e->getMessage();
        } finally {
            $this->analizando = false;
        }
    }

    public function importar(): void
    {
        $this->error = '';
        $this->resultado = [];

        if (empty($this->analisis['mensajes'])) {
            $this->error = 'Primero analiza el archivo.';
            return;
        }
        if ($this->autorYo === '') {
            $this->error = 'Elige cuál autor eres tú (el negocio).';
            return;
        }
        $tel = preg_replace('/\D+/', '', $this->telefonoCliente);
        if (!$tel || strlen($tel) < 7) {
            $this->error = 'Teléfono del cliente inválido.';
            return;
        }

        $tenant = Tenant::where('slug', $this->tenantSlug)->first();
        if (!$tenant) {
            $this->error = 'Tenant no encontrado.';
            return;
        }

        $this->importando = true;

        try {
            $svc = app(WhatsappImportTxtService::class);

            // Intentar conseguir connection_id del tenant (mejora trazabilidad)
            $connId = null;
            try {
                $ids = app(WhatsappResolverService::class)->connectionIdsValidos();
                $connId = $ids[0] ?? null;
            } catch (\Throwable $e) {
                // No es crítico
            }

            $this->resultado = $svc->importar(
                parseado:        $this->analisis,
                tenantId:        $tenant->id,
                autorYo:         $this->autorYo,
                telefonoCliente: $tel,
                nombreCliente:   $this->nombreCliente,
                connectionId:    $connId,
                fuente:          'wa_export_txt',
            );

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "✅ Importados {$this->resultado['insertados']} mensajes "
                    . ($this->resultado['cliente_creado'] ? '(nuevo cliente)' : '(cliente existente)')
                    . ($this->resultado['conv_creada'] ? ', nueva conversación' : ', conversación existente'),
            ]);
        } catch (\Throwable $e) {
            $this->error = 'Error al importar: ' . $e->getMessage();
        } finally {
            $this->importando = false;
        }
    }

    public function limpiar(): void
    {
        $this->reset(['textoExport', 'nombreArchivo', 'autorYo', 'telefonoCliente', 'nombreCliente', 'analisis', 'resultado', 'error']);
    }

    private function resetExceptUi(): void
    {
        $this->reset(['analisis', 'resultado']);
    }

    public function render()
    {
        $tenants = app(TenantManager::class)->withoutTenant(function () {
            return Tenant::where('activo', true)->orderBy('nombre')->get(['id','slug','nombre']);
        });

        return view('livewire.admin.importar-historial-wa', [
            'tenants' => $tenants,
        ])->layout('layouts.app');
    }
}
