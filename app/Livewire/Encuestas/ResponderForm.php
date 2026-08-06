<?php

namespace App\Livewire\Encuestas;

use App\Models\Encuesta;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaRespuestaValor;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Página PÚBLICA para responder una encuesta personalizada (/e/{token}).
 * Se renderiza dinámicamente según los campos definidos por el tenant.
 */
class ResponderForm extends Component
{
    public string $token;
    public ?Encuesta $encuesta = null;

    public string $respondente_nombre = '';
    /** @var array<int,mixed> valores por campo_id (checkbox = array) */
    public array $valores = [];
    public bool $enviada = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->encuesta = Encuesta::withoutGlobalScopes()
            ->with('campos')
            ->where('token', $token)
            ->where('activa', true)
            ->first();

        if (!$this->encuesta) {
            abort(404, 'Encuesta no encontrada o inactiva.');
        }

        // Inicializar checkbox como arrays
        foreach ($this->encuesta->campos as $c) {
            $this->valores[$c->id] = $c->tipo === 'checkbox' ? [] : null;
        }
    }

    public function enviar(): void
    {
        // Validación dinámica de requeridos
        $errores = [];
        foreach ($this->encuesta->campos as $c) {
            if (!$c->requerido) continue;
            $v = $this->valores[$c->id] ?? null;
            $vacio = is_array($v) ? count($v) === 0 : (trim((string) $v) === '' || $v === null);
            if ($vacio) {
                $errores["valores.{$c->id}"] = "«{$c->etiqueta}» es obligatorio.";
            }
        }
        if ($errores) {
            foreach ($errores as $k => $m) $this->addError($k, $m);
            return;
        }

        $respuesta = new EncuestaRespuesta();
        $respuesta->encuesta_id = $this->encuesta->id;
        $respuesta->tenant_id   = $this->encuesta->tenant_id; // encuesta pública: fijar tenant del dueño
        $respuesta->token       = Str::random(24);
        $respuesta->respondente_nombre = trim($this->respondente_nombre) ?: null;
        $respuesta->vista_at    = now();
        $respuesta->completada_at = now();
        $respuesta->saveQuietly();

        foreach ($this->encuesta->campos as $c) {
            $v = $this->valores[$c->id] ?? null;
            if (is_array($v)) $v = json_encode(array_values($v), JSON_UNESCAPED_UNICODE);
            EncuestaRespuestaValor::create([
                'encuesta_respuesta_id' => $respuesta->id,
                'encuesta_campo_id'     => $c->id,
                'valor'                 => ($v === null || $v === '') ? null : (string) $v,
            ]);
        }

        $this->enviada = true;
    }

    public function render()
    {
        return view('livewire.encuestas.responder-form')
            ->layout('layouts.public');
    }
}
