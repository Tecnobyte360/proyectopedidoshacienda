@php
    $t       = $tenant ?? null;
    $accent  = $t?->color_primario ?: '#1f6f8b';
    $marca   = $t?->nombre ?: 'Seguimiento';
    $logo    = $t?->logo_url ?: null;
    $inicial = mb_strtoupper(mb_substr($marca, 0, 1));

    $esRecoger    = ($pedido->metodo_entrega ?? '') === 'recoger';
    $estadoActual = $pedido->estado ?? 'nuevo';
    $cancelado    = $estadoActual === 'cancelado';

    // 4 pasos estilo DOBLAMOS (varía el 3º según recoger/domicilio)
    $pasos = [
        ['key' => 'recibido',    'label' => 'Pedido recibido', 'icon' => 'fa-solid fa-receipt'],
        ['key' => 'preparacion', 'label' => 'En preparación',  'icon' => 'fa-solid fa-mug-hot'],
        $esRecoger
            ? ['key' => 'transito', 'label' => 'Listo para recoger', 'icon' => 'fa-solid fa-store']
            : ['key' => 'transito', 'label' => 'En camino',          'icon' => 'fa-solid fa-motorcycle'],
        ['key' => 'entregado',   'label' => 'Entregado',       'icon' => 'fa-solid fa-circle-check'],
    ];

    // Mapa estado → índice del paso
    $mapa = [
        'nuevo' => 0, 'recibido' => 0, 'pendiente' => 0,
        'en_preparacion' => 1, 'en_preparacion_pickup' => 1, 'preparando' => 1,
        'repartidor_en_camino' => 2, 'en_camino' => 2, 'pickup_listo' => 2, 'listo' => 2, 'muy_cerca' => 2,
        'entregado' => 3, 'completado' => 3,
    ];
    $indiceActual = $mapa[$estadoActual] ?? 0;

    // Hero según estado
    if ($cancelado) {
        $heroTitulo = 'Tu pedido fue cancelado';
        $heroSub    = 'Si crees que es un error, escríbenos por WhatsApp.';
        $heroIcon   = 'fa-solid fa-ban';
        $pill       = 'Cancelado';
    } elseif ($indiceActual >= 3) {
        $heroTitulo = '¡Tu pedido fue entregado!';
        $heroSub    = 'Gracias por tu compra. ☕';
        $heroIcon   = 'fa-solid fa-circle-check';
        $pill       = 'Entregado';
    } elseif ($indiceActual == 2) {
        $heroTitulo = $esRecoger ? '¡Tu pedido está listo para recoger!' : '¡Tu pedido va en camino!';
        $heroSub    = $esRecoger ? 'Pasa por él cuando quieras.' : 'Ya casi llega. Prepárate para recibirlo.';
        $heroIcon   = $esRecoger ? 'fa-solid fa-store' : 'fa-solid fa-motorcycle';
        $pill       = $esRecoger ? 'Listo' : 'En camino';
    } elseif ($indiceActual == 1) {
        $heroTitulo = 'Estamos preparando tu pedido';
        $heroSub    = 'Te avisamos apenas avance. ☕';
        $heroIcon   = 'fa-solid fa-mug-hot';
        $pill       = 'En preparación';
    } else {
        $heroTitulo = '¡Recibimos tu pedido!';
        $heroSub    = 'En un momento empezamos a prepararlo.';
        $heroIcon   = 'fa-solid fa-receipt';
        $pill       = 'Recibido';
    }

    $numeroVisible = $pedido->numero_visible ?? $pedido->id;
@endphp

<div
    id="seguimiento-pedido-root"
    data-codigo-seguimiento="{{ $pedido->codigo_seguimiento }}"
    class="trk"
    style="--accent: {{ $accent }};"
