<div class="p-4 sm:p-6">
    {{-- Encabezado --}}
    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-code text-emerald-600"></i> API de WhatsApp — Swagger
            </h1>
            <p class="text-sm text-slate-500">
                Documentación para integrar otros sistemas. El integrador hace <b>login</b> (<code>POST /api/v1/login</code>)
                con el usuario de su empresa, recibe un <b>token</b> y con él llama la API — todo scopeado a <b>su</b> tenant.
            </p>
        </div>
        <a href="{{ url('/api/docs') }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition whitespace-nowrap">
            <i class="fa-solid fa-up-right-from-square"></i> Abrir en pestaña nueva
        </a>
    </div>

    {{-- Swagger embebido --}}
    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
        <iframe src="{{ url('/api/docs') }}"
                title="KIVOX API — Swagger"
                class="w-full"
                style="height: calc(100vh - 220px); min-height: 520px; border: 0;"></iframe>
    </div>

    <p class="mt-3 text-xs text-slate-400">
        💡 En el Swagger: 1) ejecuta <b>POST /login</b> con email+contraseña → copia el <b>token</b>. 2) pulsa <b>Authorize</b> 🔒 y pégalo. 3) usa <b>Try it out</b>.
    </p>
</div>
