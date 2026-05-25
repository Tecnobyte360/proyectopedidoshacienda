<div class="p-4 md:p-6 max-w-4xl mx-auto space-y-6">

    {{-- HERO HEADER --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand to-brand-dark p-8 shadow-xl">
        <div class="absolute -right-8 -bottom-8 text-white/10">
            <i class="fa-solid fa-shield-halved text-[180px]"></i>
        </div>
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 28px 28px;"></div>
        <div class="relative">
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 backdrop-blur px-3 py-1 text-[10px] uppercase tracking-wider font-extrabold text-white">
                    <i class="fa-solid fa-lock"></i> Seguridad
                </span>
                @if($user->tieneDosFactor())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/30 backdrop-blur ring-1 ring-emerald-200/50 px-3 py-1 text-[10px] uppercase tracking-wider font-extrabold text-white">
                        <i class="fa-solid fa-circle-check"></i> 2FA Activo
                    </span>
                @endif
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight">
                Protege tu cuenta
            </h1>
            <p class="text-white/85 text-sm md:text-base mt-2 max-w-xl">
                Activa la autenticación en 2 pasos y reduce el riesgo de accesos no autorizados en un <strong class="text-white">99.9%</strong>.
            </p>
        </div>
    </div>

    {{-- SUCCESS NOTICE --}}
    @if(session('status'))
        <div class="rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-start gap-3 shadow-sm">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500 text-white flex-shrink-0">
                <i class="fa-solid fa-check"></i>
            </div>
            <div class="pt-0.5"><strong>{{ session('status') }}</strong></div>
        </div>
    @endif

    {{-- 🔑 CÓDIGOS DE RESPALDO (post-activación, 1 sola vez) --}}
    @if(session('2fa.recovery_codes'))
        <div class="rounded-3xl bg-gradient-to-br from-amber-50 via-yellow-50 to-orange-50 border-2 border-amber-300 shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-6 py-4 text-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/25 backdrop-blur">
                        <i class="fa-solid fa-key text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-base">Códigos de respaldo · Guárdalos ahora</h3>
                        <p class="text-[11px] text-white/90">Esta es la única vez que los verás. Cada uno sirve solo una vez.</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-4">
                    @foreach(session('2fa.recovery_codes') as $code)
                        <div class="group relative font-mono text-sm font-extrabold text-amber-900 text-center py-3 px-2 bg-white rounded-xl border-2 border-amber-200 hover:border-amber-400 hover:shadow-md transition cursor-pointer"
                             onclick="navigator.clipboard.writeText('{{ $code }}'); this.querySelector('.cp-tooltip').textContent='✓ Copiado';">
                            {{ $code }}
                            <div class="cp-tooltip absolute -top-7 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[10px] px-2 py-0.5 rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap pointer-events-none">
                                Click para copiar
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" onclick="navigator.clipboard.writeText(`{{ implode("\n", session('2fa.recovery_codes')) }}`); this.innerHTML='<i class=\'fa-solid fa-check\'></i> Copiados todos'"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-4 py-2 shadow transition">
                        <i class="fa-solid fa-copy"></i> Copiar todos
                    </button>
                    <button type="button" onclick="window.print()"
                            class="inline-flex items-center gap-2 rounded-xl bg-white border border-amber-300 hover:bg-amber-50 text-amber-900 text-xs font-bold px-4 py-2 transition">
                        <i class="fa-solid fa-print"></i> Imprimir
                    </button>
                    <span class="text-[11px] text-amber-800 ml-auto">
                        💡 Guárdalos en 1Password, Bitwarden o un papel físico seguro
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- ENROLAMIENTO EN CURSO (QR + confirmación) --}}
    @if(isset($qrUrl))
        <div class="rounded-3xl bg-white border border-slate-200 shadow-xl overflow-hidden">
            {{-- Paso 1: QR --}}
            <div class="p-6 md:p-8">
                <div class="flex items-center gap-3 mb-5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-dark text-white text-sm font-extrabold shadow">1</div>
                    <h3 class="text-lg font-extrabold text-slate-800">Escanea con tu app autenticadora</h3>
                </div>

                <div class="grid md:grid-cols-[auto,1fr] gap-6 items-start">
                    {{-- QR --}}
                    <div class="mx-auto md:mx-0">
                        <div class="rounded-2xl bg-gradient-to-br from-brand-soft/40 to-white p-4 border-2 border-brand/20 shadow-inner">
                            <img src="{{ $qrUrl }}" alt="QR 2FA" class="rounded-xl bg-white">
                        </div>
                        <p class="text-[10px] text-center text-slate-500 mt-2 font-semibold">
                            <i class="fa-solid fa-qrcode"></i> Apunta la cámara aquí
                        </p>
                    </div>

                    {{-- Instrucciones + código manual --}}
                    <div class="space-y-4">
                        <div class="space-y-2 text-sm text-slate-600 leading-relaxed">
                            <p>1. Abre tu app autenticadora (Google Authenticator, Authy, 1Password, etc.)</p>
                            <p>2. Toca <strong>"Agregar cuenta"</strong> o el botón <strong>+</strong></p>
                            <p>3. Selecciona <strong>"Escanear código QR"</strong></p>
                            <p>4. Apunta a este QR ☝️</p>
                        </div>

                        <details class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2">
                            <summary class="cursor-pointer text-xs font-bold text-slate-700">
                                <i class="fa-solid fa-keyboard"></i> ¿No puedes escanear? Ingresa código manual
                            </summary>
                            <div class="mt-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <code id="manual-secret" class="flex-1 font-mono text-sm font-extrabold text-brand-dark bg-white border border-slate-200 rounded-lg px-3 py-2 break-all tracking-wider">{{ $secret }}</code>
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $secret }}'); this.innerHTML='<i class=\'fa-solid fa-check\'></i>'"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand hover:bg-brand-dark text-white text-xs transition">
                                        <i class="fa-solid fa-copy"></i>
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-500">Asegúrate de seleccionar <strong>Time-based (TOTP)</strong> en tu app.</p>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            {{-- Divisor con label --}}
            <div class="relative h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent">
                <span class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-white px-3 text-[10px] uppercase tracking-wider text-slate-400 font-bold">después</span>
            </div>

            {{-- Paso 2: Confirmar --}}
            <div class="p-6 md:p-8 bg-gradient-to-br from-brand-soft/20 to-white">
                <div class="flex items-center gap-3 mb-5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-dark text-white text-sm font-extrabold shadow">2</div>
                    <div class="flex-1">
                        <h3 class="text-lg font-extrabold text-slate-800">Confirma con el primer código</h3>
                        <p class="text-xs text-slate-500">Ingresa los 6 dígitos que aparecen ahora en tu app</p>
                    </div>
                </div>

                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-4 text-sm text-rose-700 flex items-start gap-2">
                        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex gap-3 max-w-md">
                    @csrf
                    <input type="text" name="code" required autofocus inputmode="numeric"
                           autocomplete="one-time-code"
                           placeholder="000000" maxlength="6"
                           class="flex-1 text-center text-3xl font-extrabold tracking-[0.5em] py-4 rounded-2xl border-2 border-slate-200 bg-white focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/20 transition text-brand-dark">
                    <button type="submit"
                            class="px-6 rounded-2xl bg-gradient-to-r from-brand to-brand-dark text-white font-extrabold text-sm transition hover:scale-[1.02] shadow-lg flex-shrink-0">
                        <i class="fa-solid fa-shield-check"></i>
                        <span class="hidden sm:inline ml-1">Activar</span>
                    </button>
                </form>
            </div>
        </div>
    @else
        {{-- ESTADO ACTUAL (no enrolando) --}}
        <div class="rounded-3xl bg-white border border-slate-200 shadow-xl overflow-hidden">
            <div class="p-6 md:p-8 flex items-start gap-5">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl flex-shrink-0 shadow
                            {{ $user->tieneDosFactor() ? 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                    <i class="fa-solid {{ $user->tieneDosFactor() ? 'fa-shield-check' : 'fa-shield-halved' }} text-2xl"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-2">
                        <h3 class="text-xl font-extrabold text-slate-800">Autenticación en 2 pasos</h3>
                        @if($user->tieneDosFactor())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Activo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactivo
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        @if($user->tieneDosFactor())
                            🛡️ Tu cuenta está <strong>protegida</strong> con autenticación en 2 pasos desde el
                            <strong>{{ $user->two_factor_enabled_at->format('d/m/Y') }}</strong>.
                            Cada vez que inicies sesión, deberás ingresar un código de tu app autenticadora.
                        @else
                            Sin 2FA, cualquier persona con tu contraseña puede acceder. Activarlo agrega una capa
                            extra que pide un código de tu celular cada vez que inicias sesión.
                        @endif
                    </p>
                </div>
            </div>

            <div class="px-6 md:px-8 pb-6 md:pb-8">
                @if($user->tieneDosFactor())
                    {{-- Desactivar 2FA --}}
                    <details class="group rounded-2xl bg-rose-50/60 border border-rose-200 overflow-hidden">
                        <summary class="cursor-pointer px-4 py-3 font-bold text-rose-800 text-sm flex items-center gap-2 list-none">
                            <i class="fa-solid fa-chevron-right text-xs group-open:rotate-90 transition"></i>
                            <i class="fa-solid fa-shield-xmark"></i>
                            Desactivar 2FA (no recomendado)
                        </summary>
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="px-4 pb-4 space-y-3 border-t border-rose-200 pt-4">
                            @csrf
                            <p class="text-xs text-rose-800 leading-relaxed">
                                ⚠️ Esto reduce la seguridad de tu cuenta. Si decides hacerlo, confirma con tu contraseña.
                            </p>
                            <input type="password" name="password" required placeholder="Tu contraseña actual"
                                   class="w-full rounded-xl border border-rose-300 px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-rose-500 focus:ring-4 focus:ring-rose-200">
                            @error('password') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            <button type="submit"
                                    class="w-full rounded-xl bg-gradient-to-r from-rose-500 to-rose-700 hover:from-rose-600 hover:to-rose-800 text-white font-extrabold text-sm py-3 transition shadow-md">
                                <i class="fa-solid fa-shield-xmark"></i> Desactivar 2FA
                            </button>
                        </form>
                    </details>
                @else
                    {{-- CTA Activar --}}
                    <button type="button" wire:click="iniciarEnroll"
                            class="w-full rounded-2xl bg-gradient-to-r from-brand to-brand-dark hover:from-brand-dark hover:to-brand text-white font-extrabold text-base py-4 transition hover:scale-[1.01] shadow-xl">
                        <i class="fa-solid fa-shield-halved mr-2"></i>
                        Activar autenticación en 2 pasos
                    </button>
                    <p class="text-[11px] text-slate-500 text-center mt-3">
                        <i class="fa-solid fa-clock"></i> Solo te tomará 30 segundos
                    </p>
                @endif
            </div>
        </div>
    @endif

</div>
