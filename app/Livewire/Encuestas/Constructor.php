<?php

namespace App\Livewire\Encuestas;

use App\Models\Encuesta;
use App\Models\EncuestaCampo;
use Livewire\Component;

/**
 * Constructor de encuestas personalizables del tenant (form builder).
 * Vistas: lista → editar campos → resultados. Todo en un componente.
 */
class Constructor extends Component
{
    // Vista activa
    public string $vista = 'lista';           // lista | campos | resultados

    // ── Modal encuesta (meta) ──────────────────────────────
    public bool $modalEncuesta = false;
    public ?int $encuestaId = null;
    public string $nombre = '';
    public string $descripcion = '';
    public string $mensaje_gracias = '';
    public bool $activa = true;

    // ── Editor de campos ───────────────────────────────────
    public ?int $campoEncuestaId = null;      // encuesta cuyos campos editamos

    public bool $modalCampo = false;
    public ?int $campoId = null;
    public string $c_etiqueta = '';
    public string $c_tipo = 'texto';
    public string $c_opciones_text = '';       // una por línea (radio/checkbox)
    public string $c_placeholder = '';
    public bool $c_requerido = false;

    // ── Resultados ─────────────────────────────────────────
    public ?int $resultadosEncuestaId = null;

    protected function rulesEncuesta(): array
    {
        return [
            'nombre'          => 'required|string|max:150',
            'descripcion'     => 'nullable|string|max:1000',
            'mensaje_gracias' => 'nullable|string|max:300',
        ];
    }

    // ═══ ENCUESTA (meta) ═══
    public function nuevaEncuesta(): void
    {
        $this->reset(['encuestaId', 'nombre', 'descripcion', 'mensaje_gracias']);
        $this->activa = true;
        $this->resetValidation();
        $this->modalEncuesta = true;
    }

    public function editarEncuesta(int $id): void
    {
        $e = Encuesta::findOrFail($id);
        $this->encuestaId      = $e->id;
        $this->nombre          = $e->nombre;
        $this->descripcion     = (string) $e->descripcion;
        $this->mensaje_gracias = (string) $e->mensaje_gracias;
        $this->activa          = (bool) $e->activa;
        $this->resetValidation();
        $this->modalEncuesta = true;
    }

    public function guardarEncuesta(): void
    {
        $data = $this->validate($this->rulesEncuesta());
        $data['activa'] = $this->activa;

        $e = Encuesta::updateOrCreate(['id' => $this->encuestaId], $data);
        $this->modalEncuesta = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Encuesta guardada.']);

        // Si es nueva, pasar directo a agregar campos.
        if (!$this->encuestaId) {
            $this->abrirCampos($e->id);
        }
    }

    public function toggleActiva(int $id): void
    {
        $e = Encuesta::find($id);
        if ($e) { $e->activa = !$e->activa; $e->save(); }
    }

    public function eliminarEncuesta(int $id): void
    {
        Encuesta::where('id', $id)->delete(); // cascade borra campos y respuestas
        if ($this->campoEncuestaId === $id || $this->resultadosEncuestaId === $id) {
            $this->vista = 'lista';
        }
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Encuesta eliminada.']);
    }

    // ═══ CAMPOS ═══
    public function abrirCampos(int $encuestaId): void
    {
        $this->campoEncuestaId = $encuestaId;
        $this->vista = 'campos';
    }

    public function nuevoCampo(): void
    {
        $this->reset(['campoId', 'c_etiqueta', 'c_opciones_text', 'c_placeholder']);
        $this->c_tipo = 'texto';
        $this->c_requerido = false;
        $this->resetValidation();
        $this->modalCampo = true;
    }

