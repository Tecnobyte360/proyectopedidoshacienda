<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@verbatim
<style>
:root{
  --cream:#FBFBF7;--cream2:#F4F4EC;--ink:#0D120E;--ink2:#2A332C;
  --green:#0EA26B;--green-d:#0B8659;--forest:#07301F;--forest2:#0A3B27;
  --lime:#C6F45F;--lime-d:#A7E03A;
  --gray:#6C7A70;--line:#E4E6DC;
  --shadow:0 24px 60px rgba(10,20,40,.10);--shadow-l:0 20px 50px rgba(15,23,42,.08);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-font-smoothing:antialiased}
html{scroll-behavior:smooth;overflow-x:hidden;max-width:100%}
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--cream);color:var(--ink);letter-spacing:-.002em;overflow-x:hidden;max-width:100%;width:100%}
a{color:inherit;text-decoration:none}img{max-width:100%}
.disp{font-family:'Space Grotesk',sans-serif;font-weight:700;letter-spacing:-.02em}
section{position:relative;padding:88px 24px}
.wrap{max-width:1150px;margin:0 auto;position:relative;z-index:2}
h2{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(30px,4.2vw,52px);line-height:1.08;letter-spacing:-.022em}
.lead{font-size:clamp(16px,1.7vw,19px);line-height:1.72;color:var(--ink2);max-width:640px;font-weight:500}
.center{text-align:center}.center .lead{margin-left:auto;margin-right:auto}
.num-tag{font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:13px;letter-spacing:.28em;color:var(--green-d);display:inline-flex;align-items:center;gap:14px;margin-bottom:22px;text-transform:uppercase}
.num-tag::after{content:'';flex:0 0 54px;height:1px;background:var(--green-d)}
.soft{background:var(--cream2)}
/* buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:11px;font-weight:800;font-size:15px;border-radius:999px;padding:15px 30px;transition:.22s;cursor:pointer;border:none;font-family:'Plus Jakarta Sans',sans-serif}
.btn .arr{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:11px;transition:.25s}
.btn-ink{background:var(--ink);color:var(--cream)}
.btn-ink .arr{background:var(--lime);color:var(--ink)}
.btn-ink:hover{background:var(--forest);transform:translateY(-2px)}.btn-ink:hover .arr{transform:rotate(45deg)}
.btn-lime{background:var(--lime);color:var(--ink)}.btn-lime .arr{background:var(--ink);color:var(--lime)}
.btn-lime:hover{background:var(--lime-d);transform:translateY(-2px)}.btn-lime:hover .arr{transform:rotate(45deg)}
.btn-line{background:transparent;color:var(--ink);border:1.5px solid #C9CDBE}
.btn-line:hover{border-color:var(--ink);transform:translateY(-2px)}
.btn-sm{padding:11px 22px;font-size:13.5px}
/* header */
header{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(251,251,247,.9);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
header::after{content:'';position:absolute;left:0;bottom:0;height:2px;width:100%;background:linear-gradient(90deg,transparent,var(--green) 22%,var(--lime) 30%,var(--green) 38%,transparent 60%);background-size:200% 100%;opacity:.9;animation:navGlow 5s linear infinite}
@keyframes navGlow{0%{background-position:120% 0}100%{background-position:-80% 0}}
.nav{max-width:1150px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:12px 24px}
.logo{display:flex;align-items:center}
.logo img{height:60px;width:auto}
.nav-links{display:flex;gap:26px;font-size:14px;font-weight:600;color:var(--gray)}
.nav-links a{transition:.2s;display:inline-flex;align-items:center;gap:7px}
.nav-links a:hover,.nav-links a.on{color:var(--green-d)}
@media(max-width:940px){.nav-links{display:none}}
/* subhero */
.subhero{padding:130px 24px 60px;position:relative;overflow:hidden}
.subhero.comm{background:linear-gradient(180deg,#F0FAF5,#FBFBF7 75%)}
.subhero.oper{background:radial-gradient(1000px 600px at 75% -10%,#0E3B27 0%,var(--forest) 60%);color:#EAF6EE}
.subhero .wrap{display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center}
@media(max-width:900px){.subhero .wrap{grid-template-columns:1fr}}
.subhero .eyebrow{display:inline-flex;align-items:center;gap:9px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;border-radius:999px;padding:8px 16px;margin-bottom:20px}
.subhero.comm .eyebrow{background:#fff;color:var(--green-d);border:1px solid #C9EBDD}
.subhero.oper .eyebrow{background:rgba(198,244,95,.12);color:var(--lime);border:1px solid rgba(198,244,95,.3)}
.subhero h1{font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:clamp(34px,4.6vw,58px);line-height:1.05;letter-spacing:-.03em}
.subhero.oper h1{color:#fff}
.subhero .sub{font-size:clamp(16px,1.8vw,19px);line-height:1.7;margin-top:20px;font-weight:500}
.subhero.comm .sub{color:var(--ink2)}.subhero.oper .sub{color:#B9CDBF}
.subhero .cta-row{display:flex;gap:13px;margin-top:32px;flex-wrap:wrap}
.subhero .back{display:inline-flex;align-items:center;gap:8px;font-size:13.5px;font-weight:700;color:inherit;opacity:.7;margin-bottom:22px}
.subhero .back:hover{opacity:1}
/* feature rows */
.frow{display:grid;grid-template-columns:1fr 1fr;gap:52px;align-items:center;margin-bottom:20px}
.frow.rev .ftext{order:2}.frow.rev .fvis{order:1}
@media(max-width:880px){.frow{grid-template-columns:1fr;gap:30px}.frow.rev .ftext,.frow.rev .fvis{order:0}}
.ftext h2{margin-bottom:14px}
.flist{list-style:none;margin-top:20px;display:grid;gap:12px}
.flist li{display:flex;gap:12px;font-size:15px;font-weight:600;line-height:1.5;color:var(--ink2)}
.flist li i{color:var(--green);margin-top:3px;flex-shrink:0}
.fvis{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow-l);padding:26px;min-height:220px}
/* chips */
.chips{display:flex;flex-wrap:wrap;gap:11px;margin-top:14px}
.chip{display:inline-flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:12px 20px;font-size:14px;font-weight:700;color:var(--ink2)}
.chip i{color:var(--green)}
/* cta band */
.cta{background:var(--lime);border-radius:28px;max-width:1150px;margin:0 auto;padding:70px 30px;text-align:center;position:relative;overflow:hidden}
.cta::before{content:'';position:absolute;top:-80px;left:-50px;width:280px;height:280px;border-radius:50%;border:1.5px solid rgba(13,18,14,.14)}
.cta h2{color:var(--ink);max-width:760px;margin:0 auto}
.cta p{color:#3D4A34;margin:16px auto 28px;max-width:520px;font-size:16.5px;font-weight:600}
/* footer */
footer{background:var(--ink);color:#B9C4BB;padding:56px 24px 30px;border-radius:32px 32px 0 0;margin-top:40px}
.foot{max-width:1150px;margin:0 auto;display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:36px}
@media(max-width:760px){.foot{grid-template-columns:1fr}}
.foot h4{font-size:12px;font-weight:800;color:#fff;margin-bottom:14px;letter-spacing:.12em;text-transform:uppercase}
.foot a{display:block;font-size:14px;color:#8fa094;margin-bottom:10px;transition:.2s}
.foot a:hover{color:var(--lime)}
.foot-copy{max-width:1150px;margin:34px auto 0;border-top:1px solid rgba(255,255,255,.1);padding-top:20px;text-align:center;font-size:12.5px;color:#71807a}
.reveal{opacity:0;transform:translateY(24px);transition:opacity .7s ease,transform .7s ease}
.reveal.in{opacity:1;transform:none}
.d1{transition-delay:.08s}.d2{transition-delay:.16s}.d3{transition-delay:.24s}
@media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important}}
</style>
@endverbatim
