<div class="px-6 lg:px-10 py-8">

    {{-- HEADER --}}
    <div class="mb-6">
        <h2 class="text-3xl font-extrabold text-slate-800">
            <i class="fa-solid fa-layer-group text-brand mr-2"></i>
            Plantillas estándar del bot
        </h2>
        <p class="text-sm text-slate-500 mt-1">
            Las plantillas base que se aplican automáticamente a cada tenant según su tipo de negocio. UN solo prompt maestro para todos tus clientes.
        </p>
    </div>

    {{-- INFO MAESTRA --}}
    <div class="rounded-2xl bg-gradient-to-br from-brand-soft to-white border-2 border-brand p-5 mb-6">
        <div class="flex items-start justify-between gap-4 mb-3">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-xl bg-brand flex items-center justify-center shadow">
                    <i class="fa-solid fa-star text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Plantilla Maestra (Genérica)</h3>
                    <p class="text-xs text-slate-500">Esta es la base que TODOS los tenants reciben automáticamente.</p>
                </div>
            </div>
            <button wire:click="$toggle('verPlantillaCompleta')"
                    class="rounded-xl bg-brand hover:bg-brand-dark text-white px-4 py-2 text-sm font-bold shadow whitespace-nowrap">
                @if ($verPlantillaCompleta)
                    <i class="fa-solid fa-eye-slash mr-1"></i> Ocultar
                @else
                    <i class="fa-solid fa-eye mr-1"></i> Ver completa
                @endif
            </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
            <div class="rounded-xl bg-white p-3 border border-slate-200">
                <div class="text-[11px] text-slate-500">Caracteres</div>
                <div class="text-xl font-extrabold text-slate-800">{{ number_format($charsMaestra) }}</div>
            </div>
            <div class="rounded-xl bg-white p-3 border border-slate-200">
                <div class="text-[11px] text-slate-500">Tokens estimados</div>
                <div class="text-xl font-extrabold text-slate-800">~{{ number_format($tokensMaestra) }}</div>
            </div>
            <div class="rounded-xl bg-white p-3 border border-slate-200">
                <div class="text-[11px] text-slate-500">Variables</div>
                <div class="text-xl font-extrabold text-slate-800">{{ substr_count($plantillaMaestra, '{') }}</div>
            </div>
            <div class="rounded-xl bg-white p-3 border border-slate-200">
                <div class="text-[11px] text-slate-500">Bloques especializados</div>
                <div class="text-xl font-extrabold text-slate-800">{{ count($bloques) }}</div>
            </div>
        </div>

        @if ($verPlantillaCompleta)
            <pre class="mt-3 rounded-xl bg-slate-900 text-emerald-100 text-[11px] p-4 overflow-x-auto max-h-96 whitespace-pre-wrap font-mono">{{ $plantillaMaestra }}</pre>
        @endif
    </div>

    {{-- BLOQUES POR TIPO DE NEGOCIO --}}
    <div class="mb-6">
        <h3 class="text-base font-bold text-slate-800 mb-1">
            <i class="fa-solid fa-puzzle-piece text-brand mr-1"></i>
            Bloques especializados por tipo de negocio
        </h3>
        <p class="text-xs text-slate-500 mb-4">
            Cuando configuras un tenant con un tipo específico, el bot recibe estas instrucciones adicionales para adaptar su tono y enfoque.
        </p>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
            @foreach ($bloques as $tipo => $contenido)
                @php $meta = $tiposMeta[$tipo] ?? ['emoji' => '🏢', 'color' => 'slate', 'desc' => '']; @endphp
                <button wire:click="$set('tipoSeleccionado', '{{ $tipo }}')"
                        class="rounded-xl border-2 p-3 text-center transition {{ $tipoSeleccionado === $tipo ? 'border-brand bg-brand-soft shadow' : 'border-slate-200 bg-white hover:bg-slate-50' }}">
                    <div class="text-2xl mb-1">{{ $meta['emoji'] }}</div>
                    <div class="text-xs font-bold text-slate-800 capitalize">{{ $tipo }}</div>
                    <div class="text-[10px] text-slate-500 leading-tight mt-0.5">{{ $meta['desc'] }}</div>
                </button>
            @endforeach
        </div>

        {{-- Preview del bloque seleccionado --}}
        @if ($bloqueSeleccionado)
            @php $meta = $tiposMeta[$tipoSeleccionado] ?? []; @endphp
            <div class="rounded-2xl bg-white border-2 border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">{{ $meta['emoji'] ?? '' }}</span>
                        <h4 class="text-sm font-bold text-slate-800 capitalize">Bloque: {{ $tipoSeleccionado }}</h4>
                        <span class="text-[11px] text-slate-500">— {{ mb_strlen($bloqueSeleccionado) }} chars · ~{{ (int) ceil(mb_strlen($bloqueSeleccionado) / 4) }} tokens</span>
                    </div>
                    <button x-data
                            @click="navigator.clipboard.writeText(@js($bloqueSeleccionado)); $el.innerHTML = '<i class=\'fa-solid fa-check mr-1\'></i> Copiado'; setTimeout(() => $el.innerHTML = '<i class=\'fa-solid fa-clipboard mr-1\'></i> Copiar', 2000)"
                            class="rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-1.5 text-xs font-semibold">
                        <i class="fa-solid fa-clipboard mr-1"></i> Copiar
                    </button>
                </div>
                <pre class="text-[12px] p-4 overflow-x-auto whitespace-pre-wrap font-mono text-slate-800 bg-white max-h-80 overflow-y-auto">{{ $bloqueSeleccionado }}</pre>
            </div>
        @endif
    </div>

    {{-- TENANTS Y SUS TIPOS --}}
    <div class="rounded-2xl bg-white shadow border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50">
            <h3 class="text-sm font-bold text-slate-700">
                <i class="fa-solid fa-building text-slate-500 mr-1"></i>
                ¿Qué bloque está usando cada tenant?
            </h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-xs">
                <tr>
                    <th class="px-4 py-2 text-left">Tenant</th>
                    <th class="px-4 py-2 text-left">Slug</th>
                    <th class="px-4 py-2 text-left">Tipo configurado</th>
                    <th class="px-4 py-2 text-center">Bloque que recibe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($tenants as $t)
                    @php $tieneTipo = $t->tipo_negocio && isset($tiposMeta[$t->tipo_negocio]); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-bold text-slate-800">{{ $t->nombre }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $t->slug }}</td>
                        <td class="px-4 py-2">
                            @if ($t->tipo_negocio)
                                <span class="inline-flex items-center gap-1 text-xs">
                                    {{ $tiposMeta[$t->tipo_negocio]['emoji'] ?? '🏢' }}
                                    <span class="capitalize">{{ $t->tipo_negocio }}</span>
                                </span>
                            @else
                                <span class="text-xs text-amber-600">⚠️ Sin tipo asignado</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-center">
                            @if ($tieneTipo)
                                <span class="inline-block rounded-md bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs font-bold">
                                    ✓ Bloque {{ $t->tipo_negocio }} aplicado
                                </span>
                            @else
                                <span class="inline-block rounded-md bg-slate-100 text-slate-500 px-2 py-0.5 text-xs">
                                    Solo plantilla maestra
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- AYUDA --}}
    <div class="mt-6 rounded-2xl bg-blue-50 border border-blue-200 p-4">
        <h4 class="text-sm font-bold text-blue-800 mb-2">
            <i class="fa-solid fa-circle-info mr-1"></i> ¿Cómo funciona?
        </h4>
        <div class="text-xs text-blue-700 space-y-1">
            <p>1. Cada tenant tiene un campo <strong>tipo_negocio</strong> en su configuración (en <code>/admin/tenants</code>).</p>
            <p>2. La <strong>plantilla maestra</strong> se aplica a TODOS los tenants automáticamente.</p>
            <p>3. Según el tipo, se le concatena el <strong>bloque especializado</strong> correspondiente (tono, ejemplos, reglas).</p>
            <p>4. El bot ya viene afinado para esa industria sin tener que escribir prompt manualmente.</p>
            <p>5. Si quieres personalizar más, ve al tenant → <code>/configuracion/bot</code> → activar <strong>"Usar prompt personalizado"</strong>.</p>
        </div>
    </div>
</div>
