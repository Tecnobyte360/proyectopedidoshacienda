<?php

namespace App\Livewire\Sap;

use App\Services\Sap\Asistente\AsistenteSapService;
use Livewire\Component;

/**
 * Chat del Asistente IA + SAP, embebido en el panel del cliente (layouts.app).
 * Usa el tenant actual (por TenantManager dentro de AsistenteSapService), así
 * que consulta la conexión y agentes activos de ese cliente.
 */
class Chat extends Component
{
    /** @var array<int,array{role:string,content:string,tools:array}> */
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

        // Historial = intervenciones previas (sin el mensaje actual).
        $historial = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => $m['content']],
            $this->mensajes,
        );
        array_pop($historial);

        $r = app(AsistenteSapService::class)->responder($texto, $historial);

        $this->mensajes[] = [
            'role'    => 'assistant',
            'content' => $r['respuesta'] ?? 'No obtuve respuesta.',
            'tools'   => array_values(array_map(fn ($t) => $t['tool'] ?? '', $r['tools_usadas'] ?? [])),
        ];
    }

    public function preguntar(string $q): void
    {
        $this->entrada = $q;
        $this->enviar();
    }

    public function render()
    {
        return view('livewire.sap.chat')->layout('layouts.app');
    }
}
