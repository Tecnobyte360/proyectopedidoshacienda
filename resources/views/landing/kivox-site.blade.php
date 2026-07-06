<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>KIVOX — Tu empresa entera, respondiendo en segundos</title>
<meta name="description" content="KIVOX conecta WhatsApp, Instagram, Facebook, tu web y tu ERP en una sola plataforma con inteligencia artificial. Vende más, atiende mejor y automatiza procesos. Integrado con SAP Business One, HGI y cualquier ERP o API a medida.">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://kivox.co">
<meta property="og:type" content="website">
<meta property="og:site_name" content="KIVOX">
<meta property="og:title" content="KIVOX — Plataforma omnicanal empresarial con IA">
<meta property="og:description" content="Tu empresa entera, respondiendo en segundos. Canales + IA + ERP en una sola plataforma.">
<meta property="og:url" content="https://kivox.co">
<meta property="og:image" content="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#FBFBF7">
<link rel="icon" type="image/png" href="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png">
@verbatim
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"SoftwareApplication","name":"KIVOX","applicationCategory":"BusinessApplication","operatingSystem":"Web","description":"Plataforma omnicanal empresarial con inteligencia artificial. Conecta WhatsApp, Instagram, Facebook, web y ERP (SAP Business One, HGI y API a medida).","url":"https://kivox.co","author":{"@type":"Organization","name":"TecnoByte360","email":"comercial@tecnobyte360.com"},"offers":{"@type":"Offer","priceCurrency":"COP","price":"0","description":"Demo gratuita"}}
</script>
@endverbatim
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
@verbatim
<style>
:root{
  --cream:#FBFBF7;--cream2:#F4F4EC;--ink:#0D120E;--ink2:#2A332C;
  --green:#0EA26B;--green-d:#0B8659;--forest:#07301F;--forest2:#0A3B27;
  --lime:#C6F45F;--lime-d:#A7E03A;
  --gray:#6C7A70;--line:#E4E6DC;--line-f:rgba(198,244,95,.14);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-font-smoothing:antialiased}
html{scroll-behavior:smooth;overflow-x:hidden;max-width:100%}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--cream);color:var(--ink);letter-spacing:-.002em;overflow-x:hidden;max-width:100%;width:100%;position:relative}
a{color:inherit;text-decoration:none}img{max-width:100%}
.disp{font-family:'Space Grotesk',sans-serif;font-weight:700;letter-spacing:-.02em}
section{position:relative;padding:100px 24px}
.wrap{max-width:1200px;margin:0 auto;position:relative;z-index:2}
h2{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(34px,5vw,62px);line-height:1.06;letter-spacing:-.022em}
.lead{font-size:clamp(16px,1.7vw,19px);line-height:1.75;color:var(--ink2);max-width:620px;font-weight:500}
.num-tag{font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:13px;letter-spacing:.3em;color:var(--green);display:flex;align-items:center;gap:14px;margin-bottom:26px;text-transform:uppercase}
.num-tag::after{content:'';flex:0 0 60px;height:1px;background:var(--green)}
#bar{position:fixed;top:0;left:0;height:3px;width:0;z-index:300;background:var(--lime)}
/* botones */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:11px;font-weight:800;font-size:15px;border-radius:999px;padding:16px 32px;transition:.25s;cursor:pointer;border:none;font-family:'Plus Jakarta Sans',sans-serif}
.btn .arr{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:11px;transition:.25s}
.btn-ink{background:var(--ink);color:var(--cream)}
.btn-ink .arr{background:var(--lime);color:var(--ink)}
.btn-ink:hover{background:var(--forest);transform:translateY(-2px)}
.btn-ink:hover .arr{transform:rotate(45deg)}
.btn-lime{background:var(--lime);color:var(--ink)}
.btn-lime .arr{background:var(--ink);color:var(--lime)}
.btn-lime:hover{background:var(--lime-d);transform:translateY(-2px)}
.btn-lime:hover .arr{transform:rotate(45deg)}
.btn-line{background:transparent;color:var(--ink);border:1.5px solid #C9CDBE}
.btn-line:hover{border-color:var(--ink);transform:translateY(-2px)}
.btn-sm{padding:11px 22px;font-size:13.5px}
/* header */
header{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(251,251,247,.85);backdrop-filter:blur(14px);border-bottom:1px solid var(--line);transition:.3s}
header::after{content:'';position:absolute;left:0;bottom:0;height:2px;width:100%;background:linear-gradient(90deg,transparent,var(--green) 22%,var(--lime) 30%,var(--green) 38%,transparent 60%);background-size:200% 100%;opacity:.9;animation:navGlow 5s linear infinite}
@keyframes navGlow{0%{background-position:120% 0}100%{background-position:-80% 0}}
.nav{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:15px 24px;position:relative}
.logo{display:flex;align-items:center;gap:10px;font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:19px;letter-spacing:-.02em;position:relative}
.logo::before{content:'';position:absolute;left:-16px;top:50%;transform:translateY(-50%);width:96px;height:96px;border-radius:50%;background:radial-gradient(circle,rgba(14,162,107,.22),rgba(198,244,95,.1) 45%,transparent 70%);z-index:-1;animation:logoGlow 3.4s ease-in-out infinite}
@keyframes logoGlow{0%,100%{opacity:.55;transform:translateY(-50%) scale(1)}50%{opacity:1;transform:translateY(-50%) scale(1.12)}}
.logo img{height:32px;width:auto}
.nav-links{display:flex;gap:30px;font-size:14px;font-weight:600;color:var(--gray)}
.nav-links a{transition:.2s}
.nav-links a:hover{color:var(--ink)}
@media(max-width:940px){.nav-links{display:none}}
.nav-item{position:relative;display:flex;align-items:center}
.nav-trig{cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;color:var(--gray-d);font-weight:600}
.nav-trig i{font-size:9px;transition:.25s}
.nav-item:hover .nav-trig{color:var(--ink)}
.nav-item:hover .nav-trig i{transform:rotate(180deg)}
.nav-drop{position:absolute;top:100%;left:50%;transform:translateX(-50%) translateY(10px);padding-top:16px;opacity:0;visibility:hidden;transition:.22s;z-index:120}
.nav-item:hover .nav-drop{opacity:1;visibility:visible;transform:translateX(-50%) translateY(0)}
.nav-drop .dd{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 22px 46px rgba(13,18,14,.14);padding:8px;min-width:244px}
.nav-drop .dh{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--green-d);padding:9px 12px 6px;display:flex;align-items:center;gap:7px}
.nav-drop a{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:9px;font-size:14px;font-weight:600;color:var(--ink2);white-space:nowrap}
.nav-drop a:hover{background:var(--cream2);color:var(--green-d)}
.nav-drop a i{width:20px;text-align:center;color:var(--green);font-size:14px}
/* mobile menu grupos */
.mnav .mg{font-size:10.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--green-d);padding:16px 6px 6px;border:none}
.burger{display:none;width:42px;height:42px;border-radius:50%;border:1.5px solid #C9CDBE;background:transparent;color:var(--ink);font-size:15px;cursor:pointer}
@media(max-width:940px){.burger{display:grid;place-items:center}}
.mnav{display:none;position:fixed;top:63px;left:0;right:0;z-index:99;background:var(--cream);border-bottom:1px solid var(--line);padding:12px 24px 20px;box-shadow:0 24px 44px rgba(13,18,14,.07)}
.mnav.open{display:block}
.mnav a{display:block;font-weight:700;font-size:16px;padding:13px 4px;border-bottom:1px solid var(--line)}
.mnav a:last-child{border:none;color:var(--green-d)}
/* hero */
.hero{padding:124px 24px 20px;overflow:hidden}
@media(max-width:960px){.hero{padding:120px 24px 30px}}
.hero .hgrid{display:grid;grid-template-columns:1.1fr .9fr;gap:44px;align-items:center}
.hero .hgrid>*{min-width:0}
.phone-zone{max-width:100%}
@media(max-width:960px){.hero .hgrid{grid-template-columns:1fr}}
.hero h1{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(33px,4.3vw,58px);line-height:1.08;letter-spacing:-.018em}
.hero h1 .hl{position:relative;display:inline-block;color:var(--green-d)}
.hero h1 .hl svg{position:absolute;left:0;bottom:-6px;width:100%;height:14px}
.hero h1 .hl svg path{stroke:var(--lime-d);stroke-width:5;fill:none;stroke-linecap:round;stroke-dasharray:600;stroke-dashoffset:600;animation:draw 1.2s .6s forwards}
@keyframes draw{to{stroke-dashoffset:0}}
.hero .lead{margin-top:28px}
.hctas{display:flex;gap:14px;margin-top:38px;flex-wrap:wrap;align-items:center}
.hero .chips{display:flex;gap:10px;flex-wrap:wrap;margin-top:34px}
.hero .chip{font-size:12.5px;font-weight:700;color:var(--ink2);background:#fff;border:1px solid var(--line);border-radius:999px;padding:9px 16px;display:inline-flex;gap:8px;align-items:center}
.hero .chip i{color:var(--green)}
/* badge circular girando */
.spin-badge{position:absolute;top:-26px;left:-2%;width:104px;height:104px;z-index:5;display:grid;place-items:center}
@media(max-width:960px){.spin-badge{display:none}}
.spin-badge svg{position:absolute;inset:0;animation:spin 14s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.spin-badge .c{width:52px;height:52px;border-radius:50%;background:var(--lime);display:grid;place-items:center;font-size:20px;color:var(--ink)}
/* phone */
.phone-zone{position:relative;display:flex;justify-content:center;align-items:center;min-height:600px}
@media(max-width:960px){.phone-zone{min-height:auto;margin-top:26px}}
/* ===== escenario 3D premium ===== */
.stage-glow{position:absolute;inset:-6% -8%;z-index:0;pointer-events:none}
.stage-glow .rays{position:absolute;left:50%;top:47%;width:760px;height:760px;transform:translate(-50%,-50%);background:conic-gradient(from 0deg,transparent 0 6deg,rgba(198,244,95,.28) 7deg 9deg,transparent 10deg 18deg,rgba(14,162,107,.20) 19deg 21deg,transparent 22deg 29deg,rgba(198,244,95,.22) 30deg 32deg,transparent 33deg 41deg,rgba(14,162,107,.16) 42deg 44deg,transparent 45deg);border-radius:50%;-webkit-mask:radial-gradient(circle,transparent 20%,#000 30%,#000 60%,transparent 74%);mask:radial-gradient(circle,transparent 20%,#000 30%,#000 60%,transparent 74%);animation:spin 46s linear infinite}
.stage-glow .halo{position:absolute;left:50%;top:47%;width:560px;height:560px;transform:translate(-50%,-50%);background:radial-gradient(circle,rgba(30,201,133,.5),rgba(14,162,107,.24) 40%,rgba(198,244,95,.10) 60%,transparent 72%);filter:blur(3px);animation:haloPulse 5s ease-in-out infinite}
@keyframes haloPulse{50%{opacity:.75;transform:translate(-50%,-50%) scale(1.05)}}
@media(max-width:600px){.stage-glow .rays{width:460px;height:460px}.stage-glow .halo{width:320px;height:320px}}
/* pódium glossy */
.podium{position:absolute;left:50%;bottom:1%;width:380px;height:130px;transform:translateX(-50%);z-index:2;pointer-events:none}
.podium .disc{position:absolute;left:50%;bottom:10px;transform:translateX(-50%);width:340px;height:96px;border-radius:50%;background:radial-gradient(ellipse at 50% 32%,#2fe098,#0fa869 48%,#0a6e49);box-shadow:0 40px 80px rgba(15,168,105,.6),0 0 60px rgba(47,224,152,.45),inset 0 9px 24px rgba(255,255,255,.4),inset 0 -12px 22px rgba(6,60,40,.45)}
.podium .ring{position:absolute;left:50%;bottom:-4px;transform:translateX(-50%);width:404px;height:118px;border-radius:50%;border:2px solid rgba(198,244,95,.7);box-shadow:0 0 50px rgba(198,244,95,.5);animation:ringGlow 4s ease-in-out infinite}
@keyframes ringGlow{50%{opacity:.45;transform:translateX(-50%) scale(1.04)}}
@media(max-width:600px){.podium{width:260px;bottom:-4%}.podium .disc{width:240px;height:64px}.podium .ring{width:280px;height:80px}}
/* chips flotantes de beneficio */
.fchip{position:absolute;z-index:6;display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.92);backdrop-filter:blur(6px);border:1px solid var(--line);border-radius:14px;padding:10px 14px;font-size:12.5px;font-weight:800;color:var(--ink);box-shadow:0 18px 40px rgba(13,18,14,.14);max-width:186px;line-height:1.25}
.fchip i{width:26px;height:26px;flex:none;border-radius:8px;background:#EAF7F0;color:var(--green-d);display:grid;place-items:center;font-size:12px}
.fchip.fc1{top:20%;right:-3%;animation:floaty 5.5s ease-in-out infinite}
.fchip.fc2{top:45%;right:-6%;animation:floaty 6.5s ease-in-out .6s infinite}
.fchip.fc3{top:69%;right:-2%;animation:floaty 6s ease-in-out 1.2s infinite}
@keyframes floaty{50%{transform:translateY(-11px)}}
@media(max-width:1180px){.fchip{max-width:160px;font-size:11.5px}.fchip.fc1{right:-6%}.fchip.fc2{right:-9%}.fchip.fc3{right:-6%}}
@media(max-width:960px){.fchip.fc1{top:8%;right:0}.fchip.fc2{top:44%;right:-2%}.fchip.fc3{top:80%;right:2%}}
@media(max-width:600px){.fchip{display:none}}
/* spark del titular */
.hero h1 .spark{display:inline-block;color:var(--lime-d);margin-left:6px;font-size:.62em;transform:translateY(-.18em);filter:drop-shadow(0 2px 6px rgba(198,244,95,.6));animation:sparkle 2.4s ease-in-out infinite}
@keyframes sparkle{0%,100%{opacity:1;transform:translateY(-.18em) scale(1)}50%{opacity:.55;transform:translateY(-.18em) scale(.82)}}
/* subtítulo, features y trust */
.hsub{margin-top:22px;font-size:clamp(16px,1.4vw,19px);color:var(--gray2,#546055);line-height:1.55;max-width:520px}
.hsub b{color:var(--green-d);font-weight:800}
.hfeats{display:grid;grid-template-columns:repeat(4,auto);gap:22px;margin-top:30px}
@media(max-width:520px){.hfeats{grid-template-columns:repeat(2,1fr);gap:16px}}
.hf{display:flex;flex-direction:column;gap:9px;max-width:120px}
.hf-ic{width:44px;height:44px;border-radius:13px;background:linear-gradient(135deg,#eafaf0,#d6f6e4);border:1px solid #cdeeda;color:var(--green-d);display:grid;place-items:center;font-size:17px;box-shadow:0 8px 18px rgba(14,162,107,.14)}
.hf b{font-size:12.5px;font-weight:800;color:var(--ink);line-height:1.28}
.htrust{display:inline-flex;align-items:center;gap:9px;margin-top:26px;font-size:13.5px;font-weight:700;color:var(--gray2,#546055)}
.htrust i{color:var(--green)}
.btn-grad{background:linear-gradient(135deg,#12B76A,#0B8659)!important;color:#fff!important;border:none!important;box-shadow:0 14px 30px rgba(11,134,89,.32)}
.btn-grad:hover{filter:brightness(1.05);transform:translateY(-2px)}
.phone{width:min(330px,86vw);background:var(--ink);border-radius:44px;padding:12px;box-shadow:0 60px 110px rgba(10,70,46,.4),0 20px 50px rgba(13,18,14,.28);position:relative;z-index:3;transform:rotate(-1deg) translateY(-28px)}
@media(max-width:960px){.phone{transform:rotate(-1deg)}}
.verif{display:inline-flex;align-items:center;justify-content:center;width:15px;height:15px;border-radius:50%;background:#25D366;color:#fff;font-size:8px;vertical-align:middle;margin-left:3px}
.meta-badge{position:absolute;top:2%;right:-4%;z-index:7;display:flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:10px 15px;box-shadow:0 20px 44px rgba(13,18,14,.14);transform:rotate(3deg)}
.meta-badge i{font-size:24px;color:#0081FB}
.meta-badge span{font-size:11px;font-weight:600;color:var(--gray);line-height:1.3}
.meta-badge b{color:var(--ink);font-size:12.5px;font-weight:800}
@media(max-width:960px){.meta-badge{right:2%;top:-6px}}
.phone .screen{background:#EDE8DF;border-radius:34px;overflow:hidden}
.phone .ph-top{display:flex;align-items:center;gap:10px;background:var(--green-d);color:#fff;padding:14px 16px 12px}
.phone .ph-top .av{width:36px;height:36px;border-radius:50%;background:#fff;display:grid;place-items:center;font-size:14px;overflow:hidden;flex-shrink:0}
.phone .ph-top .av img{width:100%;height:100%;object-fit:contain;padding:4px}
.phone .ph-top b{font-size:13px;display:block}
.phone .ph-top small{font-size:10px;opacity:.85}
.ph-chat{padding:16px 13px;height:360px;overflow-y:auto;scroll-behavior:smooth;scrollbar-width:none;display:flex;flex-direction:column;gap:9px;background-image:radial-gradient(rgba(13,18,14,.045) 1px,transparent 1px);background-size:18px 18px}
.ph-chat::-webkit-scrollbar{display:none}
.pm,.ptag,.pay-card,.ptyping{flex-shrink:0}
.ph-input{display:flex;align-items:center;gap:11px;padding:9px 13px;background:#EDE8DF;border-top:1px solid rgba(13,18,14,.06);color:#7c857e;font-size:15px}
.ph-input .ph-field{flex:1;background:#fff;border-radius:99px;padding:8px 14px;font-size:11.5px;color:#9aa39c}
.ph-input i{cursor:default}
.ph-input .ph-mic{width:32px;height:32px;border-radius:50%;background:var(--green-d);color:#fff;display:grid;place-items:center;font-size:13px;flex-shrink:0}
.ph-powered{display:flex;align-items:center;justify-content:center;gap:7px;background:var(--ink);color:#a8b4ac;font-size:9.5px;font-weight:600;padding:7px;letter-spacing:.02em}
.ph-powered img{height:13px;width:auto}
.ph-powered b{color:var(--lime);font-weight:800}
.pm{max-width:84%;padding:9px 12px;border-radius:13px;font-size:12.5px;line-height:1.5;opacity:0;transform:translateY(10px);transition:.4s ease;box-shadow:0 2px 5px rgba(0,0,0,.06)}
.pm.show{opacity:1;transform:none}
.pm.in{background:#fff;align-self:flex-start;border-bottom-left-radius:4px}
.pm.out{background:#D6F5C6;align-self:flex-end;border-bottom-right-radius:4px}
.pm .t{display:block;font-size:9px;color:rgba(13,18,14,.45);margin-top:3px;text-align:right}
.ptag{align-self:center;font-size:9.5px;font-weight:700;color:var(--green-d);background:#fff;border:1px solid var(--line);border-radius:99px;padding:4px 12px;opacity:0;transition:.4s;display:inline-flex;gap:6px;align-items:center}
.ptag.show{opacity:1}
.ptyping{display:inline-flex;gap:4px;padding:10px 13px;background:#fff;border-radius:13px;border-bottom-left-radius:4px;align-self:flex-start;opacity:0;transition:.3s}
.ptyping.show{opacity:1}
.pay-card{align-self:flex-end;max-width:90%;background:#fff;border:1px solid var(--line);border-radius:14px;padding:0;overflow:hidden;box-shadow:0 6px 16px rgba(13,18,14,.1);opacity:0;transform:translateY(10px);transition:.4s ease}
.pay-card.show{opacity:1;transform:none}
.pay-card .ph{background:var(--forest);color:#fff;padding:9px 13px;font-size:10.5px;font-weight:700;display:flex;align-items:center;gap:7px}
.pay-card .ph i{color:var(--lime)}
.pay-card .pb{padding:12px 13px}
.pay-card .amt{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:19px;color:var(--ink);letter-spacing:-.02em}
.pay-card .amt small{display:block;font-family:'Plus Jakarta Sans';font-size:9.5px;font-weight:600;color:#98a29e;letter-spacing:0}
.pay-card .pgo{margin-top:9px;background:var(--green-d);color:#fff;text-align:center;font-size:12px;font-weight:800;border-radius:9px;padding:9px}
.pay-card .plogos{margin-top:9px;font-size:9px;font-weight:700;color:#98a29e;letter-spacing:.05em;text-align:center;display:flex;gap:8px;justify-content:center;align-items:center}
.ptyping span{width:6px;height:6px;border-radius:50%;background:#9aa89e;animation:tp 1s infinite}
.ptyping span:nth-child(2){animation-delay:.15s}.ptyping span:nth-child(3){animation-delay:.3s}
@keyframes tp{0%,60%,100%{transform:none;opacity:.5}30%{transform:translateY(-4px);opacity:1}}
.phone-note{position:absolute;bottom:26px;left:-4%;background:#fff;border:1px solid var(--line);border-radius:14px;padding:13px 16px;font-size:12px;font-weight:700;box-shadow:0 20px 44px rgba(13,18,14,.12);z-index:3;transform:rotate(-4deg)}
.phone-note small{display:block;font-weight:600;color:var(--gray)}
.phone-note i{color:var(--green)}
@media(max-width:960px){.phone-note{left:0}}
/* ticker */
.ticker{background:var(--ink);color:var(--cream);padding:18px 0;overflow:hidden;transform:rotate(-.7deg);width:100%;margin:16px 0 40px;position:relative}
@media(min-width:1500px){.ticker{transform:rotate(-.5deg)}}
.ticker .tk{display:flex;gap:44px;width:max-content;animation:mq 30s linear infinite;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:15px;letter-spacing:.02em}
@keyframes mq{to{transform:translateX(-50%)}}
.ticker .tk span{display:inline-flex;gap:12px;align-items:center;white-space:nowrap}
.ticker .tk .dotx{color:var(--lime)}
/* caos vs orden */
.split{display:grid;grid-template-columns:1fr 1fr;gap:0;overflow:hidden;margin-top:52px}
.split.full{width:100%;margin-left:calc(-50vw + 50%);margin-right:calc(-50vw + 50%);max-width:100vw;border-radius:0}
@media(max-width:880px){.split{grid-template-columns:1fr}}
.split .half{padding:64px clamp(24px,6vw,90px);min-height:440px;position:relative;display:flex;flex-direction:column;justify-content:center}
.split .half h3{margin-bottom:14px}
.split .antes{background:var(--cream2)}
.split .antes h3,.split .despues h3{font-family:'Space Grotesk',sans-serif;font-size:14px;letter-spacing:.24em;text-transform:uppercase;margin-bottom:26px;display:flex;align-items:center;gap:10px}
.split .antes h3{color:#B0442A}
.split .despues{background:var(--forest);color:#EAF6EE;border-left:1.5px solid var(--ink)}
@media(max-width:880px){.split .despues{border-left:none;border-top:1.5px solid var(--ink)}}
.split .despues h3{color:var(--lime)}
/* mapa de relaciones (caos) */
.relmap{position:relative;height:340px;margin:8px auto 0;max-width:540px;width:100%}
.relmap svg{position:absolute;inset:0;width:100%;height:100%;z-index:1}
.relmap .rl{stroke:#CE7457;stroke-width:1.4;fill:none;stroke-dasharray:4 5;vector-effect:non-scaling-stroke;opacity:.65;animation:relflow 1.4s linear infinite}
@keyframes relflow{to{stroke-dashoffset:-18}}
.relmap .node{position:absolute;transform:translate(-50%,-50%);z-index:2;display:flex;flex-direction:column;align-items:center;gap:6px;width:96px;text-align:center}
.relmap .node .nd{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;font-size:19px;background:#fff;border:1px solid #E7E2D6;box-shadow:0 8px 20px rgba(13,18,14,.09);transition:.3s}
.relmap .node:hover .nd{transform:scale(1.08)}
.relmap .node b{font-size:11px;font-weight:800;color:#5F5A50;line-height:1.2}
.relmap .node small{font-size:9.5px;font-weight:600;color:#a99f8f;display:block;margin-top:1px}
.relmap .node .nd.wa{color:#1FA855}.relmap .node .nd.ig{color:#C0396B}.relmap .node .nd.fb{color:#2160C4}.relmap .node .nd.bk{color:#B0642A}.relmap .node .nd.db{color:#8A6D2A}.relmap .node .nd.cl{color:#6b6357}
.relmap .warn{position:absolute;left:50%;bottom:2px;transform:translateX(-50%);z-index:3;font-size:11px;font-weight:800;color:#B0442A;background:#FBEAE4;border-radius:99px;padding:6px 14px;white-space:nowrap;letter-spacing:.02em}
/* mapa de relaciones (orden / hub) */
.hubmap{position:relative;height:340px;margin:8px auto 0;max-width:540px;width:100%}
.hubmap svg{position:absolute;inset:0;width:100%;height:100%;z-index:1}
.hubmap .hl{stroke:var(--lime);stroke-width:1.6;fill:none;stroke-dasharray:4 5;vector-effect:non-scaling-stroke;opacity:.8;animation:hubflow 1.3s linear infinite}
@keyframes hubflow{to{stroke-dashoffset:-18}}
.hubmap .node{position:absolute;transform:translate(-50%,-50%);z-index:2;display:flex;flex-direction:column;align-items:center;gap:5px;width:96px;text-align:center}
.hubmap .node .nd{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:18px;background:rgba(255,255,255,.08);border:1px solid rgba(198,244,95,.25);color:#EAF6EE;transition:.3s}
.hubmap .node:hover .nd{background:rgba(198,244,95,.16);transform:scale(1.08)}
.hubmap .node b{font-size:11px;font-weight:800;color:#EAF6EE;line-height:1.2}
.hubmap .node small{font-size:9px;font-weight:700;color:var(--lime);display:block}
.hubmap .hub{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:3;width:82px;height:82px;border-radius:22px;background:linear-gradient(135deg,var(--lime),#8FCE2A);display:grid;place-items:center;box-shadow:0 0 44px rgba(198,244,95,.45);animation:hubpulse 2.6s ease-in-out infinite}
.hubmap .hub img{width:48px;height:48px;object-fit:contain}
@keyframes hubpulse{0%,100%{box-shadow:0 0 44px rgba(198,244,95,.4)}50%{box-shadow:0 0 66px rgba(198,244,95,.65)}}
/* pulsos de datos viajando hacia el hub */
.hubmap .pulse{position:absolute;width:9px;height:9px;border-radius:50%;background:var(--lime);box-shadow:0 0 12px var(--lime),0 0 4px #fff;transform:translate(-50%,-50%);z-index:1;opacity:0;animation:flowIn 2.6s ease-in infinite}
@keyframes flowIn{0%{opacity:0}12%{opacity:1}80%{opacity:1}100%{left:50%;top:50%;opacity:0}}
/* flotar suave de los íconos */
.hubmap .node .nd{animation:ndFloat 3.8s ease-in-out infinite}
@keyframes ndFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
.hubmap .node:nth-of-type(2) .nd{animation-delay:.3s}
.hubmap .node:nth-of-type(3) .nd{animation-delay:.6s}
.hubmap .node:nth-of-type(4) .nd{animation-delay:.9s}
.hubmap .node:nth-of-type(5) .nd{animation-delay:1.2s}
.hubmap .node:nth-of-type(6) .nd{animation-delay:1.5s}
.hubmap .node:nth-of-type(7) .nd{animation-delay:1.8s}
/* anillo de brillo girando detrás del hub */
.hubmap .hub::after{content:'';position:absolute;inset:-9px;border-radius:26px;background:conic-gradient(from 0deg,transparent 55%,rgba(198,244,95,.55),transparent 80%);z-index:-1;animation:hubspin 4.5s linear infinite}
@keyframes hubspin{to{transform:rotate(360deg)}}
.mess{position:relative;height:250px}
.mess .m{position:absolute;background:#fff;border:1px solid #DDDCD0;border-radius:11px;padding:10px 14px;font-size:12px;font-weight:700;color:var(--ink2);box-shadow:0 10px 24px rgba(13,18,14,.08)}
.mess .m small{display:block;font-weight:600;color:#98a29a;font-size:10px}
.order{display:grid;gap:9px}
.order .o{display:flex;align-items:center;gap:11px;background:rgba(255,255,255,.05);border:1px solid rgba(198,244,95,.16);border-radius:12px;padding:12px 15px;font-size:13px;font-weight:600}
.order .o i{color:var(--lime);width:16px;text-align:center}
.order .o .okx{margin-left:auto;font-size:10px;font-weight:800;color:var(--lime);background:rgba(198,244,95,.1);border-radius:99px;padding:3px 10px}
/* filas acordeón */
.rows{margin-top:60px;border-top:1.5px solid var(--ink)}
.row{border-bottom:1.5px solid var(--ink);cursor:pointer}
.row .rh{display:grid;grid-template-columns:70px 1fr auto;gap:20px;align-items:center;padding:30px 8px;transition:.25s}
@media(max-width:700px){.row .rh{grid-template-columns:44px 1fr auto;padding:24px 4px}}
.row:hover .rh{background:rgba(14,162,107,.04)}
.row .rn{font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:15px;color:var(--gray)}
.row .rt{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(21px,3.2vw,34px);letter-spacing:-.03em;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.row .pill{font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;border-radius:99px;padding:5px 12px}
.row .plus{width:40px;height:40px;border-radius:50%;border:1.5px solid var(--ink);display:grid;place-items:center;font-size:15px;transition:.3s;flex-shrink:0}
.row.open .plus{background:var(--ink);color:var(--lime);transform:rotate(45deg)}
.row .rb{max-height:0;overflow:hidden;transition:max-height .45s cubic-bezier(.2,.8,.2,1)}
.row .rbi{display:grid;grid-template-columns:70px 1fr;gap:20px;padding:0 8px 34px}
@media(max-width:700px){.row .rbi{grid-template-columns:1fr;padding:0 4px 28px}}
.row .rb p{font-size:15.5px;line-height:1.75;color:var(--ink2);max-width:560px;font-weight:500}
.row .rb .feats{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}
.row .rb .feats span{font-size:12px;font-weight:700;background:#fff;border:1px solid var(--line);border-radius:99px;padding:7px 14px}
.row .rb .feats span i{color:var(--green);margin-right:5px}
/* IA forest */
.ia{background:var(--forest);color:#EAF6EE;border-radius:32px;max-width:1240px;margin:0 auto;padding:90px 24px;overflow:hidden;position:relative}
.ia::before{content:'';position:absolute;top:-120px;right:-100px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(198,244,95,.14),transparent 70%)}
.ia .num-tag{color:var(--lime)}
.ia .num-tag::after{background:var(--lime)}
.ia h2{color:#fff}
.ia .lead{color:#B9CDBF}
.ia-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center}
@media(max-width:920px){.ia-grid{grid-template-columns:1fr}}
.ia-stats{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:480px){.ia-stats{grid-template-columns:1fr}}
.ia-st{background:rgba(255,255,255,.045);border:1px solid var(--line-f);border-radius:18px;padding:26px 22px;transition:.25s}
.ia-st:hover{transform:translateY(-4px);border-color:rgba(198,244,95,.4)}
.ia-st b{font-family:'Space Grotesk',sans-serif;font-size:clamp(30px,3.4vw,44px);font-weight:700;letter-spacing:-.03em;color:var(--lime);display:block}
.ia-st span{font-size:13px;color:#B9CDBF;font-weight:600;line-height:1.5;display:block;margin-top:6px}
.ia-feats{display:grid;gap:11px;margin-top:30px}
.ia-feats .f{display:flex;gap:13px;font-size:15px;font-weight:600;align-items:flex-start;line-height:1.55}
.ia-feats .f i{color:var(--lime);margin-top:3px}
/* filmstrip */
.strip-wrap{overflow-x:auto;scroll-snap-type:x mandatory;display:flex;align-items:center;justify-content:flex-start;gap:18px;padding:52px 24px 20px;max-width:1240px;margin:0 auto;scrollbar-width:none}
.strip-wrap::-webkit-scrollbar{display:none}
.clip{flex:0 0 auto;height:min(380px,52vh);scroll-snap-align:center;border-radius:22px;overflow:hidden;position:relative;border:1.5px solid var(--ink);background:var(--ink);box-shadow:0 24px 50px rgba(13,18,14,.16)}
.clip video{height:100%;width:auto;aspect-ratio:16/9;object-fit:cover;display:block}
.clip.v video{aspect-ratio:9/16}
.clip .cap{position:absolute;left:14px;bottom:14px;right:14px;display:flex;justify-content:space-between;align-items:center;background:rgba(13,18,14,.78);backdrop-filter:blur(8px);color:#fff;font-size:12.5px;font-weight:700;border-radius:12px;padding:10px 15px}
.clip .cap i{color:var(--lime)}
.strip-hint{text-align:center;font-size:12.5px;color:var(--gray);font-weight:600;margin-top:8px}
/* así se ve por dentro */
.shots{margin-top:44px}
.shot-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}
.shot-tab{padding:11px 18px;border-radius:999px;border:1.5px solid var(--line);background:#fff;font-family:inherit;font-size:13.5px;font-weight:700;color:var(--gray-d);cursor:pointer;transition:.2s;display:inline-flex;gap:8px;align-items:center;white-space:nowrap}
.shot-tab i{color:var(--green)}
.shot-tab:hover{border-color:var(--green);color:var(--green-d)}
.shot-tab.on{background:var(--ink);border-color:var(--ink);color:#fff}
.shot-tab.on i{color:var(--lime)}
.shot-frame{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:var(--shadow-l)}
.shot-bar{display:flex;align-items:center;gap:7px;padding:12px 16px;background:var(--cream2);border-bottom:1px solid var(--line)}
.shot-bar i{width:11px;height:11px;border-radius:50%;display:inline-block}
.shot-url{margin-left:10px;font-size:12px;color:var(--gray-d);background:#fff;border:1px solid var(--line);border-radius:8px;padding:5px 16px;font-weight:600}
.shot-view{position:relative;aspect-ratio:16/9;background:linear-gradient(135deg,#EAF6EF,#F4F4EC);overflow:hidden}
.shot-ph{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:11px;color:var(--gray);z-index:1;text-align:center;padding:20px}
.shot-ph i{font-size:36px;color:var(--green)}
.shot-ph b{font-size:16px;font-weight:800;color:var(--ink2)}
.shot-ph small{font-size:12px}
.shot-view img{position:absolute;inset:0;z-index:2;width:100%;height:100%;object-fit:cover;object-position:top center;display:block}
/* maquetas de pantalla en vivo */
.mk{position:absolute;inset:0;display:none;font-family:'Plus Jakarta Sans',sans-serif;overflow:hidden}
.mk.on{display:block;animation:mkfade .4s ease}
@keyframes mkfade{from{opacity:0}to{opacity:1}}
/* login */
.mklogin{display:grid;grid-template-columns:1fr 1fr;height:100%}
.mklogin .l{background:radial-gradient(circle at 40% 45%,#12B76A,#07301F);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:clamp(8px,2vw,18px);color:#fff}
.mklogin .l .lg{width:clamp(54px,9vw,120px);height:clamp(54px,9vw,120px);border-radius:50%;background:rgba(255,255,255,.12);display:grid;place-items:center;font-family:'Space Grotesk';font-weight:700;font-size:clamp(24px,5vw,56px);border:1px solid rgba(255,255,255,.2)}
.mklogin .l .tg{font-size:clamp(7px,1.2vw,13px);letter-spacing:.22em;font-weight:800;opacity:.9}
.mklogin .r{background:#fff;display:flex;flex-direction:column;justify-content:center;padding:0 clamp(14px,5vw,70px);gap:clamp(6px,1.2vw,11px)}
.mklogin .r .lgm{width:clamp(30px,4vw,48px);height:clamp(30px,4vw,48px);border-radius:11px;background:linear-gradient(135deg,#12B76A,#07301F);display:grid;place-items:center;color:#fff;font-weight:800;margin:0 auto 2px;font-size:clamp(13px,2vw,20px)}
.mklogin .r h4{text-align:center;font-family:'Space Grotesk';font-size:clamp(13px,2.2vw,24px);font-weight:700;color:#0F172A}
.mklogin .r .sub{text-align:center;font-size:clamp(7px,1.2vw,12px);color:#94A3B8;margin-bottom:clamp(2px,1vw,8px)}
.mklogin .r .fld{display:flex;align-items:center;gap:9px;border:1px solid #E4EAF2;border-radius:10px;padding:clamp(8px,1.4vw,13px);font-size:clamp(7px,1.2vw,12px);color:#9aa3b0}
.mklogin .r .fld i{color:#12B76A}
.mklogin .r .sbtn{background:linear-gradient(135deg,#12B76A,#0B8659);color:#fff;text-align:center;border-radius:10px;padding:clamp(8px,1.4vw,13px);font-weight:800;font-size:clamp(8px,1.3vw,13px);margin-top:2px}
/* chat */
.mkchat{display:grid;grid-template-columns:26% 30% 1fr;height:100%;background:#F5F8FB}
.mkchat .cside{background:#fff;border-right:1px solid #E4EAF2;padding:clamp(6px,1.2vw,13px) clamp(4px,.8vw,9px);overflow:hidden}
.mkchat .cside .b{font-weight:800;font-size:clamp(7px,1.1vw,11px);color:#0F172A;margin-bottom:clamp(6px,1.4vw,13px);display:flex;gap:5px;align-items:center}
.mkchat .cside .b i{color:#12B76A}
.mkchat .cside .it{display:flex;gap:6px;align-items:center;padding:clamp(4px,.9vw,7px) clamp(4px,.8vw,8px);border-radius:6px;color:#55627A;font-weight:600;margin-bottom:2px;font-size:clamp(6.5px,1vw,10px)}
.mkchat .cside .it.on{background:#E7F8EE;color:#0B8659}
.mkchat .clist{background:#fff;border-right:1px solid #E4EAF2;padding:clamp(5px,1vw,10px) clamp(4px,.8vw,8px);overflow:hidden}
.mkchat .clist .c{display:flex;gap:7px;align-items:center;padding:clamp(4px,1vw,8px) clamp(3px,.6vw,6px);border-radius:8px}
.mkchat .clist .c.on{background:#F5F8FB}
.mkchat .clist .av{width:clamp(18px,2.6vw,28px);height:clamp(18px,2.6vw,28px);border-radius:50%;background:#25D366;color:#fff;display:grid;place-items:center;font-size:clamp(6px,1vw,9px);font-weight:800;flex-shrink:0}
.mkchat .clist .c b{font-size:clamp(7px,1.1vw,10px);color:#0F172A;display:block}
.mkchat .clist .c small{font-size:clamp(6px,.9vw,9px);color:#94A3B8}
.mkchat .cconv{padding:clamp(8px,1.6vw,16px);display:flex;flex-direction:column;gap:clamp(4px,1vw,8px);background-image:radial-gradient(rgba(13,18,14,.04) 1px,transparent 1px);background-size:16px 16px}
.mkchat .cconv .m{max-width:78%;padding:clamp(6px,1.1vw,10px) clamp(8px,1.3vw,12px);border-radius:11px;font-size:clamp(7px,1.1vw,11px);line-height:1.4}
.mkchat .cconv .m.in{background:#fff;align-self:flex-start;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.mkchat .cconv .m.out{background:#D6F5C6;align-self:flex-end}
.mkchat .cconv .tg{align-self:center;font-size:clamp(6px,.95vw,9px);color:#0B8659;background:#fff;border:1px solid #E4EAF2;border-radius:99px;padding:3px 9px;font-weight:700}
@media(max-width:600px){.mkchat{grid-template-columns:1fr}.mkchat .cside,.mkchat .clist{display:none}}
/* despachos */
.mkdesp{height:100%;position:relative;background:linear-gradient(160deg,#E7F4EE,#F5F8FB)}
.mkdesp .grid{position:absolute;inset:0;background-image:linear-gradient(rgba(14,162,107,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(14,162,107,.07) 1px,transparent 1px);background-size:clamp(28px,4vw,46px) clamp(28px,4vw,46px)}
.mkdesp .road{position:absolute;height:6px;background:rgba(148,163,184,.28);border-radius:6px}
.mkdesp .hd{position:absolute;top:clamp(8px,1.6vw,16px);left:clamp(8px,1.6vw,16px);background:#fff;border:1px solid #E4EAF2;border-radius:10px;padding:clamp(6px,1.2vw,10px) clamp(9px,1.6vw,15px);font-size:clamp(7px,1.1vw,11px);font-weight:800;color:#0F172A;box-shadow:0 6px 16px rgba(0,0,0,.08);display:flex;gap:8px;align-items:center;z-index:3}
.mkdesp .hd .live{font-size:clamp(6px,.9vw,8px);color:#0B8659;background:#E7F8EE;border-radius:99px;padding:2px 8px}
.mkdesp .hd .live::before{content:'●';color:#12B76A;margin-right:3px}
.mkdesp .pin{position:absolute;width:clamp(24px,3.6vw,38px);height:clamp(24px,3.6vw,38px);border-radius:50%;display:grid;place-items:center;font-size:clamp(11px,1.8vw,17px);color:#fff;transform:translate(-50%,-50%);z-index:2}
.mkdesp .pin.g{background:#12B76A;box-shadow:0 4px 12px rgba(18,183,106,.4)}
.mkdesp .pin.o{background:#F59E0B}
.mkdesp .pin.b{background:#3B82F6}
.mkdesp .zoom{position:absolute;right:clamp(8px,1.6vw,16px);bottom:clamp(8px,1.6vw,16px);background:#fff;border:1px solid #E4EAF2;border-radius:8px;display:grid;z-index:3;font-size:12px;color:#55627A;overflow:hidden}
.mkdesp .zoom span{padding:clamp(4px,.8vw,8px) clamp(7px,1.2vw,11px);text-align:center}
.mkdesp .zoom span:first-child{border-bottom:1px solid #E4EAF2}
/* departamentos */
.mkdep{height:100%;background:#F5F8FB;padding:clamp(10px,2vw,20px);overflow:hidden}
.mkdep .hd{font-weight:800;font-size:clamp(9px,1.5vw,14px);color:#0F172A;margin-bottom:clamp(8px,1.6vw,14px);display:flex;gap:8px;align-items:center}
.mkdep .hd i{color:#12B76A}
.mkdep .g{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(6px,1.2vw,11px)}
.mkdep .card{background:#fff;border:1px solid #E4EAF2;border-radius:10px;padding:clamp(8px,1.4vw,13px)}
.mkdep .card .t{font-weight:800;font-size:clamp(7px,1.1vw,11px);color:#0F172A;display:flex;gap:6px;align-items:center;margin-bottom:clamp(5px,1vw,8px)}
.mkdep .card .t .st{margin-left:auto;font-size:clamp(6px,.9vw,8px);color:#0B8659;font-weight:800}
.mkdep .card .kw{display:flex;flex-wrap:wrap;gap:4px}
.mkdep .card .kw span{font-size:clamp(5.5px,.9vw,8px);background:#F5F8FB;border:1px solid #E4EAF2;border-radius:5px;padding:2px 6px;color:#55627A;font-weight:600}
@media(max-width:600px){.mkdep .g{grid-template-columns:repeat(2,1fr)}.mkdep .card:nth-child(n+5){display:none}}
/* reportes */
.mkrep{height:100%;background:#F5F8FB;padding:clamp(10px,2vw,18px);overflow:hidden}
.mkrep .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(5px,1vw,9px);margin-bottom:clamp(7px,1.4vw,12px)}
.mkrep .kpi{background:#fff;border:1px solid #E4EAF2;border-radius:10px;padding:clamp(8px,1.4vw,13px)}
.mkrep .kpi.hero{background:linear-gradient(135deg,#12B76A,#0B8659);border:none}
.mkrep .kpi small{font-size:clamp(5.5px,.9vw,8px);font-weight:800;color:#94A3B8;text-transform:uppercase;letter-spacing:.03em;display:block}
.mkrep .kpi.hero small{color:rgba(255,255,255,.8)}
.mkrep .kpi b{font-size:clamp(11px,1.9vw,18px);color:#0F172A;display:block;letter-spacing:-.02em}
.mkrep .kpi.hero b{color:#fff}
.mkrep .row{display:grid;grid-template-columns:1.6fr 1fr;gap:clamp(5px,1vw,9px)}
.mkrep .panel{background:#fff;border:1px solid #E4EAF2;border-radius:10px;padding:clamp(9px,1.6vw,13px)}
.mkrep .panel .pt{font-size:clamp(7px,1.1vw,10px);font-weight:800;color:#0F172A;margin-bottom:clamp(7px,1.4vw,11px)}
.mkrep .bars{display:flex;align-items:flex-end;gap:clamp(3px,.7vw,6px);height:clamp(46px,8vw,84px)}
.mkrep .bars i{flex:1;border-radius:3px 3px 0 0;background:linear-gradient(180deg,#12B76A,rgba(18,183,106,.3))}
.mkrep .donut{width:clamp(60px,10vw,110px);height:clamp(60px,10vw,110px);border-radius:50%;margin:clamp(4px,1vw,10px) auto 0;background:conic-gradient(#12B76A 0 78%,#7C5CFF 78% 92%,#3B82F6 92% 100%);display:grid;place-items:center}
.mkrep .donut::after{content:'';width:62%;height:62%;border-radius:50%;background:#fff}
.shot-cap{margin-top:16px;text-align:center;font-size:13.5px;color:var(--gray-d);font-weight:600}
.shot-cap b{color:var(--green-d)}
/* 2 pilares */
.pil2{display:grid;grid-template-columns:1fr auto 1fr;gap:0;margin-top:52px;text-align:left;align-items:stretch}
@media(max-width:880px){.pil2{grid-template-columns:1fr}}
.pcard{border-radius:24px;padding:clamp(26px,3vw,42px);position:relative;overflow:hidden;transition:.28s}
.pcard.comm{background:#fff;border:1px solid var(--line);box-shadow:var(--shadow-l)}
.pcard.oper{background:radial-gradient(520px 420px at 25% 0%,#0B3B27,var(--forest) 65%);color:#EAF6EE}
.pcard:hover{transform:translateY(-6px)}
.pcard .pic{width:56px;height:56px;border-radius:15px;display:grid;place-items:center;font-size:24px;margin-bottom:16px}
.pcard.comm .pic{background:var(--cream2);color:var(--green-d)}
.pcard.oper .pic{background:rgba(198,244,95,.14);border:1px solid rgba(198,244,95,.3);color:var(--lime)}
.pcard .eb{font-size:11.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;display:block;margin-bottom:7px}
.pcard.comm .eb{color:var(--green-d)}
.pcard.oper .eb{color:var(--lime)}
.pcard h3{font-family:'Space Grotesk',sans-serif;font-size:clamp(20px,2.3vw,27px);font-weight:700;letter-spacing:-.02em;line-height:1.12}
.pcard.comm h3{color:var(--ink)}
.pcard.oper h3{color:#fff}
.pcard .tl{font-size:14.5px;font-weight:600;margin:13px 0 22px;line-height:1.55}
.pcard.comm .tl{color:var(--gray-d)}
.pcard.oper .tl{color:#B9CDBF}
.pcard ul{list-style:none;display:grid;gap:13px}
.pcard li{display:flex;gap:12px;font-size:14.5px;font-weight:600;line-height:1.45}
.pcard li i{margin-top:3px;flex-shrink:0;font-size:13px}
.pcard.comm li{color:var(--ink2)}.pcard.comm li i{color:var(--green)}
.pcard.oper li{color:#DCE5F1}.pcard.oper li i{color:var(--lime)}
.pil-mid{position:relative;display:grid;place-items:center;width:100px;z-index:3}
.pil-mid::before{content:'';position:absolute;top:50%;left:6px;right:6px;height:2px;transform:translateY(-50%);border-radius:2px;background:linear-gradient(90deg,rgba(14,162,107,.12),rgba(14,162,107,.5),rgba(14,162,107,.12));z-index:0}
.pil-mid .fpulse{position:absolute;top:50%;left:6px;width:9px;height:9px;border-radius:50%;background:var(--lime);box-shadow:0 0 12px var(--lime),0 0 4px #fff;transform:translate(-50%,-50%);z-index:1;animation:pilflow 2.2s linear infinite}
@keyframes pilflow{0%{left:6px;opacity:0}12%{opacity:1}88%{opacity:1}100%{left:calc(100% - 6px);opacity:0}}
.pil-mid .plus2{position:relative;z-index:2;width:54px;height:54px;border-radius:50%;background:var(--ink);color:var(--lime);display:grid;place-items:center;font-size:20px;box-shadow:0 12px 26px rgba(13,18,14,.22)}
@media(max-width:880px){.pil-mid{width:auto;padding:16px 0}
.pil-mid::before{left:50%;right:auto;top:6px;bottom:6px;width:2px;height:auto;transform:translateX(-50%);background:linear-gradient(180deg,rgba(14,162,107,.12),rgba(14,162,107,.5),rgba(14,162,107,.12))}
.pil-mid .fpulse{left:50%;top:6px;animation:pilflowV 2.2s linear infinite}}
@keyframes pilflowV{0%{top:6px;opacity:0}12%{opacity:1}88%{opacity:1}100%{top:calc(100% - 6px);opacity:0}}
.pil-note{text-align:center;margin-top:32px;font-size:16px;font-weight:700;color:var(--ink2)}
.pil-note b{color:var(--green-d)}
.pcard-btn{display:inline-flex;align-items:center;gap:9px;margin-top:26px;font-weight:800;font-size:14px;border-radius:999px;padding:13px 22px;transition:.22s;text-decoration:none;cursor:pointer}
.pcard.comm .pcard-btn{background:var(--ink);color:var(--cream)}
.pcard.comm .pcard-btn:hover{background:var(--forest);transform:translateY(-2px)}
.pcard.oper .pcard-btn{background:var(--lime);color:var(--ink)}
.pcard.oper .pcard-btn:hover{filter:brightness(1.08);transform:translateY(-2px)}
.pcard-btn i{transition:.2s}
.pcard-btn:hover i{transform:translateX(4px)}
/* efecto glow debajo de los pilares */
#modulos{position:relative;overflow:hidden}
#modulos::after{content:'';position:absolute;left:50%;bottom:-150px;transform:translateX(-50%);width:min(1050px,94%);height:400px;background:radial-gradient(ellipse at center,rgba(198,244,95,.22),rgba(14,162,107,.1) 42%,transparent 72%);z-index:0;pointer-events:none;animation:pglow 5.5s ease-in-out infinite}
@keyframes pglow{0%,100%{opacity:.5;transform:translateX(-50%) scale(1)}50%{opacity:1;transform:translateX(-50%) scale(1.07)}}
#modulos .wrap{position:relative;z-index:2}
/* conecta toda la empresa */
.conx{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center;margin-top:8px}
@media(max-width:940px){.conx{grid-template-columns:1fr;gap:40px}}
.conx .clist{display:grid;gap:16px;margin-top:26px}
.conx .ci{display:flex;gap:15px;align-items:flex-start}
.conx .ci .cicon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:18px;flex-shrink:0;background:var(--cream2);color:var(--green-d)}
.conx .ci b{font-size:15.5px;font-weight:800;display:block;margin-bottom:3px}
.conx .ci p{font-size:14px;color:var(--gray-d);line-height:1.6}
.conx .ci .verifmini{display:inline-flex;width:15px;height:15px;border-radius:50%;background:#25D366;color:#fff;font-size:8px;align-items:center;justify-content:center;vertical-align:middle;margin:0 2px}
/* diagrama de conexión */
.cxmap{background:var(--forest);border-radius:24px;padding:34px 26px;position:relative;overflow:hidden}
.cxmap::before{content:'';position:absolute;top:-80px;right:-60px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(198,244,95,.13),transparent 70%)}
.cxcols{display:grid;grid-template-columns:1fr auto 1fr;gap:14px;align-items:center;position:relative;z-index:2}
@media(max-width:480px){.cxcols{grid-template-columns:1fr;gap:20px}}
.cxcol{display:grid;gap:10px}
.cxcol .lbl{font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--lime);text-align:center;margin-bottom:2px}
.cxnum{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.06);border:1px solid rgba(198,244,95,.2);border-radius:12px;padding:11px 13px;color:#EAF6EE;font-size:12.5px;font-weight:700;transition:.25s}
.cxnum:hover{background:rgba(198,244,95,.12);border-color:rgba(198,244,95,.5);transform:translateX(3px)}
.cxnum i.wa{color:#3BE466}
.cxnum .vf{margin-left:auto;width:16px;height:16px;border-radius:50%;background:#25D366;color:#fff;font-size:8px;display:grid;place-items:center;flex-shrink:0;box-shadow:0 0 0 0 rgba(37,211,102,.5);animation:vfpulse 2.4s ease-out infinite}
@keyframes vfpulse{0%{box-shadow:0 0 0 0 rgba(37,211,102,.5)}70%{box-shadow:0 0 0 7px rgba(37,211,102,0)}100%{box-shadow:0 0 0 0 rgba(37,211,102,0)}}
.cxhub{width:96px;height:96px;border-radius:26px;background:linear-gradient(135deg,var(--lime),#8FCE2A);display:grid;place-items:center;box-shadow:0 0 44px rgba(198,244,95,.45);animation:hubpulse 2.6s ease-in-out infinite;margin:0 auto}
.cxhub img{width:56px;height:56px;object-fit:contain}
.cxflow{width:52px;height:3px;border-radius:3px;background:rgba(198,244,95,.2);position:relative;overflow:hidden}
.cxflow::after{content:'';position:absolute;top:0;left:-40%;width:40%;height:100%;border-radius:3px;background:linear-gradient(90deg,transparent,var(--lime));animation:cxflow 1.5s linear infinite}
@keyframes cxflow{to{left:110%}}
@media(max-width:480px){.cxflow{width:3px;height:40px}.cxflow::after{left:0;top:-40%;width:100%;height:40%;background:linear-gradient(180deg,transparent,var(--lime));animation:cxflowV 1.5s linear infinite}}
@keyframes cxflowV{to{top:110%}}
.cxperson{display:flex;align-items:center;gap:9px;background:rgba(255,255,255,.06);border:1px solid rgba(198,244,95,.2);border-radius:12px;padding:11px 13px;color:#EAF6EE;font-size:12.5px;font-weight:700;transition:.25s}
.cxperson:hover{background:rgba(198,244,95,.12);border-color:rgba(198,244,95,.5);transform:translateX(-3px)}
.cxperson i{color:var(--lime)}
.cxperson small{display:block;font-weight:600;color:#9DB3A2;font-size:10px}
.cxfoot{margin-top:22px;display:flex;gap:10px;justify-content:center;position:relative;z-index:2;flex-wrap:wrap}
.cxfoot .cf{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.07);border:1px solid rgba(198,244,95,.22);border-radius:999px;padding:9px 16px;font-size:12.5px;font-weight:700;color:#EAF6EE}
.cxfoot .cf i{color:var(--lime)}
/* departamentos */
.deps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:48px}
@media(max-width:900px){.deps{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.deps{grid-template-columns:1fr}}
.dep{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;text-align:left;transition:.25s}
.dep:hover{transform:translateY(-5px);box-shadow:var(--shadow-l);border-color:var(--green)}
.dep .di{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;font-size:19px;margin-bottom:15px}
.dep b{font-size:15px;font-weight:800;display:block;margin-bottom:5px}
.dep p{font-size:13px;color:var(--gray-d);line-height:1.55}
/* mapa en vivo (Leaflet) */
.livemap-wrap{margin-top:44px;border-radius:24px;overflow:hidden;border:1px solid var(--line);box-shadow:var(--shadow-l);background:#fff}
.livemap-bar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 20px;border-bottom:1px solid var(--line);background:#fff}
.livemap-bar .t{font-weight:800;font-size:15px;color:var(--ink);display:flex;align-items:center;gap:9px}
.livemap-bar .t i{color:var(--green-d)}
.livemap-bar .live{display:inline-flex;align-items:center;gap:7px;font-size:11.5px;font-weight:800;color:#0B8659;background:#E7F8EE;border-radius:99px;padding:5px 12px;letter-spacing:.04em}
.livemap-bar .live::before{content:'';width:8px;height:8px;border-radius:50%;background:#12B76A;animation:vfpulse 2s ease-out infinite}
.livemap-bar .leg{margin-left:auto;display:flex;gap:16px;flex-wrap:wrap;font-size:12px;font-weight:700;color:var(--gray-d)}
.livemap-bar .leg span{display:inline-flex;align-items:center;gap:7px}
.livemap-bar .leg .d{width:11px;height:11px;border-radius:50%}
#liveMap{height:clamp(340px,52vh,520px);width:100%;background:#EAF6EF;z-index:1}
.moto-mk{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-size:19px;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.3);border:2.5px solid #fff}
.moto-mk.g{background:#12B76A}.moto-mk.b{background:#3B82F6}.moto-mk.o{background:#F59E0B}
.moto-mk::after{content:'';position:absolute;inset:-6px;border-radius:50%;border:2px solid currentColor;opacity:.4;animation:motoping 2s ease-out infinite}
@keyframes motoping{0%{transform:scale(.7);opacity:.5}100%{transform:scale(1.4);opacity:0}}
.home-mk{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-size:15px;background:#fff;border:1px solid var(--line);box-shadow:0 2px 8px rgba(0,0,0,.2)}
.leaflet-container{font-family:'Plus Jakarta Sans',sans-serif}
/* erp */
.erp-list{margin-top:50px;border-top:1.5px solid var(--ink)}
.erp-it{display:flex;justify-content:space-between;align-items:center;padding:26px 8px;border-bottom:1.5px solid var(--ink);font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(20px,3vw,32px);letter-spacing:-.03em;transition:.25s;gap:16px;flex-wrap:wrap}
.erp-it:hover{padding-left:22px;color:var(--green-d)}
.erp-it small{font-family:'Plus Jakarta Sans',sans-serif;font-size:12.5px;font-weight:700;color:var(--gray);letter-spacing:.04em;text-transform:uppercase}
/* casos chips */
.cases{display:flex;flex-wrap:wrap;gap:11px;margin-top:44px}
.case{display:inline-flex;align-items:center;gap:9px;background:#fff;border:1.5px solid var(--line);border-radius:999px;padding:13px 22px;font-size:14.5px;font-weight:700;color:var(--ink2);transition:.2s}
.case:hover{border-color:var(--ink);transform:translateY(-3px)}
.case i{color:var(--green)}
/* testimonio */
.t-quote{max-width:1000px;margin:0 auto;text-align:center}
.t-quote .st{color:#E9A13B;font-size:18px;letter-spacing:5px;margin-bottom:28px}
.t-quote p{font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:clamp(21px,3.4vw,36px);line-height:1.35;letter-spacing:-.03em}
.t-quote p em{font-style:normal;color:var(--green-d)}
.t-quote .who{margin-top:32px;display:inline-flex;align-items:center;gap:14px}
.t-quote .who .av{width:50px;height:50px;border-radius:50%;background:var(--forest);color:var(--lime);display:grid;place-items:center;font-weight:800;font-size:15px}
.t-quote .who b{display:block;font-size:15.5px;text-align:left}
.t-quote .who small{color:var(--gray);font-size:13px;font-weight:600;display:block;text-align:left}
/* números */
.bignums{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border:1.5px solid var(--ink);border-radius:24px;overflow:hidden;margin-top:60px}
@media(max-width:820px){.bignums{grid-template-columns:repeat(2,1fr)}}
.bn{padding:38px 20px;text-align:center;border-right:1.5px solid var(--ink)}
.bn:last-child{border-right:none}
@media(max-width:820px){.bn:nth-child(2){border-right:none}.bn:nth-child(1),.bn:nth-child(2){border-bottom:1.5px solid var(--ink)}}
.bn b{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(28px,3.8vw,46px);letter-spacing:-.03em;display:block;color:var(--green-d)}
.bn span{font-size:12px;font-weight:700;color:var(--gray);letter-spacing:.08em;text-transform:uppercase}
/* quiénes somos (video + texto) */
.nosgrid2{display:grid;grid-template-columns:1fr 1fr;gap:clamp(30px,4vw,52px);align-items:center}
@media(max-width:920px){.nosgrid2{grid-template-columns:1fr}}
.nos-video{position:relative;border-radius:24px;overflow:hidden;box-shadow:0 30px 70px rgba(7,48,31,.22);border:1px solid var(--line);background:var(--ink)}
.nos-video video{width:100%;height:100%;object-fit:cover;display:block;aspect-ratio:4/3}
.nos-video::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 55%,rgba(7,48,31,.55));pointer-events:none}
.nos-video .vbadge{position:absolute;left:16px;bottom:16px;z-index:2;background:rgba(7,48,31,.72);backdrop-filter:blur(8px);color:#fff;font-size:12.5px;font-weight:700;border-radius:11px;padding:9px 14px;display:inline-flex;gap:9px;align-items:center;border:1px solid rgba(198,244,95,.25)}
.nos-video .vbadge .live{width:8px;height:8px;border-radius:50%;background:var(--lime);box-shadow:0 0 0 0 rgba(198,244,95,.6);animation:vfpulse 2.2s ease-out infinite}
.nos-video .vtag{position:absolute;top:16px;left:16px;z-index:2;background:rgba(255,255,255,.92);color:var(--ink);font-size:11px;font-weight:800;border-radius:9px;padding:7px 12px;display:inline-flex;gap:7px;align-items:center}
.nos-video .vtag i{color:var(--green-d)}
.nos-points{display:grid;gap:13px;margin:24px 0}
.nos-point{display:flex;gap:13px;align-items:flex-start;font-size:14.5px;color:var(--ink2);line-height:1.5;font-weight:500}
.nos-point .pi{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;font-size:14px;flex-shrink:0;background:var(--cream2);color:var(--green-d)}
.nos-point b{color:var(--ink);font-weight:800}
.cf-light2{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 16px;font-size:13px;font-weight:700;color:var(--ink2)}
.cf-light2 i{color:var(--green)}
.nos-hubpanel{background:radial-gradient(520px 420px at 50% 45%,#0B3B27 0%,var(--forest) 65%);border-radius:24px;padding:20px 14px;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 30px 70px rgba(7,48,31,.22);border:1px solid var(--line);min-height:380px}
.nos-hubpanel .hubmap{max-width:460px;width:100%;height:360px;margin:0}
/* quiénes somos (2 zonas) */
.nosgrid{display:grid;grid-template-columns:1.08fr .92fr;gap:22px;align-items:stretch}
@media(max-width:920px){.nosgrid{grid-template-columns:1fr}}
.nos-main{background:radial-gradient(700px 400px at 20% 0%,#0B3B27 0%,var(--forest) 60%);border-radius:28px;padding:clamp(32px,4vw,54px);color:#EAF6EE;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center}
.nos-main::before{content:'';position:absolute;top:-90px;right:-70px;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(198,244,95,.13),transparent 70%)}
.nos-main .badge-logo{width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,var(--lime),#8FCE2A);display:grid;place-items:center;margin-bottom:22px;box-shadow:0 0 40px rgba(198,244,95,.4);position:relative;z-index:2}
.nos-main .badge-logo img{width:36px;height:36px;object-fit:contain}
.nos-main .num-tag{color:var(--lime);position:relative;z-index:2}
.nos-main .num-tag::after{background:var(--lime)}
.nos-main h2{color:#fff;font-size:clamp(26px,3.2vw,40px);position:relative;z-index:2}
.nos-main h2 em{font-style:normal;color:var(--lime)}
.nos-main .lead{color:#B9CDBF;margin-top:16px;position:relative;z-index:2;max-width:none}
.nos-main .chips{display:flex;gap:10px;flex-wrap:wrap;margin-top:26px;position:relative;z-index:2}
.nos-main .chip2{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid rgba(198,244,95,.24);border-radius:999px;padding:10px 16px;font-size:12.5px;font-weight:700;color:#EAF6EE}
.nos-main .chip2 i{color:var(--lime)}
.nos-main .chip2 .vf2{width:15px;height:15px;border-radius:50%;background:#25D366;color:#fff;font-size:8px;display:grid;place-items:center}
.nos-side{display:grid;gap:18px}
.nos-card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:clamp(24px,2.6vw,32px);transition:.26s;display:flex;gap:18px;align-items:flex-start}
.nos-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-l);border-color:var(--green)}
.nos-card .ci{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;font-size:21px;flex-shrink:0}
.nos-card h4{font-size:17px;font-weight:800;color:var(--ink);margin-bottom:6px;letter-spacing:-.02em}
.nos-card p{font-size:14px;color:var(--gray-d);line-height:1.6}
/* quiénes somos (premium forest) */
.aboutx{background:radial-gradient(900px 500px at 15% 0%,#0B3B27 0%,var(--forest) 60%);border-radius:32px;max-width:1240px;margin:0 auto;padding:74px clamp(24px,5vw,72px);position:relative;overflow:hidden;color:#EAF6EE}
.aboutx::before{content:'';position:absolute;top:-120px;left:-80px;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(198,244,95,.14),transparent 70%)}
.aboutx::after{content:'';position:absolute;bottom:-140px;right:-90px;width:420px;height:420px;border-radius:50%;background:radial-gradient(circle,rgba(198,244,95,.1),transparent 70%)}
.aboutx .top{text-align:center;max-width:780px;margin:0 auto;position:relative;z-index:2}
.aboutx .top .badge-logo{width:66px;height:66px;border-radius:18px;background:linear-gradient(135deg,var(--lime),#8FCE2A);display:grid;place-items:center;margin:0 auto 22px;box-shadow:0 0 40px rgba(198,244,95,.4)}
.aboutx .top .badge-logo img{width:40px;height:40px;object-fit:contain}
.aboutx .num-tag{color:var(--lime);justify-content:center;display:inline-flex}
.aboutx .num-tag::after{background:var(--lime)}
.aboutx h2{color:#fff}
.aboutx h2 em{font-style:normal;color:var(--lime)}
.aboutx .lead{color:#B9CDBF;margin:18px auto 0}
.aboutx .cols{display:grid;grid-template-columns:repeat(3,1fr);gap:2px;margin-top:56px;position:relative;z-index:2;background:rgba(198,244,95,.14);border-radius:20px;overflow:hidden}
@media(max-width:800px){.aboutx .cols{grid-template-columns:1fr;gap:1px}}
.aboutx .col{text-align:center;padding:36px 26px;background:var(--forest);transition:.3s}
.aboutx .col:hover{background:#0B3B27}
.aboutx .col .ci{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;font-size:23px;margin:0 auto 18px;background:rgba(198,244,95,.12);border:1px solid rgba(198,244,95,.28);color:var(--lime)}
.aboutx .col b{font-size:17px;font-weight:800;display:block;margin-bottom:9px;color:#fff}
.aboutx .col p{font-size:14px;color:#B9CDBF;line-height:1.62}
.aboutx .chips{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:44px;position:relative;z-index:2}
.aboutx .chip2{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid rgba(198,244,95,.24);border-radius:999px;padding:11px 19px;font-size:13px;font-weight:700;color:#EAF6EE}
.aboutx .chip2 i{color:var(--lime)}
.aboutx .chip2 .vf2{width:15px;height:15px;border-radius:50%;background:#25D366;color:#fff;font-size:8px;display:grid;place-items:center}
/* CTA lime */
.cta{background:var(--lime);border-radius:32px;max-width:1240px;margin:0 auto 70px;padding:100px 30px;text-align:center;position:relative;overflow:hidden}
.cta::before{content:'';position:absolute;top:-90px;left:-60px;width:300px;height:300px;border-radius:50%;border:1.5px solid rgba(13,18,14,.15)}
.cta::after{content:'';position:absolute;bottom:-110px;right:-70px;width:360px;height:360px;border-radius:50%;border:1.5px solid rgba(13,18,14,.15)}
.cta h2{color:var(--ink);max-width:900px;margin:0 auto;font-size:clamp(32px,5.4vw,68px)}
.cta p{color:#3D4A34;margin:20px auto 36px;max-width:520px;font-size:17px;font-weight:600;line-height:1.65}
/* footer */
footer{background:var(--ink);color:#B9C4BB;padding:70px 24px 34px;border-radius:32px 32px 0 0}
.foot{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:40px}
@media(max-width:760px){.foot{grid-template-columns:1fr}}
.foot h4{font-size:12px;font-weight:800;color:#fff;margin-bottom:16px;letter-spacing:.14em;text-transform:uppercase}
.foot a{display:block;font-size:14.5px;color:#8fa094;margin-bottom:11px;transition:.2s}
.foot a:hover{color:var(--lime)}
.foot-mark{max-width:1200px;margin:50px auto 0;font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(70px,14vw,190px);letter-spacing:-.05em;line-height:.85;color:rgba(255,255,255,.06);text-align:center;user-select:none}
.foot-copy{max-width:1200px;margin:20px auto 0;border-top:1px solid rgba(255,255,255,.09);padding-top:22px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:12.5px;color:#71807a}
/* wa float */
.wa-float{position:fixed;left:22px;bottom:22px;z-index:150;width:54px;height:54px;border-radius:50%;background:#25D366;color:#fff;display:grid;place-items:center;font-size:26px;box-shadow:0 14px 36px rgba(37,211,102,.4);transition:.25s;animation:waPulse 2.8s infinite}
.wa-float:hover{transform:scale(1.08)}
@keyframes waPulse{0%,100%{box-shadow:0 14px 36px rgba(37,211,102,.4)}50%{box-shadow:0 14px 36px rgba(37,211,102,.4),0 0 0 13px rgba(37,211,102,.1)}}
.reveal{opacity:0;transform:translateY(26px);transition:opacity .75s ease,transform .75s ease}
.reveal.in{opacity:1;transform:none}
.d1{transition-delay:.08s}.d2{transition-delay:.16s}.d3{transition-delay:.24s}
/* ============ RESPONSIVE ============ */
/* monitores grandes: escalar el contenido para que no quede chico en el centro */
@media(min-width:1500px){
  .wrap{max-width:1360px}
  .nav{max-width:1360px}
  section{padding:120px 24px}
  .hero{padding:150px 24px 80px}
  .hero h1{font-size:clamp(52px,3.9vw,72px)}
  .hero .lead{font-size:20px;max-width:560px}
  .phone{width:380px}
  h2{font-size:clamp(42px,3.4vw,56px)}
  .lead{font-size:19.5px}
  .mods,.deps{gap:22px}
  .split .half{min-height:520px}
  .relmap,.hubmap{max-width:600px;height:380px}
  .cta{max-width:1440px}
  .erp{max-width:1360px}
  .ia,.strip-wrap{max-width:1440px}
}
@media(min-width:1900px){
  .wrap{max-width:1500px}
  .nav{max-width:1500px}
  .hero h1{font-size:76px}
  .phone{width:400px}
}
@media(max-width:1024px){
  section{padding:80px 22px}
  .erp{padding:48px 32px}
}
@media(max-width:760px){
  section{padding:64px 18px}
  .split .half{padding:34px 22px;min-height:auto}
  .erp{padding:40px 22px}
  .cta{padding:70px 24px}
  .ia{padding:70px 18px}
  .testi .tx{padding:30px 24px}
  .foot-mark{font-size:clamp(56px,20vw,120px)}
}
@media(max-width:600px){
  .hero{padding:112px 18px 24px}
  .hero h1{font-size:clamp(30px,8vw,40px)}
  .num-tag{letter-spacing:.18em;font-size:12px}
  .num-tag::after{flex-basis:34px}
  /* mapas de relaciones compactos */
  .relmap,.hubmap{height:290px}
  .relmap .node,.hubmap .node{width:72px;gap:4px}
  .relmap .node .nd{width:40px;height:40px;font-size:16px;border-radius:11px}
  .hubmap .node .nd{width:38px;height:38px;font-size:15px}
  .relmap .node b,.hubmap .node b{font-size:9.5px}
  .relmap .node small{font-size:8px}
  .hubmap .hub{width:64px;height:64px;border-radius:18px}
  .hubmap .hub img{width:38px;height:38px}
  .relmap .warn{font-size:10px;padding:5px 11px}
  /* navbar: solo hamburguesa en móvil (evita desborde horizontal) */
  .nav .btn{display:none}
  .hero .hgrid>div{max-width:100%;min-width:0}
  .hsub{max-width:100%;overflow-wrap:anywhere}
  .hfeats{margin-top:24px}
  .hctas{flex-direction:column;align-items:stretch;width:100%}
  .hctas .btn{width:100%;justify-content:center}
  /* teléfono un poco más chico y centrado */
  .phone{width:min(300px,82vw);transform:none!important;margin:0 auto}
  .meta-badge{top:-10px;right:0;left:auto;padding:8px 12px}
  .meta-badge i{font-size:20px}
  .spin-badge{display:none}
  .erp-it{font-size:18px}
  .erp-it small{display:none}
  .row .rt{font-size:19px}
  .row .rt .pill{font-size:9.5px;padding:4px 9px}
  .row .rh{grid-template-columns:32px 1fr auto;gap:12px}
}
@media(max-width:380px){
  .hero h1{font-size:26px}
  .btn{padding:13px 20px;font-size:14px}
  .relmap .node small{display:none}
}
@media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important}html{scroll-behavior:auto}.pm,.ptag,.ptyping{opacity:1!important;transform:none!important}}
</style>
@endverbatim
</head>
<body>
<div id="bar"></div>

<header>
  <nav class="nav">
    <a class="logo" href="#top" aria-label="KIVOX"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" style="height:66px" onerror="this.outerHTML='<span style=&quot;width:52px;height:52px;border-radius:12px;background:#0EA26B;display:grid;place-items:center;font-weight:800;color:#fff;font-size:26px&quot;>K</span>'"></a>
    <div class="nav-links">
      <div class="nav-item">
        <a class="nav-trig" href="/comunicacion">Omnicanalidad <i class="fa-solid fa-chevron-down"></i></a>
        <div class="nav-drop"><div class="dd">
          <div class="dh"><i class="fa-solid fa-comments"></i> Omnicanalidad &amp; Marketing</div>
          <a href="#modulos"><i class="fa-solid fa-layer-group"></i> Atención omnicanal</a>
          <a href="#ia"><i class="fa-solid fa-robot"></i> Bots con IA</a>
          <a href="#modulos"><i class="fa-solid fa-bullhorn"></i> Campañas masivas</a>
          <a href="#conecta"><i class="fa-solid fa-sitemap"></i> Departamentos</a>
        </div></div>
      </div>
      <div class="nav-item">
        <a class="nav-trig" href="/operacion">Pedidos &amp; Domicilios <i class="fa-solid fa-chevron-down"></i></a>
        <div class="nav-drop"><div class="dd">
          <div class="dh"><i class="fa-solid fa-gears"></i> Pedidos &amp; Domicilios</div>
          <a href="#modulos"><i class="fa-solid fa-cart-shopping"></i> Pedidos y pagos</a>
          <a href="#mapa"><i class="fa-solid fa-map-location-dot"></i> Despachos en vivo</a>
          <a href="#erp"><i class="fa-solid fa-plug"></i> Integraciones ERP</a>
          <a href="#produccion"><i class="fa-solid fa-circle-play"></i> En producción</a>
        </div></div>
      </div>
      <a href="#nosotros">Nosotros</a>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <a class="btn btn-line btn-sm" href="https://admin.kivox.co/login">Acceder</a>
      <a class="btn btn-ink btn-sm btn-grad" href="#demo">Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
      <button class="burger" id="burger" aria-label="Menú"><i class="fa-solid fa-bars"></i></button>
    </div>
  </nav>
</header>
<div class="mnav" id="mnav">
  <div class="mg">Omnicanalidad &amp; Marketing</div>
  <a href="#modulos">Atención omnicanal</a>
  <a href="#ia">Bots con IA</a>
  <a href="#modulos">Campañas masivas</a>
  <a href="#conecta">Departamentos</a>
  <div class="mg">Pedidos &amp; Domicilios</div>
  <a href="#modulos">Pedidos y pagos</a>
  <a href="#mapa">Despachos en vivo</a>
  <a href="#erp">Integraciones ERP</a>
  <a href="#produccion">En producción</a>
  <div class="mg">Empresa</div>
  <a href="#nosotros">Nosotros</a>
  <a href="#demo">Solicitar demo →</a>
</div>

{{-- ============ HERO ============ --}}
<section class="hero" id="top">
  <div class="wrap hgrid">
    <div>
      <h1 class="reveal">Tu empresa entera, respondiendo en <span class="hl">2 segundos<svg viewBox="0 0 300 14" preserveAspectRatio="none"><path d="M4 10 C 80 2, 220 2, 296 8"/></svg></span>.<span class="spark"><i class="fa-solid fa-bolt"></i></span></h1>
      <p class="hsub reveal d1">Atiende, vende y gestiona pedidos <b>automáticamente</b> por WhatsApp, Instagram y más.</p>
      <div class="hfeats reveal d1">
        <div class="hf"><span class="hf-ic"><i class="fa-solid fa-bolt"></i></span><b>Respuesta instantánea</b></div>
        <div class="hf"><span class="hf-ic"><i class="fa-solid fa-shield-halved"></i></span><b>Pago seguro integrado</b></div>
        <div class="hf"><span class="hf-ic"><i class="fa-solid fa-chart-simple"></i></span><b>Todo tu negocio en un solo lugar</b></div>
        <div class="hf"><span class="hf-ic"><i class="fa-solid fa-user-group"></i></span><b>Nadie tocó el teléfono</b></div>
      </div>
      <div class="hctas reveal d2">
        <a class="btn btn-ink btn-grad" href="#demo">Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
        <a class="btn btn-line" href="#modulos">Qué hace KIVOX</a>
      </div>
      <p class="htrust reveal d2"><i class="fa-solid fa-circle-check"></i> Seguro, confiable y 100% automatizado</p>
    </div>
    <div class="phone-zone reveal d2">
      <div class="stage-glow"><span class="rays"></span><span class="halo"></span></div>
      <div class="podium"><span class="disc"></span><span class="ring"></span></div>
      <div class="fchip fc1"><i class="fa-regular fa-clock"></i> Responde al instante</div>
      <div class="fchip fc2"><i class="fa-solid fa-cart-shopping"></i> Cierra más ventas</div>
      <div class="fchip fc3"><i class="fa-solid fa-chart-line"></i> Ahorra tiempo y aumenta tu productividad</div>
      <div class="spin-badge">
        <svg viewBox="0 0 100 100"><defs><path id="circ" d="M 50,50 m -38,0 a 38,38 0 1,1 76,0 a 38,38 0 1,1 -76,0"/></defs><text style="font-size:10.5px;font-weight:700;letter-spacing:.18em;fill:#0D120E;font-family:'Space Grotesk',sans-serif"><textPath href="#circ">OMNICANAL · IA · PEDIDOS · ERP · </textPath></text></svg>
        <span class="c"><i class="fa-solid fa-bolt"></i></span>
      </div>
      <div class="phone">
        <div class="screen">
          <div class="ph-top">
            <span class="av"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" onerror="this.outerHTML='<i class=&quot;fa-brands fa-whatsapp&quot;></i>'"></span>
            <div><b>Distribuidora El Roble <span class="verif" title="Cuenta verificada por Meta"><i class="fa-solid fa-check"></i></span></b><small>en línea · atendido por KIVOX IA</small></div>
          </div>
          <div class="ph-chat" id="phChat"></div>
          <div class="ph-input">
            <i class="fa-regular fa-face-smile"></i>
            <div class="ph-field">Escribe un mensaje</div>
            <i class="fa-solid fa-paperclip"></i>
            <i class="fa-solid fa-camera"></i>
            <span class="ph-mic"><i class="fa-solid fa-microphone"></i></span>
          </div>
          <div class="ph-powered"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" onerror="this.style.display='none'"> Automatizado por <b>KIVOX</b> · IA activa</div>
        </div>
      </div>
      <div class="phone-note"><i class="fa-solid fa-robot"></i> Nadie tocó el teléfono<small>la IA vendió sola</small></div>
      <div class="meta-badge"><i class="fa-brands fa-meta"></i><span>Meta Business<br><b>Partner Oficial</b></span></div>
    </div>
  </div>
</section>

{{-- ============ TICKER ============ --}}
<div class="ticker" aria-hidden="true">
  <div class="tk" id="tk">
    <span><i class="fa-brands fa-whatsapp" style="color:#25D366"></i> WHATSAPP <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><i class="fa-brands fa-instagram" style="color:#E1306C"></i> INSTAGRAM <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><i class="fa-brands fa-facebook-messenger" style="color:#0084FF"></i> MESSENGER <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><img src="/logos/hgi.png" alt="HGI" style="height:22px;width:auto;background:#fff;border-radius:5px;padding:2px 6px"> HGI <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><img src="/logos/sap.svg" alt="SAP" style="height:20px;width:auto"> SAP BUSINESS ONE <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><i class="fa-brands fa-meta" style="color:#0081FB"></i> META <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><img src="/logos/wompi.svg" alt="Wompi" style="height:20px;width:auto;background:#fff;border-radius:5px;padding:3px 7px"> WOMPI <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
    <span><img src="/logos/bold.png" alt="Bold" style="height:24px;width:auto;border-radius:6px"> BOLD <i class="fa-solid fa-circle dotx" style="font-size:6px"></i></span>
  </div>
</div>

{{-- ============ QUIÉNES SOMOS (video + texto) ============ --}}
<section id="nosotros" style="padding:70px 16px">
  <div class="wrap nosgrid2">
    {{-- Diagrama de conexión (hub) --}}
    <div class="nos-hubpanel reveal">
      <div class="hubmap" aria-hidden="true">
        <svg viewBox="0 0 100 100" preserveAspectRatio="none">
          <line class="hl" x1="50" y1="50" x2="50" y2="13"/>
          <line class="hl" x1="50" y1="50" x2="86" y2="30"/>
          <line class="hl" x1="50" y1="50" x2="84" y2="76"/>
          <line class="hl" x1="50" y1="50" x2="50" y2="90"/>
          <line class="hl" x1="50" y1="50" x2="16" y2="76"/>
          <line class="hl" x1="50" y1="50" x2="14" y2="30"/>
        </svg>
        <span class="pulse" style="left:50%;top:13%;animation-delay:0s"></span>
        <span class="pulse" style="left:86%;top:30%;animation-delay:.45s"></span>
        <span class="pulse" style="left:84%;top:76%;animation-delay:.9s"></span>
        <span class="pulse" style="left:50%;top:90%;animation-delay:1.3s"></span>
        <span class="pulse" style="left:16%;top:76%;animation-delay:1.7s"></span>
        <span class="pulse" style="left:14%;top:30%;animation-delay:2.1s"></span>
        <div class="hub"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" onerror="this.outerHTML='<span style=&quot;font-weight:800;color:#07301F;font-size:15px&quot;>KIVOX</span>'"></div>
        <div class="node" style="left:50%;top:13%"><span class="nd"><i class="fa-brands fa-whatsapp"></i></span><b>WhatsApp</b></div>
        <div class="node" style="left:86%;top:30%"><span class="nd"><i class="fa-brands fa-instagram"></i></span><b>Instagram</b></div>
        <div class="node" style="left:84%;top:76%"><span class="nd"><i class="fa-solid fa-cubes"></i></span><b>ERP / SAP</b></div>
        <div class="node" style="left:50%;top:90%"><span class="nd"><i class="fa-solid fa-map-location-dot"></i></span><b>Despachos</b></div>
        <div class="node" style="left:16%;top:76%"><span class="nd"><i class="fa-solid fa-globe"></i></span><b>Web</b></div>
        <div class="node" style="left:14%;top:30%"><span class="nd"><i class="fa-brands fa-facebook"></i></span><b>Facebook</b></div>
      </div>
    </div>
    {{-- Texto --}}
    <div class="nos-text reveal d1">
      <h2>Detrás de KIVOX está <span style="color:var(--green-d)">TecnoByte360</span></h2>
      <p class="lead" style="margin-top:16px"><strong style="color:var(--ink)">TecnoByte360 es una compañía colombiana de desarrollo de software</strong>. Creamos soluciones tecnológicas a la medida para empresas de todo tipo — y KIVOX es uno de nuestros productos. Hoy muchas empresas tienen WhatsApp, Instagram, Facebook, su web y su ERP por separado, y <strong style="color:var(--ink)">no saben cómo integrarlos ni administrarlos correctamente</strong>. Por eso creamos KIVOX: para conectar y ordenar todos tus medios en una sola plataforma.</p>
      <div class="nos-points">
        <div class="nos-point"><span class="pi"><i class="fa-solid fa-building"></i></span><span><b>Para empresas de todos los sectores.</b> Nos conectamos con cualquier tipo de negocio: comercio, industria, servicios, logística y más.</span></div>
        <div class="nos-point"><span class="pi"><i class="fa-solid fa-cloud"></i></span><span><b>Software 100% en la nube.</b> Úsalo desde la web o desde la app móvil, siempre actualizado.</span></div>
        <div class="nos-point"><span class="pi"><i class="fa-solid fa-microchip"></i></span><span><b>Respaldo tecnológico real.</b> API oficial de Meta e integraciones empresariales.</span></div>
        <div class="nos-point"><span class="pi"><i class="fa-solid fa-shield-halved"></i></span><span><b>Tus datos son de tu empresa.</b> Todo queda en tu plataforma, con soporte local.</span></div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <span class="cf-light2"><i class="fa-solid fa-layer-group"></i> Todos los sectores</span>
        <span class="cf-light2"><i class="fa-solid fa-location-dot"></i> Hecho en Colombia</span>
        <span class="cf-light2"><i class="fa-brands fa-meta"></i> Meta Business Partner</span>
      </div>
      <a href="https://portafolio.tecnobyte360.com/" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:9px;margin-top:22px;font-weight:800;color:var(--green-d);font-size:15px;text-decoration:none">Conoce el portafolio de TecnoByte360 <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:12px"></i></a>
    </div>
  </div>
</section>

{{-- ============ QUÉ HACE (2 pilares) ============ --}}
<section id="modulos" class="center" style="padding-top:40px">
  <div class="wrap">
    <h2 class="reveal d1">Dos mundos, una sola plataforma</h2>
    <p class="lead reveal d2">KIVOX conecta las dos mitades de tu negocio: la que <strong style="color:var(--ink)">habla</strong> con tus clientes y la que <strong style="color:var(--ink)">entrega</strong>.</p>
    <div class="pil2 reveal d2">
      {{-- Pilar 1 --}}
      <div class="pcard comm">
        <div class="pic"><i class="fa-solid fa-comments"></i></div>
        <span class="eb">Omnicanalidad &amp; Marketing</span>
        <h3>Atiende y véndele a tus clientes por todos lados</h3>
        <p class="tl">Todo tu frente de comunicación, en un solo lugar.</p>
        <ul>
          <li><i class="fa-solid fa-check"></i> Omnicanal: WhatsApp, Instagram, Facebook y web</li>
          <li><i class="fa-solid fa-check"></i> Bots con inteligencia artificial 24/7</li>
          <li><i class="fa-solid fa-check"></i> Campañas y envíos masivos por WhatsApp</li>
          <li><i class="fa-solid fa-check"></i> Gestión y segmentación de clientes</li>
          <li><i class="fa-solid fa-check"></i> Métricas de conversaciones y asesores</li>
        </ul>
        <a class="pcard-btn" href="/comunicacion">Ver todo Omnicanalidad &amp; Marketing <i class="fa-solid fa-arrow-right"></i></a>
      </div>
      {{-- conector --}}
      <div class="pil-mid"><span class="fpulse"></span><span class="fpulse" style="animation-delay:1.1s"></span><span class="plus2"><i class="fa-solid fa-plus"></i></span></div>
      {{-- Pilar 2 --}}
      <div class="pcard oper">
        <div class="pic"><i class="fa-solid fa-gears"></i></div>
        <span class="eb">Pedidos &amp; Domicilios</span>
        <h3>Convierte cada conversación en un pedido entregado</h3>
        <p class="tl">El motor que mueve tu negocio, conectado a tus sistemas.</p>
        <ul>
          <li><i class="fa-solid fa-check"></i> Toma de pedidos de inicio a fin</li>
          <li><i class="fa-solid fa-check"></i> Integración con tu ERP (SAP, HGI, Siigo…)</li>
          <li><i class="fa-solid fa-check"></i> Pasarelas de pago (Wompi, Bold, Nequi, PSE)</li>
          <li><i class="fa-solid fa-check"></i> Despachos, logística y mapa en vivo</li>
          <li><i class="fa-solid fa-check"></i> Reportes de ventas y entregas</li>
        </ul>
        <a class="pcard-btn" href="/operacion">Ver todo Pedidos &amp; Domicilios <i class="fa-solid fa-arrow-right"></i></a>
      </div>
    </div>
    <p class="pil-note reveal d2">Y las dos mitades trabajan juntas, automáticamente, en <b>una sola plataforma</b>.</p>
  </div>
</section>

{{-- ============ MAPA EN VIVO ============ --}}
<section id="mapa" class="center" style="padding-top:40px">
  <div class="wrap">
    <h2 class="reveal d1">Mira a tus domiciliarios<br>moverse en el mapa, en vivo</h2>
    <p class="lead reveal d2">Cada domiciliario con GPS en tiempo real. Sabes quién está disponible, quién va en ruta y dónde está cada pedido — al segundo.</p>
    <div class="livemap-wrap reveal d2">
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
    </div>
    <p class="reveal d2" style="font-size:13px;color:var(--gray);font-weight:600;margin-top:14px"><i class="fa-solid fa-circle-info" style="color:var(--green)"></i> Demostración con recorridos simulados en Bello, Antioquia. En tu cuenta se ven tus domiciliarios reales.</p>
  </div>
</section>

{{-- ============ CONECTA TODA LA EMPRESA ============ --}}
<section id="conecta">
  <div class="wrap">
    <h2 class="reveal d1">Conecta varios números verificados por Meta<br>a todos tus asesores, en un solo lugar.</h2>
    <div class="conx">
      <div class="reveal d1">
        <p class="lead">Cada número de WhatsApp verificado por Meta, atendido por todos tus asesores comerciales al mismo tiempo — desde el computador o desde la app móvil.</p>
        <div class="clist">
          <div class="ci"><span class="cicon"><i class="fa-brands fa-whatsapp"></i></span><div><b>Varios números, todos tus asesores</b><p>Conecta múltiples líneas verificadas por Meta<span class="verifmini"><i class="fa-solid fa-check"></i></span> y que todo tu equipo atienda desde la misma bandeja, sin mezclar chats.</p></div></div>
          <div class="ci"><span class="cicon"><i class="fa-solid fa-shield-halved"></i></span><div><b>La información es de tu empresa</b><p>Cada cliente, conversación y pedido queda guardado en tu plataforma — no en el celular de un asesor. Si alguien se va, tus datos se quedan contigo.</p></div></div>
          <div class="ci"><span class="cicon"><i class="fa-solid fa-display"></i><i class="fa-solid fa-mobile-screen" style="font-size:12px;margin-left:2px"></i></span><div><b>Desde la web o la app móvil</b><p>Tus asesores atienden desde el navegador en el computador o desde la aplicación móvil de KIVOX, estén donde estén.</p></div></div>
        </div>
      </div>
      <div class="cxmap reveal d2">
        <div class="cxcols">
          <div class="cxcol">
            <div class="lbl">Tus números Meta</div>
            <div class="cxnum"><i class="fa-brands fa-whatsapp wa"></i> Ventas <span class="vf"><i class="fa-solid fa-check"></i></span></div>
            <div class="cxnum"><i class="fa-brands fa-whatsapp wa"></i> Soporte <span class="vf"><i class="fa-solid fa-check"></i></span></div>
            <div class="cxnum"><i class="fa-brands fa-whatsapp wa"></i> Cartera <span class="vf"><i class="fa-solid fa-check"></i></span></div>
          </div>
          <div style="display:grid;gap:14px;justify-items:center">
            <div class="cxflow"></div>
            <div class="cxhub"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" onerror="this.outerHTML='<span style=&quot;font-weight:800;color:#07301F&quot;>KIVOX</span>'"></div>
            <div class="cxflow"></div>
          </div>
          <div class="cxcol">
            <div class="lbl">Tus asesores</div>
            <div class="cxperson"><i class="fa-solid fa-user"></i> <span>Asesor 1<small>Web · computador</small></span></div>
            <div class="cxperson"><i class="fa-solid fa-user"></i> <span>Asesor 2<small>App móvil</small></span></div>
            <div class="cxperson"><i class="fa-solid fa-robot"></i> <span>Bot IA<small>24/7 automático</small></span></div>
          </div>
        </div>
        <div class="cxfoot">
          <span class="cf"><i class="fa-solid fa-desktop"></i> Web</span>
          <span class="cf"><i class="fa-solid fa-mobile-screen"></i> App móvil</span>
          <span class="cf"><i class="fa-brands fa-meta"></i> Verificado por Meta</span>
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-top:70px">
      <h2 class="reveal d1" style="font-size:clamp(26px,3.6vw,40px)">Conecta todos los departamentos de tu empresa</h2>
      <p class="lead reveal d2" style="margin:14px auto 0">El bot deriva cada conversación al área correcta según palabras clave — y silencia hasta que el equipo responde.</p>
    </div>
    <div class="deps">
      <div class="dep reveal"><div class="di" style="background:#E7F8EE;color:#1FB863"><i class="fa-solid fa-headset"></i></div><b>Servicio al cliente</b><p>Atención, seguimiento de pedidos y respuestas al instante.</p></div>
      <div class="dep reveal d1"><div class="di" style="background:#EAF1FF;color:#3B82F6"><i class="fa-solid fa-clipboard-list"></i></div><b>Peticiones y PQR</b><p>Quejas, reclamos y solicitudes con trazabilidad completa.</p></div>
      <div class="dep reveal d2"><div class="di" style="background:#FFF4E0;color:#F59E0B"><i class="fa-solid fa-handshake"></i></div><b>Área comercial</b><p>Cotizaciones, precios mayoristas y cierre de ventas.</p></div>
      <div class="dep reveal d3"><div class="di" style="background:#F1ECFF;color:#7C5CFF"><i class="fa-solid fa-user-tie"></i></div><b>Recursos Humanos</b><p>Vacantes, hojas de vida y solicitudes del personal.</p></div>
      <div class="dep reveal"><div class="di" style="background:#FCE7F3;color:#EC4899"><i class="fa-solid fa-file-invoice-dollar"></i></div><b>Cartera y facturación</b><p>Estados de cuenta, pagos y cobros — conectado a tu ERP.</p></div>
      <div class="dep reveal d1"><div class="di" style="background:#E4F9FE;color:#0891B2"><i class="fa-solid fa-truck-fast"></i></div><b>Logística y envíos</b><p>Rastreo de domicilios, guías y tiempos de entrega.</p></div>
      <div class="dep reveal d2"><div class="di" style="background:#E7F8EE;color:#25D366"><i class="fa-solid fa-clipboard-check"></i></div><b>Calidad</b><p>Encuestas de satisfacción y trazabilidad de cada caso.</p></div>
      <div class="dep reveal d3"><div class="di" style="background:#FDECEC;color:#E4572E"><i class="fa-solid fa-plus"></i></div><b>Y las que necesites</b><p>Crea los departamentos que tu empresa requiera, sin límite.</p></div>
    </div>
  </div>
</section>

{{-- ============ IA (forest) ============ --}}
<section id="ia" style="padding:60px 16px">
  <div class="ia">
    <div class="wrap ia-grid">
      <div>
        <h2 class="reveal d1">El empleado que nunca duerme, nunca se enferma y nunca deja un chat en visto.</h2>
        <div class="ia-feats reveal d2">
          <div class="f"><i class="fa-solid fa-comment-dots"></i><span>Conversa en lenguaje natural — tus clientes no notan la diferencia</span></div>
          <div class="f"><i class="fa-solid fa-bag-shopping"></i><span>Toma pedidos completos y los crea en tu ERP</span></div>
          <div class="f"><i class="fa-solid fa-gears"></i><span>Automatiza procesos internos: cartera, encuestas, recordatorios</span></div>
        </div>
      </div>
      <div class="ia-stats reveal d2">
        <div class="ia-st"><b>91%</b><span>de conversaciones resueltas sin intervención humana</span></div>
        <div class="ia-st"><b>2,1 s</b><span>tiempo promedio de respuesta, a cualquier hora</span></div>
        <div class="ia-st"><b>∞</b><span>clientes atendidos a la vez, sin filas ni esperas</span></div>
        <div class="ia-st"><b>24/7</b><span>vendiendo mientras tu competencia duerme</span></div>
      </div>
    </div>
  </div>
</section>

{{-- ============ EN PRODUCCIÓN (filmstrip) ============ --}}
<section id="produccion" style="padding-bottom:60px">
  <div class="wrap">
    <h2 class="reveal d1">Esto no es un render.<br>Es KIVOX trabajando hoy.</h2>
    <p class="lead reveal d2" style="margin-top:22px">Grabado en el punto de venta de un cliente real: pedidos entrando, despachos en el mapa y comandas imprimiéndose solas.</p>
  </div>
  <div class="strip-wrap reveal d2">
    <div class="clip"><video src="/videos/kivox-accion-1.mp4" autoplay muted loop playsinline preload="metadata"></video><span class="cap"><span><i class="fa-solid fa-cash-register"></i> Pedidos en el punto de venta</span><i class="fa-solid fa-circle" style="font-size:7px;color:#3BE466"></i></span></div>
    <div class="clip"><video src="/videos/kivox-accion-2.mp4" autoplay muted loop playsinline preload="metadata"></video><span class="cap"><span><i class="fa-solid fa-map-location-dot"></i> Despachos con GPS en vivo</span><i class="fa-solid fa-circle" style="font-size:7px;color:#3BE466"></i></span></div>
    <div class="clip v"><video src="/videos/kivox-accion-3.mp4" autoplay muted loop playsinline preload="metadata"></video><span class="cap"><span><i class="fa-solid fa-print"></i> Comanda desde el celular</span><i class="fa-solid fa-circle" style="font-size:7px;color:#3BE466"></i></span></div>
  </div>
  <div class="strip-hint"><i class="fa-solid fa-arrows-left-right"></i> Desliza para ver más</div>
</section>

{{-- ============ ERP ============ --}}
<section id="erp" style="padding-top:40px">
  <div class="wrap">
    <h2 class="reveal d1">Habla el idioma de tu ERP.</h2>
    <div class="erp-list reveal d2">
      <div class="erp-it"><span style="display:inline-flex;align-items:center;gap:16px"><img src="/logos/sap.svg" alt="SAP" style="height:34px;width:auto">SAP Business One</span> <small>Pedidos · Inventario · Socios de negocio</small></div>
      <div class="erp-it"><span style="display:inline-flex;align-items:center;gap:16px"><img src="/logos/hgi.png" alt="HGI" style="height:34px;width:auto">HGI</span> <small>Documentos · Cartera · Terceros</small></div>
      <div class="erp-it">Tu ERP o API a medida <small>REST · Webhooks · Bases de datos</small></div>
    </div>
  </div>
</section>

{{-- ============ CASOS ============ --}}
<section style="padding-top:40px">
  <div class="wrap">
    <h2 class="reveal d1">Si tus clientes te escriben,<br>KIVOX es para ti.</h2>
    <div class="cases reveal d2">
      <span class="case"><i class="fa-solid fa-utensils"></i> Restaurantes</span>
      <span class="case"><i class="fa-solid fa-warehouse"></i> Distribuidoras</span>
      <span class="case"><i class="fa-solid fa-screwdriver-wrench"></i> Ferreterías</span>
      <span class="case"><i class="fa-solid fa-industry"></i> Industrias</span>
      <span class="case"><i class="fa-solid fa-store"></i> Comercios</span>
      <span class="case"><i class="fa-solid fa-briefcase"></i> Servicios</span>
      <span class="case"><i class="fa-solid fa-truck-ramp-box"></i> Logística</span>
      <span class="case"><i class="fa-solid fa-building-columns"></i> Financiero</span>
    </div>
  </div>
</section>

{{-- ============ TESTIMONIO ============ --}}
<section>
  <div class="t-quote reveal">
    <div class="st">★★★★★</div>
    <p>"Antes perdíamos pedidos porque no alcanzábamos a responder el WhatsApp. Hoy <em>la IA toma los pedidos sola</em>, la comanda sale directo en la impresora y vemos a los domiciliarios en el mapa en vivo."</p>
    <div class="who"><span class="av">LH</span><span><b>Alimentos La Hacienda SAS</b><small>Distribuidora de carnes · Bello, Antioquia</small></span></div>
  </div>
</section>

{{-- ============ CTA ============ --}}
<section style="padding:40px 16px 0" id="demo">
  <div class="cta reveal">
    <h2 class="disp">¿Hablamos?</h2>
    <p>Agenda una demo gratuita y mira a KIVOX atender, vender y despachar en vivo con los datos de tu negocio.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;position:relative;z-index:2">
      <a class="btn btn-ink" href="https://wa.me/573216499744?text=Hola%2C%20quiero%20una%20demo%20de%20KIVOX" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Solicitar demo <span class="arr"><i class="fa-solid fa-arrow-right"></i></span></a>
      <a class="btn btn-line" href="mailto:comercial@tecnobyte360.com" style="border-color:rgba(13,18,14,.35)"><i class="fa-solid fa-envelope"></i> comercial@tecnobyte360.com</a>
    </div>
  </div>
</section>

{{-- ============ FOOTER ============ --}}
<footer>
  <div class="foot">
    <div>
      <a class="logo" href="#top" style="color:#fff"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" style="height:30px" onerror="this.style.display='none'">KIVOX</a>
      <p style="font-size:14.5px;line-height:1.7;margin-top:16px;max-width:330px;color:#8fa094">Plataforma omnicanal empresarial con inteligencia artificial. Un producto de TecnoByte360.</p>
    </div>
    <div><h4>Plataforma</h4><a href="#nosotros">Por qué KIVOX</a><a href="#modulos">Qué hace</a><a href="#ia">Inteligencia artificial</a><a href="#erp">Integraciones</a></div>
    <div><h4>Empresa</h4><a href="https://admin.kivox.co/login">Acceder</a><a href="mailto:comercial@tecnobyte360.com">Contacto</a><a href="/privacidad">Privacidad</a><a href="/terminos">Términos</a></div>
  </div>
  <div class="foot-mark">KIVOX</div>
  <div class="foot-copy"><span>© {{ date('Y') }} KIVOX · Un producto de TecnoByte360</span><span>Hecho en Colombia 🇨🇴</span></div>
</footer>

@verbatim
<script>
addEventListener('scroll',()=>{document.getElementById('bar').style.width=(scrollY/(document.body.scrollHeight-innerHeight)*100)+'%'},{passive:true});
const burger=document.getElementById('burger'),mnav=document.getElementById('mnav');
burger.addEventListener('click',()=>mnav.classList.toggle('open'));
mnav.addEventListener('click',()=>mnav.classList.remove('open'));

/* chat del teléfono (loop) */
const guion=[
  {k:'in',t:'Hola, ¿tienen la ref. 4050? La necesito hoy 🙏',d:900},
  {k:'typing',d:1000},
  {k:'out',t:'¡Hola! 👋 Sí: 36 unidades disponibles a $128.400. ¿Te confirmo el pedido?',d:1400},
  {k:'tag',t:'IA · inventario consultado en el ERP',d:1000},
  {k:'in',t:'Sí, 10 unidades a la bodega norte',d:1200},
  {k:'typing',d:900},
  {k:'out',t:'Listo ✅ Pedido #8842 creado. Te envío el enlace de pago 👇',d:1300},
  {k:'pay',d:900},
  {k:'tag',t:'Enlace de pago enviado automáticamente',d:1600},
  {k:'in',t:'Ya pagué ✅',d:1200},
  {k:'typing',d:900},
  {k:'out',t:'¡Pago confirmado! 🎉 Tu pedido sale hoy 🚚',d:1400},
  {k:'tag',t:'Pago recibido · pedido creado en SAP · despacho con GPS',d:2800},
];
const chatEl=document.getElementById('phChat');
function play(i=0){
  if(i===0)chatEl.innerHTML='';
  if(i>=guion.length)return setTimeout(()=>play(0),2600);
  const s=guion[i];let el;
  if(s.k==='typing'){el=document.createElement('div');el.className='ptyping';el.innerHTML='<span></span><span></span><span></span>';}
  else if(s.k==='tag'){el=document.createElement('div');el.className='ptag';el.innerHTML='<i class="fa-solid fa-robot"></i> '+s.t;}
  else if(s.k==='pay'){el=document.createElement('div');el.className='pay-card';el.innerHTML='<div class="ph"><i class="fa-solid fa-lock"></i> Pago seguro · KIVOX</div><div class="pb"><div class="amt">$1.284.000<small>Pedido #8842 · Distribuidora El Roble</small></div><div class="pgo"><i class="fa-solid fa-credit-card"></i> Pagar ahora</div><div class="plogos">Wompi · Bold · Nequi · PSE · Tarjetas</div></div>';}
  else{el=document.createElement('div');el.className='pm '+s.k;el.innerHTML=s.t+'<span class="t">'+new Date().getHours().toString().padStart(2,'0')+':'+new Date().getMinutes().toString().padStart(2,'0')+' ✓✓</span>';}
  chatEl.appendChild(el);
  requestAnimationFrame(()=>requestAnimationFrame(()=>{el.classList.add('show');chatEl.scrollTop=chatEl.scrollHeight;}));
  setTimeout(()=>{if(s.k==='typing')el.remove();play(i+1);},s.d);
}
play();

/* ticker duplicado */
const tk=document.getElementById('tk');tk.innerHTML+=tk.innerHTML;

/* filas acordeón */
document.querySelectorAll('#rows .row').forEach(r=>{
  const b=r.querySelector('.rb');
  if(r.classList.contains('open'))b.style.maxHeight=b.scrollHeight+'px';
  r.querySelector('.rh').addEventListener('click',()=>{
    document.querySelectorAll('#rows .row').forEach(x=>{if(x!==r){x.classList.remove('open');x.querySelector('.rb').style.maxHeight=0}});
    const open=r.classList.toggle('open');
    b.style.maxHeight=open?b.scrollHeight+'px':0;
  });
});

/* reveal */
const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

/* contadores */
const fmt=(n,f)=>f==='m'?(n>=1e6?'+'+(n/1e6).toFixed(1).replace('.',',')+' M':'+'+Math.round(n/1e3)+' mil'):f==='k'?Math.round(n).toLocaleString('es-CO'):Math.round(n).toString();
const co=new IntersectionObserver(es=>es.forEach(e=>{if(!e.isIntersecting)return;co.unobserve(e.target);const el=e.target,t=+el.dataset.t,f=el.dataset.fmt,st=performance.now();(function tkk(now){const p=Math.min((now-st)/1700,1),ea=1-Math.pow(1-p,3);el.textContent=fmt(t*ea,f);if(p<1)requestAnimationFrame(tkk)})(st)}),{threshold:.5});
document.querySelectorAll('.count').forEach(el=>co.observe(el));

/* ---- mapa en vivo de domiciliarios (Leaflet) ---- */
function initLiveMap(){
  const el=document.getElementById('liveMap');
  if(!el||typeof L==='undefined'){return setTimeout(initLiveMap,300);}
  const map=L.map('liveMap',{center:[6.3388,-75.5575],zoom:14,zoomControl:true,scrollWheelZoom:false,attributionControl:true});
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',{
    subdomains:'abcd',maxZoom:19,attribution:'© OpenStreetMap © CARTO'
  }).addTo(map);
  const motoIcon=(c)=>L.divIcon({className:'',html:'<div class="moto-mk '+c+'" style="color:'+({g:'#12B76A',b:'#3B82F6',o:'#F59E0B'}[c])+'">🛵</div>',iconSize:[40,40],iconAnchor:[20,20]});
  const homeIcon=L.divIcon({className:'',html:'<div class="home-mk">🏠</div>',iconSize:[30,30],iconAnchor:[15,15]});
  // rutas (recorridos)
  const routes=[
    {c:'g',pts:[[6.3462,-75.5588],[6.3440,-75.5560],[6.3416,-75.5535],[6.3392,-75.5520],[6.3372,-75.5505],[6.3360,-75.5490]]},
    {c:'b',pts:[[6.3322,-75.5592],[6.3348,-75.5572],[6.3376,-75.5556],[6.3402,-75.5566],[6.3424,-75.5586],[6.3446,-75.5602]]},
    {c:'o',pts:[[6.3408,-75.5648],[6.3390,-75.5614],[6.3374,-75.5586],[6.3360,-75.5560],[6.3348,-75.5534]]}
  ];
  // casas destino
  [[6.3360,-75.5490],[6.3446,-75.5602],[6.3348,-75.5534]].forEach(p=>L.marker(p,{icon:homeIcon}).addTo(map));
  const motos=routes.map(r=>{
    r.pts.forEach(()=>{});
    L.polyline(r.pts,{color:{g:'#12B76A',b:'#3B82F6',o:'#F59E0B'}[r.c],weight:3,opacity:.35,dashArray:'6 8'}).addTo(map);
    const m=L.marker(r.pts[0],{icon:motoIcon(r.c)}).addTo(map);
    return {m,pts:r.pts,t:Math.random()*(r.pts.length-1),spd:0.006+Math.random()*0.004,dir:1};
  });
  function lerp(a,b,f){return [a[0]+(b[0]-a[0])*f,a[1]+(b[1]-a[1])*f];}
  function tick(){
    motos.forEach(o=>{
      o.t+=o.spd*o.dir;
      if(o.t>=o.pts.length-1){o.t=o.pts.length-1;o.dir=-1;}
      if(o.t<=0){o.t=0;o.dir=1;}
      const i=Math.floor(o.t),f=o.t-i,a=o.pts[i],b=o.pts[Math.min(i+1,o.pts.length-1)];
      o.m.setLatLng(lerp(a,b,f));
    });
    requestAnimationFrame(tick);
  }
  tick();
  // recalcular tamaño cuando entra en viewport (evita mapa gris)
  const mo=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){map.invalidateSize();}}),{threshold:.1});
  mo.observe(el);
}
window.addEventListener('load',initLiveMap);
</script>
<script src="https://kivox.co/widget.js?token=NaNuLzK7DWESYvSFwEPBpCqQ3qG65Xq3&v=4" defer></script>
@endverbatim
</body>
</html>
