<?php

namespace App\Livewire\Configuracion\Flujos;

use App\Models\Departamento;
use App\Models\FlujoBot;
use Livewire\Component;

class Index extends Component
{
    public bool   $modalAbierto = false;
    public ?int   $editandoId   = null;

    public string $nombre       = '';
    public string $descripcion  = '';
    public bool   $activo       = true;
    public int    $prioridad    = 0;
    public array  $grafo        = [];

    protected function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:120',
            'descripcion'  => 'nullable|string|max:500',
            'activo'       => 'boolean',
            'prioridad'    => 'integer|min:0|max:1000',
            'grafo'        => 'array',
        ];
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'descripcion', 'prioridad']);
        $this->activo = true;
        $this->grafo  = $this->grafoVacio();
        $this->modalAbierto = true;
        $this->dispatch('flujo-cargado', grafo: $this->grafo);
    }

    public function editar(int $id): void
    {
        $f = FlujoBot::findOrFail($id);
        $this->editandoId  = $f->id;
        $this->nombre      = $f->nombre;
        $this->descripcion = (string) $f->descripcion;
        $this->activo      = (bool) $f->activo;
        $this->prioridad   = (int) $f->prioridad;
        $this->grafo       = is_array($f->grafo) && !empty($f->grafo) ? $f->grafo : $this->grafoVacio();
        $this->modalAbierto = true;
        $this->dispatch('flujo-cargado', grafo: $this->grafo);
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->reset(['editandoId', 'nombre', 'descripcion', 'prioridad', 'grafo']);
    }

    /**
     * Recibe el JSON exportado desde Drawflow y guarda en BD.
     */
    public function guardar(array $grafoExportado = []): void
    {
        if (!empty($grafoExportado)) {
            $this->grafo = $grafoExportado;
        }

        $data = $this->validate();

        FlujoBot::updateOrCreate(['id' => $this->editandoId], $data);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $this->editandoId ? '✅ Flujo actualizado.' : '✅ Flujo creado.',
        ]);

        $this->cerrarModal();
    }

    public function toggleActivo(int $id): void
    {
        $f = FlujoBot::findOrFail($id);
        $f->activo = !$f->activo;
        $f->save();
    }

    public function eliminar(int $id): void
    {
        FlujoBot::where('id', $id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Flujo eliminado.']);
    }

    public function duplicar(int $id): void
    {
        $f = FlujoBot::findOrFail($id);
        $nuevo = $f->replicate();
        $nuevo->nombre = $f->nombre . ' (copia)';
        $nuevo->activo = false;
        $nuevo->save();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Flujo duplicado (queda inactivo).']);
    }

    /**
     * Instala 5 flujos típicos prediseñados para que el tenant tenga algo
     * funcional desde el primer minuto. Resuelve departamentos por nombre
     * con búsqueda flexible; si no existen, deja departamento_id vacío
     * (el usuario lo asigna al editar).
     */
    public function instalarPlantillas(): void
    {
        $resolver = function (array $nombres): ?int {
            foreach ($nombres as $n) {
                $d = Departamento::where('activo', true)
                    ->where('nombre', 'like', '%' . $n . '%')
                    ->first();
                if ($d) return $d->id;
            }
            return Departamento::where('activo', true)->orderBy('orden')->value('id');
        };

        $servicio   = $resolver(['Servicio al Cliente', 'PQR', 'Atención']);
        $comercial  = $resolver(['Comercial', 'Ventas', 'B2B']);
        $rh         = $resolver(['RH', 'Recursos Humanos', 'Talento']);
        $facturacion = $resolver(['Facturación', 'Cobros', 'Cartera']);

        $plantillas = [
            [
                'nombre'      => '🔴 Sede cerrada — aviso automático',
                'descripcion' => 'Si la sede está cerrada, avisa al cliente y no toma pedidos.',
                'prioridad'   => 100,
                'grafo'       => $this->grafoSedeCerrada(),
            ],
            [
                'nombre'      => '👋 Saludo personalizado en primer mensaje',
                'descripcion' => 'En el primer mensaje del cliente, deja contexto a la IA para que dé bienvenida cálida.',
                'prioridad'   => 95,
                'grafo'       => $this->grafoPrimerMensaje(),
            ],
            [
                'nombre'      => '📦 Cliente pregunta por su pedido',
                'descripcion' => 'Detecta consulta de estado y muestra los últimos pedidos automáticamente.',
                'prioridad'   => 85,
                'grafo'       => $this->grafoConsultaPedido(),
            ],
            [
                'nombre'      => '😡 Cliente molesto — deriva a Servicio',
                'descripcion' => 'Detecta tono de molestia/queja y deriva al departamento de servicio.',
                'prioridad'   => 90,
                'grafo'       => $this->grafoIntencion(
                    'cliente molesto, irritado, frustrado, enojado, va a demandar, va a reportar',
                    $servicio,
                    'Cliente con tono negativo detectado por flujo'
                ),
            ],
            [
                'nombre'      => '💼 Cotización mayorista / B2B',
                'descripcion' => 'Pasa a Comercial cuando preguntan por mayoristas, restaurantes o empresa.',
                'prioridad'   => 80,
                'grafo'       => $this->grafoPalabras(
                    'mayorista, b2b, corporativo, empresa, restaurante, hotel, cotización empresarial',
                    $comercial,
                    'Solicitud B2B / mayorista'
                ),
            ],
            [
                'nombre'      => '📋 Hoja de vida / empleo',
                'descripcion' => 'Deriva a Recursos Humanos cuando preguntan por vacantes o envían HV.',
                'prioridad'   => 80,
                'grafo'       => $this->grafoPalabras(
                    'hoja de vida, hv, empleo, vacante, trabajo, oferta laboral, contratación',
                    $rh,
                    'Tema laboral / hoja de vida'
                ),
            ],
            [
                'nombre'      => '🚨 Reclamos y PQR',
                'descripcion' => 'Deriva a Servicio al Cliente cuando hay reclamo o devolución.',
                'prioridad'   => 80,
                'grafo'       => $this->grafoPalabras(
                    'reclamo, queja, pqr, devolución, devolver, mala calidad, dañado, llegó mal',
                    $servicio,
                    'Reclamo o PQR'
                ),
            ],
        ];

        $creados = 0;
        foreach ($plantillas as $p) {
            // Evitar duplicar si ya existe uno con el mismo nombre
            $existe = FlujoBot::where('nombre', $p['nombre'])->exists();
            if ($existe) continue;

            FlujoBot::create([
                'nombre'      => $p['nombre'],
                'descripcion' => $p['descripcion'],
                'prioridad'   => $p['prioridad'],
                'activo'      => true,
                'grafo'       => $p['grafo'],
            ]);
            $creados++;
        }

        if ($creados === 0) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Las plantillas ya estaban instaladas.']);
            return;
        }

        // Avisar si quedaron flujos sin departamento real
        $sinDepto = !$servicio || !$comercial || !$rh;
        $this->dispatch('notify', [
            'type'    => $sinDepto ? 'warning' : 'success',
            'message' => $sinDepto
                ? "✅ Se instalaron {$creados} flujos. Algunos departamentos no existen aún — revisa cada flujo y asigna el correcto."
                : "✅ Se instalaron {$creados} flujos listos para usar.",
        ]);
    }

    /* ─── Builders de grafos prediseñados ─── */

    private function grafoSedeCerrada(): array
    {
        $nodos = [
            $this->nodoTrigger(1, 60, 80, [3]),
            $this->nodoCondHorario(2, 320, 80, 'cerrada', [3], []),
            $this->nodoMensaje(3, 580, 80,
                "Hola {nombre}, en este momento estamos cerrados 🙏. Te atendemos cuando abramos y con gusto te despachamos. ¿Te aviso apenas abramos?",
                [4]
            ),
            $this->nodoFin(4, 840, 80),
        ];
        // Conectar trigger → condicion (override)
        $nodos[1]['outputs']['output_1']['connections'] = [['node' => '2', 'output' => 'input_1']];
        return $this->envolver($nodos);
    }

    private function grafoIntencion(string $intencion, ?int $departamentoId, string $razon): array
    {
        $nodos = [
            $this->nodoTrigger(1, 60, 80, [2]),
            $this->nodoCondIntencion(2, 320, 80, $intencion, [3], []),
            $this->nodoDerivar(3, 580, 80, $departamentoId, $razon, [4]),
            $this->nodoFin(4, 840, 80),
        ];
        return $this->envolver($nodos);
    }

    private function grafoPalabras(string $palabras, ?int $departamentoId, string $razon): array
    {
        $nodos = [
            $this->nodoTrigger(1, 60, 80, [2]),
            $this->nodoCondPalabras(2, 320, 80, $palabras, [3], []),
            $this->nodoDerivar(3, 580, 80, $departamentoId, $razon, [4]),
            $this->nodoFin(4, 840, 80),
        ];
        return $this->envolver($nodos);
    }

    private function grafoPrimerMensaje(): array
    {
        $nodos = [
            $this->nodoBase(1, 'trigger_primer_msg', ['label' => 'Primer mensaje'], 60, 80, 0, 1),
            $this->nodoBase(2, 'accion_pasar_ia', [
                'label' => 'Continuar con IA',
                'contexto_extra' => 'Este es el PRIMER mensaje del cliente en la plataforma. Salúdalo cálido por su nombre, preséntate brevemente y pregúntale cómo puedes ayudarlo. NO listes catálogo todavía — espera a que pida algo concreto.',
            ], 320, 80, 1, 0),
        ];
        $nodos[0]['outputs']['output_1']['connections'] = [['node' => '2', 'output' => 'input_1']];
        return $this->envolver($nodos);
    }

    private function grafoConsultaPedido(): array
    {
        $nodos = [
            $this->nodoTrigger(1, 60, 80, [2]),
            $this->nodoCondPalabras(2, 320, 80,
                'mi pedido, mis pedidos, estado de mi pedido, cómo va mi pedido, cómo va mi orden, seguimiento, ya salió mi pedido',
                [3], []
            ),
            $this->nodoBase(3, 'accion_consultar_pedidos', ['label' => 'Consultar pedidos', 'limite' => 3], 580, 80, 1, 1),
            $this->nodoFin(4, 840, 80),
        ];
        $nodos[2]['outputs']['output_1']['connections'] = [['node' => '4', 'output' => 'input_1']];
        return $this->envolver($nodos);
    }

    private function envolver(array $nodos): array
    {
        $data = [];
        foreach ($nodos as $n) {
            $data[(string) $n['id']] = $n;
        }
        return ['drawflow' => ['Home' => ['data' => $data]]];
    }

    private function nodoBase(int $id, string $tipo, array $data, int $x, int $y, int $inputs, int $outputs): array
    {
        $node = [
            'id'       => $id,
            'name'     => $tipo,
            'data'     => array_merge(['tipo' => $tipo], $data),
            'class'    => $tipo,
            'html'     => '',
            'typenode' => false,
            'inputs'   => [],
            'outputs'  => [],
            'pos_x'    => $x,
            'pos_y'    => $y,
        ];
        for ($i = 1; $i <= $inputs; $i++)  $node['inputs']["input_{$i}"]   = ['connections' => []];
        for ($i = 1; $i <= $outputs; $i++) $node['outputs']["output_{$i}"] = ['connections' => []];
        return $node;
    }

    private function conexiones(array $idsDestino): array
    {
        return array_map(fn ($n) => ['node' => (string) $n, 'output' => 'input_1'], $idsDestino);
    }

    private function nodoTrigger(int $id, int $x, int $y, array $next): array
    {
        $n = $this->nodoBase($id, 'trigger', ['label' => 'Inicio'], $x, $y, 0, 1);
        $n['outputs']['output_1']['connections'] = $this->conexiones($next);
        return $n;
    }

    private function nodoCondHorario(int $id, int $x, int $y, string $estado, array $si, array $no): array
    {
        $n = $this->nodoBase($id, 'cond_horario', ['label' => 'Horario sede', 'estado' => $estado], $x, $y, 1, 2);
        $n['outputs']['output_1']['connections'] = $this->conexiones($si);
        $n['outputs']['output_2']['connections'] = $this->conexiones($no);
        return $n;
    }

    private function nodoCondPalabras(int $id, int $x, int $y, string $palabras, array $si, array $no): array
    {
        $n = $this->nodoBase($id, 'cond_palabras', ['label' => 'Si contiene', 'palabras' => $palabras], $x, $y, 1, 2);
        $n['outputs']['output_1']['connections'] = $this->conexiones($si);
        $n['outputs']['output_2']['connections'] = $this->conexiones($no);
        return $n;
    }

    private function nodoCondIntencion(int $id, int $x, int $y, string $intencion, array $si, array $no): array
    {
        $n = $this->nodoBase($id, 'cond_intencion', ['label' => 'Intención IA', 'intencion' => $intencion], $x, $y, 1, 2);
        $n['outputs']['output_1']['connections'] = $this->conexiones($si);
        $n['outputs']['output_2']['connections'] = $this->conexiones($no);
        return $n;
    }

    private function nodoDerivar(int $id, int $x, int $y, ?int $deptoId, string $razon, array $next): array
    {
        $n = $this->nodoBase($id, 'accion_derivar', [
            'label' => 'Derivar',
            'departamento_id' => $deptoId,
            'razon' => $razon,
        ], $x, $y, 1, 1);
        $n['outputs']['output_1']['connections'] = $this->conexiones($next);
        return $n;
    }

    private function nodoMensaje(int $id, int $x, int $y, string $mensaje, array $next): array
    {
        $n = $this->nodoBase($id, 'accion_mensaje', ['label' => 'Mensaje', 'mensaje' => $mensaje], $x, $y, 1, 1);
        $n['outputs']['output_1']['connections'] = $this->conexiones($next);
        return $n;
    }

    private function nodoFin(int $id, int $x, int $y): array
    {
        return $this->nodoBase($id, 'fin', ['label' => 'Fin'], $x, $y, 1, 0);
    }

    private function grafoVacio(): array
    {
        return [
            'drawflow' => [
                'Home' => [
                    'data' => [],
                ],
            ],
        ];
    }

    public function render()
    {
        return view('livewire.configuracion.flujos.index', [
            'flujos'        => FlujoBot::orderByDesc('prioridad')->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->orderBy('nombre')->get(),
            'sedes'         => \App\Models\Sede::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'zonas'         => \App\Models\ZonaCobertura::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos'     => \App\Models\Producto::where('activo', true)->orderBy('nombre')->limit(200)->get(['id', 'codigo', 'nombre']),
            'ans'           => \App\Models\AnsPedido::where('activo', true)->orderBy('accion')->get(['id', 'accion', 'tiempo_minutos']),
            'configBot'     => \App\Models\ConfiguracionBot::actual(),
        ])->layout('layouts.app');
    }
}
