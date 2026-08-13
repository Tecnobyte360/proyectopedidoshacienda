<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<title>Catálogo · {{ $tenant->nombre }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  :root{
    --brand:{{ $colorPrim }}; --brand-2:{{ $colorSec }};
    --tint:color-mix(in srgb, var(--brand) 10%, #fff);
    --tint-2:color-mix(in srgb, var(--brand) 16%, #fff);
    --bg:#f3f5f7; --card:#ffffff; --ink:#111827; --ink-soft:#6b7280;
    --line:#eceef1; --line-2:#e2e6ea;
    --radius:14px;
    --font:'Plus Jakarta Sans',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
    --shadow:0 1px 2px rgba(16,24,40,.04),0 4px 16px rgba(16,24,40,.05);
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{
    background:var(--bg);font-family:var(--font);color:var(--ink);font-size:14px;
    -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;
    max-width:560px;margin:0 auto;padding-bottom:90px;
  }

  /* header */
  header.brand{
    background:linear-gradient(135deg,var(--brand) 0%,var(--brand-2) 100%);
    color:#fff;padding:22px 20px 20px;position:relative;overflow:hidden;
  }
  header.brand::after{content:"";position:absolute;right:-30px;top:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.08)}
  header.brand .row{display:flex;align-items:center;gap:13px;position:relative}
  header.brand .logo{width:56px;height:56px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,.18)}
  header.brand .logo img{width:100%;height:100%;object-fit:cover}
  header.brand h1{font-size:20px;font-weight:800;margin:0;line-height:1.15;letter-spacing:-.01em}
  header.brand p{margin:3px 0 0;font-size:12.5px;font-weight:500;color:rgba(255,255,255,.85)}

  /* buscador */
  .search-wrap{padding:12px 16px 8px;background:var(--bg);position:sticky;top:0;z-index:6}
  .search{display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--line-2);border-radius:12px;padding:11px 14px;box-shadow:var(--shadow)}
  .search i{color:var(--ink-soft);font-size:14px}
  .search input{border:0;outline:0;background:transparent;flex:1;font-size:15px;font-family:var(--font);color:var(--ink);font-weight:500}
  .search input::placeholder{color:#9aa2ac;font-weight:400}

  /* chips */
  .chips{display:flex;gap:8px;overflow-x:auto;padding:6px 16px 12px;background:var(--bg);position:sticky;top:60px;z-index:5;scrollbar-width:none}
  .chips::-webkit-scrollbar{display:none}
  .chip{flex:0 0 auto;border:1px solid var(--line-2);background:var(--card);color:var(--ink-soft);font-weight:600;font-size:13px;padding:8px 15px;border-radius:999px;cursor:pointer;white-space:nowrap;transition:.15s;display:flex;align-items:center;gap:7px;font-family:var(--font)}
  .chip i{font-size:12px}
  .chip[aria-selected="true"]{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 3px 10px color-mix(in srgb,var(--brand) 35%,transparent)}

  /* secciones */
  main{padding:2px 16px 8px}
  .cat{margin-top:18px}
  .cat-head{display:flex;align-items:center;gap:11px;margin-bottom:12px}
  .cat-head .ic{width:32px;height:32px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--tint);color:var(--brand-2);font-size:15px}
  .cat-head h2{font-size:16px;font-weight:700;margin:0;letter-spacing:-.01em}
  .cat-head .count{margin-left:auto;font-size:11px;color:var(--ink-soft);background:var(--card);border:1px solid var(--line);padding:3px 9px;border-radius:999px;font-weight:600}

  .grid{display:flex;flex-direction:column;gap:9px}
  .item{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:11px 13px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow);transition:border-color .15s}
  .item.incart{border-color:var(--brand);box-shadow:0 0 0 1px var(--brand),var(--shadow)}
  .item .thumb{position:relative;width:46px;height:46px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--tint);color:var(--brand-2);font-size:17px;overflow:hidden}
  .item .thumb .ph{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;background:#fff}
  .item .body{flex:1;min-width:0}
  .item .name{font-weight:600;font-size:14px;line-height:1.25;letter-spacing:-.005em;color:var(--ink)}
  .item .meta{display:flex;align-items:center;gap:8px;margin-top:3px}
  .item .price{font-weight:700;font-size:14.5px;color:var(--brand-2);font-variant-numeric:tabular-nums;letter-spacing:-.01em}
  .item .unit{font-size:11.5px;color:var(--ink-soft);font-weight:500}
  .item .tag{font-size:10px;font-weight:700;color:#b7791f;background:#fef6e7;padding:2px 7px;border-radius:6px;letter-spacing:.02em}
  .plus{width:34px;height:34px;border-radius:10px;flex-shrink:0;border:0;cursor:pointer;background:var(--brand);color:#fff;font-size:15px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px color-mix(in srgb,var(--brand) 40%,transparent);transition:.12s}
  .plus:active{transform:scale(.92)}
  .stepper{display:flex;align-items:center;gap:9px;flex-shrink:0;background:var(--tint);border-radius:10px;padding:3px}
  .stepper button{width:28px;height:28px;border-radius:8px;border:0;background:var(--card);color:var(--brand-2);font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow)}
  .stepper .qty{font-weight:700;font-variant-numeric:tabular-nums;min-width:18px;text-align:center;font-size:14px;color:var(--brand-2)}

  .empty{text-align:center;color:var(--ink-soft);padding:48px 20px;font-size:14px;display:none}
  .empty i{font-size:26px;color:#c7ccd2;display:block;margin-bottom:10px}
  footer.sig{text-align:center;font-size:11px;color:#9aa2ac;padding:18px 0 8px;font-weight:500}

  /* barra carrito */
  .cartbar{position:fixed;left:0;right:0;bottom:0;max-width:560px;margin:0 auto;padding:10px 16px calc(12px + env(safe-area-inset-bottom));background:linear-gradient(180deg,transparent,var(--bg) 30%);transform:translateY(130%);transition:transform .28s cubic-bezier(.2,.7,.2,1);z-index:20}
  .cartbar.show{transform:translateY(0)}
  .cartbar button{width:100%;border:0;border-radius:14px;padding:14px 16px;cursor:pointer;font-family:var(--font);background:linear-gradient(135deg,var(--brand),var(--brand-2));color:#fff;font-weight:700;font-size:14.5px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 22px color-mix(in srgb,var(--brand) 45%,transparent)}
  .cartbar .left{display:flex;align-items:center;gap:11px}
  .cartbar .badge{background:rgba(255,255,255,.22);border-radius:8px;min-width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;padding:0 7px;font-size:13px;font-weight:800}
  .cartbar .total{font-variant-numeric:tabular-nums;font-size:15px}
  .cartbar i{font-size:16px}
  .wa-note{position:fixed;bottom:0;left:0;right:0;max-width:560px;margin:0 auto;background:#fff4e5;color:#8a5a1e;font-size:12px;text-align:center;padding:9px;display:none;font-weight:600}
</style>
</head>
<body>
  <header class="brand">
    <div class="row">
      <div class="logo">@if(!empty($logoUrl))<img src="{{ $logoUrl }}" alt="Logo {{ $tenant->nombre }}">@else<i class="fa-solid fa-drumstick-bite"></i>@endif</div>
      <div>
        <h1>{{ $tenant->nombre }}</h1>
        <p>Arma tu pedido y envíalo por WhatsApp</p>
      </div>
    </div>
  </header>

  <div class="search-wrap">
    <label class="search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input id="q" type="text" placeholder="Buscar producto…" autocomplete="off">
    </label>
  </div>

  <nav class="chips" id="chips"></nav>
  <main id="list"></main>
  <div class="empty" id="empty"><i class="fa-solid fa-magnifying-glass"></i>Sin resultados</div>
  <footer class="sig">Catálogo en vivo · {{ $tenant->nombre }}</footer>

  <div class="cartbar" id="cartbar">
    <button onclick="enviarPedido()">
      <span class="left"><span class="badge" id="cartCount">0</span><i class="fa-brands fa-whatsapp"></i> Enviar pedido</span>
      <span class="total" id="cartTotal">$0</span>
    </button>
  </div>
  <div class="wa-note" id="waNote">Este catálogo aún no tiene número de WhatsApp configurado.</div>

<script>
const TENANT=@json($tenant->nombre), WA=@json($waNumero), CATS=@json($categorias), PRODS=@json($productos);
const FA={RES:'fa-cow',CERDO:'fa-bacon',POLLO:'fa-drumstick-bite',PESCADO:'fa-fish',EMBUTIDOS:'fa-hotdog',ABARROTES:'fa-basket-shopping'};
function faFor(name){const u=(name||'').toUpperCase();for(const k in FA){if(u.includes(k))return FA[k];}return 'fa-utensils';}
const fmt=n=>'$'+Math.round(n).toLocaleString('es-CO');
const cart={}, prodById={}; PRODS.forEach(p=>prodById[p.id]=p);
const catName={}; CATS.forEach(c=>catName[c.id]=c.n);

const chipsEl=document.getElementById('chips');
const DEST=PRODS.some(p=>p.d);
let active=DEST?'DEST':(CATS[0]?.id ?? null);
function buildChips(){
  const all=[]; if(DEST)all.push({id:'DEST',n:'Destacados',fa:'fa-star'});
  CATS.forEach(c=>all.push({id:c.id,n:c.n,fa:faFor(c.n)}));
  chipsEl.innerHTML='';
  all.forEach(c=>{
    const b=document.createElement('button');
    b.className='chip';b.setAttribute('aria-selected',c.id===active);
    b.innerHTML=`<i class="fa-solid ${c.fa}"></i><span>${c.n}</span>`;
    b.onclick=()=>{active=c.id;document.getElementById('q').value='';render();[...chipsEl.children].forEach((el,i)=>el.setAttribute('aria-selected',all[i].id===active));window.scrollTo({top:document.querySelector('main').offsetTop-6,behavior:'smooth'});};
    chipsEl.appendChild(b);
  });
}
const listEl=document.getElementById('list'), emptyEl=document.getElementById('empty');
function itemHTML(p){
  const inc=cart[p.id]>0;
  const thumb=`<i class="fa-solid ${faFor(catName[p.cid])}"></i>`+(p.img?`<img class="ph" loading="lazy" decoding="async" src="${p.img}" alt="" onerror="this.remove()">`:'');
  const ctrl=inc
    ?`<div class="stepper"><button onclick="dec(${p.id})">−</button><span class="qty" id="q${p.id}">${cart[p.id]}</span><button onclick="inc(${p.id})">+</button></div>`
    :`<button class="plus" onclick="inc(${p.id})"><i class="fa-solid fa-plus"></i></button>`;
  return `<div class="item ${inc?'incart':''}" id="it${p.id}">
    <div class="thumb">${thumb}</div>
    <div class="body">
      <div class="name">${p.n}</div>
      <div class="meta"><span class="price">${fmt(p.pr)}</span><span class="unit">/ ${p.u}</span>${p.d?'<span class="tag">Destacado</span>':''}</div>
    </div>${ctrl}</div>`;
}
function section(icon,titulo,items){
  return `<section class="cat"><div class="cat-head"><div class="ic"><i class="fa-solid ${icon}"></i></div><h2>${titulo}</h2><span class="count">${items.length}</span></div><div class="grid">${items.map(itemHTML).join('')}</div></section>`;
}
function render(){
  const q=(document.getElementById('q').value||'').trim().toLowerCase();
  if(q){
    const res=PRODS.filter(p=>p.n.toLowerCase().includes(q));
    emptyEl.style.display=res.length?'none':'block';
    listEl.innerHTML=res.length?section('fa-magnifying-glass','Resultados',res):'';
    return;
  }
  emptyEl.style.display='none';
  if(active==='DEST'){listEl.innerHTML=section('fa-star','Destacados',PRODS.filter(p=>p.d));return;}
  const cat=CATS.find(c=>c.id===active)||CATS[0];
  listEl.innerHTML=section(faFor(cat.n),cat.n,PRODS.filter(p=>p.cid===cat.id));
}
function inc(id){cart[id]=(cart[id]||0)+1;refresh(id);}
function dec(id){cart[id]=(cart[id]||0)-1;if(cart[id]<=0)delete cart[id];refresh(id);}
function refresh(id){const el=document.getElementById('it'+id);if(el)el.outerHTML=itemHTML(prodById[id]);updateCart();}
function updateCart(){
  const ids=Object.keys(cart);
  const count=ids.reduce((s,id)=>s+cart[id],0);
  const total=ids.reduce((s,id)=>s+cart[id]*prodById[id].pr,0);
  document.getElementById('cartCount').textContent=count;
  document.getElementById('cartTotal').textContent=fmt(total);
  document.getElementById('cartbar').classList.toggle('show',count>0);
}
function enviarPedido(){
  const ids=Object.keys(cart);if(!ids.length)return;
  if(!WA){document.getElementById('waNote').style.display='block';return;}
  let total=0;const lineas=ids.map(id=>{const p=prodById[id],q=cart[id];total+=q*p.pr;return `• ${q} ${p.u} — ${p.n}`;});
  const texto=`Hola ${TENANT} 🥩, quiero hacer un pedido:\n\n${lineas.join('\n')}\n\n*Total aprox: ${fmt(total)}*`;
  window.location.href=`https://wa.me/${WA}?text=${encodeURIComponent(texto)}`;
}
document.getElementById('q').addEventListener('input',render);
buildChips();render();
</script>
</body>
</html>
