<?php

namespace App\Services\Agenda;

use App\Models\AgendaConfiguracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con Google Calendar por REST (sin paquete composer).
 * UN OAuth app de KIVOX; cada tenant autoriza su cuenta → refresh token propio.
 *
 * Configurado y listo: cuando existan GOOGLE_CALENDAR_CLIENT_ID/SECRET en .env,
 * el flujo "Conectar" funciona y las citas se sincronizan.
 */
class GoogleCalendarService
{
    private const AUTH   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN  = 'https://oauth2.googleapis.com/token';
    private const API    = 'https://www.googleapis.com/calendar/v3';
    private const USERINFO = 'https://www.googleapis.com/oauth2/v2/userinfo';
    private const SCOPES = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email';

    /** Credenciales del TENANT; si no tiene, cae al .env global (compatibilidad). */
    private function clientId(AgendaConfiguracion $cfg): ?string
    {
        return $cfg->google_client_id ?: config('services.google_calendar.client_id');
    }
    private function clientSecret(AgendaConfiguracion $cfg): ?string
    {
        return $cfg->google_client_secret ?: config('services.google_calendar.client_secret');
    }
    private function redirectUri(): string
    {
        return (string) config('services.google_calendar.redirect_uri');
    }

    /** ¿El tenant tiene cargadas sus credenciales OAuth? */
    public function configurado(AgendaConfiguracion $cfg): bool
    {
        return !empty($this->clientId($cfg)) && !empty($this->clientSecret($cfg));
    }

    /** URL de consentimiento de Google. `state` lleva el tenant_id. */
    public function urlAutorizacion(AgendaConfiguracion $cfg): string
    {
        return self::AUTH . '?' . http_build_query([
            'client_id'     => $this->clientId($cfg),
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'access_type'   => 'offline',   // para obtener refresh_token
            'prompt'        => 'consent',
            'state'         => $cfg->tenant_id,
        ]);
    }

    /** Intercambia el `code` del callback por tokens y los guarda en la config del tenant. */
    public function conectar(string $code, AgendaConfiguracion $cfg): bool
    {
        $resp = Http::asForm()->post(self::TOKEN, [
            'code'          => $code,
            'client_id'     => $this->clientId($cfg),
            'client_secret' => $this->clientSecret($cfg),
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);
        if (!$resp->successful()) {
            Log::warning('GoogleCalendar conectar falló: ' . $resp->body());
            return false;
        }
        $tok = $resp->json();

        $email = null;
        try {
            $u = Http::withToken($tok['access_token'])->get(self::USERINFO);
            $email = $u->json('email');
        } catch (\Throwable $e) { /* opcional */ }

        $cfg->update([
            'google_conectado'       => true,
            'google_access_token'    => $tok['access_token'] ?? null,
            'google_refresh_token'   => $tok['refresh_token'] ?? $cfg->google_refresh_token, // Google no re-manda refresh si ya lo dio
            'google_token_expira_at' => now()->addSeconds((int) ($tok['expires_in'] ?? 3500)),
            'google_calendar_id'     => $cfg->google_calendar_id ?: 'primary',
            'google_cuenta_email'    => $email,
        ]);
        return true;
    }

    public function desconectar(AgendaConfiguracion $cfg): void
    {
        $cfg->update([
            'google_conectado' => false, 'google_access_token' => null,
            'google_refresh_token' => null, 'google_token_expira_at' => null,
            'google_cuenta_email' => null,
        ]);
    }

    /** Devuelve un access token válido (refresca si expiró). Null si no hay conexión. */
    public function accessToken(AgendaConfiguracion $cfg): ?string
    {
        if (!$cfg->google_conectado || !$cfg->google_refresh_token) return null;

        if ($cfg->google_token_expira_at && now()->lt($cfg->google_token_expira_at->subMinute()) && $cfg->google_access_token) {
            return $cfg->google_access_token;
        }
        // refrescar
        $resp = Http::asForm()->post(self::TOKEN, [
            'client_id'     => $this->clientId($cfg),
            'client_secret' => $this->clientSecret($cfg),
            'refresh_token' => $cfg->google_refresh_token,
            'grant_type'    => 'refresh_token',
        ]);
        if (!$resp->successful()) {
            Log::warning('GoogleCalendar refresh falló: ' . $resp->body());
            return null;
        }
        $tok = $resp->json();
        $cfg->update([
            'google_access_token'    => $tok['access_token'] ?? null,
            'google_token_expira_at' => now()->addSeconds((int) ($tok['expires_in'] ?? 3500)),
        ]);
        return $tok['access_token'] ?? null;
    }

    /** Crea el evento en Google Calendar. Devuelve el eventId o null. */
    public function crearEvento(AgendaConfiguracion $cfg, \App\Models\Cita $cita): ?string
    {
        $token = $this->accessToken($cfg);
        if (!$token) return null;
        $calId = $cfg->google_calendar_id ?: 'primary';
        $tz    = $cfg->zona_horaria ?: 'America/Bogota';

        $resp = Http::withToken($token)->post(self::API . "/calendars/" . rawurlencode($calId) . "/events", [
            'summary'     => 'Cita: ' . $cita->paciente_nombre,
            'description' => trim(($cita->motivo ? "Motivo: {$cita->motivo}\n" : '') . ($cita->paciente_telefono ? "Tel: {$cita->paciente_telefono}" : '')),
            'start'       => ['dateTime' => Carbon::parse($cita->inicio_at)->setTimezone($tz)->toRfc3339String(), 'timeZone' => $tz],
            'end'         => ['dateTime' => Carbon::parse($cita->fin_at)->setTimezone($tz)->toRfc3339String(), 'timeZone' => $tz],
        ]);
        if (!$resp->successful()) {
            Log::warning('GoogleCalendar crearEvento falló: ' . $resp->body());
            return null;
        }
        return $resp->json('id');
    }

    public function eliminarEvento(AgendaConfiguracion $cfg, string $eventId): void
    {
        $token = $this->accessToken($cfg);
        if (!$token) return;
        $calId = $cfg->google_calendar_id ?: 'primary';
        try {
            Http::withToken($token)->delete(self::API . "/calendars/" . rawurlencode($calId) . "/events/" . rawurlencode($eventId));
        } catch (\Throwable $e) { Log::warning('GoogleCalendar eliminarEvento: ' . $e->getMessage()); }
    }

    /** Intervalos ocupados [ [start,end], ... ] entre dos fechas (para no chocar). */
    public function ocupados(AgendaConfiguracion $cfg, Carbon $desde, Carbon $hasta): array
    {
        $token = $this->accessToken($cfg);
        if (!$token) return [];
        $calId = $cfg->google_calendar_id ?: 'primary';
        $resp = Http::withToken($token)->post(self::API . '/freeBusy', [
            'timeMin' => $desde->toRfc3339String(),
            'timeMax' => $hasta->toRfc3339String(),
            'items'   => [['id' => $calId]],
        ]);
        if (!$resp->successful()) return [];
        return $resp->json("calendars.{$calId}.busy") ?? [];
    }
}
