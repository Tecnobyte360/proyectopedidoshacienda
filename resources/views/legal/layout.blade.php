<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('titulo', 'Documento legal') — Kivox</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sora', system-ui, sans-serif; }
        .legal h2 { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:2.5rem; margin-bottom:1rem; }
        .legal h3 { font-size:1.125rem; font-weight:600; color:#1e293b; margin-top:1.5rem; margin-bottom:0.5rem; }
        .legal p  { color:#334155; line-height:1.7; margin-bottom:1rem; }
        .legal ul { list-style:disc; padding-left:1.5rem; color:#334155; margin-bottom:1rem; }
        .legal li { line-height:1.7; margin-bottom:0.35rem; }
        .legal a  { color:#d97706; text-decoration:underline; }
        .legal a:hover { color:#b45309; }
        .legal strong { font-weight:600; color:#0f172a; }
    </style>
</head>
<body class="bg-slate-50">
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-6 py-5 flex items-center justify-between">
            <a href="https://kivox.co" class="flex items-center gap-2 text-slate-900 font-extrabold text-xl">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white">K</span>
                Kivox
            </a>
            <nav class="hidden sm:flex items-center gap-5 text-sm text-slate-600">
                <a href="{{ route('legal.privacidad') }}" class="hover:text-slate-900">Privacidad</a>
                <a href="{{ route('legal.terminos') }}" class="hover:text-slate-900">Términos</a>
                <a href="{{ route('legal.eliminar-datos') }}" class="hover:text-slate-900">Eliminar datos</a>
            </nav>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-6 py-12">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 md:p-12">
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 mb-2">@yield('titulo', 'Documento legal')</h1>
            <p class="text-sm text-slate-500 mb-8">Última actualización: @yield('actualizado', '25 de mayo de 2026')</p>
            <div class="legal">
                @yield('contenido')
            </div>
        </div>
    </main>

    <footer class="max-w-4xl mx-auto px-6 py-8 text-center text-xs text-slate-500">
        <p>
            © {{ date('Y') }} <strong class="text-slate-700">Kivox</strong> · una plataforma de
            <a href="https://tecnobyte360.com" class="text-amber-600 hover:text-amber-700">TecnoByte360 S.A.S.</a>
        </p>
        <p class="mt-2">
            Contacto: <a href="mailto:soporte@kivox.co" class="text-amber-600 hover:text-amber-700">soporte@kivox.co</a>
        </p>
    </footer>
</body>
</html>