>
    {{-- Toast de actualización en tiempo real (lo usa el JS de real-time) --}}
    <div id="seguimiento-estado-flash" class="trk-flash hidden opacity-0">
        <div class="trk-flash__icon"><i class="fa-solid fa-bolt"></i></div>
        <div>
            <p class="trk-flash__title">Actualización en tiempo real</p>
            <p id="seguimiento-estado-flash-text" class="trk-flash__text">El estado de tu pedido cambió.</p>
        </div>
    </div>

    {{-- Barra de marca --}}
    <header class="trk-top">
        <div class="trk-brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $marca }}" class="trk-logo">
            @else
                <div class="trk-logo trk-logo--letter">{{ $inicial }}</div>
            @endif
            <div class="trk-brand-txt">
                <span class="trk-brand-name">{{ $marca }}</span>
                <span class="trk-brand-sub">Seguimiento</span>
            </div>
        </div>
        <div class="trk-live" title="Se actualiza en vivo">
            <span class="trk-live-dot"></span> En vivo
        </div>
    </header>

    <main class="trk-main">
        {{-- HERO --}}
        <section class="trk-hero {{ $cancelado ? 'is-cancel' : ($indiceActual>=3 ? 'is-done' : '') }}">
            <div class="trk-hero-icon"><i class="{{ $heroIcon }}"></i></div>
            <h1 class="trk-hero-title">{{ $heroTitulo }}</h1>
            <p class="trk-hero-sub">{{ $heroSub }}</p>
            <span class="trk-pill"><i class="fa-solid fa-circle"></i> {{ $pill }}</span>
        </section>

        {{-- Saludo + pedido --}}
        <div class="trk-hola">
            Hola <b>{{ $pedido->cliente_nombre ?? 'Cliente' }}</b>
            <span class="trk-hola-sep">·</span>
            Pedido <b>#{{ $numeroVisible }}</b>
        </div>

        {{-- Estado de pago (si aplica) --}}
        @php
            $linkPago   = $pedido->urlPagoWompi();
            $estadoPago = $pedido->estado_pago ?? 'pendiente';
        @endphp
        @if (!$cancelado && ($linkPago || $estadoPago === 'aprobado'))
            <div class="trk-pago {{ $estadoPago === 'aprobado' ? 'ok' : '' }}">
                <div class="trk-pago-ico">{!! $estadoPago === 'aprobado' ? '✅' : '💳' !!}</div>
                <div class="trk-pago-txt">
                    <b>{{ $estadoPago === 'aprobado' ? 'Pago confirmado' : 'Paga en línea' }}</b>
                    <span>{{ $estadoPago === 'aprobado' ? 'Recibimos tu pago, ¡gracias!' : 'Tarjeta, Nequi, PSE o Bancolombia.' }}</span>
                </div>
                @if ($linkPago && $estadoPago !== 'aprobado')
                    <a href="{{ $linkPago }}" target="_blank" rel="noopener" class="trk-pago-btn">Pagar</a>
                @endif
            </div>
        @endif

        {{-- TIMELINE VERTICAL --}}
        @unless ($cancelado)
        <section class="trk-card">
            <div class="trk-steps">
                @foreach ($pasos as $i => $paso)
                    @php $done = $i <= $indiceActual; $isNow = $i === $indiceActual; @endphp
                    <div class="trk-step {{ $done ? 'done' : 'todo' }} {{ $isNow ? 'now' : '' }}">
                        <div class="trk-step-rail">
                            <div class="trk-step-node">
                                <i class="fa-solid {{ $done ? 'fa-check' : 'fa-circle' }}"></i>
                            </div>
                            @unless ($loop->last)
                                <div class="trk-step-line {{ $i < $indiceActual ? 'fill' : '' }}"></div>
                            @endunless
                        </div>
                        <div class="trk-step-body">
                            <p class="trk-step-label">{{ $paso['label'] }}</p>
                            @php
                                $ev = $historial->firstWhere('estado_nuevo', $estadoActual);
                                $evPaso = $historial->first(function ($h) use ($mapa, $i) {
                                    return ($mapa[$h->estado_nuevo] ?? -1) === $i;
                                });
                            @endphp
                            @if ($evPaso && $evPaso->fecha_evento)
                                <p class="trk-step-time">{{ optional($evPaso->fecha_evento)->format('d/m · h:i a') }}</p>
                            @elseif ($isNow)
                                <p class="trk-step-time">En proceso…</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        @endunless

        {{-- PRODUCTOS --}}
        <section class="trk-card">
            <div class="trk-card-head">
                <h2>Tu pedido</h2>
                <span class="trk-total">${{ number_format($pedido->total, 0, ',', '.') }}</span>
            </div>
            <div class="trk-prods">
                @forelse ($pedido->detalles as $d)
                    <div class="trk-prod">
                        <span class="trk-prod-q">{{ rtrim(rtrim(number_format($d->cantidad, 3, '.', ''), '0'), '.') }}×</span>
                        <span class="trk-prod-n">{{ $d->producto }}</span>
                        <span class="trk-prod-p">${{ number_format($d->subtotal, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <div class="trk-prod"><span class="trk-prod-n">Sin productos registrados</span></div>
                @endforelse
            </div>
            @if ($esRecoger)
                <div class="trk-entrega"><i class="fa-solid fa-store"></i> Recoges en sede{{ $pedido->sede?->nombre ? ': '.$pedido->sede->nombre : '' }}</div>
            @elseif ($pedido->direccion)
                <div class="trk-entrega"><i class="fa-solid fa-location-dot"></i> {{ $pedido->direccion }}{{ $pedido->barrio ? ', '.$pedido->barrio : '' }}</div>
            @endif
        </section>

        <p class="trk-foot">Esta página se actualiza sola. {{ $marca }}</p>
    </main>

    @push('styles')
    <style>
        :root { --ok:#22a565; --ok-soft:#e7f6ee; --ink:#0f172a; --ink2:#64748b; --line:#e6e9ef; }
        *,:before,:after{box-sizing:border-box}
        .trk{min-height:100vh;background:#f4f6f8;font-family:'DM Sans',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:var(--ink);padding-bottom:32px}
        .trk-top{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}
        .trk-brand{display:flex;align-items:center;gap:11px;min-width:0}
        .trk-logo{width:38px;height:38px;border-radius:11px;object-fit:cover;flex-shrink:0;box-shadow:0 2px 6px rgba(0,0,0,.08)}
        .trk-logo--letter{display:grid;place-items:center;background:var(--accent);color:#fff;font-weight:800;font-size:18px;font-family:Syne,sans-serif}
        .trk-brand-txt{display:flex;flex-direction:column;line-height:1.15;min-width:0}
        .trk-brand-name{font-weight:800;font-size:15px;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .trk-brand-sub{font-size:11px;color:var(--ink2);text-transform:uppercase;letter-spacing:.12em}
        .trk-live{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:var(--ok);background:var(--ok-soft);padding:6px 11px;border-radius:999px}
        .trk-live-dot{width:8px;height:8px;border-radius:50%;background:var(--ok);box-shadow:0 0 0 0 rgba(34,165,101,.5);animation:trkpulse 1.8s infinite}
        @keyframes trkpulse{0%,100%{box-shadow:0 0 0 0 rgba(34,165,101,.5)}50%{box-shadow:0 0 0 6px rgba(34,165,101,0)}}

        .trk-main{max-width:520px;margin:0 auto;padding:18px 16px 0}

        .trk-hero{position:relative;border-radius:22px;padding:30px 24px 26px;text-align:left;color:#fff;overflow:hidden;
            background:linear-gradient(135deg, color-mix(in srgb,var(--accent) 92%, #000 8%), color-mix(in srgb,var(--accent) 60%, #0f172a 40%));
            box-shadow:0 16px 34px rgba(15,23,42,.18)}
        .trk-hero.is-done{background:linear-gradient(135deg,#12995f,#0b6b43)}
        .trk-hero.is-cancel{background:linear-gradient(135deg,#b91c3b,#7f1225)}
        .trk-hero-icon{width:52px;height:52px;border-radius:15px;display:grid;place-items:center;background:rgba(255,255,255,.18);font-size:22px;margin-bottom:14px}
        .trk-hero-title{font-family:Syne,sans-serif;font-size:clamp(22px,6vw,28px);font-weight:800;line-height:1.12;margin:0;letter-spacing:-.02em;text-wrap:balance}
        .trk-hero-sub{margin:8px 0 0;font-size:14px;opacity:.9}
        .trk-pill{display:inline-flex;align-items:center;gap:7px;margin-top:16px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:700}
        .trk-pill i{font-size:7px}

        .trk-hola{margin:16px 4px 4px;font-size:14.5px;color:var(--ink2)}
        .trk-hola b{color:var(--ink)}
        .trk-hola-sep{margin:0 6px;color:#cbd5e1}

        .trk-pago{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px 16px;margin-top:12px}
        .trk-pago.ok{background:var(--ok-soft);border-color:#bfe6cf}
        .trk-pago-ico{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:#f1f5f9;font-size:18px;flex-shrink:0}
        .trk-pago-txt{display:flex;flex-direction:column;min-width:0;flex:1}
        .trk-pago-txt b{font-size:14px}
        .trk-pago-txt span{font-size:12px;color:var(--ink2);margin-top:1px}
        .trk-pago-btn{background:var(--accent);color:#fff;font-weight:700;text-decoration:none;padding:9px 16px;border-radius:11px;font-size:13.5px;flex-shrink:0}

        .trk-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 18px;margin-top:14px}
        .trk-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .trk-card-head h2{font-family:Syne,sans-serif;font-size:15px;font-weight:800;margin:0}
        .trk-total{font-family:Syne,sans-serif;font-weight:800;font-size:17px;color:var(--ink)}

        /* Timeline vertical */
        .trk-steps{display:flex;flex-direction:column}
        .trk-step{display:flex;gap:14px}
        .trk-step-rail{display:flex;flex-direction:column;align-items:center;flex-shrink:0}
        .trk-step-node{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:13px;flex-shrink:0;
            background:#eef1f5;color:#b3bdcb;border:2px solid #e2e7ee;transition:all .3s}
        .trk-step.done .trk-step-node{background:var(--ok);color:#fff;border-color:var(--ok);box-shadow:0 4px 10px rgba(34,165,101,.28)}
        .trk-step.todo .trk-step-node i{font-size:8px}
        .trk-step.now .trk-step-node{animation:trknode 1.8s infinite}
        @keyframes trknode{0%,100%{box-shadow:0 0 0 0 rgba(34,165,101,.45)}50%{box-shadow:0 0 0 7px rgba(34,165,101,0)}}
        .trk-step-line{width:3px;flex:1;min-height:26px;background:#e2e7ee;border-radius:2px;margin:3px 0}
        .trk-step-line.fill{background:var(--ok)}
        .trk-step-body{padding-bottom:20px;padding-top:5px}
        .trk-step:last-child .trk-step-body{padding-bottom:2px}
        .trk-step-label{margin:0;font-size:15px;font-weight:700;color:#9aa5b4}
        .trk-step.done .trk-step-label{color:var(--ink)}
        .trk-step-time{margin:2px 0 0;font-size:12px;color:var(--ink2)}
        .trk-step.now .trk-step-label{color:var(--ok)}

        .trk-prods{display:flex;flex-direction:column;gap:9px}
        .trk-prod{display:flex;align-items:baseline;gap:9px;font-size:14px}
        .trk-prod-q{font-weight:800;color:var(--accent);flex-shrink:0}
        .trk-prod-n{flex:1;color:var(--ink);min-width:0}
        .trk-prod-p{font-weight:700;color:var(--ink2);white-space:nowrap}
        .trk-entrega{margin-top:14px;padding-top:12px;border-top:1px dashed var(--line);font-size:13px;color:var(--ink2);display:flex;align-items:center;gap:8px}
        .trk-entrega i{color:var(--accent)}

        .trk-foot{text-align:center;color:#9aa5b4;font-size:12.5px;margin:22px 0 0}

        .trk-flash{position:fixed;top:14px;left:16px;right:16px;max-width:360px;margin:0 auto;z-index:80;display:flex;align-items:center;gap:12px;
            padding:13px 15px;border-radius:15px;background:#fff;border:1px solid #bfe6cf;box-shadow:0 18px 40px rgba(15,23,42,.18);transition:opacity .35s,transform .35s}
        .trk-flash.hidden{display:none}
        .trk-flash.opacity-0{opacity:0;transform:translateY(-10px)}
        .trk-flash.opacity-100{opacity:1;transform:translateY(0)}
        .trk-flash__icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:var(--ok-soft);color:var(--ok);flex-shrink:0}
        .trk-flash__title{margin:0;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--ok)}
        .trk-flash__text{margin:3px 0 0;font-size:13px;font-weight:600;color:var(--ink)}
    </style>
    @endpush
</div>
