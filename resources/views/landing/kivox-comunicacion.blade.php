<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>KIVOX — Omnicanalidad & Marketing | Atención omnicanal con IA</title>
<meta name="description" content="Atiende y véndele a tus clientes por WhatsApp, Instagram, Facebook y web, con bots de IA, campañas masivas y gestión por departamentos. El mundo de comunicación de KIVOX.">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://kivox.co/comunicacion">
<meta name="theme-color" content="#FBFBF7">
<link rel="icon" type="image/png" href="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png">
@include('landing.partials.kivox-styles')
@verbatim
<style>
.mods{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:40px}
@media(max-width:900px){.mods{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.mods{grid-template-columns:1fr}}
.mod{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;text-align:left;transition:.25s}
.mod:hover{transform:translateY(-5px);box-shadow:var(--shadow-l);border-color:var(--green)}
.mod .mi{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;font-size:19px;margin-bottom:14px;background:var(--cream2);color:var(--green-d)}
.mod b{font-size:15px;display:block;margin-bottom:6px}
.mod p{font-size:13px;color:var(--gray);line-height:1.55}
.subhero.comm{position:relative}
.subhero.comm::before{content:'';position:absolute;top:-130px;right:-110px;width:440px;height:440px;border-radius:50%;background:radial-gradient(circle,rgba(18,183,106,.15),transparent 68%);z-index:0}
.subhero.comm::after{content:'';position:absolute;bottom:-150px;left:-130px;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(198,244,95,.18),transparent 68%);z-index:0}
.subhero .wrap{position:relative;z-index:2}
.inbox-hd{display:flex;align-items:center;gap:11px;padding-bottom:14px;margin-bottom:12px;border-bottom:1px solid var(--line)}
.inbox-hd .ic{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--green),var(--green-d));color:#fff;display:grid;place-items:center;font-size:16px}
.inbox-hd b{font-size:14.5px;display:block}
.inbox-hd small{font-size:11.5px;color:var(--green-d);font-weight:600}
.inbox-hd .live{margin-left:auto;font-size:10px;font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:4px 10px;display:inline-flex;gap:6px;align-items:center}
.inbox-hd .live::before{content:'';width:7px;height:7px;border-radius:50%;background:#12B76A;animation:cpulse 1.6s infinite}
@keyframes cpulse{50%{opacity:.3}}
.chanchips{display:flex;flex-direction:column;gap:9px}
.chanchips .c{display:flex;align-items:center;gap:13px;background:#fff;border:1px solid var(--line);border-radius:13px;padding:12px 14px;font-weight:700;font-size:13.5px;transition:.22s;cursor:default}
.chanchips .c:hover{transform:translateX(4px);border-color:var(--green);box-shadow:0 8px 20px rgba(14,162,107,.08)}
.chanchips .c i.av{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:17px;flex-shrink:0}
.chanchips .c .tx{display:flex;flex-direction:column;line-height:1.3}
.chanchips .c .tx small{font-size:11.5px;font-weight:600;color:var(--gray)}
.chanchips .c .badge{margin-left:auto;background:var(--green);color:#fff;font-size:10px;font-weight:800;border-radius:99px;min-width:20px;height:20px;display:grid;place-items:center;padding:0 6px}
.chanchips .c .iatag{margin-left:auto;width:22px;height:22px;border-radius:50%;background:var(--cream2);color:var(--green-d);display:grid;place-items:center;font-size:11px}
.float-badge{position:absolute;background:#fff;border:1px solid var(--line);border-radius:13px;padding:10px 14px;box-shadow:0 18px 40px rgba(13,18,14,.14);z-index:6;display:flex;align-items:center;gap:10px;font-size:12px;font-weight:800;animation:flt 5s ease-in-out infinite}
.float-badge small{display:block;font-weight:600;color:var(--gray)}
@keyframes flt{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
.trust-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:26px}
.trust-row .t{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:var(--ink2);background:#fff;border:1px solid var(--line);border-radius:999px;padding:9px 15px}
.trust-row .t i{color:var(--green)}
.camp .bar{display:grid;grid-template-columns:100px 1fr 50px;gap:11px;align-items:center;font-size:12.5px;font-weight:600;margin-bottom:11px}
.camp .bar .tr{height:8px;border-radius:99px;background:var(--cream2);overflow:hidden}
.camp .bar .tr i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--green),var(--lime));width:0;transition:width 1.4s cubic-bezier(.2,.8,.2,1)}
.camp .bar b{text-align:right;color:var(--green-d)}
</style>
@endverbatim
</head>
<body>
@include('landing.partials.kivox-header', ['active' => 'comunicacion'])

{{-- SUBHERO --}}
<section class="subhero comm" id="top">
  <div class="wrap">
    <div>
      <a class="back" href="/"><i class="fa-solid fa-arrow-left"></i> Volver a KIVOX</a>
      <h1>Atiende y véndele a tus clientes por todos lados</h1>
      <p class="sub">Tus clientes te escriben por WhatsApp, Instagram, Facebook o tu web. KIVOX lo unifica todo en una sola bandeja, con inteligencia artificial que atiende y vende 24/7.</p>
      <div class="cta-row">
        <a class="btn btn-ink" href="/#demo">Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
        <a class="btn btn-line" href="/operacion"><i class="fa-solid fa-gears"></i> Ver Pedidos &amp; Domicilios</a>
      </div>
      <div class="trust-row">
        <span class="t"><i class="fa-brands fa-meta"></i> API oficial de Meta</span>
        <span class="t"><i class="fa-solid fa-robot"></i> IA 24/7</span>
        <span class="t"><i class="fa-solid fa-bolt"></i> Responde en 2 s</span>
      </div>
    </div>
    <div style="position:relative" class="reveal d1">
      <div class="float-badge" style="top:-22px;left:-18px"><i class="fa-brands fa-meta" style="font-size:22px;color:#0081FB"></i><span>Meta Business<br>Partner Oficial</span></div>
      <div class="float-badge" style="bottom:-18px;right:-14px;animation-delay:1.2s"><i class="fa-solid fa-wand-magic-sparkles" style="font-size:19px;color:#0EA26B"></i><span>Respondido<small>en 2,1 segundos</small></span></div>
      <div class="fvis">
        <div class="inbox-hd">
          <span class="ic"><i class="fa-solid fa-inbox"></i></span>
          <div><b>Bandeja unificada</b><small>atendida por KIVOX IA</small></div>
          <span class="live">EN VIVO</span>
        </div>
        <div class="chanchips">
          <div class="c"><i class="av fa-brands fa-whatsapp" style="background:#E7F8EE;color:#25D366"></i><span class="tx">WhatsApp Business<small>Ruiz Fernanda · Pedido #103</small></span><span class="badge">2</span></div>
          <div class="c"><i class="av fa-brands fa-instagram" style="background:#FDEEF4;color:#DC2743"></i><span class="tx">Instagram<small>@modaurbana · Vi la promo ✨</small></span><span class="iatag"><i class="fa-solid fa-wand-magic-sparkles"></i></span></div>
          <div class="c"><i class="av fa-brands fa-facebook" style="background:#EAF1FF;color:#1877F2"></i><span class="tx">Messenger<small>Vergara Oscar · ¿Domicilio hoy?</small></span><span class="badge">1</span></div>
          <div class="c"><i class="av fa-solid fa-globe" style="background:var(--cream2);color:var(--green-d)"></i><span class="tx">Chat web<small>Nuevo visitante · agendó demo</small></span><span class="iatag"><i class="fa-solid fa-wand-magic-sparkles"></i></span></div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- OMNICANAL --}}
