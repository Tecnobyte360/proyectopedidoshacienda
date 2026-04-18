<?php

namespace App\Services;

use App\Models\BotAlerta;
use Illuminate\Support\Facades\Log;

/**
 * Registra alertas operativas del bot (problemas con OpenAI, WhatsApp, etc).
 * Deduplica errores repetidos en una ventana corta para no saturar la BD.
 */
class BotAlertaService
{
    /** Ventana de deduplicación: si la misma alerta vuelve a ocurrir en X minutos, solo incrementa contador */
    private const VENTANA_DEDUP_MIN = 5;

    /**
     * Registra (o actualiza) una alerta.
     */
    public function registrar(
        string $tipo,
        string $titulo,
        string $mensaje,
        string $severidad = BotAlerta::SEV_WARNING,
        ?int $codigoHttp = null,
        array $contexto = []
    ): BotAlerta {
        $hash = md5($tipo . '|' . $titulo . '|' . $codigoHttp);

        // Buscar alerta existente reciente con el mismo hash (para deduplicar)
        $existente = BotAlerta::where('hash_dedup', $hash)
            ->where('resuelta', false)
            ->where('ultima_ocurrencia_at', '>=', now()->subMinutes(self::VENTANA_DEDUP_MIN))
            ->orderByDesc('id')
            ->first();

        if ($existente) {
            $existente->update([
                'ocurrencias'          => $existente->ocurrencias + 1,
                'ultima_ocurrencia_at' => now(),
                'mensaje'              => $mensaje,        // siempre el más reciente
                'contexto'             => $contexto,
            ]);
            return $existente;
        }

        return BotAlerta::create([
            'tipo'                 => $tipo,
            'severidad'            => $severidad,
            'titulo'               => $titulo,
            'mensaje'              => mb_substr($mensaje, 0, 1000),
            'contexto'             => $contexto,
            'codigo_http'          => $codigoHttp,
            'hash_dedup'           => $hash,
            'ocurrencias'          => 1,
            'ultima_ocurrencia_at' => now(),
        ]);
    }

    /**
     * Clasifica una respuesta fallida de OpenAI y registra la alerta correcta.
     */
    public function registrarErrorOpenAI(?int $statusCode, ?string $body, array $contexto = []): BotAlerta
    {
        $bodyJson = $body ? json_decode($body, true) : null;
        $errorCode = $bodyJson['error']['code']    ?? null;
        $errorType = $bodyJson['error']['type']    ?? null;
        $errorMsg  = $bodyJson['error']['message'] ?? 'Error desconocido de OpenAI';

        // Clasificar
        if ($statusCode === 401 || in_array($errorCode, ['invalid_api_key', 'invalid_authentication'], true)) {
            return $this->registrar(
                BotAlerta::TIPO_OPENAI_KEY,
                '🔑 API key de OpenAI inválida',
                "La API key configurada no es válida o fue revocada. Genera una nueva en platform.openai.com y actualiza OPENAI_API_KEY en .env.\n\nMensaje: {$errorMsg}",
                BotAlerta::SEV_CRITICA,
                $statusCode,
                $contexto
            );
        }

        if ($statusCode === 429) {
            // 429 puede ser rate limit O sin créditos
            $esCredito = stripos($errorMsg, 'quota') !== false
                || stripos($errorMsg, 'billing') !== false
                || stripos($errorMsg, 'insufficient') !== false
                || $errorCode === 'insufficient_quota';

            if ($esCredito) {
                return $this->registrar(
                    BotAlerta::TIPO_OPENAI_CREDITO,
                    '💸 Sin saldo en OpenAI',
                    "Tu cuenta de OpenAI se quedó sin créditos o el plan no tiene saldo suficiente. Recarga en platform.openai.com/billing.\n\nMensaje: {$errorMsg}",
                    BotAlerta::SEV_CRITICA,
                    $statusCode,
                    $contexto
                );
            }

            return $this->registrar(
                BotAlerta::TIPO_OPENAI_RATE,
                '⏱️ Rate limit de OpenAI',
                "Estás haciendo demasiadas peticiones por minuto. El bot reintenta automáticamente. Considera reducir el tráfico o subir de tier en platform.openai.com.\n\nMensaje: {$errorMsg}",
                BotAlerta::SEV_WARNING,
                $statusCode,
                $contexto
            );
        }

        if ($statusCode === 404 || $errorCode === 'model_not_found') {
            return $this->registrar(
                BotAlerta::TIPO_OPENAI_MODELO,
                '🧠 Modelo de OpenAI no encontrado',
                "El modelo configurado no existe o no tienes acceso. Revisa /configuracion/bot y elige uno válido (ej: gpt-4o-mini, gpt-4o).\n\nMensaje: {$errorMsg}",
                BotAlerta::SEV_CRITICA,
                $statusCode,
                $contexto
            );
        }

        if (in_array($statusCode, [408, 504], true)) {
            return $this->registrar(
                BotAlerta::TIPO_OPENAI_TIMEOUT,
                '⌛ Timeout llamando a OpenAI',
                "OpenAI tardó demasiado en responder. Puede ser su servidor saturado o un problema de red.\n\nMensaje: {$errorMsg}",
                BotAlerta::SEV_WARNING,
                $statusCode,
                $contexto
            );
        }

        // Cualquier otro error
        return $this->registrar(
            BotAlerta::TIPO_OPENAI_OTRO,
            '🤖 Error inesperado de OpenAI',
            "Status {$statusCode}: {$errorMsg}",
            BotAlerta::SEV_WARNING,
            $statusCode,
            $contexto
        );
    }

    /**
     * Cuenta alertas no resueltas, para badges.
     */
    public function contadorNoResueltas(): int
    {
        return BotAlerta::where('resuelta', false)->count();
    }

    /**
     * Cuenta no resueltas críticas (para color del badge).
     */
    public function contadorCriticas(): int
    {
        return BotAlerta::where('resuelta', false)
            ->where('severidad', BotAlerta::SEV_CRITICA)
            ->count();
    }
}
