<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>KIVOX — Pedidos & Domicilios | Pedidos, ERP, pagos y despachos</title>
<meta name="description" content="Convierte cada conversación en un pedido entregado: toma de pedidos, integración con tu ERP (SAP, HGI), pasarelas de pago (Wompi, Bold) y despachos con mapa en vivo. El mundo de operación de KIVOX.">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://kivox.co/operacion">
<meta name="theme-color" content="#07301F">
<link rel="icon" type="image/png" href="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
@include('landing.partials.kivox-styles')
@verbatim
<style>
.erp-logos{display:flex;flex-direction:column;gap:10px}
.erp-logos .e{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--line);border-radius:13px;padding:13px 16px;font-weight:700;font-size:14px}
.erp-logos .e i{color:var(--green);width:22px;text-align:center;font-size:17px}
.erp-logos .e img{height:22px;width:auto}
.pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px}
.pay-grid .p{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 10px;text-align:center;font-weight:800;font-size:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;min-height:76px;transition:.2s}
.pay-grid .p:hover{border-color:var(--green);transform:translateY(-3px);box-shadow:var(--shadow-l)}
.pay-grid .p img{height:22px;width:auto;max-width:80px;object-fit:contain}
.pay-grid .p .ci{font-size:22px}
.pay-grid .p span{font-size:11px;color:var(--gray-d)}
/* simulación del ciclo del pedido (tarjeta clara) */
.sim-hd{display:flex;align-items:center;gap:11px;margin-bottom:14px}
.sim-hd .ic{width:38px;height:38px;border-radius:11px;background:var(--cream2);color:var(--green-d);display:grid;place-items:center;font-size:16px}
.sim-hd b{font-size:14px;color:var(--ink);display:block;line-height:1.2}
.sim-hd small{font-size:11px;color:var(--gray)}
.sim-hd .live{margin-left:auto;font-size:10px;font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:4px 10px;display:inline-flex;gap:6px;align-items:center}
.sim-hd .live::before{content:'';width:7px;height:7px;border-radius:50%;background:#12B76A;animation:cpulse 1.4s infinite}
@keyframes cpulse{50%{opacity:.3}}
.sim-steps{position:relative;padding-left:2px}
.sim-steps::before{content:'';position:absolute;left:16px;top:15px;bottom:15px;width:2px;background:var(--line)}
.sim-line{position:absolute;left:16px;top:15px;width:2px;height:0;background:var(--green);box-shadow:0 0 8px rgba(14,162,107,.5);transition:height .5s ease;z-index:1}
.sim-step{display:flex;gap:13px;align-items:center;padding:6px 0;opacity:.4;transition:opacity .4s;position:relative}
.sim-step.on,.sim-step.done{opacity:1}
.sim-step .dot{width:30px;height:30px;border-radius:50%;background:#fff;border:1.5px solid var(--line);display:grid;place-items:center;font-size:12px;color:#9aa89e;flex-shrink:0;z-index:2;transition:.3s}
.sim-step.done .dot{background:var(--green);color:#fff;border-color:var(--green)}
.sim-step.on .dot{background:var(--green-d);color:#fff;border-color:var(--green-d);box-shadow:0 0 0 6px rgba(14,162,107,.14);animation:simpulse 1s infinite}
@keyframes simpulse{50%{box-shadow:0 0 0 9px rgba(14,162,107,.04)}}
.sim-step b{font-size:12.5px;color:var(--ink);font-weight:700;display:block;line-height:1.25}
.sim-step small{font-size:10px;color:var(--gray)}
.paycard{background:var(--forest);border-radius:16px;padding:18px;color:#fff}
.paycard .amt{font-family:'Space Grotesk';font-weight:700;font-size:24px}
.paycard .go{margin-top:12px;background:var(--green);text-align:center;border-radius:10px;padding:11px;font-weight:800;font-size:13px}
.kpi-card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:18px;box-shadow:var(--shadow-l)}
.kpi-hd{display:flex;align-items:center;gap:11px;margin-bottom:15px}
.kpi-hd .kpi-ic{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#0EA26B,#0B8659);color:#fff;display:grid;place-items:center;font-size:15px;box-shadow:0 6px 16px rgba(14,162,107,.28)}
.kpi-hd b{font-size:14px;color:var(--ink);display:block;line-height:1.2}
.kpi-hd small{font-size:11px;color:var(--gray)}
.kpi-hd .kpi-live{margin-left:auto;font-size:10px;font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:4px 10px;display:inline-flex;gap:6px;align-items:center;letter-spacing:.04em}
.kpi-hd .kpi-live::before{content:'';width:7px;height:7px;border-radius:50%;background:#12B76A;animation:cpulse 1.4s infinite}
.kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:11px}
.kpi{position:relative;background:var(--cream);border:1px solid var(--line);border-radius:14px;padding:14px;transition:.2s;overflow:hidden}
.kpi:hover{border-color:var(--green);transform:translateY(-3px);box-shadow:0 12px 26px rgba(7,48,31,.08)}
.kpi .ki{width:30px;height:30px;border-radius:9px;background:#EAF7F0;color:var(--green-d);display:grid;place-items:center;font-size:13px;margin-bottom:10px}
.kpi b{font-family:'Space Grotesk',sans-serif;font-size:24px;display:block;color:var(--ink);letter-spacing:-.02em;line-height:1}
.kpi small{font-size:11px;color:var(--gray);font-weight:700;display:block;margin-top:4px}
.kpi em{font-style:normal;font-size:10px;font-weight:800;margin-top:9px;display:inline-flex;align-items:center;gap:4px}
.kpi em.up{color:#0B8659}
.kpi em.muted{color:var(--gray)}
/* mapa */
.livemap-wrap{border-radius:22px;overflow:hidden;border:1px solid var(--line);box-shadow:var(--shadow-l);background:#fff;margin-top:40px}
.livemap-bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid var(--line)}
.livemap-bar .t{font-weight:800;font-size:15px;display:flex;align-items:center;gap:9px}.livemap-bar .t i{color:var(--green-d)}
.livemap-bar .live{display:inline-flex;align-items:center;gap:7px;font-size:11.5px;font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:5px 12px}
.livemap-bar .live::before{content:'';width:8px;height:8px;border-radius:50%;background:#12B76A;animation:vfp 2s infinite}
@keyframes vfp{0%{box-shadow:0 0 0 0 rgba(18,183,106,.5)}70%{box-shadow:0 0 0 7px rgba(18,183,106,0)}100%{box-shadow:0 0 0 0 rgba(18,183,106,0)}}
.livemap-bar .leg{margin-left:auto;display:flex;gap:14px;flex-wrap:wrap;font-size:12px;font-weight:700;color:var(--gray)}
.livemap-bar .leg span{display:inline-flex;align-items:center;gap:6px}.livemap-bar .leg .d{width:11px;height:11px;border-radius:50%}
#liveMap{height:clamp(340px,50vh,500px);width:100%;background:#EAF6EF;z-index:1}
.moto-mk{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;font-size:18px;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.3);border:2.5px solid #fff}
.moto-mk.g{background:#12B76A}.moto-mk.b{background:#3B82F6}.moto-mk.o{background:#F59E0B}
.home-mk{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:14px;background:#fff;border:1px solid var(--line);box-shadow:0 2px 8px rgba(0,0,0,.2)}
.leaflet-container{font-family:'Plus Jakarta Sans',sans-serif}
.livemap-wrap{position:relative}
/* teléfono del domiciliario */
.dphone{position:absolute;left:clamp(12px,2vw,26px);bottom:clamp(12px,2vw,26px);z-index:500;width:clamp(148px,17vw,208px);background:#0D120E;border-radius:26px;padding:7px;box-shadow:0 24px 54px rgba(0,0,0,.42);transform:rotate(-3deg)}
@media(max-width:560px){.dphone{position:static;transform:none;margin:14px auto 0;width:220px}}
.dphone .scr{background:#EDE8DF;border-radius:20px;overflow:hidden}
.dphone .hd{background:linear-gradient(120deg,#12B76A,#0B8659);color:#fff;padding:9px 11px;display:flex;align-items:center;gap:8px}
.dphone .hd .av{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.22);display:grid;place-items:center;font-size:12px}
.dphone .hd b{font-size:10.5px;display:block;line-height:1.2}
.dphone .hd small{font-size:8px;opacity:.85}
.dphone .mini{height:66px;background:linear-gradient(160deg,#E7F4EE,#F5F8FB);position:relative}
.dphone .mini::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(14,162,107,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(14,162,107,.08) 1px,transparent 1px);background-size:20px 20px}
.dphone .mini .pin{position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);width:24px;height:24px;border-radius:50%;background:#12B76A;color:#fff;display:grid;place-items:center;font-size:12px;box-shadow:0 3px 8px rgba(18,183,106,.4)}
.dphone .body{padding:9px}
.dphone .card{background:#fff;border-radius:10px;padding:9px;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.dphone .card .pid{font-weight:800;color:#0F172A;font-size:11px}
.dphone .card .addr{color:#55627A;font-size:8.5px;margin-top:3px;line-height:1.3}
.dphone .card .st{font-size:8px;font-weight:800;color:#7C5CFF;margin-top:6px;display:inline-block}
.dphone .go{background:#12B76A;color:#fff;text-align:center;border-radius:8px;padding:8px;font-size:9px;font-weight:800;margin-top:8px}
.dphone-badge{position:absolute;right:clamp(12px,2vw,22px);top:clamp(12px,2vw,22px);z-index:500;background:#fff;border:1px solid var(--line);border-radius:11px;padding:9px 13px;box-shadow:0 14px 34px rgba(13,18,14,.16);font-size:11px;font-weight:800;display:flex;align-items:center;gap:9px}
.dphone-badge small{display:block;font-weight:600;color:var(--gray);font-size:9.5px}
.dphone-badge i{color:var(--green-d);font-size:16px}
.tms-chips{display:flex;gap:11px;flex-wrap:wrap;justify-content:center;margin-top:20px}
.tms-chips .c{display:inline-flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:11px 18px;font-size:13.5px;font-weight:700;color:var(--ink2)}
.tms-chips .c i{color:var(--green)}
/* encuestas de satisfacción */
.surv-feed{display:grid;gap:11px}
.surv-item{background:#fff;border:1px solid var(--line);border-radius:14px;padding:15px 17px}
.surv-item .top{display:flex;align-items:center;gap:11px;margin-bottom:8px}
.surv-item .av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--green),var(--green-d));color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px;flex-shrink:0}
.surv-item .top b{font-size:14px;color:var(--ink);display:block;line-height:1.2}
.surv-item .top small{font-size:11px;color:var(--gray)}
.surv-item .stars{color:#F5B437;font-size:14px;letter-spacing:2px;margin-left:auto}
.surv-item .cmt{font-size:13px;color:var(--ink2);line-height:1.5;font-style:italic}
.surv-item .foot{display:flex;gap:9px;margin-top:9px;flex-wrap:wrap}
.surv-item .rec{font-size:10.5px;font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:4px 11px;display:inline-flex;gap:6px;align-items:center}
.surv-item .stt{font-size:10.5px;font-weight:800;color:#7C5CFF;background:#F1ECFF;border-radius:99px;padding:4px 11px}
/* tarjetas de automatización */
.auto-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:44px}
@media(max-width:900px){.auto-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.auto-grid{grid-template-columns:1fr}}
.auto-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;text-align:left;transition:.25s}
.auto-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-l);border-color:var(--green)}
.auto-card .ai{width:48px;height:48px;border-radius:13px;display:grid;place-items:center;font-size:20px;margin-bottom:15px}
.auto-card b{font-size:15.5px;display:block;margin-bottom:7px;color:var(--ink)}
.auto-card p{font-size:13.5px;color:var(--gray-d);line-height:1.6}
/* galería de pantallas reales */
.shots{margin-top:40px}
.shot-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;justify-content:center}
.shot-tab{padding:11px 18px;border-radius:999px;border:1.5px solid var(--line);background:#fff;font-family:inherit;font-size:13.5px;font-weight:700;color:var(--gray);cursor:pointer;transition:.2s;display:inline-flex;gap:8px;align-items:center;white-space:nowrap}
.shot-tab i{color:var(--green)}
.shot-tab:hover{border-color:var(--green);color:var(--green-d)}
.shot-tab.on{background:var(--ink);border-color:var(--ink);color:#fff}
.shot-tab.on i{color:var(--lime)}
.shot-frame{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:var(--shadow-l);max-width:960px;margin:0 auto}
.shot-bar{display:flex;align-items:center;gap:7px;padding:12px 16px;background:var(--cream2);border-bottom:1px solid var(--line)}
.shot-bar i{width:11px;height:11px;border-radius:50%;display:inline-block}
.shot-url{margin-left:10px;font-size:12px;color:var(--gray);background:#fff;border:1px solid var(--line);border-radius:8px;padding:5px 16px;font-weight:600}
.shot-view{position:relative;aspect-ratio:16/9;background:linear-gradient(135deg,#EAF6EF,#F4F4EC);overflow:hidden}
.shot-ph{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:11px;color:var(--gray);text-align:center;padding:20px}
.shot-ph i{font-size:34px;color:var(--green)}.shot-ph b{font-size:16px;font-weight:800;color:var(--ink)}.shot-ph small{font-size:12px}
.shot-view img{position:absolute;inset:0;z-index:2;width:100%;height:100%;object-fit:cover;object-position:top center}
/* maqueta Pedidos */
.pmk{position:absolute;inset:0;background:#F5F8FB;padding:clamp(9px,1.5vw,18px);text-align:left;overflow:hidden}
.pmk-top{display:flex;align-items:center;gap:9px;margin-bottom:clamp(8px,1.3vw,14px);flex-wrap:wrap}
.pmk-top .ttl{font-weight:800;font-size:clamp(11px,1.6vw,16px);color:#0F172A;display:flex;align-items:center;gap:9px}
.pmk-top .ttl .ic{width:clamp(24px,3vw,34px);height:clamp(24px,3vw,34px);border-radius:9px;background:linear-gradient(135deg,#12B76A,#0B8659);color:#fff;display:grid;place-items:center;font-size:clamp(11px,1.5vw,15px)}
.pmk-top .rt{font-size:clamp(6.5px,1vw,9px);font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:3px 9px}
.pmk-top .rt::before{content:'●';margin-right:3px;color:#12B76A}
.pmk-top .wa{margin-left:auto;font-size:clamp(6.5px,1vw,9px);font-weight:700;color:#0B8659;background:#E7F8EE;border:1px solid #cdeeda;border-radius:8px;padding:5px 10px;display:flex;gap:6px;align-items:center}
.pmk-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:clamp(4px,.9vw,9px);margin-bottom:clamp(8px,1.3vw,13px)}
@media(max-width:560px){.pmk-kpis{grid-template-columns:repeat(3,1fr)}}
.pmk-kpi{background:#fff;border:1px solid #E4EAF2;border-radius:9px;padding:clamp(6px,1.1vw,11px)}
.pmk-kpi small{font-size:clamp(5px,.8vw,8px);font-weight:800;color:#94A3B8;text-transform:uppercase;letter-spacing:.03em;display:block}
.pmk-kpi b{font-size:clamp(12px,1.9vw,20px);color:#0F172A;display:block;letter-spacing:-.02em}
.pmk-kpi .u{height:3px;border-radius:3px;margin-top:5px;width:55%}
.pmk-table{background:#fff;border:1px solid #E4EAF2;border-radius:11px;overflow:hidden}
.pmk-th,.pmk-tr{display:grid;grid-template-columns:.9fr 1.8fr 1.1fr .9fr 1.2fr;gap:9px;padding:clamp(6px,1.1vw,12px) clamp(9px,1.4vw,16px);align-items:center;font-size:clamp(7px,1vw,11px)}
.pmk-th{background:#F8FAFC;color:#94A3B8;font-weight:800;text-transform:uppercase;letter-spacing:.03em;border-bottom:1px solid #E4EAF2}
.pmk-tr{border-bottom:1px solid #F1F5F9}
.pmk-tr .pid{font-weight:800;color:#0F172A}
.pmk-tr .hgi{font-size:clamp(6px,.85vw,8px);color:#7C5CFF;background:#F1ECFF;border-radius:5px;padding:1px 5px;margin-left:3px}
.pmk-tr .cli{font-weight:700;color:#0F172A}
.pmk-tr .cli small{display:block;font-weight:500;color:#94A3B8;font-size:clamp(5.5px,.8vw,8px)}
.pmk-tr .st{font-size:clamp(6.5px,.9vw,9px);font-weight:800;border-radius:99px;padding:3px 9px;display:inline-block}
.pmk-tr .tot{font-weight:800;color:#0F172A}
.pmk-tr .act{font-size:clamp(6.5px,.9vw,9px);font-weight:800;color:#fff;border-radius:8px;padding:6px 10px;text-align:center}
@media(max-width:640px){.pmk-th,.pmk-tr{grid-template-columns:.9fr 1.6fr 1fr 1.1fr}.pmk-th span:nth-child(4),.pmk-tr>*:nth-child(4){display:none}}
</style>
@endverbatim
</head>
<body>
@include('landing.partials.kivox-header', ['active' => 'operacion'])

{{-- SUBHERO --}}
<section class="subhero oper" id="top">
  <div class="wrap">
    <div>
      <a class="back" href="/" style="color:#EAF6EE"><i class="fa-solid fa-arrow-left"></i> Volver a KIVOX</a>
      <h1>Convierte cada conversación en un pedido entregado</h1>
      <p class="sub">El motor que mueve tu negocio: pedidos que se crean en tu ERP, pagos en línea y domicilios con mapa en vivo. Del chat a la puerta del cliente, sin digitar dos veces.</p>
      <div class="cta-row">
        <a class="btn btn-lime" href="/#demo">Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
        <a class="btn btn-line" href="/comunicacion" style="color:#EAF6EE;border-color:rgba(198,244,95,.4)"><i class="fa-solid fa-comments"></i> Ver Omnicanalidad</a>
      </div>
    </div>
    <div class="fvis reveal d1">
      <div class="sim-hd">
        <span class="ic"><i class="fa-solid fa-bolt"></i></span>
        <div><b>Pedido #8842 · en vivo</b><small>del chat a la puerta del cliente</small></div>
        <span class="live">EN VIVO</span>
      </div>
      <div class="sim-steps" id="simSteps">
        <div class="sim-line" id="simLine"></div>
        <div class="sim-step"><span class="dot"><i class="fa-brands fa-whatsapp"></i></span><span><b>Pedido recibido</b><small>por WhatsApp · Ruiz Fernanda</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-wand-magic-sparkles"></i></span><span><b>IA tomó el pedido</b><small>10 productos · inventario consultado</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-cubes"></i></span><span><b>Creado en tu ERP</b><small>SAP / HGI · doc #36672</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-credit-card"></i></span><span><b>Enlace de pago enviado</b><small>Wompi · Bold · Nequi · PSE</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-lock"></i></span><span><b>Pago confirmado</b><small>$1.284.000 · vía Wompi ✓</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-motorcycle"></i></span><span><b>Domiciliario asignado</b><small>automático · Cristian A. 🛵</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-location-dot"></i></span><span><b>En ruta</b><small>mapa en vivo · GPS activo</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-box-open"></i></span><span><b>Entregado</b><small>código de entrega verificado</small></span></div>
        <div class="sim-step"><span class="dot"><i class="fa-solid fa-star"></i></span><span><b>Encuesta enviada</b><small>★★★★★ · el cliente recomienda</small></span></div>
      </div>
    </div>
  </div>
</section>

{{-- PEDIDOS --}}
<section>
  <div class="wrap frow">
    <div class="ftext reveal">
      <h2>Del chat a la venta,<br>sin fricción</h2>
      <p class="lead">Catálogo en vivo con precios por cliente, pedidos tomados por la IA o por tu equipo, y comandas que salen directo en la impresora del punto de venta.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> Catálogo con precios por cliente</li>
        <li><i class="fa-solid fa-circle-check"></i> Pedidos creados directo en tu ERP</li>
        <li><i class="fa-solid fa-circle-check"></i> Comanda automática en la impresora</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div class="kpi-card">
        <div class="kpi-hd">
          <span class="kpi-ic"><i class="fa-solid fa-chart-line"></i></span>
          <div><b>Resumen de hoy</b><small>Actualizado en tiempo real</small></div>
          <span class="kpi-live">EN VIVO</span>
        </div>
        <div class="kpis">
          <div class="kpi"><span class="ki"><i class="fa-solid fa-bag-shopping"></i></span><b>312</b><small>Pedidos hoy</small><em class="up"><i class="fa-solid fa-arrow-trend-up"></i> +18% vs. ayer</em></div>
          <div class="kpi"><span class="ki"><i class="fa-solid fa-sack-dollar"></i></span><b>$4,2 M</b><small>Ventas del día</small><em class="up"><i class="fa-solid fa-arrow-trend-up"></i> +12% vs. ayer</em></div>
          <div class="kpi"><span class="ki"><i class="fa-solid fa-receipt"></i></span><b>#8842</b><small>Último pedido</small><em class="muted"><i class="fa-solid fa-clock"></i> hace 40 s</em></div>
          <div class="kpi"><span class="ki"><i class="fa-solid fa-bolt"></i></span><b>2,1 s</b><small>Toma por IA</small><em class="up"><i class="fa-solid fa-gauge-high"></i> Respuesta veloz</em></div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- GALERÍA PANTALLAS REALES --}}
<section class="center" style="padding-top:20px">
  <div class="wrap">
    <h2 class="reveal">Tu operación, en una sola pantalla</h2>
    <p class="lead reveal d1">Del pedido al despacho — así se ve KIVOX gestionando tu operación de verdad.</p>
    <div class="shots reveal d1">
      <div class="shot-frame">
        <div class="shot-bar"><i style="background:#ff5f57"></i><i style="background:#febc2e"></i><i style="background:#28c840"></i><span class="shot-url">admin.kivox.co/pedidos</span></div>
        <div class="shot-view" style="aspect-ratio:auto">
          <div class="pmk" style="position:relative">
            <div class="pmk-top">
              <span class="ttl"><span class="ic"><i class="fa-solid fa-bag-shopping"></i></span> Gestión de Pedidos</span>
              <span class="rt">EN TIEMPO REAL</span>
              <span class="wa"><i class="fa-brands fa-whatsapp"></i> WhatsApp · Meta Cloud API · ACTIVA</span>
            </div>
            <div class="pmk-kpis">
              <div class="pmk-kpi"><small>Nuevos</small><b>1</b><div class="u" style="background:#3B82F6"></div></div>
              <div class="pmk-kpi"><small>Programados</small><b>2</b><div class="u" style="background:#06B6D4"></div></div>
              <div class="pmk-kpi"><small>En proceso</small><b>0</b><div class="u" style="background:#F59E0B"></div></div>
              <div class="pmk-kpi"><small>Despachados</small><b>3</b><div class="u" style="background:#7C5CFF"></div></div>
              <div class="pmk-kpi"><small>Entregados</small><b>60</b><div class="u" style="background:#12B76A"></div></div>
              <div class="pmk-kpi"><small>Cancelados</small><b>0</b><div class="u" style="background:#EF4444"></div></div>
            </div>
            <div class="pmk-table">
              <div class="pmk-th"><span>Pedido</span><span>Cliente</span><span>Estado</span><span>Total</span><span>Acción</span></div>
              <div class="pmk-tr"><span><span class="pid">#142</span><span class="hgi">HGI 36672</span></span><span class="cli">Juan Gómez<small>Belén</small></span><span><span class="st" style="background:#EAF1FF;color:#3B82F6">● Nuevo</span></span><span class="tot">$32.525</span><span class="act" style="background:#F59E0B">🍳 Iniciar preparación</span></div>
              <div class="pmk-tr"><span><span class="pid">#141</span><span class="hgi">HGI 36671</span></span><span class="cli">María López<small>Bello</small></span><span><span class="st" style="background:#F1ECFF;color:#7C5CFF">● Despachado</span></span><span class="tot">$199.403</span><span class="act" style="background:#12B76A">✔ Confirmar entrega</span></div>
              <div class="pmk-tr"><span><span class="pid">#140</span><span class="hgi">HGI 36670</span></span><span class="cli">Carlos Ruiz<small>Bello</small></span><span><span class="st" style="background:#E7F8EE;color:#0B8659">● Entregado</span></span><span class="tot">$256.500</span><span class="act" style="background:#A7C7B4">✔ Entregado</span></div>
              <div class="pmk-tr" style="border:none"><span><span class="pid">#139</span><span class="hgi">HGI 36669</span></span><span class="cli">Ana Torres<small>Bello</small></span><span><span class="st" style="background:#E7F8EE;color:#0B8659">● Entregado</span></span><span class="tot">$162.755</span><span class="act" style="background:#A7C7B4">✔ Entregado</span></div>
            </div>
          </div>
        </div>
      </div>
      <p style="font-size:13px;color:var(--gray);font-weight:600;margin-top:14px"><i class="fa-solid fa-bolt" style="color:var(--green)"></i> Los pedidos entran en tiempo real por WhatsApp con la API oficial de Meta y se gestionan aquí, hasta la entrega.</p>
    </div>
  </div>
</section>

{{-- PAGOS --}}
<section class="soft">
  <div class="wrap frow rev">
    <div class="ftext reveal">
      <h2>Cobra al instante,<br>por donde te escriben</h2>
      <p class="lead">La IA envía el enlace de pago automáticamente y confirma cuando el cliente paga. Sin llamadas, sin comprobantes por foto.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> Enlace de pago automático tras el pedido</li>
        <li><i class="fa-solid fa-circle-check"></i> Confirmación de pago en tiempo real</li>
        <li><i class="fa-solid fa-circle-check"></i> Paso automático a despacho</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div style="font-size:13px;font-weight:800;margin-bottom:14px">Pasarelas integradas</div>
      <div class="pay-grid">
        <div class="p"><img src="/logos/wompi.svg" alt="Wompi" onerror="this.outerHTML='<b>Wompi</b>'"></div>
        <div class="p"><img src="/logos/bold-new.svg" alt="Bold" style="height:36px" onerror="this.outerHTML='<b>Bold</b>'"></div>
        <div class="p"><img src="/logos/nequi.svg" alt="Nequi" onerror="this.outerHTML='<b>Nequi</b>'"></div>
        <div class="p"><img src="/logos/pse-new.png" alt="PSE" style="height:30px" onerror="this.outerHTML='<b>PSE</b>'"></div>
      </div>
    </div>
  </div>
</section>

{{-- DESPACHOS / MAPA EN VIVO --}}
<section class="center">
  <div class="wrap">
    <h2 class="reveal">Asigna domiciliarios automáticamente<br>y míralos en el mapa, en vivo</h2>
    <p class="lead reveal d1">El TMS de KIVOX <strong style="color:var(--ink)">asigna cada pedido al domiciliario ideal por zona</strong>, le llega a su app móvil, y tú ves dónde está cada uno en tiempo real.</p>
    <div class="tms-chips reveal d1">
      <span class="c"><i class="fa-solid fa-wand-magic-sparkles"></i> Asignación automática por zona</span>
      <span class="c"><i class="fa-solid fa-mobile-screen"></i> App para el domiciliario</span>
      <span class="c"><i class="fa-solid fa-location-crosshairs"></i> GPS en tiempo real</span>
    </div>
    <div class="livemap-wrap reveal d1">
      <div class="livemap-bar">
        <span class="t"><i class="fa-solid fa-map-location-dot"></i> Mapa de domiciliarios</span>
        <span class="live">EN VIVO</span>
        <span class="leg">
          <span><span class="d" style="background:#12B76A"></span> En ruta</span>
          <span><span class="d" style="background:#3B82F6"></span> Recogiendo</span>
          <span><span class="d" style="background:#F59E0B"></span> Ocupado</span>
        </span>
      </div>
      <div id="liveMap"></div>
      {{-- badge asignación automática --}}
      <div class="dphone-badge"><i class="fa-solid fa-wand-magic-sparkles"></i><span>Asignación automática<small>Pedido #142 → Cristian A. 🛵</small></span></div>
      {{-- teléfono del domiciliario --}}
      <div class="dphone">
        <div class="scr">
          <div class="hd"><span class="av"><i class="fa-solid fa-motorcycle"></i></span><div><b>KIVOX Domiciliario</b><small>Cristian A. · en ruta</small></div></div>
          <div class="mini"><span class="pin">🛵</span></div>
          <div class="body">
            <div class="card">
              <div class="pid">Pedido #142</div>
              <div class="addr"><i class="fa-solid fa-location-dot" style="color:#12B76A"></i> Carrera 54 #53-21 · Belén</div>
              <span class="st">● Asignado automáticamente</span>
              <div class="go"><i class="fa-solid fa-diamond-turn-right"></i> Navegar y entregar</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <p class="reveal d1" style="font-size:13px;color:var(--gray);font-weight:600;margin-top:14px"><i class="fa-solid fa-circle-info" style="color:var(--green)"></i> Demostración con recorridos simulados en Bello, Antioquia. En tu cuenta se ven tus domiciliarios reales.</p>
  </div>
</section>

{{-- ERP --}}
<section class="soft">
  <div class="wrap frow">
    <div class="ftext reveal">
      <h2>Habla el idioma<br>de tu ERP</h2>
      <p class="lead">Pedidos, clientes, inventario, facturación y cartera fluyen entre KIVOX y tus sistemas — en ambos sentidos, sin digitar dos veces.</p>
    </div>
    <div class="fvis reveal d1">
      <div class="erp-logos">
        <div class="e"><img src="/logos/sap.svg" alt="SAP" onerror="this.outerHTML='<i class=&quot;fa-solid fa-cubes&quot;></i>'"> SAP Business One</div>
        <div class="e"><img src="/logos/hgi.png" alt="HGI" style="background:#fff;border-radius:4px" onerror="this.outerHTML='<i class=&quot;fa-solid fa-database&quot;></i>'"> HGI</div>
        <div class="e"><i class="fa-solid fa-code"></i> Cualquier ERP o API a medida</div>
      </div>
    </div>
  </div>
</section>

{{-- REPORTES --}}
<section>
  <div class="wrap frow rev">
    <div class="ftext reveal">
      <h2>Tu operación,<br>medida al segundo</h2>
      <p class="lead">Ventas por sede, top productos, top domiciliarios, entregas y estados — todo en un tablero, sin Excel.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> Ventas y ticket promedio por sede</li>
        <li><i class="fa-solid fa-circle-check"></i> Top productos y top domiciliarios</li>
        <li><i class="fa-solid fa-circle-check"></i> Tasa de entrega y estados de pedido</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div class="kpis" style="grid-template-columns:repeat(2,1fr)">
        <div class="kpi" style="background:linear-gradient(135deg,#12B76A,#0B8659);border:none"><b style="color:#fff">$2.724.450</b><small style="color:rgba(255,255,255,.8)">Ingresos (7 días)</small></div>
        <div class="kpi"><b>28</b><small>Pedidos</small></div>
        <div class="kpi"><b>25</b><small>Entregados</small></div>
        <div class="kpi"><b>89,3%</b><small>Tasa de entrega</small></div>
      </div>
    </div>
  </div>
</section>

{{-- AUTOMATIZACIONES --}}
<section class="soft">
  <div class="wrap frow">
    <div class="ftext reveal">
      <h2>Automatiza lo que<br>hoy haces a mano</h2>
      <p class="lead">Después de cada entrega, KIVOX envía una <strong style="color:var(--ink)">encuesta de satisfacción automática</strong> y guarda la calificación, si el cliente te recomienda y sus comentarios — sin que muevas un dedo.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> Encuesta automática al entregar cada pedido</li>
        <li><i class="fa-solid fa-circle-check"></i> Calificación, recomendación y comentarios</li>
        <li><i class="fa-solid fa-circle-check"></i> Todo reflejado en tu plataforma</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div style="font-size:13px;font-weight:800;color:var(--ink);margin-bottom:14px"><i class="fa-solid fa-star" style="color:#F5B437"></i> Encuestas de satisfacción</div>
      <div class="surv-feed">
        <div class="surv-item">
          <div class="top"><span class="av">CA</span><div><b>Cardona Alejandro</b><small>Pedido #131 · Cristian A.</small></div><span class="stars">★★★★★</span></div>
          <p class="cmt">"Súper atento, me llamó dos veces y me reprogramó la entrega porque no estaba en casa. Excelente servicio."</p>
          <div class="foot"><span class="rec"><i class="fa-solid fa-thumbs-up"></i> Recomienda</span><span class="stt">✓ Respondida</span></div>
        </div>
        <div class="surv-item">
          <div class="top"><span class="av">MJ</span><div><b>Martínez Juliana</b><small>Pedido #130 · Humberto A.</small></div><span class="stars">★★★★★</span></div>
          <div class="foot"><span class="rec"><i class="fa-solid fa-thumbs-up"></i> Recomienda</span><span class="stt">✓ Respondida</span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="wrap center" style="margin-top:20px">
    <div class="auto-grid" style="text-align:left">
      <div class="auto-card reveal"><div class="ai" style="background:#E7F8EE;color:#1FB863"><i class="fa-solid fa-star"></i></div><b>Encuestas de satisfacción</b><p>Automáticas al entregar, con calificación, recomendación y comentarios.</p></div>
      <div class="auto-card reveal d1"><div class="ai" style="background:#EAF1FF;color:#3B82F6"><i class="fa-solid fa-credit-card"></i></div><b>Pagos en línea</b><p>Enlace de pago automático; lo ves reflejado en la plataforma, integrado con Wompi y Bold.</p></div>
      <div class="auto-card reveal d2"><div class="ai" style="background:#FFF4E0;color:#F59E0B"><i class="fa-solid fa-user-plus"></i></div><b>Creación de clientes</b><p>Cada nuevo contacto se crea automáticamente en tu base de datos, sin digitar.</p></div>
      <div class="auto-card reveal d3"><div class="ai" style="background:#FCE7F3;color:#EC4899"><i class="fa-solid fa-cake-candles"></i></div><b>Cumpleaños y fechas</b><p>Mensajes automáticos de cumpleaños, recompra y fechas especiales.</p></div>
    </div>
    <p class="lead reveal d1" style="margin-top:24px;font-weight:700;color:var(--ink2)">…y mucho más: recordatorios de cartera, seguimiento de pedidos, reactivación de clientes inactivos.</p>
  </div>
</section>

{{-- CTA --}}
<section style="padding-bottom:0">
  <div class="cta reveal">
    <h2 class="disp">¿Hablamos?</h2>
    <p>Agenda una demo y mira a KIVOX tomar pedidos, cobrar y despachar en vivo con los datos de tu negocio.</p>
    <div style="display:flex;gap:13px;justify-content:center;flex-wrap:wrap;position:relative;z-index:2">
      <a class="btn btn-ink" href="https://wa.me/573216499744?text=Hola%2C%20quiero%20una%20demo%20de%20KIVOX" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
      <a class="btn btn-line" href="/comunicacion" style="border-color:rgba(13,18,14,.35)"><i class="fa-solid fa-comments"></i> Ver Omnicanalidad</a>
    </div>
  </div>
</section>

@include('landing.partials.kivox-footer')
@verbatim
<script>
function initLiveMap(){
  const el=document.getElementById('liveMap');
  if(!el||typeof L==='undefined'){return setTimeout(initLiveMap,300);}
  const map=L.map('liveMap',{center:[6.3388,-75.5575],zoom:14,scrollWheelZoom:false});
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{subdomains:'abcd',maxZoom:19,attribution:'© OpenStreetMap © CARTO'}).addTo(map);
  const motoIcon=(c)=>L.divIcon({className:'',html:'<div class="moto-mk '+c+'" style="color:'+({g:'#12B76A',b:'#3B82F6',o:'#F59E0B'}[c])+'">🛵</div>',iconSize:[38,38],iconAnchor:[19,19]});
  const homeIcon=L.divIcon({className:'',html:'<div class="home-mk">🏠</div>',iconSize:[28,28],iconAnchor:[14,14]});
  const routes=[
    {c:'g',pts:[[6.3462,-75.5588],[6.3440,-75.5560],[6.3416,-75.5535],[6.3392,-75.5520],[6.3372,-75.5505],[6.3360,-75.5490]]},
    {c:'b',pts:[[6.3322,-75.5592],[6.3348,-75.5572],[6.3376,-75.5556],[6.3402,-75.5566],[6.3424,-75.5586],[6.3446,-75.5602]]},
    {c:'o',pts:[[6.3408,-75.5648],[6.3390,-75.5614],[6.3374,-75.5586],[6.3360,-75.5560],[6.3348,-75.5534]]}
  ];
  [[6.3360,-75.5490],[6.3446,-75.5602],[6.3348,-75.5534]].forEach(p=>L.marker(p,{icon:homeIcon}).addTo(map));
  const motos=routes.map(r=>{
    L.polyline(r.pts,{color:{g:'#12B76A',b:'#3B82F6',o:'#F59E0B'}[r.c],weight:3,opacity:.35,dashArray:'6 8'}).addTo(map);
    return {m:L.marker(r.pts[0],{icon:motoIcon(r.c)}).addTo(map),pts:r.pts,t:Math.random()*(r.pts.length-1),spd:0.006+Math.random()*0.004,dir:1};
  });
  const lerp=(a,b,f)=>[a[0]+(b[0]-a[0])*f,a[1]+(b[1]-a[1])*f];
  (function tick(){
    motos.forEach(o=>{o.t+=o.spd*o.dir;if(o.t>=o.pts.length-1){o.t=o.pts.length-1;o.dir=-1;}if(o.t<=0){o.t=0;o.dir=1;}const i=Math.floor(o.t),f=o.t-i;o.m.setLatLng(lerp(o.pts[i],o.pts[Math.min(i+1,o.pts.length-1)],f));});
    requestAnimationFrame(tick);
  })();
  new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting)map.invalidateSize();}),{threshold:.1}).observe(el);
}
window.addEventListener('load',initLiveMap);
/* simulación del ciclo del pedido */
(function(){
  const steps=[...document.querySelectorAll('#simSteps .sim-step')];
  const line=document.getElementById('simLine');
  if(!steps.length)return;
  const y=i=>steps[i].offsetTop-steps[0].offsetTop+15;
  let cur=0;
  function tick(){
    steps.forEach((s,idx)=>{s.classList.toggle('on',idx===cur);s.classList.toggle('done',idx<cur);});
    if(line)line.style.height=y(cur)+'px';
    if(cur>=steps.length-1){
      setTimeout(()=>{
        steps[cur].classList.remove('on');steps[cur].classList.add('done');
        setTimeout(()=>{cur=0;steps.forEach(s=>s.classList.remove('on','done'));if(line)line.style.height='0px';setTimeout(tick,800);},2200);
      },1000);
      return;
    }
    cur++;setTimeout(tick,1050);
  }
  tick();
})();
/* galería de pantallas */
const shotTabs=document.getElementById('shotTabs');
if(shotTabs){
  const img=document.getElementById('shotImg'),url=document.getElementById('shotUrl'),ph=document.querySelector('.shot-ph');
  shotTabs.addEventListener('click',e=>{
    const b=e.target.closest('.shot-tab');if(!b)return;
    shotTabs.querySelectorAll('.shot-tab').forEach(x=>x.classList.remove('on'));b.classList.add('on');
    url.textContent=b.dataset.url;
    ph.querySelector('b').textContent=b.textContent.trim();
    img.style.display='';img.src=b.dataset.img;
    img.onerror=function(){this.style.display='none'};
  });
}
</script>
@endverbatim
</body>
</html>
