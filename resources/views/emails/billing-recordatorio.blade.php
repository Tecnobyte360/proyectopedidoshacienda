@php
    $brand     = $cfg->nombre ?: 'Kivox';
    $brandHex  = $cfg->color_primario ?: '#d68643';
    $brandHexD = $cfg->color_secundario ?: '#a85f24';
    $logo      = $cfg->logo_url;
    $soporte   = $cfg->email_soporte ?: 'comercial@tecnobyte360.com';
    $telSop    = $cfg->telefono_soporte;

    // Configuración por etapa
    $stage = match ($etapa) {
        'factura'     => ['emoji' => '🧾', 'titulo' => 'Tu factura mensual está lista', 'sub' => "Recibe tu mensualidad de {$brand}", 'color' => '#0ea5e9'],
        'preaviso'    => ['emoji' => '📅', 'titulo' => 'Tu suscripción vence en 3 días', 'sub' => 'Te recordamos para que no se interrumpa el servicio', 'color' => '#f59e0b'],
        'vence_hoy'   => ['emoji' => '⏰', 'titulo' => 'Tu suscripción vence HOY', 'sub' => 'Paga antes del final del día', 'color' => '#f97316'],
        'vencio_ayer' => ['emoji' => '⚠️', 'titulo' => 'Tu suscripción venció', 'sub' => 'Para no interrumpir el servicio paga lo antes posible', 'color' => '#dc2626'],
        'urgencia'    => ['emoji' => '🚨', 'titulo' => '¡Tu acceso será suspendido!', 'sub' => 'Última oportunidad antes de bloqueo', 'color' => '#dc2626'],
        'suspendido'  => ['emoji' => '🔒', 'titulo' => 'Tu acceso fue suspendido', 'sub' => 'Paga para reactivar inmediatamente', 'color' => '#991b1b'],
        default       => ['emoji' => '📨', 'titulo' => 'Recordatorio de pago', 'sub' => '', 'color' => $brandHex],
    };

    $monto = $pago?->monto ?? $suscripcion->monto ?? 0;
    $fecha = $suscripcion->fecha_fin?->format('d/m/Y') ?? '—';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $brand }} · Recordatorio</title>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; }
        table { border-collapse: collapse; }
        .container { max-width: 600px; margin: 32px auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(15,23,42,0.08); }
        .header { background: linear-gradient(135deg, {{ $stage['color'] }}, {{ $brandHexD }}); padding: 40px 32px; text-align: center; color: #ffffff; }
        .header-icon { display: inline-block; width: 80px; height: 80px; line-height: 80px; border-radius: 20px; background: rgba(255,255,255,0.18); backdrop-filter: blur(4px); font-size: 42px; margin-bottom: 20px; }
        .header h1 { font-size: 26px; font-weight: 800; margin: 0 0 8px; letter-spacing: -0.3px; }
        .header p { font-size: 14px; opacity: 0.92; margin: 0; }
        .body { padding: 32px; }
        .greeting { font-size: 15px; color: #475569; line-height: 1.6; margin-bottom: 24px; }
        .invoice-box { background: linear-gradient(135deg, #fafbfc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .invoice-row { display: table; width: 100%; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .invoice-row:last-child { border-bottom: none; padding-top: 18px; padding-bottom: 0; }
        .invoice-label { display: table-cell; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; width: 50%; }
        .invoice-value { display: table-cell; font-size: 14px; color: #1e293b; font-weight: 700; text-align: right; }
        .invoice-total { font-size: 32px !important; color: {{ $stage['color'] }} !important; font-weight: 800; }
        .btn-pagar { display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff !important; padding: 18px 40px; border-radius: 14px; text-decoration: none; font-weight: 800; font-size: 16px; box-shadow: 0 8px 24px rgba(16,185,129,0.3); letter-spacing: 0.2px; }
        .btn-wrapper { text-align: center; margin: 32px 0; }
        .info-secundaria { background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 16px; margin: 24px 0; font-size: 13px; color: #1e3a8a; line-height: 1.5; }
        .footer { background: #f8fafc; padding: 24px 32px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 4px 0; font-size: 12px; color: #94a3b8; }
        .footer a { color: {{ $brandHex }}; text-decoration: none; font-weight: 600; }
        .brand-strip { background: linear-gradient(90deg, {{ $brandHex }}, {{ $brandHexD }}); height: 4px; }
    </style>
</head>
<body>
    <div class="container">

        {{-- HEADER --}}
        <div class="header">
            <div class="header-icon">{{ $stage['emoji'] }}</div>
            <h1>{{ $stage['titulo'] }}</h1>
            <p>{{ $stage['sub'] }}</p>
        </div>

        <div class="brand-strip"></div>

        {{-- BODY --}}
        <div class="body">
            <p class="greeting">
                Hola <strong>{{ $tenant->nombre }}</strong>,<br>
                @if($etapa === 'suspendido')
                    Tu acceso a la plataforma {{ $brand }} fue suspendido por falta de pago.
                    Realiza tu pago ahora y tu cuenta se reactivará automáticamente.
                @elseif(in_array($etapa, ['vencio_ayer', 'urgencia']))
                    Tu suscripción de {{ $brand }} está vencida. Para no perder el acceso, te invitamos a regularizar tu pago lo antes posible.
                @elseif($etapa === 'vence_hoy')
                    Te recordamos que tu mensualidad de {{ $brand }} vence <strong>hoy</strong>. Realiza el pago para mantener tu acceso activo.
                @elseif($etapa === 'preaviso')
                    Tu mensualidad de {{ $brand }} vence en 3 días. Te enviamos este recordatorio para que tengas tiempo de pagar sin interrupciones.
                @else
                    Tu factura mensual de {{ $brand }} está disponible. Puedes pagar en línea con el botón de abajo.
                @endif
            </p>

            {{-- CUADRO DE FACTURA --}}
            <div class="invoice-box">
                <div class="invoice-row">
                    <span class="invoice-label">Plan</span>
                    <span class="invoice-value">{{ $suscripcion->plan?->nombre ?? '—' }}</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Ciclo</span>
                    <span class="invoice-value">{{ ucfirst($suscripcion->ciclo) }}</span>
                </div>
                <div class="invoice-row">
                    <span class="invoice-label">Fecha de vencimiento</span>
                    <span class="invoice-value">{{ $fecha }}</span>
                </div>
                @if($pago?->cubre_desde && $pago?->cubre_hasta)
                    <div class="invoice-row">
                        <span class="invoice-label">Período cubierto</span>
                        <span class="invoice-value">{{ $pago->cubre_desde->format('d/m/Y') }} → {{ $pago->cubre_hasta->format('d/m/Y') }}</span>
                    </div>
                @endif
                <div class="invoice-row">
                    <span class="invoice-label">Monto a pagar</span>
                    <span class="invoice-value invoice-total">${{ number_format((float)$monto, 0, ',', '.') }}<br><small style="font-size: 12px; color: #94a3b8; font-weight: 500;">COP</small></span>
                </div>
            </div>

            {{-- BOTÓN GRANDE --}}
            @if($linkPago)
                <div class="btn-wrapper">
                    <a href="{{ $linkPago }}" class="btn-pagar">💳 Pagar ahora con Wompi</a>
                </div>
                <p style="text-align: center; font-size: 11px; color: #94a3b8; margin-top: -16px;">
                    Pago seguro · Procesado por Wompi (PCI DSS Level 1)
                </p>
            @endif

            @if($etapa === 'suspendido')
                <div class="info-secundaria">
                    <strong>💡 Reactivación automática:</strong> apenas confirmemos tu pago, recuperarás acceso completo a la plataforma sin que tengas que hacer nada extra.
                </div>
            @elseif($etapa === 'urgencia')
                <div class="info-secundaria" style="background: #fef2f2; border-left-color: #ef4444; color: #991b1b;">
                    <strong>🚨 Atención:</strong> si no recibimos tu pago pronto, tu acceso a la plataforma será bloqueado temporalmente. Tus datos están seguros pero no podrás usar las funciones hasta que pagues.
                </div>
            @endif

            <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin-top: 20px;">
                Si ya realizaste el pago, ignora este correo. Si tienes dudas o necesitas ayuda, responde este email o escríbenos a
                <a href="mailto:{{ $soporte }}" style="color: {{ $brandHex }};">{{ $soporte }}</a>
                @if($telSop)
                    o por WhatsApp al <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $telSop) }}" style="color: {{ $brandHex }};">{{ $telSop }}</a>
                @endif.
            </p>
        </div>

        {{-- FOOTER --}}
        <div class="footer">
            @if($logo)
                <img src="{{ str_starts_with($logo, 'http') ? $logo : url($logo) }}" alt="{{ $brand }}" style="max-height: 32px; margin-bottom: 8px;">
            @endif
            <p><strong style="color: #475569;">{{ $brand }}</strong> · Plataforma SaaS de gestión de pedidos</p>
            <p>Este correo se envió automáticamente. No hace falta responder.</p>
            <p>© {{ now()->format('Y') }} {{ $brand }} · Todos los derechos reservados</p>
        </div>
    </div>
</body>
</html>
