<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subdominio no encontrado</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-[#fbe9d7] via-white to-[#f5d4ad]">
    <div class="text-center max-w-md">
        <div class="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-[#d68643] to-[#a85f24] text-white shadow-2xl mb-6">
            <i class="fa-solid fa-circle-question text-3xl"></i>
        </div>
        <h1 class="text-3xl font-extrabold text-slate-800 mb-2">Página no encontrada</h1>
        <p class="text-slate-600 mb-2">
            {{ $exception?->getMessage() ?: 'El recurso que buscas no existe.' }}
        </p>
        <p class="text-sm text-slate-500 mb-6">
            Verifica la URL o vuelve al inicio.
        </p>
        <a href="https://{{ config('app.tenant_base_domain', 'tecnobyte360.com') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#d68643] to-[#a85f24] hover:from-[#c97a36] hover:to-[#965520] text-white font-bold px-6 py-3 transition shadow-lg">
            <i class="fa-solid fa-house"></i> Ir a la página principal
        </a>
    </div>
</body>
</html>
