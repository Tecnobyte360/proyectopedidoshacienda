@php $active = $active ?? ''; @endphp
<header>
  <nav class="nav">
    <a class="logo" href="/site" aria-label="KIVOX"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" onerror="this.outerHTML='<span style=&quot;width:52px;height:52px;border-radius:12px;background:#0EA26B;display:grid;place-items:center;font-weight:800;color:#fff;font-size:26px&quot;>K</span>'"></a>
    <div class="nav-links">
      <a href="/comunicacion" class="{{ $active==='comunicacion' ? 'on' : '' }}"><i class="fa-solid fa-comments"></i> Omnicanalidad</a>
      <a href="/operacion" class="{{ $active==='operacion' ? 'on' : '' }}"><i class="fa-solid fa-gears"></i> Pedidos &amp; Domicilios</a>
      <a href="/site#erp">Integraciones</a>
      <a href="/site#nosotros">Nosotros</a>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <a class="btn btn-line btn-sm" href="https://admin.kivox.co/login">Acceder</a>
      <a class="btn btn-ink btn-sm" href="/site#demo">Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
    </div>
  </nav>
</header>
