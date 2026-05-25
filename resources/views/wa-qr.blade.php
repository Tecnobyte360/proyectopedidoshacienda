<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="10">  {{-- Auto-refresh cada 10s --}}
    <title>QR WhatsApp · {{ $tenant->nombre }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; margin:0; background:linear-gradient(135deg,#10b981 0%,#047857 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:white; border-radius:24px; padding:32px; max-width:480px; width:100%; box-shadow:0 30px 80px rgba(0,0,0,0.25); text-align:center; }
        h1 { margin:0 0 8px; color:#0f172a; font-size:24px; }
        .sub { color:#64748b; font-size:13px; margin-bottom:20px; }
        .badge { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:999px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; }
        .badge-ok { background:#d1fae5; color:#047857; }
        .badge-wait { background:#fef3c7; color:#92400e; }
        .badge-off { background:#fee2e2; color:#991b1b; }
        .qr-wrap { background:white; padding:16px; border-radius:16px; display:inline-block; margin:20px 0; border:2px solid #e2e8f0; }
        .qr-wrap img { display:block; }
        .steps { text-align:left; background:#f1f5f9; padding:16px; border-radius:12px; margin-top:16px; font-size:13px; color:#475569; }
        .steps ol { margin:8px 0 0 18px; padding:0; }
        .steps li { margin-bottom:4px; }
        .info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:12px; }
        .info-row:last-child { border:none; }
        .info-row .lbl { color:#94a3b8; text-transform:uppercase; font-weight:700; letter-spacing:0.05em; font-size:10px; }
        .info-row .val { color:#0f172a; font-weight:600; font-family:monospace; }
        .btn-regen { background:#ef4444; color:white; padding:10px 20px; border:none; border-radius:10px; font-weight:700; cursor:pointer; margin-top:16px; font-size:13px; }
        .btn-regen:hover { background:#dc2626; }
        .connected-icon { font-size:64px; color:#10b981; margin:20px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1><i class="fa-solid fa-mobile-screen"></i> WhatsApp · {{ $tenant->nombre }}</h1>
        <p class="sub">Conexión #{{ $connId }}{{ $phone ? ' · '.$phone : '' }}</p>

        @php
            $statusBadge = match($status) {
                'CONNECTED' => ['badge-ok', '✓ Conectado'],
                'qrcode'    => ['badge-wait', '⚠ Esperando escaneo de QR'],
                'OPENING'   => ['badge-wait', '⏳ Iniciando...'],
                'TIMEOUT', 'DISCONNECTED' => ['badge-off', '✗ Desconectado'],
                default => ['badge-wait', $status]
            };
        @endphp
        <div class="badge {{ $statusBadge[0] }}">
            {{ $statusBadge[1] }}
        </div>

        @if($qrcode)
            <div class="qr-wrap">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=10&data={{ urlencode($qrcode) }}"
                     alt="QR WhatsApp" width="320" height="320">
            </div>
            <div class="steps">
                <strong><i class="fa-solid fa-mobile-screen"></i> Pasos para conectar:</strong>
                <ol>
                    <li>Abre <b>WhatsApp</b> en el celular {{ $phone ?: 'que quieres conectar' }}</li>
                    <li>Toca el menú (⋮) → <b>Dispositivos vinculados</b></li>
                    <li>Toca <b>Vincular un dispositivo</b></li>
                    <li>Apunta la cámara hacia este QR <i class="fa-solid fa-arrow-up"></i></li>
                </ol>
            </div>
        @elseif($status === 'CONNECTED' && $battery !== null && $battery !== 'NONE')
            <div class="connected-icon"><i class="fa-solid fa-circle-check"></i></div>
            <p style="font-size:16px; font-weight:700; color:#0f172a;">¡Conectado y funcionando!</p>
            <div style="margin-top:16px;">
                <div class="info-row"><span class="lbl">Batería</span><span class="val">{{ $battery }}%</span></div>
                <div class="info-row"><span class="lbl">Teléfono</span><span class="val">{{ $phone }}</span></div>
            </div>
        @else
            <div style="margin:24px 0; color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:32px;"></i>
                <p style="margin-top:8px; font-size:14px;">
                    La sesión está en estado "{{ $status }}" pero no hay QR disponible y no hay batería detectada.
                    Esto suele indicar una sesión zombi.
                </p>
                <p style="font-size:12px; color:#64748b;">Click el botón rojo abajo para forzar nuevo QR.</p>
            </div>
        @endif

        <form method="POST" action="/wa-qr/{{ $tenant->id }}/regenerar" style="display:inline;">
            @csrf
            <button type="submit" class="btn-regen">
                <i class="fa-solid fa-rotate-right"></i> Generar nuevo QR (forzar reconexión)
            </button>
        </form>

        <p style="margin-top:16px; font-size:11px; color:#94a3b8;">
            <i class="fa-solid fa-arrows-rotate"></i> Esta página se refresca sola cada 10 segundos
        </p>
    </div>
</body>
</html>
