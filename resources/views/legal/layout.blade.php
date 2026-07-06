<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('titulo', 'Documento legal') — KIVOX</title>
    <meta name="robots" content="index,follow">
    <link rel="icon" type="image/png" href="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--cream:#FBFBF7;--cream2:#F4F4EC;--ink:#0D120E;--green:#0EA26B;--green-d:#0B8659;--forest:#07301F;--lime:#C6F45F;--gray:#6C7A70;--line:#E4E6DC}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--cream);color:var(--ink);line-height:1.6;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        .lg-header{background:rgba(251,251,247,.9);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}
        .lg-header .in{max-width:900px;margin:0 auto;padding:16px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .lg-logo img{height:38px;width:auto;display:block}
        .lg-nav{display:flex;gap:22px;font-size:14px;font-weight:700;color:var(--gray)}
        .lg-nav a{transition:.2s}.lg-nav a:hover{color:var(--green-d)}
        @media(max-width:560px){.lg-nav{display:none}}
        main{max-width:900px;margin:0 auto;padding:44px 22px 20px}
        .card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 44px rgba(13,18,14,.06);padding:44px 40px}
        @media(max-width:560px){.card{padding:30px 22px;border-radius:18px}}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--green-d);background:#EAF7F0;border:1px solid #cdeeda;border-radius:99px;padding:7px 14px;margin-bottom:18px}
        h1{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(28px,5vw,40px);letter-spacing:-.02em;line-height:1.1;color:var(--ink)}
        .updated{font-size:13.5px;color:var(--gray);font-weight:600;margin-top:12px;padding-bottom:22px;margin-bottom:26px;border-bottom:1px solid var(--line)}
        .legal h2{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700;color:var(--ink);letter-spacing:-.01em;margin-top:2.4rem;margin-bottom:.9rem}
        .legal h3{font-size:1.08rem;font-weight:700;color:var(--ink);margin-top:1.5rem;margin-bottom:.5rem}
        .legal p{color:#3d4a42;line-height:1.75;margin-bottom:1rem}
        .legal ul{list-style:none;padding-left:0;margin-bottom:1rem}
        .legal li{position:relative;padding-left:26px;line-height:1.7;margin-bottom:.55rem;color:#3d4a42}
        .legal li::before{content:'';position:absolute;left:4px;top:10px;width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 0 3px rgba(14,162,107,.14)}
        .legal a{color:var(--green-d);font-weight:700;text-decoration:underline;text-underline-offset:2px}
        .legal a:hover{color:var(--green)}
        .legal strong{font-weight:800;color:var(--ink)}
        .lg-footer{max-width:900px;margin:0 auto;padding:34px 22px 48px;text-align:center;font-size:13px;color:var(--gray);line-height:1.8}
        .lg-footer a{color:var(--green-d);font-weight:700}
        .lg-footer a:hover{color:var(--green)}
    </style>
</head>
<body>
    <header class="lg-header">
        <div class="in">
            <a class="lg-logo" href="/" aria-label="KIVOX"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" onerror="this.outerHTML='<b style=&quot;font-family:Space Grotesk;font-size:22px&quot;>KIVOX</b>'"></a>
            <nav class="lg-nav">
                <a href="{{ route('legal.privacidad') }}">Privacidad</a>
                <a href="{{ route('legal.terminos') }}">Términos</a>
                <a href="{{ route('legal.eliminar-datos') }}">Eliminar datos</a>
                <a href="/">Volver a KIVOX</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="card">
            <span class="eyebrow">Documento legal</span>
            <h1>@yield('titulo', 'Documento legal')</h1>
            <p class="updated">Última actualización: @yield('actualizado', '25 de mayo de 2026')</p>
            <div class="legal">
                @yield('contenido')
            </div>
        </div>
    </main>

    <footer class="lg-footer">
        <p>© {{ date('Y') }} <strong style="color:var(--ink)">KIVOX</strong> · Un producto de
            <a href="https://portafolio.tecnobyte360.com/" target="_blank" rel="noopener">TecnoByte360</a> · Hecho en Colombia 🇨🇴</p>
        <p style="margin-top:6px">Contacto: <a href="mailto:comercial@tecnobyte360.com">comercial@tecnobyte360.com</a></p>
    </footer>
</body>
</html>