    public function editarCampo(int $id): void
    {
        $c = EncuestaCampo::where('encuesta_id', $this->campoEncuestaId)->findOrFail($id);
        $this->campoId        = $c->id;
        $this->c_etiqueta     = $c->etiqueta;
        $this->c_tipo         = $c->tipo;
        $this->c_opciones_text = collect($c->opciones['lista'] ?? [])->implode("\n");
        $this->c_placeholder  = (string) $c->placeholder;
        $this->c_requerido    = (bool) $c->requerido;
        $this->resetValidation();
        $this->modalCampo = true;
    }

    public function guardarCampo(): void
    {
        $this->validate([
            'c_etiqueta' => 'required|string|max:200',
            'c_tipo'     => 'required|in:' . implode(',', array_keys(EncuestaCampo::TIPOS)),
        ]);

        $opciones = null;
        if (in_array($this->c_tipo, ['radio', 'checkbox'], true)) {
            $lista = collect(preg_split('/\r\n|\r|\n/', $this->c_opciones_text))
                ->map(fn ($l) => trim($l))->filter()->values()->all();
            if (count($lista) < 2) {
                $this->addError('c_opciones_text', 'Agrega al menos 2 opciones (una por línea).');
                return;
            }
            $opciones = ['lista' => $lista];
        } elseif ($this->c_tipo === 'estrellas') {
            $opciones = ['max' => 5];
        }

        $data = [
            'encuesta_id' => $this->campoEncuestaId,
            'etiqueta'    => trim($this->c_etiqueta),
            'tipo'        => $this->c_tipo,
            'opciones'    => $opciones,
            'placeholder' => trim($this->c_placeholder) ?: null,
            'requerido'   => $this->c_requerido,
        ];

        if ($this->campoId) {
            EncuestaCampo::where('encuesta_id', $this->campoEncuestaId)->where('id', $this->campoId)->update($data);
        } else {
            $data['orden'] = (int) EncuestaCampo::where('encuesta_id', $this->campoEncuestaId)->max('orden') + 1;
            EncuestaCampo::create($data);
        }
        $this->modalCampo = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Campo guardado.']);
    }

    public function eliminarCampo(int $id): void
    {
        EncuestaCampo::where('encuesta_id', $this->campoEncuestaId)->where('id', $id)->delete();
    }

    public function moverCampo(int $id, string $dir): void
    {
        $campos = EncuestaCampo::where('encuesta_id', $this->campoEncuestaId)
            ->orderBy('orden')->orderBy('id')->get();
        $idx = $campos->search(fn ($c) => $c->id === $id);
        if ($idx === false) return;
        $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
        if ($swap < 0 || $swap >= $campos->count()) return;

        $a = $campos[$idx]; $b = $campos[$swap];
        $oa = $a->orden; $ob = $b->orden;
        // si empatan en orden, forzar secuencia por índice
        if ($oa === $ob) { $oa = $idx; $ob = $swap; }
        $a->update(['orden' => $ob]);
        $b->update(['orden' => $oa]);
    }

    // ═══ RESULTADOS ═══
    public function abrirResultados(int $encuestaId): void
    {
        $this->resultadosEncuestaId = $encuestaId;
        $this->vista = 'resultados';
    }

    public function volver(): void
    {
        $this->vista = 'lista';
        $this->campoEncuestaId = null;
        $this->resultadosEncuestaId = null;
    }

    public function render()
    {
        $encuestas = Encuesta::withCount(['campos', 'respuestasCompletadas as respuestas_count'])
            ->orderByDesc('id')->get();

        $encuestaCampos = null;
        if ($this->vista === 'campos' && $this->campoEncuestaId) {
            $encuestaCampos = Encuesta::with('campos')->find($this->campoEncuestaId);
        }

        $resultados = null;
        if ($this->vista === 'resultados' && $this->resultadosEncuestaId) {
            $resultados = Encuesta::with([
                'campos',
                'respuestasCompletadas' => fn ($q) => $q->with('valores')->latest('completada_at'),
            ])->find($this->resultadosEncuestaId);
        }

        return view('livewire.encuestas.constructor', compact('encuestas', 'encuestaCampos', 'resultados'))
            ->layout('layouts.app');
    }
}
