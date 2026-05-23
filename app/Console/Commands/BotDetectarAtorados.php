<?php

namespace App\Console\Commands;

use App\Services\Bots\ConversationRescueAgent;
use Illuminate\Console\Command;

/**
 * 🚑 BOT DETECTAR ATORADOS
 *
 * Corre cada 2 min. Detecta conversaciones donde el bot quedó atorado
 * en error repetido (ERP caído, etc.) y rescata al cliente activando
 * modo humano + notificando al operador.
 *
 * Uso manual:
 *   docker exec chatpedidos_app php artisan bot:detectar-atorados
 */
class BotDetectarAtorados extends Command
{
    protected $signature = 'bot:detectar-atorados';

    protected $description = 'Detecta conversaciones donde el bot quedó atorado en error y rescata al cliente';

    public function handle(ConversationRescueAgent $agent): int
    {
        $stats = $agent->detectarYRescatar();

        $this->info(sprintf(
            '🚑 Rescate: revisadas=%d 🚑rescatadas=%d 👤en_handoff=%d',
            $stats['revisadas'],
            $stats['rescatadas'],
            $stats['ya_en_handoff']
        ));

        return self::SUCCESS;
    }
}
