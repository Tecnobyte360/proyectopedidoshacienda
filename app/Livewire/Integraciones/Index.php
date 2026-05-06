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

    // 🚀 EXPORT DE PEDIDOS al ERP del cliente
    public bool   $exporta_pedidos       = false;
    public string $export_tabla          = 'TblDocumentos';
    public string $export_empresa        = '1';
    public string $export_transaccion    = '009';
    public string $export_bodega         = '1';
    public string $export_cartera        = '1';
    public string $export_usuario        = 'admin';
    public string $export_consecutivo    = '600';
    public string $export_ano            = '';
    public string $export_periodo        = '';
    public string $export_plazo          = '0';
    public string $export_sucursal       = '0';
    public string $export_ccosto         = '0';
    public string $export_subccosto      = '0';

    // 📋 EXPORT DETALLE (líneas del pedido a TblDetalleDocumentos)
    public bool   $export_detalle_activo = false;
    public string $export_detalle_tabla  = 'TblDetalleDocumentos';

    // Resultado del "Probar conexión"
    public ?array $testResult = null;
    public int $pruebaPage = 1;
    public int $pruebaPerPage = 25;

    // Explorador de BD
    public array $tablas = [];
    public string $tablaSeleccionada = '';
    public array $columnas = [];
    public array $muestraTabla = [];
    public ?string $explorarError = null;

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
        $this->nombre     = $this->utf8Safe($i->nombre);
        $this->tipo       = $i->tipo;
        $this->entidad    = $i->entidad;
        $this->activo     = $i->activo;

        $c = $i->config ?? [];
        $this->host     = $this->utf8Safe($c['host']     ?? '');
        $this->port     = (string) ($c['port'] ?? '');
        $this->database = $this->utf8Safe($c['database'] ?? '');
        $this->username = $this->utf8Safe($c['username'] ?? '');
        $this->password = $this->utf8Safe($c['password'] ?? '');
        $this->query    = $this->utf8Safe($c['query']    ?? '');
        $this->mapeo    = array_merge($this->mapeo, $c['mapeo'] ?? []);

        // Cargar config de export
        $this->exporta_pedidos = (bool) $i->exporta_pedidos;
        $exp = $c['export'] ?? [];
        $this->export_tabla       = (string) ($exp['tabla']               ?? 'TblDocumentos');
        $this->export_empresa     = (string) ($exp['empresa']             ?? '1');
        $this->export_transaccion = (string) ($exp['transaccion']         ?? '009');
        $this->export_bodega      = (string) ($exp['bodega']              ?? '1');
        $this->export_cartera     = (string) ($exp['cartera']             ?? '1');
        $this->export_usuario     = (string) ($exp['usuario_grabador']    ?? 'admin');
        $this->export_consecutivo = (string) ($exp['consecutivo_inicial'] ?? '1');
        $this->export_ano         = (string) ($exp['ano']                 ?? date('Y'));
        $this->export_periodo     = (string) ($exp['periodo']             ?? date('n'));
        $this->export_plazo       = (string) ($exp['plazo']               ?? '0');
        $this->export_sucursal    = (string) ($exp['sucursal']            ?? '0');
        $this->export_ccosto      = (string) ($exp['ccosto']              ?? '0');
        $this->export_subccosto   = (string) ($exp['subccosto']           ?? '0');

        // Cargar config de detalle
        $det = $exp['detalle'] ?? [];
        $this->export_detalle_activo = (bool) ($det['activo'] ?? false);
        $this->export_detalle_tabla  = (string) ($det['tabla'] ?? 'TblDetalleDocumentos');

        $this->modal = true;
    }

    /**
     * Asegura que un string sea UTF-8 válido. Los valores cifrados/binarios
     * legacy en config rompen Livewire al serializar a JSON.
     */
    private function utf8Safe($value): string
    {
        if (!is_string($value) || $value === '') return '';
        // mb_convert_encoding limpia bytes inválidos sustituyéndolos
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * Limpia recursivamente strings no-UTF8 de un array (resultados SQL Server,
     * etc.). Detecta el encoding origen (CP1252/ISO-8859-1) y convierte a UTF-8.
     * Sin esto, Livewire revienta al serializar el snapshot.
     */
    private function utf8SafeArray($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[is_string($k) ? $this->utf8SafeString($k) : $k] = $this->utf8SafeArray($v);
            }
            return $out;
        }
        if (is_string($value)) {
            return $this->utf8SafeString($value);
        }
        return $value;
    }

    private function utf8SafeString(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }
        // Intenta detectar; si falla cae a Windows-1252 (lo más común en SQL Server LATAM)
        $detected = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252';
        return mb_convert_encoding($value, 'UTF-8', $detected);
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
            'export'   => [
                'tabla'              => trim($this->export_tabla) ?: 'TblDocumentos',
                'empresa'            => (int) $this->export_empresa,
                'transaccion'        => trim($this->export_transaccion),
                'bodega'             => (int) $this->export_bodega,
                'cartera'            => (int) $this->export_cartera,
                'usuario_grabador'   => trim($this->export_usuario) ?: 'admin',
                'consecutivo_inicial'=> (int) $this->export_consecutivo,
                'ano'                => (int) $this->export_ano ?: (int) date('Y'),
                'periodo'            => (int) $this->export_periodo ?: (int) date('n'),
                'ano_cartera'        => (int) $this->export_ano ?: (int) date('Y'),
                'periodo_cartera'    => (int) $this->export_periodo ?: (int) date('n'),
                'plazo'              => trim($this->export_plazo) ?: '0',
                'sucursal'           => trim($this->export_sucursal) ?: '0',
                'ccosto'             => trim($this->export_ccosto) ?: '0',
                'subccosto'          => trim($this->export_subccosto) ?: '0',
                'tran_aux'           => 0,
                'doc_ref'            => 0,
                // 📋 Detalle (líneas del pedido)
                'detalle' => [
                    'activo' => $this->export_detalle_activo,
                    'tabla'  => trim($this->export_detalle_tabla) ?: 'TblDetalleDocumentos',
                    // Los demás campos heredan del header por default (ver IntegracionExportService::camposDetalle)
                ],
            ],
        ];

        $datosBase = [
            'nombre'  => $this->nombre,
            'tipo'    => $this->tipo,
            'entidad' => $this->entidad,
            'activo'  => $this->activo,
            'config'  => $config,
            'exporta_pedidos' => $this->exporta_pedidos,
        ];

        if ($this->editandoId) {
            Integracion::findOrFail($this->editandoId)->update($datosBase);
        } else {
            Integracion::create($datosBase);
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

    private function temporalParaExplorar(): Integracion
    {
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
        return $temp;
    }

    /**
     * Carga la lista de tablas de la BD externa.
     */
    public function listarTablas(): void
    {
        $this->explorarError = null;
        $r = $this->utf8SafeArray(
            app(IntegracionSyncService::class)->listarTablas($this->temporalParaExplorar())
        );
        if ($r['ok']) {
            $this->tablas = $r['tablas'];
        } else {
            $this->tablas = [];
            $this->explorarError = $r['mensaje'];
        }
    }

    /**
     * Al seleccionar una tabla, carga sus columnas y genera un query base.
     */
    public function seleccionarTabla(string $tabla): void
    {
        $this->tablaSeleccionada = $tabla;
        $this->explorarError = null;

        $r = $this->utf8SafeArray(
            app(IntegracionSyncService::class)->describirTabla($this->temporalParaExplorar(), $tabla)
        );
        if (!$r['ok']) {
            $this->explorarError = $r['mensaje'];
            return;
        }

        $this->columnas     = $r['columnas'];
        $this->muestraTabla = $r['muestra'];

        // Autogenerar query base con todas las columnas
        $cols = array_map(fn ($c) => $c['nombre'], $r['columnas']);
        $tblRef = $this->tipo === 'sqlsrv' ? "[" . str_replace('.', '].[', $tabla) . "]" : $tabla;
        $this->query = "SELECT " . implode(', ', $cols) . "\nFROM " . $tblRef;

        // Auto-mapeo heurístico: busca columnas parecidas a nuestros campos
        $this->autoMapear($cols);
    }

    private function autoMapear(array $cols): void
    {
        $normalizados = [];
        foreach ($cols as $col) {
            $normalizados[strtolower(preg_replace('/[^a-z0-9]/i', '', $col))] = $col;
        }

        $reglas = [
            'codigo'      => ['codigo', 'cod', 'codart', 'codproducto', 'sku', 'referencia', 'ref'],
            'nombre'      => ['nombre', 'descripcion', 'descrip', 'articulo', 'producto', 'nombreproducto'],
            'categoria'   => ['categoria', 'familia', 'grupo', 'linea', 'seccion'],
            'precio_base' => ['preciovta', 'precioventa', 'precio', 'pvp', 'valor', 'preciobase', 'pvta'],
            'unidad'      => ['unidad', 'unid', 'um', 'unidadmedida', 'presentacion'],
            'descripcion' => ['descripcionlarga', 'descriplarga', 'detalles', 'detalle', 'observaciones'],
        ];

        foreach ($reglas as $campoDestino => $patrones) {
            foreach ($patrones as $p) {
                if (isset($normalizados[$p])) {
                    $this->mapeo[$campoDestino] = $normalizados[$p];
                    break;
                }
            }
        }
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

        $this->testResult = $this->utf8SafeArray(
            app(IntegracionSyncService::class)->probarConexion($temp, $this->pruebaPage, $this->pruebaPerPage)
        );
    }

    public function pruebaIrPagina(int $pagina): void
    {
        $this->pruebaPage = max(1, $pagina);
        $this->probarConexion();
    }

    public function pruebaCambiarPerPage(int $n): void
    {
        $this->pruebaPerPage = max(10, min(200, $n));
        $this->pruebaPage = 1;
        $this->probarConexion();
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
