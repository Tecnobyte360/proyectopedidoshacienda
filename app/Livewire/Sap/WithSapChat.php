<?php

namespace App\Livewire\Sap;

use App\Services\Sap\Asistente\AsistenteSapService;
use Illuminate\Support\Str;

/**
 * Lógica compartida del chat IA + SAP (usada por el widget flotante y por la
 * vista full-page). Mantiene el historial y renderiza el markdown de las
 * respuestas a HTML.
 */
trait WithSapChat
{
    /** @var array<int,array{role:string,content:string,html?:string,tools:array}> */
    public array $mensajes = [];

    public string $entrada = '';

    public function enviar(): void
    {
        $texto = trim($this->entrada);
        if ($texto === '') {
            return;
        }

        $this->mensajes[] = ['role' => 'user', 'content' => $texto, 'tools' => []];
        $this->entrada = '';

        $historial = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => $m['content']],
            $this->mensajes,
        );
        array_pop($historial);

        $r    = app(AsistenteSapService::class)->responder($texto, $historial);
        $resp = $r['respuesta'] ?? 'No obtuve respuesta.';

        $this->mensajes[] = [
            'role'    => 'assistant',
            'content' => $resp,
            'html'    => Str::markdown($resp, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'tools'   => array_values(array_map(fn ($t) => $t['tool'] ?? '', $r['tools_usadas'] ?? [])),
        ];
    }

    public function preguntar(string $q): void
    {
        $this->entrada = $q;
        $this->enviar();
    }
}
