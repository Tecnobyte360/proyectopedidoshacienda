<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\AgendaConfiguracion;
use App\Services\Agenda\GoogleCalendarService;
use App\Services\TenantManager;
use Illuminate\Http\Request;

/**
 * Flujo OAuth de Google Calendar para la Agenda del tenant.
 */
class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarService $google) {}

    /** Inicia el consentimiento OAuth. */
    public function conectar(Request $request)
    {
        if (!$this->google->configurado()) {
            return redirect()->route('agenda.index')
                ->with('error', 'Faltan las credenciales de Google (GOOGLE_CALENDAR_CLIENT_ID/SECRET).');
        }
        $tenantId = (int) app(TenantManager::class)->id();
        if (!$tenantId) {
            return redirect()->route('agenda.index')->with('error', 'No hay tenant en contexto.');
        }
        return redirect()->away($this->google->urlAutorizacion($tenantId));
    }

    /** Callback de Google: intercambia el code y guarda los tokens. */
    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect()->route('agenda.index')->with('error', 'Google canceló la conexión: ' . $request->get('error'));
        }
        $code     = (string) $request->get('code');
        $tenantId = (int) $request->get('state');
        if ($code === '' || !$tenantId) {
            return redirect()->route('agenda.index')->with('error', 'Respuesta de Google incompleta.');
        }

        $cfg = AgendaConfiguracion::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['dias' => [1, 2, 3, 4, 5], 'hora_inicio' => '08:00', 'hora_fin' => '18:00']
        );

        $ok = $this->google->conectar($code, $cfg);

        return redirect()->route('agenda.index')->with(
            $ok ? 'success' : 'error',
            $ok ? '✅ Google Calendar conectado.' : 'No se pudo conectar con Google. Intenta de nuevo.'
        );
    }
}
