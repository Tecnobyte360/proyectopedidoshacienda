<div class="px-4 lg:px-8 py-6">

    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                🏧 Editor de Menú del Bot
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Chatbot por opciones numéricas, sin IA. Ideal para bancos e instituciones: respuestas fijas y exactas.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" wire:model="botModoMenu" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span class="text-sm font-medium text-gray-700">Activar modo menú</span>
            </label>
            <button wire:click="guardar"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow">
                💾 Guardar cambios
            </button>
        </div>
    </div>

    @if (session('ok'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
            {{ session('ok') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Columna editor --}}
        <div class="xl:col-span-2 space-y-6">

            {{-- Bienvenida --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    👋 Mensaje de bienvenida
                    <span class="text-xs font-normal text-gray-400">(lo primero que ve el cliente)</span>
                </h2>
                <textarea wire:model="welcomeText" rows="6"
                          class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-emerald-500 focus:border-emerald-500"
                          placeholder="Bienvenido...&#10;1 - Opción A&#10;2 - Opción B"></textarea>

                <div class="mt-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Opciones (número → a dónde lleva)</span>
                        <button wire:click="addWelcomeOption" class="text-xs text-emerald-700 hover:underline">+ Agregar opción</button>
                    </div>
                    @forelse ($welcomeOptions as $i => $opt)
                        <div class="flex items-center gap-2 mb-2" wire:key="wopt-{{ $i }}">
                            <input type="text" wire:model="welcomeOptions.{{ $i }}.k"
                                   class="w-16 rounded-lg border-gray-300 text-sm text-center" placeholder="1">
                            <span class="text-gray-400">→</span>
                            <select wire:model="welcomeOptions.{{ $i }}.target"
                                    class="flex-1 rounded-lg border-gray-300 text-sm">
                                @foreach ($this->targetIds as $tid)
                                    <option value="{{ $tid }}">{{ $tid }}</option>
                                @endforeach
                            </select>
                            <button wire:click="removeWelcomeOption({{ $i }})" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin opciones aún.</p>
                    @endforelse
                </div>
            </div>

            {{-- Nodos --}}
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">📋 Menús y respuestas</h2>
                <button wire:click="addNode"
                        class="text-sm px-3 py-1.5 rounded-lg bg-gray-800 text-white hover:bg-gray-900">+ Nueva respuesta</button>
            </div>

            @forelse ($nodes as $i => $n)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5" wire:key="node-{{ $i }}">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1 text-xs font-mono bg-gray-100 text-gray-600 px-2 py-1 rounded">
                            🔖 {{ $n['id'] }}
                        </span>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                <input type="checkbox" wire:model="nodes.{{ $i }}.isMenu"
                                       class="rounded border-gray-300 text-emerald-600">
                                Es un submenú (tiene opciones)
                            </label>
                            <button wire:click="removeNode({{ $i }})" class="text-red-500 hover:text-red-700 text-sm">🗑️ Eliminar</button>
                        </div>
                    </div>

                    <textarea wire:model="nodes.{{ $i }}.text" rows="4"
                              class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-emerald-500 focus:border-emerald-500"
                              placeholder="Texto de la respuesta..."></textarea>

                    <div class="mt-3 flex items-center gap-2">
                        <span class="text-xs text-gray-500">Al marcar 0 vuelve a:</span>
                        <select wire:model="nodes.{{ $i }}.back" class="rounded-lg border-gray-300 text-sm">
                            @foreach ($this->targetIds as $tid)
                                <option value="{{ $tid }}">{{ $tid }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Opciones del submenú --}}
                    <div class="mt-4 border-t border-gray-100 pt-3" x-data x-show="$wire.nodes[{{ $i }}].isMenu">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Opciones</span>
                            <button wire:click="addNodeOption({{ $i }})" class="text-xs text-emerald-700 hover:underline">+ Agregar opción</button>
                        </div>
                        @foreach ($n['options'] as $j => $opt)
                            <div class="flex items-center gap-2 mb-2" wire:key="node-{{ $i }}-opt-{{ $j }}">
                                <input type="text" wire:model="nodes.{{ $i }}.options.{{ $j }}.k"
                                       class="w-16 rounded-lg border-gray-300 text-sm text-center" placeholder="1">
                                <span class="text-gray-400">→</span>
                                <select wire:model="nodes.{{ $i }}.options.{{ $j }}.target"
                                        class="flex-1 rounded-lg border-gray-300 text-sm">
                                    @foreach ($this->targetIds as $tid)
                                        <option value="{{ $tid }}">{{ $tid }}</option>
                                    @endforeach
                                </select>
                                <button wire:click="removeNodeOption({{ $i }}, {{ $j }})" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-gray-50 rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-400">
                    No hay respuestas aún. Dale a <b>“+ Nueva respuesta”</b> para crear la primera.
                </div>
            @endforelse
        </div>

        {{-- Columna ayuda / preview --}}
        <div class="space-y-4">
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-900 sticky top-4">
                <h3 class="font-semibold mb-2">💡 Cómo funciona</h3>
                <ul class="list-disc list-inside space-y-1 text-emerald-800/90">
                    <li>El cliente navega <b>marcando números</b>.</li>
                    <li>Cada respuesta es <b>fija y exacta</b> (sin IA).</li>
                    <li><b>Una opción</b> lleva a otra respuesta o submenú.</li>
                    <li>Marcar <b>0</b> vuelve al menú indicado.</li>
                    <li>Usa <code>*texto*</code> para <b>negrita</b> en WhatsApp.</li>
                </ul>
                <hr class="my-3 border-emerald-200">
                <p class="text-xs text-emerald-700">
                    Recuerda darle <b>Guardar cambios</b>. Se aplica al instante.
                </p>
            </div>
        </div>
    </div>
</div>