<section>
  <div class="wrap frow">
    <div class="ftext reveal">
      <h2>Todos tus chats,<br>una sola bandeja</h2>
      <p class="lead">Tu equipo responde desde un solo lugar, con el historial completo de cada cliente y roles por área. Nada se pierde.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> WhatsApp, Instagram, Facebook y web unificados</li>
        <li><i class="fa-solid fa-circle-check"></i> Varios números por negocio, sin mezclar</li>
        <li><i class="fa-solid fa-circle-check"></i> Bandeja compartida con roles por equipo</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div class="mods" style="grid-template-columns:1fr 1fr;margin-top:0">
        <div class="mod"><div class="mi"><i class="fa-solid fa-inbox"></i></div><b>Bandeja unificada</b><p>Todos los canales en una vista.</p></div>
        <div class="mod"><div class="mi"><i class="fa-solid fa-users"></i></div><b>Roles por área</b><p>Cada asesor ve lo suyo.</p></div>
        <div class="mod"><div class="mi"><i class="fa-solid fa-clock-rotate-left"></i></div><b>Historial completo</b><p>Todo el contexto del cliente.</p></div>
        <div class="mod"><div class="mi"><i class="fa-solid fa-hashtag"></i></div><b>Varios números</b><p>Ventas, soporte, cartera…</p></div>
      </div>
    </div>
  </div>
</section>

