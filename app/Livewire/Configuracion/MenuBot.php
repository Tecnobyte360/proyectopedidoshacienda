<?php

namespace App\Livewire\Configuracion;

use App\Models\ConfiguracionBot;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Editor visual del MENÚ DETERMINISTA del bot (sin IA).
 * Permite editar los textos, opciones y submenús sin tocar código.
 */
#[Layout('layouts.app')]
class MenuBot extends Component
{
    public bool   $botModoMenu = false;

    public string $welcomeText = '';
    /** @var array<int,array{k:string,target:string}> */
    public array  $welcomeOptions = [];

    /** @var array<int,array{id:string,text:string,back:string,isMenu:bool,options:array}> */
    public array  $nodes = [];

    public function mount(): void
    {
        $cfg  = ConfiguracionBot::actual();
        $menu = is_array($cfg->menu_json) ? $cfg->menu_json : [];

        $this->botModoMenu = (bool) ($cfg->bot_modo_menu ?? false);

        $this->welcomeText    = (string) ($menu['welcome']['text'] ?? '');
        $this->welcomeOptions = $this->mapToRows($menu['welcome']['options'] ?? []);

        foreach (($menu['nodes'] ?? []) as $id => $n) {
            $tieneOpts = !empty($n['options']);
            $this->nodes[] = [
                'id'      => (string) $id,
                'text'    => (string) ($n['text'] ?? ''),
                'back'    => (string) ($n['back'] ?? 'welcome'),
                'isMenu'  => $tieneOpts,
                'options' => $this->mapToRows($n['options'] ?? []),
            ];
        }
    }

    /** Convierte ['1'=>'destino'] en [['k'=>'1','target'=>'destino']]. */
    private function mapToRows($assoc): array
    {
        $rows = [];
        foreach ((array) $assoc as $k => $v) {
            $rows[] = ['k' => (string) $k, 'target' => (string) $v];
        }
        return $rows;
    }

    /** IDs válidos para los <select> de destino. */
    public function getTargetIdsProperty(): array
    {
        $ids = ['welcome'];
        foreach ($this->nodes as $n) {
            if (trim($n['id']) !== '') $ids[] = $n['id'];
        }
        return array_values(array_unique($ids));
    }

    // ── Opciones de la bienvenida ──
    public function addWelcomeOption(): void
    {
        $this->welcomeOptions[] = ['k' => (string) (count($this->welcomeOptions) + 1), 'target' => 'welcome'];
    }

    public function removeWelcomeOption(int $i): void
    {
        unset($this->welcomeOptions[$i]);
        $this->welcomeOptions = array_values($this->welcomeOptions);
    }

    // ── Nodos ──
    public function addNode(): void
    {
        $this->nodes[] = [
            'id'      => 'nodo_' . Str::lower(Str::random(5)),
            'text'    => '',
            'back'    => 'welcome',
            'isMenu'  => false,
            'options' => [],
        ];
    }

    public function removeNode(int $i): void
    {
        unset($this->nodes[$i]);
        $this->nodes = array_values($this->nodes);
    }

    public function addNodeOption(int $i): void
    {
        $this->nodes[$i]['options'][] = ['k' => (string) (count($this->nodes[$i]['options']) + 1), 'target' => 'welcome'];
    }

    public function removeNodeOption(int $i, int $j): void
    {
        unset($this->nodes[$i]['options'][$j]);
        $this->nodes[$i]['options'] = array_values($this->nodes[$i]['options']);
    }

    public function guardar(): void
    {
        // Bienvenida
        $welcome = [
            'text'    => $this->welcomeText,
            'options' => $this->rowsToMap($this->welcomeOptions),
        ];

        // Nodos
        $nodesOut = [];
        foreach ($this->nodes as $n) {
            $id = trim($n['id']);
            if ($id === '' || $id === 'welcome') continue;   // id reservado / vacío

            $nodo = [
                'text' => $n['text'],
                'back' => trim($n['back']) !== '' ? $n['back'] : 'welcome',
            ];
            if (!empty($n['isMenu'])) {
                $nodo['options'] = $this->rowsToMap($n['options']);
            }
            $nodesOut[$id] = $nodo;
        }

        $menu = ['welcome' => $welcome, 'nodes' => $nodesOut];

        $cfg = ConfiguracionBot::actual();
        $cfg->menu_json     = $menu;
        $cfg->bot_modo_menu = $this->botModoMenu;
        if ($this->botModoMenu) {
            // En modo menú apagamos la maquinaria de IA/pedidos para que no interfiera.
            $cfg->bot_modo_agente            = false;
            $cfg->usar_prompt_personalizado  = false;
            $cfg->agrupar_mensajes_activo    = false;
        }
        $cfg->save();

        ConfiguracionBot::limpiarCache();

        session()->flash('ok', '✅ Menú guardado. Los cambios ya están en vivo.');
    }

    /** [['k'=>'1','target'=>'x']] → ['1'=>'x'] (descarta filas vacías). */
    private function rowsToMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $k = trim((string) ($r['k'] ?? ''));
            $t = trim((string) ($r['target'] ?? ''));
            if ($k === '' || $t === '') continue;
            $map[$k] = $t;
        }
        return $map;
    }

    public function render()
    {
        return view('livewire.configuracion.menu-bot');
    }
}
