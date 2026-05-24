@php
    $brand     = $cfg->nombre ?: 'Kivox';
    $brandHex  = $cfg->color_primario ?: '#d68643';
    $brandHexD = $cfg->color_secundario ?: '#a85f24';
    $logo      = $cfg->logo_url;
    $soporte   = $cfg->email_soporte ?: 'comercial@tecnobyte360.com';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brand }} · Restablecer contraseña</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; }
        .container { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(15,23,42,0.08); }
        .header { background: linear-gradient(135deg, {{ $brandHex }}, {{ $brandHexD }}); padding: 40px 32px; text-align: center; color: #fff; }
        .icon-wrap { display: inline-block; width: 80px; height: 80px; line-height: 80px; border-radius: 20px; background: rgba(255,255,255,0.18); font-size: 38px; margin-bottom: 20px; }
        .header h1 { font-size: 24px; font-weight: 800; margin: 0 0 6px; }
        .header p { font-size: 14px; opacity: 0.9; margin: 0; }
        .body { padding: 32px; }
        .body p { font-size: 14px; line-height: 1.65; color: #475569; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, {{ $brandHex }}, {{ $brandHexD }}); color: #fff !important; padding: 16px 36px; border-radius: 14px; text-decoration: none; font-weight: 800; font-size: 15px; box-shadow: 0 8px 24px rgba(214,134,67,0.3); }
        .link-fallback { background: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 12px; font-family: monospace; font-size: 11px; color: #475569; word-break: break-all; }
        .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 14px; margin: 20px 0; font-size: 12px; color: #78350f; }
        .footer { background: #f8fafc; padding: 22px 32px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; }
        .footer a { color: {{ $brandHex }}; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon-wrap">🔑</div>
            <h1>Restablece tu contraseña</h1>
            <p>Recibimos una solicitud para tu cuenta</p>
        </div>

        <div class="body">
            <p>Hola <strong>{{ $user->name }}</strong>,</p>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta <strong>{{ $user->email }}</strong> en {{ $brand }}.</p>
            <p>Haz click en el botón para crear una nueva contraseña:</p>

            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn">🔑 Restablecer contraseña</a>
            </div>

            <p style="font-size: 12px; color: #64748b;">¿No se ve el botón? Copia y pega este enlace:</p>
            <div class="link-fallback">{{ $resetUrl }}</div>

            <div class="info-box">
                <strong>⏰ Importante:</strong> este enlace expira en <strong>60 minutos</strong> y solo se puede usar una vez.
                Si no fuiste tú quien lo solicitó, ignora este correo — tu contraseña actual seguirá funcionando.
            </div>

            <p style="font-size: 12px; color: #64748b; margin-top: 24px;">
                Si tienes problemas, escríbenos a <a href="mailto:{{ $soporte }}" style="color: {{ $brandHex }};">{{ $soporte }}</a>
            </p>
        </div>

        <div class="footer">
            <p><strong style="color: #475569;">{{ $brand }}</strong> · Plataforma SaaS</p>
            <p>© {{ now()->format('Y') }} {{ $brand }} · Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>
