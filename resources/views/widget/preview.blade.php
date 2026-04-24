<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Preview Widget</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 40px; background: #f1f5f9; }
        .demo { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        .info { background: #e0f2fe; padding: 16px; border-radius: 10px; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="demo">
        <h1>Página de prueba del widget</h1>
        <p>Esta es una página de ejemplo para probar cómo se ve el widget en un sitio externo.</p>
        <p>El widget debería aparecer como botón flotante en una esquina. Haz clic para abrirlo.</p>
        <div class="info">
            <strong>Nota:</strong> cuando pegues el script en tu sitio real, el widget se verá exactamente igual.
        </div>
    </div>
    <script src="{{ url('/widget.js?token=' . $token) }}" async></script>
</body>
</html>
