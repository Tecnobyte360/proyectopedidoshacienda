<?php

namespace App\Console\Commands;

use App\Models\ConversacionWhatsapp;
use App\Services\BotPromptInspectorService;
use Illuminate\Console\Command;

/**
 * 🔍 BOT MOSTRAR PROMPT
 *
 * Reconstruye y muestra el prompt completo que se envía a OpenAI para
 * una conversación dada.
 *
 * Uso:
 *   php artisan bot:mostrar-prompt 19              (salida en stdout)
 *   php artisan bot:mostrar-prompt 19 --file=p.txt (volcar a archivo)
 *   php artisan bot:mostrar-prompt 19 --ultimos=5
 */
class BotMostrarPrompt extends Command
{
    protected $signature = 'bot:mostrar-prompt
                            {conversacion : ID de la conversación}
                            {--file= : Volcar a archivo en vez de stdout}
                            {--ultimos=20 : Cantidad de mensajes del historial a incluir}';

    protected $description = 'Muestra el prompt completo enviado a OpenAI para una conversación';

    public function handle(): int
    {
        $convId = (int) $this->argument('conversacion');
        $conv = ConversacionWhatsapp::with('cliente')->find($convId);

        if (!$conv) {
            $this->error("Conversación #{$convId} no encontrada.");
            return self::FAILURE;
        }

        $ultimos = (int) $this->option('ultimos');
        $data = app(BotPromptInspectorService::class)->inspeccionar($conv, $ultimos);

        $output = [];
        $output[] = str_repeat('═', 80);
        $output[] = '🔍 PROMPT ENVIADO A OPENAI';
        $output[] = "Conversación: #{$data['meta']['conversacion_id']}  |  Cliente: {$data['meta']['cliente_nombre']}";
        $output[] = "Tenant: {$data['meta']['tenant_id']}  |  Modelo: {$data['meta']['modelo']}  |  Temp: {$data['meta']['temperatura']}  |  Max tokens: {$data['meta']['max_tokens']}";
        $output[] = str_repeat('═', 80);

        foreach ($data['bloques'] as $b) {
            $output[] = "\n" . str_repeat('─', 80);
            $output[] = "║ {$b['titulo']}";
            if (!empty($b['subtitulo'])) {
                $output[] = "║ {$b['subtitulo']}";
            }
            $output[] = str_repeat('─', 80);
            $output[] = $b['contenido'];
        }

        $output[] = "\n" . str_repeat('─', 80);
        $output[] = "║ HISTORIAL — últimos {$ultimos} mensajes user/assistant";
        $output[] = str_repeat('─', 80);
        if (empty($data['historial'])) {
            $output[] = '(sin mensajes en el historial — ¿aislamiento por día activo?)';
        } else {
            foreach ($data['historial'] as $i => $m) {
                $rol = strtoupper($m['role']);
                $contenido = mb_substr((string) $m['content'], 0, 500);
                $output[] = "[{$i}] [{$rol}] {$contenido}";
            }
        }

        $output[] = "\n" . str_repeat('─', 80);
        $output[] = '║ RESUMEN';
        $output[] = str_repeat('─', 80);
        $output[] = "Caracteres: " . number_format($data['stats']['caracteres']);
        $output[] = "Tokens aprox: ~" . number_format($data['stats']['tokens_aprox']);
        $output[] = "Mensajes en historial: " . $data['stats']['mensajes'];
        $output[] = str_repeat('═', 80);

        $contenido = implode("\n", $output);

        if ($this->option('file')) {
            file_put_contents($this->option('file'), $contenido);
            $this->info('✅ Volcado a: ' . $this->option('file'));
        } else {
            $this->line($contenido);
        }

        return self::SUCCESS;
    }
}
