<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Alimentos La Hacienda</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-[#fbe9d7] via-white to-[#f5d4ad]">

    <div class="w-full max-w-md">
        {{-- Brand --}}
        <div class="text-center mb-8">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow-xl mb-4">
                <i class="fa-solid fa-utensils text-2xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-800">Alimentos La Hacienda</h1>
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
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20"
                           placeholder="tucorreo@hacienda.com">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        <i class="fa-solid fa-lock text-slate-400"></i> Contraseña
                    </label>
                    <input type="password" name="password" required
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#d68643] focus:ring-2 focus:ring-[#d68643]/20"
                           placeholder="••••••••">
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                           class="rounded border-slate-300 text-[#d68643] focus:ring-[#d68643]">
                    <span class="text-sm text-slate-600">Recordarme en este equipo</span>
                </label>

                <button type="submit"
                        class="w-full rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] text-white font-bold py-3 transition shadow-lg">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>
                    Entrar
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            © {{ date('Y') }} Alimentos La Hacienda · Todos los derechos reservados
        </p>
    </div>

</body>
</html>
