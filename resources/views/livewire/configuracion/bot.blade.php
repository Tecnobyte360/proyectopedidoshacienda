<div class="px-6 lg:px-10 py-8 max-w-4xl mx-auto">

    <div class="mb-6">
        <h2 class="text-3xl font-extrabold text-slate-800">Configuración del bot</h2>
        <p class="text-sm text-slate-500">Ajusta el comportamiento de Sofía y la IA.</p>
    </div>

    <form wire:submit.prevent="guardar" class="space-y-6">

        {{-- IDENTIDAD --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-user-tie"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Identidad de la asesora</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la asesora</label>
                    <input type="text" wire:model="nombre_asesora"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                    <p class="text-[11px] text-slate-400 mt-1">El bot se presentará con este nombre.</p>
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                        <div>
                            <div class="text-sm font-medium text-slate-700">Bot activo</div>
                            <div class="text-[11px] text-slate-500">Si lo apagas, no responde a nadie</div>
                        </div>
                        <input type="checkbox" wire:model="activo" class="rounded border-slate-300 text-[#d68643] h-5 w-5">
                    </label>
                </div>
            </div>
        </section>

        {{-- ENVÍO DE IMÁGENES --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                    <i class="fa-solid fa-camera"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Envío de imágenes de productos</h3>
            </div>

            <div class="space-y-4">

                {{-- Toggle principal --}}
                <label class="inline-flex items-start gap-3 cursor-pointer w-full justify-between rounded-xl border-2 border-purple-200 bg-purple-50/50 p-4 hover:bg-purple-50 transition">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-slate-800 mb-1">
                            🖼️ Enviar imágenes de productos por WhatsApp
                        </div>
                        <div class="text-xs text-slate-600">
                            Cuando esté activo, la IA podrá enviarle al cliente las fotos de los productos del catálogo.
                            Útil cuando el cliente pregunta "tienes foto?" o duda entre opciones.
                        </div>
                    </div>
                    <input type="checkbox" wire:model.live="enviar_imagenes_productos"
                           class="mt-1 rounded border-slate-300 text-[#d68643] h-6 w-6">
                </label>

                @if($enviar_imagenes_productos)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ml-2 pl-4 border-l-2 border-purple-200">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Máximo de imágenes por mensaje
                            </label>
                            <input type="number" wire:model="max_imagenes_por_mensaje" min="1" max="10"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                            <p class="text-[11px] text-slate-400 mt-1">Recomendado: 2-3 (no saturar al cliente).</p>
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                                <div>
                                    <div class="text-sm font-medium text-slate-700">Mostrar destacados al saludar</div>
                                    <div class="text-[11px] text-slate-500">Envía 1-2 fotos al iniciar la charla</div>
                                </div>
                                <input type="checkbox" wire:model="enviar_imagen_destacados" class="rounded border-slate-300 text-[#d68643] h-5 w-5">
                            </label>
                        </div>
                    </div>

                    <div class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                        <i class="fa-solid fa-circle-info mt-0.5"></i>
                        <div>
                            <strong>Importante:</strong> los productos deben tener una <code>URL de imagen</code> configurada en el catálogo.
                            Productos sin imagen serán ignorados.
                            <a href="{{ route('productos.index') }}" class="text-amber-900 underline ml-1">Ir a productos</a>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- MOTOR IA --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-brain"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Motor de IA (OpenAI)</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Modelo</label>
                    <select wire:model="modelo_openai"
                            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                        @foreach($modelosDisponibles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Temperatura
                        <span class="text-[10px] text-slate-400 font-normal">({{ $temperatura }})</span>
                    </label>
                    <input type="range" wire:model.live="temperatura" min="0" max="2" step="0.05"
                           class="w-full">
                    <div class="flex justify-between text-[10px] text-slate-400">
                        <span>0 (preciso)</span>
                        <span>2 (creativo)</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Max tokens por respuesta</label>
                    <input type="number" wire:model="max_tokens" min="100" max="4000" step="100"
                           class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]">
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 cursor-pointer w-full justify-between rounded-xl border border-slate-200 px-4 py-2.5">
                        <span class="text-sm font-medium text-slate-700">Saludar con promos vigentes</span>
                        <input type="checkbox" wire:model="saludar_con_promociones" class="rounded border-slate-300 text-[#d68643] h-5 w-5">
                    </label>
                </div>
            </div>
        </section>

        {{-- BIENVENIDA --}}
        <section class="rounded-2xl bg-white shadow border border-slate-200 p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-message"></i>
                </span>
                <h3 class="text-lg font-bold text-slate-800">Frase personalizada de bienvenida (opcional)</h3>
            </div>

            <textarea wire:model="frase_bienvenida" rows="2"
                      placeholder="Ej: ¡Hola! Bienvenido a La Hacienda 🥩 ¿qué te provoca hoy?"
                      class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#d68643] focus:ring-[#d68643]"></textarea>
            <p class="text-[11px] text-slate-400 mt-1">Si la dejas vacía, la IA improvisa el saludo según la hora y el cliente.</p>
        </section>

        {{-- BOTÓN GUARDAR --}}
        <div class="flex justify-end pt-4">
            <button type="submit"
                    class="rounded-2xl bg-[#d68643] px-8 py-3 text-white font-bold shadow hover:bg-[#c97a36] transition">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Guardar configuración
            </button>
        </div>
    </form>
</div>
