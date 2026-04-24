@php
    $brandName = $tenantBranding?->nombre ?: 'TecnoByte360';
    $brandLogo = $tenantBranding?->logo_url;
    $colorPrim = $tenantBranding?->color_primario ?: '#d68643';
    $colorSec  = $tenantBranding?->color_secundario ?: '#a85f24';
    $bgLight   = $tenantBranding?->color_primario
        ? $tenantBranding->color_primario . '22'
        : '#fbe9d7';
    $emailDomain = $tenantBranding?->slug ? $tenantBranding->slug . '.com' : 'empresa.com';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | {{ $brandName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4"
      style="background: linear-gradient(135deg, {{ $bgLight }}, #ffffff, {{ $bgLight }});">

    <div class="w-full max-w-md">
        {{-- Brand dinámico por tenant --}}
        <div class="text-center mb-8">
            @if($brandLogo)
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-2xl bg-white shadow-xl mb-4 overflow-hidden border-2 border-slate-100">
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-full w-full object-contain">
                </div>
            @else
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-xl mb-4"
                     style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                    <i class="fa-solid fa-utensils text-2xl"></i>
                </div>
            @endif
            <h1 class="text-2xl font-extrabold text-slate-800">{{ $brandName }}</h1>
            <p class="text-sm text-slate-500 mt-1">Plataforma de gestión de pedidos</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-2xl p-8 border border-slate-100">
            <h2 class="text-xl font-bold text-slate-800 mb-1">Iniciar sesión</h2>
            <p class="text-sm text-slate-500 mb-6">Ingresa con tus credenciales para continuar.</p>

            @if($errors->any())
                <div class="rounded-xl bg-rose-50 border border-rose-200 p-3 mb-4 text-sm text-rose-700">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <i class="fa-solid fa-envelope text-slate-400"></i> Correo electrónico
                    </label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           style="--tw-ring-color: {{ $colorPrim }}33;"
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:ring-2 focus:outline-none"
                           onfocus="this.style.borderColor='{{ $colorPrim }}';"
                           onblur="this.style.borderColor='';"
                           placeholder="{{ 'tucorreo@' . $emailDomain }}">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <i class="fa-solid fa-lock text-slate-400"></i> Contraseña
                    </label>
                    <input type="password" name="password" required
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:ring-2 focus:outline-none"
                           style="--tw-ring-color: {{ $colorPrim }}33;"
                           onfocus="this.style.borderColor='{{ $colorPrim }}';"
                           onblur="this.style.borderColor='';"
                           placeholder="••••••••">
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                           class="rounded border-slate-300"
                           style="color: {{ $colorPrim }};">
                    <span class="text-sm text-slate-600">Recordarme en este equipo</span>
                </label>

                <button type="submit"
                        class="w-full rounded-xl text-white font-bold py-3 transition shadow-lg hover:opacity-90"
                        style="background: linear-gradient(135deg, {{ $colorPrim }}, {{ $colorSec }});">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>
                    Entrar
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            © {{ date('Y') }} {{ $brandName }} · Todos los derechos reservados
        </p>
    </div>

</body>
</html>
