<?php

namespace App\Livewire\Integraciones;

use App\Models\Integracion;
use App\Services\IntegracionSyncService;
use Livewire\Component;

class Index extends Component
{
    public bool $modal = false;
    public ?int $editandoId = null;

    public string $nombre   = '';
    public string $tipo     = Integracion::TIPO_SQLSRV;
    public string $entidad  = Integracion::ENTIDAD_PRODUCTOS;
    public bool   $activo   = true;

    public string $host     = '';
    public string $port     = '';
    public string $database = '';
    public string $username = '';
    public string $password = '';
    public string $query    = "SELECT CodArt AS codigo, Descripcion AS nombre, Familia AS categoria, PrecioVta AS precio_base, Unidad AS unidad\nFROM Articulos\nWHERE Activo = 1";

    // Mapeo campo_destino => columna_origen (del SELECT)
    public array $mapeo = [
        'codigo'      => 'codigo',
        'nombre'      => 'nombre',
        'categoria'   => 'categoria',
        'precio_base' => 'precio_base',
        'unidad'      => 'unidad',
        'descripcion' => 'descripcion',
    ];

    // Resultado del "Probar conexión"
    public ?array $testResult = null;

    protected function rules(): array
    {
        return [
            'nombre'   => 'required|string|max:120',
            'tipo'     => 'required|in:mysql,pgsql,sqlsrv,rest',
            'entidad'  => 'required|in:productos,categorias',
            'host'     => 'required|string|max:255',
            'port'     => 'nullable|numeric',
            'database' => 'required|string|max:120',
            'username' => 'nullable|string|max:120',
            'password' => 'nullable|string|max:200',
            'query'    => 'required|string',
        ];
    }

    public function render()
    {
        return view('livewire.integraciones.index', [
            'integraciones' => Integracion::orderByDesc('id')->get(),
        ])->layout('layouts.app');
    }

    public function abrirCrear(): void
    {
        $this->resetCampos();
        $this->modal = true;
    }

    public function abrirEditar(int $id): void
    {
        $i = Integracion::findOrFail($id);
        $this->editandoId = $i->id;
        $this->nombre     = $i->nombre;
        $this->tipo       = $i->tipo;
        $this->entidad    = $i->entidad;
        $this->activo     = $i->activo;

        $c = $i->config ?? [];
        $this->host     = $c['host']     ?? '';
        $this->port     = (string) ($c['port'] ?? '');
        $this->database = $c['database'] ?? '';
        $this->username = $c['username'] ?? '';
        $this->password = $c['password'] ?? '';
        $this->query    = $c['query']    ?? '';
        $this->mapeo    = array_merge($this->mapeo, $c['mapeo'] ?? []);

        $this->modal = true;
    }

    public function cerrarModal(): void
    {
        $this->modal = false;
        $this->testResult = null;
    }

    public function guardar(): void
    {
        $data = $this->validate();

        $config = [
            'host'     => $this->host,
            'port'     => $this->port !== '' ? (int) $this->port : null,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'query'    => $this->query,
            'mapeo'    => array_filter($this->mapeo, fn ($v) => $v !== '' && $v !== null),
        ];

        if ($this->editandoId) {
            Integracion::findOrFail($this->editandoId)->update([
                'nombre' => $this->nombre, 'tipo' => $this->tipo, 'entidad' => $this->entidad,
                'activo' => $this->activo, 'config' => $config,
            ]);
        } else {
            Integracion::create([
                'nombre' => $this->nombre, 'tipo' => $this->tipo, 'entidad' => $this->entidad,
                'activo' => $this->activo, 'config' => $config,
            ]);
        }

        $this->modal = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Integración guardada']);
    }

    public function eliminar(int $id): void
    {
        Integracion::findOrFail($id)->delete();
        $this->dispatch('notify', ['type' => 'success', 'message' => '✓ Integración eliminada']);
    }

    public function toggleActivo(int $id): void
    {
        $i = Integracion::findOrFail($id);
        $i->update(['activo' => !$i->activo]);
    }

    /**
     * Prueba la conexión con los valores actuales del formulario (sin persistir).
     */
    public function probarConexion(): void
    {
        $this->testResult = null;

        // Crear integración temporal en memoria para probar
        $temp = new Integracion([
            'tenant_id' => app(\App\Services\TenantManager::class)->current()?->id,
            'nombre'    => 'TEST',
            'tipo'      => $this->tipo,
            'entidad'   => $this->entidad,
            'activo'    => true,
            'config'    => [
                'host' => $this->host, 'port' => $this->port !== '' ? (int) $this->port : null,
                'database' => $this->database, 'username' => $this->username, 'password' => $this->password,
                'query' => $this->query, 'mapeo' => $this->mapeo,
            ],
        ]);
        $temp->exists = false;

        $this->testResult = app(IntegracionSyncService::class)->probarConexion($temp);
    }

    /**
     * Ejecuta la sincronización de una integración persistida.
     */
    public function sincronizar(int $id): void
    {
        $i = Integracion::findOrFail($id);
        $r = app(IntegracionSyncService::class)->sincronizar($i);

        $this->dispatch('notify', [
            'type'    => $r['ok'] ? 'success' : 'error',
            'message' => $r['ok']
                ? "✓ Sync OK: {$r['creados']} creados, {$r['actualizados']} actualizados"
                : '❌ ' . $r['log'],
        ]);
    }

    private function resetCampos(): void
    {
        $this->editandoId = null;
        $this->nombre = '';
        $this->tipo = Integracion::TIPO_SQLSRV;
        $this->entidad = Integracion::ENTIDAD_PRODUCTOS;
        $this->activo = true;
        $this->host = '';
        $this->port = '';
        $this->database = '';
        $this->username = '';
        $this->password = '';
        $this->query = "SELECT CodArt AS codigo, Descripcion AS nombre, Familia AS categoria, PrecioVta AS precio_base, Unidad AS unidad\nFROM Articulos\nWHERE Activo = 1";
        $this->testResult = null;
    }
}
