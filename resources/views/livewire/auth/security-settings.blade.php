<div class="p-4 md:p-6 max-w-3xl mx-auto space-y-5">

    {{-- HEADER --}}
    <div class="rounded-2xl border border-[#fbe9d7] bg-gradient-to-r from-brand-soft/40 via-white to-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-dark text-white shadow-lg">
                <i class="fa-solid fa-shield-halved text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-slate-800">Seguridad de la cuenta</h1>
                <p class="text-sm text-slate-500">Configura la autenticación en 2 pasos para proteger tu acceso</p>
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700 flex items-start gap-2">
            <i class="fa-solid fa-circle-check mt-0.5"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Mostrar códigos de respaldo recién generados (1 sola vez) --}}
    @if(session('2fa.recovery_codes'))
        <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-300 p-5">
            <div class="flex items-start gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white flex-shrink-0">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-amber-900">🔑 Códigos de respaldo (guárdalos ya)</h3>
                    <p class="text-xs text-amber-800/80 mt-1">
                        Si pierdes tu teléfono, usa uno de estos códigos para iniciar sesión.
                        Cada uno funciona <strong>1 sola vez</strong>. <strong>NO se mostrarán de nuevo.</strong>
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 bg-white rounded-xl p-4 border border-amber-200">
                @foreach(session('2fa.recovery_codes') as $code)
                    <div class="font-mono text-sm font-bold text-amber-900 text-center py-2 bg-amber-50 rounded-lg border border-amber-200">
                        {{ $code }}
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-amber-800 mt-3">
                💡 Sugerencia: cópialos a tu gestor de contraseñas (1Password, Bitwarden, etc.) o imprímelos.
            </p>
        </div>
    @endif

    {{-- ENROLAMIENTO EN CURSO --}}
    @if(isset($qrUrl))
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-qrcode text-brand text-lg"></i>
                <h3 class="text-base font-bold text-slate-800">Paso 1: Escanea con tu app autenticadora</h3>
            </div>
            <div class="flex flex-col md:flex-row gap-6 items-center">
                <div class="flex-shrink-0">
                    <img src="{{ $qrUrl }}" alt="QR 2FA" class="rounded-xl border border-slate-200 p-2 bg-white">
                </div>
                <div class="flex-1">
                    <p class="text-sm text-slate-600 mb-3">
                        Abre Google Authenticator, Authy o tu app preferida y escanea este código.
                    </p>
                    <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                        <p class="text-[10px] uppercase text-slate-500 font-bold tracking-wider mb-1">
                            ¿No puedes escanear? Ingresa este código manual:
                        </p>
                        <code class="font-mono text-sm font-bold text-slate-800 break-all">{{ $secret }}</code>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-slate-200">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fa-solid fa-circle-2 text-brand text-lg"></i>
                    <h3 class="text-base font-bold text-slate-800">Paso 2: Confirma con el primer código</h3>
                </div>
                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-4 text-sm text-rose-700">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        {{ $errors->first() }}
                    </div>
                @endif
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex gap-3">
                    @csrf
                    <input type="text" name="code" required autofocus inputmode="numeric"
                           placeholder="000000" maxlength="6"
                           class="flex-1 text-center text-2xl font-extrabold tracking-[0.4em] py-3 rounded-xl border-2 border-slate-200 focus:outline-none focus:border-brand focus:ring-4 focus:ring-brand/20">
                    <button type="submit"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-brand to-brand-dark text-white font-extrabold text-sm transition hover:scale-[1.02] shadow-lg">
                        <i class="fa-solid fa-check"></i> Activar
                    </button>
                </form>
            </div>
        </div>
    @else
        {{-- ESTADO ACTUAL --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 flex items-start gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl flex-shrink-0
                            {{ $user->tieneDosFactor() ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400' }}">
                    <i class="fa-solid {{ $user->tieneDosFactor() ? 'fa-shield-check' : 'fa-shield' }} text-2xl"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-lg font-extrabold text-slate-800">Autenticación de 2 factores</h3>
                        @if($user->tieneDosFactor())
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider">
                                <i class="fa-solid fa-check"></i> Activo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider">
                                Inactivo
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-slate-600 mt-1">
                        @if($user->tieneDosFactor())
                            Tu cuenta está protegida con autenticación en 2 pasos desde el
                            <strong>{{ $user->two_factor_enabled_at->format('d/m/Y') }}</strong>.
                            Cada vez que inicies sesión necesitarás un código de tu app autenticadora.
                        @else
                            Agrega una capa extra de seguridad a tu cuenta. Cada login pedirá un código
                            de 6 dígitos generado por una app autenticadora (Google Authenticator, Authy, etc.).
                        @endif
                    </p>
                </div>
            </div>

            <div class="px-6 pb-6">
                @if($user->tieneDosFactor())
                    {{-- Desactivar 2FA --}}
                    <details class="rounded-xl bg-rose-50 border border-rose-200 p-4">
                        <summary class="cursor-pointer font-bold text-rose-800 text-sm">
                            <i class="fa-solid fa-circle-exclamation"></i> Desactivar 2FA
                        </summary>
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4 space-y-3">
                            @csrf
                            <p class="text-xs text-rose-800">
                                Confirma tu contraseña para desactivar la autenticación en 2 pasos.
                                Esto reduce la seguridad de tu cuenta.
                            </p>
                            <input type="password" name="password" required placeholder="Tu contraseña actual"
                                   class="w-full rounded-lg border border-rose-300 px-3 py-2 text-sm focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-200">
                            @error('password') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                            <button type="submit"
                                    class="w-full rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm py-2 transition">
                                <i class="fa-solid fa-shield-xmark"></i> Desactivar 2FA
                            </button>
                        </form>
                    </details>
                @else
                    {{-- Activar 2FA --}}
                    <button type="button" wire:click="iniciarEnroll"
                            class="w-full rounded-xl bg-gradient-to-r from-brand to-brand-dark text-white font-extrabold text-sm py-3 transition hover:scale-[1.01] shadow-lg">
                        <i class="fa-solid fa-shield-halved mr-2"></i>
                        Activar autenticación en 2 pasos
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- TIPS --}}
    <div class="rounded-xl bg-sky-50 border border-sky-200 p-4 text-xs text-sky-800">
        <p class="font-bold mb-1"><i class="fa-solid fa-lightbulb"></i> Apps recomendadas</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li><strong>Google Authenticator</strong> — gratis, simple (iOS/Android)</li>
            <li><strong>Authy</strong> — sincroniza entre dispositivos (iOS/Android/desktop)</li>
            <li><strong>1Password / Bitwarden</strong> — si ya usas gestor de contraseñas</li>
            <li><strong>Microsoft Authenticator</strong> — para empresas con Office 365</li>
        </ul>
    </div>
</div>
@endsection
