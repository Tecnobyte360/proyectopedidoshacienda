<div class="min-h-screen bg-slate-50" x-data="importadorComponent()" x-init="init()">
    <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 space-y-6">

        {{-- HEADER --}}
        <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-secondary text-white shadow-lg">
                    <i class="fa-solid fa-file-import text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800">Importaciones</h2>
                    <p class="text-sm text-slate-500">Carga masiva de productos y categorías desde CSV o Excel (.xlsx)</p>
                </div>
            </div>
        </div>

        {{-- TABS --}}
        <div class="inline-flex rounded-2xl bg-white border border-slate-200 p-1 shadow-sm">
            <button wire:click="setTipo('productos')"
                    class="px-5 py-2 rounded-xl text-sm font-semibold transition
                           {{ $tipo === 'productos' ? 'bg-gradient-to-r from-brand to-brand-secondary text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
                <i class="fa-solid fa-box mr-2"></i> Productos
            </button>
            <button wire:click="setTipo('categorias')"
                    class="px-5 py-2 rounded-xl text-sm font-semibold transition
                           {{ $tipo === 'categorias' ? 'bg-gradient-to-r from-brand to-brand-secondary text-white shadow' : 'text-slate-600 hover:bg-slate-50' }}">
                <i class="fa-solid fa-tags mr-2"></i> Categorías
            </button>
        </div>

        {{-- INSTRUCCIONES + PLANTILLA --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 mb-2">
                    <i class="fa-solid fa-circle-info text-brand"></i>
                    Formato del archivo — {{ ucfirst($tipo) }}
                </h3>
                @if($tipo === 'productos')
                    <p class="text-sm text-slate-600 mb-2">Columnas soportadas (el orden es libre, los headers deben coincidir):</p>
                    <ul class="text-xs text-slate-600 space-y-1">
                        <li><code class="bg-slate-100 px-1 rounded">codigo</code> — Código interno (opcional, recomendado para updates).</li>
                        <li><code class="bg-slate-100 px-1 rounded">nombre</code> — Nombre del producto <strong>(obligatorio)</strong>.</li>
                        <li><code class="bg-slate-100 px-1 rounded">categoria</code> — Nombre de la categoría (se crea si no existe).</li>
                        <li><code class="bg-slate-100 px-1 rounded">unidad</code> — ej. <em>lb, kg, unidad, paquete</em>.</li>
                        <li><code class="bg-slate-100 px-1 rounded">precio_base</code> — Acepta <code>15000</code>, <code>15.000</code>, <code>15.000,00</code>.</li>
                        <li><code class="bg-slate-100 px-1 rounded">descripcion_corta</code>, <code class="bg-slate-100 px-1 rounded">descripcion</code></li>
                        <li><code class="bg-slate-100 px-1 rounded">palabras_clave</code> — Separadas por coma o pipe.</li>
                        <li><code class="bg-slate-100 px-1 rounded">activo</code>, <code class="bg-slate-100 px-1 rounded">destacado</code> — <code>si/no/1/0</code>.</li>
                        <li><code class="bg-slate-100 px-1 rounded">orden</code> — Número.</li>
                    </ul>
                @else
                    <p class="text-sm text-slate-600 mb-2">Columnas soportadas:</p>
                    <ul class="text-xs text-slate-600 space-y-1">
                        <li><code class="bg-slate-100 px-1 rounded">nombre</code> <strong>(obligatorio)</strong></li>
                        <li><code class="bg-slate-100 px-1 rounded">descripcion</code></li>
                        <li><code class="bg-slate-100 px-1 rounded">icono_emoji</code> — ej. 🥩 🍞 🥛</li>
                        <li><code class="bg-slate-100 px-1 rounded">color</code> — hex (#d68643)</li>
                        <li><code class="bg-slate-100 px-1 rounded">orden</code></li>
                        <li><code class="bg-slate-100 px-1 rounded">activo</code></li>
                    </ul>
                @endif
                <p class="text-xs text-slate-500 mt-3">
                    <i class="fa-solid fa-lightbulb text-amber-500"></i>
                    Si ya existe un registro con el mismo <strong>{{ $tipo === 'productos' ? 'código (o nombre)' : 'nombre' }}</strong>, se actualiza; si no, se crea.
                </p>
            </div>

            <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 mb-2">
                        <i class="fa-solid fa-file-csv text-emerald-600"></i> Plantilla
                    </h3>
                    <p class="text-sm text-slate-600 mb-4">Descarga el archivo de ejemplo con los headers correctos.</p>
                </div>
                <a href="{{ route('importaciones.plantilla', ['tipo' => $tipo]) }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-4 py-2.5 text-sm transition">
                    <i class="fa-solid fa-download"></i> Descargar plantilla {{ $tipo }}.csv
                </a>
            </div>
        </div>

        {{-- UPLOADER --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center"
                 @dragover.prevent="dragOver = true"
                 @dragleave.prevent="dragOver = false"
                 @drop.prevent="handleDrop($event)"
                 :class="dragOver ? 'border-brand bg-brand-soft/30' : ''">

                <template x-if="!fileName">
                    <div>
                        <div class="mx-auto w-16 h-16 bg-brand-soft rounded-2xl flex items-center justify-center mb-3">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl text-brand-secondary"></i>
                        </div>
                        <p class="text-slate-700 font-semibold">Arrastra tu archivo aquí o haz clic para seleccionar</p>
                        <p class="text-xs text-slate-500 mt-1">CSV o XLSX — máximo 20 MB</p>
                        <label class="mt-4 inline-flex items-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold px-5 py-2.5 text-sm cursor-pointer transition">
                            <i class="fa-solid fa-folder-open"></i> Seleccionar archivo
                            <input type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="handleFile($event)">
                        </label>
                    </div>
                </template>

                <template x-if="fileName">
                    <div>
                        <div class="mx-auto w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center mb-3">
                            <i class="fa-solid fa-file-circle-check text-2xl text-emerald-600"></i>
                        </div>
                        <p class="text-slate-700 font-semibold" x-text="fileName"></p>
                        <p class="text-xs text-slate-500 mt-1" x-text="fileSize"></p>
                        <div class="flex items-center justify-center gap-2 mt-4">
                            <button @click="importar()" :disabled="loading"
                                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-5 py-2.5 text-sm transition disabled:opacity-50">
                                <i class="fa-solid" :class="loading ? 'fa-circle-notch fa-spin' : 'fa-paper-plane'"></i>
                                <span x-text="loading ? 'Procesando...' : 'Importar ahora'"></span>
                            </button>
                            <button @click="reset()" :disabled="loading"
                                    class="rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold px-4 py-2.5 text-sm transition disabled:opacity-50">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- RESUMEN --}}
        @if($resumen)
            <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 mb-3">
                    <i class="fa-solid fa-chart-simple text-brand"></i> Resumen de la última importación
                </h3>
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-center">
                        <p class="text-3xl font-extrabold text-emerald-700">{{ $resumen['creados'] }}</p>
                        <p class="text-xs font-semibold text-emerald-600">Creados</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-center">
                        <p class="text-3xl font-extrabold text-blue-700">{{ $resumen['actualizados'] }}</p>
                        <p class="text-xs font-semibold text-blue-600">Actualizados</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-center">
                        <p class="text-3xl font-extrabold text-rose-700">{{ $resumen['omitidos'] }}</p>
                        <p class="text-xs font-semibold text-rose-600">Con error</p>
                    </div>
                </div>

                @if(!empty($errores))
                    <div class="mt-4">
                        <h4 class="text-sm font-bold text-rose-700 mb-2">
                            <i class="fa-solid fa-triangle-exclamation"></i> Errores ({{ count($errores) }})
                        </h4>
                        <div class="bg-rose-50 border border-rose-200 rounded-xl p-3 max-h-48 overflow-y-auto">
                            <ul class="text-xs text-rose-700 space-y-1">
                                @foreach($errores as $err)
                                    <li>• {{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script>
        function importadorComponent() {
            return {
                fileName: '',
                fileSize: '',
                fileDataUrl: null,
                loading: false,
                dragOver: false,

                init() {
                    window.addEventListener('livewire:navigated', () => this.reset());
                },

                handleFile(e) {
                    const file = e.target.files && e.target.files[0];
                    if (file) this._readFile(file);
                },
                handleDrop(e) {
                    this.dragOver = false;
                    const file = e.dataTransfer.files && e.dataTransfer.files[0];
                    if (file) this._readFile(file);
                },
                _readFile(file) {
                    if (file.size > 20 * 1024 * 1024) {
                        alert('Archivo demasiado grande (máx 20 MB).');
                        return;
                    }
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (!['csv', 'xlsx', 'xls'].includes(ext)) {
                        alert('Solo se aceptan archivos CSV o XLSX.');
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => {
                        this.fileName = file.name;
                        this.fileSize = this._humanSize(file.size);
                        this.fileDataUrl = reader.result;
                    };
                    reader.readAsDataURL(file);
                },
                async importar() {
                    if (!this.fileDataUrl || this.loading) return;
                    this.loading = true;
                    try {
                        await this.$wire.importar(this.fileDataUrl, this.fileName);
                        this.reset();
                    } finally {
                        this.loading = false;
                    }
                },
                reset() {
                    this.fileName = '';
                    this.fileSize = '';
                    this.fileDataUrl = null;
                },
                _humanSize(bytes) {
                    if (bytes < 1024) return bytes + ' B';
                    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                    return (bytes / 1024 / 1024).toFixed(2) + ' MB';
                },
            };
        }
    </script>
</div>