{{-- BOTS IA --}}
<section class="soft">
  <div class="wrap frow rev">
    <div class="ftext reveal">
      <h2>Un vendedor que<br>nunca duerme</h2>
      <p class="lead">No es un menú de opciones: es IA que entiende lenguaje natural, atiende de inmediato y vende. Aprende de tu negocio con cada conversación.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> Responde en segundos, 24/7</li>
        <li><i class="fa-solid fa-circle-check"></i> Califica clientes y enruta conversaciones</li>
        <li><i class="fa-solid fa-circle-check"></i> Toma pedidos completos sin humano</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px"><span style="width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,var(--green),var(--green-d));color:#fff;font-size:18px"><i class="fa-solid fa-wand-magic-sparkles"></i></span><div><b style="font-size:15px">Motor de IA KIVOX</b><div style="font-size:12px;color:var(--green-d)">● activo · aprendiendo</div></div></div>
      <div class="camp">
        <div class="bar"><span>Resueltas por IA</span><span class="tr"><i data-w="91%"></i></span><b>91%</b></div>
        <div class="bar"><span>Respuesta</span><span class="tr"><i data-w="96%"></i></span><b>2,1 s</b></div>
        <div class="bar"><span>Leads hoy</span><span class="tr"><i data-w="70%"></i></span><b>+214</b></div>
      </div>
    </div>
  </div>
</section>

{{-- CAMPAÑAS --}}
<section>
  <div class="wrap frow">
    <div class="ftext reveal">
      <h2>Llega a miles<br>con un solo clic</h2>
      <p class="lead">Envíos masivos por WhatsApp con plantillas aprobadas por Meta, segmentados por grupos de clientes — y medibles al instante.</p>
      <ul class="flist">
        <li><i class="fa-solid fa-circle-check"></i> Plantillas oficiales aprobadas por Meta</li>
        <li><i class="fa-solid fa-circle-check"></i> Segmentación por grupos e historial</li>
        <li><i class="fa-solid fa-circle-check"></i> Automatizaciones: cumpleaños, recompra, cartera</li>
      </ul>
    </div>
    <div class="fvis reveal d1">
      <div style="font-size:13px;font-weight:800;color:var(--ink);margin-bottom:14px"><i class="fa-brands fa-whatsapp" style="color:#25D366"></i> Campaña: Promo fin de semana</div>
      <div class="camp">
        <div class="bar"><span>Enviados</span><span class="tr"><i data-w="100%" style="background:#94A3B8"></i></span><b>2.450</b></div>
        <div class="bar"><span>Entregados</span><span class="tr"><i data-w="97%"></i></span><b>2.376</b></div>
        <div class="bar"><span>Leídos</span><span class="tr"><i data-w="89%"></i></span><b>2.181</b></div>
        <div class="bar"><span>Respondieron</span><span class="tr"><i data-w="42%" style="background:#7C5CFF"></i></span><b>1.029</b></div>
      </div>
      <div style="margin-top:14px;background:var(--cream2);border-radius:11px;padding:12px 14px;font-size:13px;font-weight:700;color:var(--green-d)"><i class="fa-solid fa-arrow-trend-up"></i> 386 pedidos generados por esta campaña</div>
    </div>
  </div>
</section>

{{-- DEPARTAMENTOS --}}
<section class="soft center">
  <div class="wrap">
    <h2 class="reveal">Conecta todos los departamentos de tu empresa</h2>
    <p class="lead reveal d1">El bot deriva cada conversación al área correcta según palabras clave, y notifica al equipo del departamento.</p>
    <div class="mods reveal d1" style="text-align:left">
      <div class="mod"><div class="mi"><i class="fa-solid fa-headset"></i></div><b>Servicio al cliente</b><p>Atención y seguimiento.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-clipboard-list"></i></div><b>Peticiones y PQR</b><p>Quejas y reclamos con trazabilidad.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-handshake"></i></div><b>Comercial</b><p>Cotizaciones y ventas.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-user-tie"></i></div><b>Recursos Humanos</b><p>Vacantes y personal.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-file-invoice-dollar"></i></div><b>Cartera</b><p>Estados de cuenta y pagos.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-truck-fast"></i></div><b>Logística</b><p>Rastreo y envíos.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-clipboard-check"></i></div><b>Calidad</b><p>Encuestas y satisfacción.</p></div>
      <div class="mod"><div class="mi"><i class="fa-solid fa-plus"></i></div><b>Y las que necesites</b><p>Sin límite de departamentos.</p></div>
    </div>
  </div>
</section>

{{-- CTA --}}
<section style="padding-bottom:0">
  <div class="cta reveal">
    <h2 class="disp">¿Hablamos?</h2>
    <p>Agenda una demo y mira a la IA de KIVOX atender y vender en vivo por todos tus canales.</p>
    <div style="display:flex;gap:13px;justify-content:center;flex-wrap:wrap;position:relative;z-index:2">
      <a class="btn btn-ink" href="https://wa.me/573216499744?text=Hola%2C%20quiero%20una%20demo%20de%20KIVOX" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
      <a class="btn btn-line" href="/operacion" style="border-color:rgba(13,18,14,.35)"><i class="fa-solid fa-gears"></i> Ver Pedidos &amp; Domicilios</a>
    </div>
  </div>
</section>

@include('landing.partials.kivox-footer')
</body>
</html>
