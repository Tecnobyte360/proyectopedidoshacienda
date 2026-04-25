<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso denegado</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-rose-50 to-white">
    <div class="text-center max-w-md">
        <div class="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-rose-500 text-white shadow-2xl mb-6">
            <i class="fa-solid fa-lock text-3xl"></i>
        </div>
        <h1 class="text-3xl font-extrabold text-slate-800 mb-2">Acceso denegado</h1>
        <p class="text-slate-600 mb-6">
            {{ $mensaje ?? 'No tienes permisos para acceder a esta sección.' }}
        </p>
        <a href="/" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-700 text-white font-semibold px-6 py-3 transition shadow">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
</body>
</html>
