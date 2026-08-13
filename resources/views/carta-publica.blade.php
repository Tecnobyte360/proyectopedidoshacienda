<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
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
    background:linear-gradient(135deg,#2b2b2b 0%,#0b0b0b 100%);
    color:#fff;padding:22px 20px 20px;position:relative;overflow:hidden;
    border-bottom:3px solid var(--brand);
  }
  header.brand::after{content:"";position:absolute;right:-40px;top:-40px;width:150px;height:150px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,var(--brand) 35%,transparent),transparent 70%)}
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

  /* ── modal checkout ── */
  .modal{position:fixed;inset:0;z-index:50;display:none}
  .modal.open{display:block}
  .modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55)}
  .sheet{position:absolute;left:0;right:0;bottom:0;max-width:560px;margin:0 auto;background:var(--bg);border-radius:20px 20px 0 0;max-height:94vh;overflow-y:auto;animation:slideup .26s cubic-bezier(.2,.7,.2,1)}
  @keyframes slideup{from{transform:translateY(100%)}to{transform:translateY(0)}}
  .sheet-head{position:sticky;top:0;z-index:2;background:linear-gradient(135deg,#2b2b2b,#0b0b0b);color:#fff;padding:15px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid var(--brand)}
  .sheet-head h3{margin:0;font-size:16px;font-weight:700}
  .sheet-head .x{background:rgba(255,255,255,.15);border:0;color:#fff;width:30px;height:30px;border-radius:8px;font-size:15px;cursor:pointer}
  .sheet-body{padding:16px 18px calc(20px + env(safe-area-inset-bottom))}
  .co-sum{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:11px 14px;margin-bottom:6px}
  .co-sum .li{display:flex;justify-content:space-between;gap:10px;font-size:12.5px;padding:2px 0;color:var(--ink-soft)}
  .co-sum .li b{color:var(--ink);font-weight:600}
  .co-sum .tot{display:flex;justify-content:space-between;font-weight:800;font-size:15px;margin-top:8px;padding-top:8px;border-top:1px dashed var(--line-2);color:var(--ink)}
  .co-label{font-size:12.5px;font-weight:700;color:var(--ink);margin:14px 0 6px}
  .co-input{width:100%;border:1.5px solid var(--line-2);border-radius:11px;padding:12px 14px;font-size:15px;font-family:var(--font);outline:0;color:var(--ink);background:var(--card)}
  .co-input:focus{border-color:var(--brand)}
  .co-btn{width:100%;border:0;border-radius:12px;padding:13px;font-weight:700;font-size:14.5px;font-family:var(--font);cursor:pointer;background:var(--brand);color:#fff;margin-top:12px;display:flex;align-items:center;justify-content:center;gap:8px}
  .co-btn.dark{background:#111}
  .co-btn.wa{background:linear-gradient(135deg,#1f9d4d,#137a38)}
  .co-btn:disabled{opacity:.5;cursor:default}
  .co-hint{font-size:12.5px;padding:10px 12px;border-radius:10px;margin-top:10px;line-height:1.4}
  .co-hint.ok{background:#e9f9ef;color:#12703a}
  .co-hint.warn{background:#fff4e5;color:#8a5a1e}
  .co-hint.err{background:#fdecec;color:#b23b3b}
  .hiddenx{display:none}
  .spin{width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:sp .7s linear infinite;display:inline-block;vertical-align:-2px}
  @keyframes sp{to{transform:rotate(360deg)}}

  /* ── desplegable de Google Places (limpio, sin íconos rotos) ── */
  .pac-container{z-index:99999;border-radius:12px;margin-top:6px;border:1px solid var(--line-2);box-shadow:0 10px 30px rgba(0,0,0,.15);font-family:var(--font);background:#fff;overflow:hidden}
  .pac-icon,.pac-item img{display:none !important}
  .pac-item{padding:11px 14px;font-size:14px;color:var(--ink);border-top:1px solid var(--line);cursor:pointer;line-height:1.3}
  .pac-item:first-child{border-top:0}
  .pac-item:hover,.pac-item-selected{background:var(--tint)}
  .pac-item-query{font-size:14px;color:var(--ink);font-weight:600}
  .pac-matched{font-weight:700}
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

  {{-- ── Modal checkout ── --}}
  <div class="modal" id="checkout">
    <div class="backdrop" onclick="cerrarCheckout()"></div>
    <div class="sheet">
      <div class="sheet-head">
        <h3>Confirma tu pedido</h3>
        <button class="x" onclick="cerrarCheckout()">✕</button>
      </div>
      <div class="sheet-body">
        <div class="co-sum" id="coSum"></div>

        {{-- Paso 1: cédula (se valida sola al salir del campo) --}}
        <div id="coCedulaWrap">
          <div class="co-label">Tu número de cédula o NIT</div>
          <input class="co-input" id="coCedula" inputmode="numeric" autocomplete="off" placeholder="Escríbela y toca fuera para continuar">
          <div class="co-hint" id="coCedulaHint" style="display:none"></div>
        </div>

        {{-- Paso 2: datos + dirección (cobertura se valida sola) --}}
        <div id="coDatos" class="hiddenx">
          <div class="co-hint ok" id="coSaludo" style="display:none"></div>
          <div class="co-label">Nombre completo</div>
          <input class="co-input" id="coNombre" placeholder="Nombre y apellido">
          <div class="co-label">Celular de contacto</div>
          <input class="co-input" id="coCel" inputmode="numeric" placeholder="30xxxxxxxx">
          <div class="co-label">Dirección de entrega</div>
          <input class="co-input" id="coDir" placeholder="Escribe y elige tu dirección">
          <div class="co-hint" id="coCobHint" style="display:none"></div>
          <button class="co-btn wa" id="coEnviar" onclick="enviarFinal()"><i class="fa-brands fa-whatsapp"></i> Enviar pedido</button>
        </div>
      </div>
    </div>
  </div>

<script>
const SLUG=@json($tenant->slug);
const CSRF=document.querySelector('meta[name=csrf-token]').content;
const MAPS_KEY=@json($mapsKey ?? null);
let CLIENTE={existe:null,nombre:'',telefono:'',direccion:''};
let COBERTURA=null;
let PLACE_COORDS=null; // lat/lng cuando el cliente elige una sugerencia de Google
</script>
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
function pedidoLineas(){
  const ids=Object.keys(cart);let total=0;
  const lineas=ids.map(id=>{const p=prodById[id],q=cart[id];total+=q*p.pr;return {t:`• ${q} ${p.u} — ${p.n}`,sub:q*p.pr,n:p.n,q,u:p.u};});
  return {lineas,total};
}
// El botón del carrito ahora abre el checkout (no manda directo a WhatsApp).
function enviarPedido(){ if(!Object.keys(cart).length)return; abrirCheckout(); }
function abrirCheckout(){
  const {lineas,total}=pedidoLineas();
  document.getElementById('coSum').innerHTML=lineas.map(l=>`<div class="li"><b>${l.q} ${l.u}</b> ${l.n}<span style="margin-left:auto;white-space:nowrap">${fmt(l.sub)}</span></div>`).join('')+`<div class="tot"><span>Total productos</span><span>${fmt(total)}</span></div>`;
  document.getElementById('checkout').classList.add('open');document.body.style.overflow='hidden';
}
function cerrarCheckout(){document.getElementById('checkout').classList.remove('open');document.body.style.overflow='';}
async function apiCarta(path,body){
  const r=await fetch(`/carta/${SLUG}/${path}`,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},body:JSON.stringify(body)});
  return r.json();
}
const SPIN='<span class="spin" style="border-top-color:var(--brand);border-color:rgba(0,0,0,.15)"></span>';
async function verificarCedula(){
  const ced=(document.getElementById('coCedula').value||'').replace(/\D+/g,'');
  const hint=document.getElementById('coCedulaHint');
  if(ced.length<5){hint.style.display='none';return;}
  if(ced===CLIENTE.cedula)return; // ya verificada
  hint.className='co-hint';hint.style.display='block';hint.innerHTML=SPIN+' Consultando en HGI…';
  let res;try{res=await apiCarta('cliente',{cedula:ced});}catch(e){res={ok:false};}
  if(!res.ok){hint.className='co-hint err';hint.textContent=res.msg||'No se pudo consultar. Intenta de nuevo.';return;}
  CLIENTE.cedula=ced;CLIENTE.existe=!!res.existe;
  document.getElementById('coDatos').classList.remove('hiddenx');
  const saludo=document.getElementById('coSaludo');
  if(res.existe){
    hint.style.display='none';
    saludo.innerHTML=`¡Hola <b>${(res.nombre||'').split(' ')[0]||''}</b>! 👋 Ya estás registrado. Confirma tus datos:`;saludo.style.display='block';
    document.getElementById('coNombre').value=res.nombre||'';
    document.getElementById('coCel').value=res.telefono||'';
    document.getElementById('coDir').value=res.direccion||'';
  }else{
    hint.className='co-hint';hint.innerHTML='Cliente nuevo — completa tus datos 👇';hint.style.display='block';
    saludo.style.display='none';
  }
  document.getElementById('coDatos').scrollIntoView({behavior:'smooth',block:'nearest'});
}
let COB_LAST='';
async function validarCobertura(){
  const dir=(document.getElementById('coDir').value||'').trim();
  const hint=document.getElementById('coCobHint');
  if(dir.length<5)return;
  if(dir===COB_LAST)return; // no revalidar lo mismo
  COB_LAST=dir;
  hint.className='co-hint';hint.style.display='block';hint.innerHTML=SPIN+' Validando cobertura…';
  const body={direccion:dir};
  if(PLACE_COORDS){body.lat=PLACE_COORDS.lat;body.lng=PLACE_COORDS.lng;}
  let res;try{res=await apiCarta('cobertura',body);}catch(e){res={ok:false};}
  if(!res.ok){hint.className='co-hint err';hint.textContent=res.msg||'No se pudo validar.';COB_LAST='';return;}
  if(res.cubierta){
    COBERTURA={cubierta:true,costo:res.costo_envio,tiempo:res.tiempo_estimado};
    let t='✅ ¡Tenemos cobertura!';
    if(res.costo_envio!=null)t+=` Envío: <b>${fmt(res.costo_envio)}</b>`;
    if(res.tiempo_estimado)t+=` · ~${res.tiempo_estimado} min`;
    hint.className='co-hint ok';hint.innerHTML=t;hint.style.display='block';
  }else{
    COBERTURA={cubierta:false};
    hint.className='co-hint warn';hint.innerHTML=res.mensaje||'😕 Esa dirección está fuera de cobertura. Puedes recoger en sede.';hint.style.display='block';
  }
}
function enviarFinal(){
  const nombre=(document.getElementById('coNombre').value||'').trim();
  const cel=(document.getElementById('coCel').value||'').trim();
  const dir=(document.getElementById('coDir').value||'').trim();
  if(!WA){document.getElementById('waNote').style.display='block';return;}
  const {lineas,total}=pedidoLineas();
  let txt=`Hola ${TENANT} 🥩, quiero hacer un pedido:\n\n${lineas.map(l=>l.t).join('\n')}\n\n*Total productos: ${fmt(total)}*\n\n👤 *Mis datos:*`;
  if(CLIENTE.cedula)txt+=`\nCédula: ${CLIENTE.cedula}`;
  if(nombre)txt+=`\nNombre: ${nombre}`;
  if(cel)txt+=`\nCelular: ${cel}`;
  if(dir)txt+=`\nDirección: ${dir}`;
  if(COBERTURA&&COBERTURA.cubierta&&COBERTURA.costo!=null)txt+=`\nEnvío: ${fmt(COBERTURA.costo)}`;
  window.location.href=`https://wa.me/${WA}?text=${encodeURIComponent(txt)}`;
}
// 🔎 Cédula: se valida sola al salir del campo o con Enter.
document.getElementById('coCedula').addEventListener('blur',verificarCedula);
document.getElementById('coCedula').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();e.target.blur();}});
// 📍 Dirección: al editar a mano invalidamos coords previas; al salir, se valida sola.
document.getElementById('coDir').addEventListener('input',()=>{PLACE_COORDS=null;COB_LAST='';});
document.getElementById('coDir').addEventListener('blur',()=>{setTimeout(validarCobertura,300);});

// 🗺️ Google Places Autocomplete + auto-validación al elegir una sugerencia.
let CARTA_AUTOCOMPLETE=null;
window.initCartaMaps=function(){
  try{
    const input=document.getElementById('coDir');
    CARTA_AUTOCOMPLETE=new google.maps.places.Autocomplete(input,{
      componentRestrictions:{country:'co'},
      fields:['formatted_address','geometry'],
      types:['address'],
    });
    CARTA_AUTOCOMPLETE.addListener('place_changed',()=>{
      const place=CARTA_AUTOCOMPLETE.getPlace();
      if(!place||!place.geometry){PLACE_COORDS=null;return;}
      if(place.formatted_address)input.value=place.formatted_address;
      PLACE_COORDS={lat:place.geometry.location.lat(),lng:place.geometry.location.lng()};
      validarCobertura(); // ← auto-valida, sin botón
    });
    // El pop-up de Google debe quedar por encima del modal
    const obs=new MutationObserver(()=>{document.querySelectorAll('.pac-container').forEach(el=>el.style.zIndex=99999);});
    obs.observe(document.body,{childList:true});
  }catch(e){console.warn('Places no disponible:',e);}
};
document.getElementById('q').addEventListener('input',render);
buildChips();render();
</script>
@if(!empty($mapsKey))
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&callback=initCartaMaps&language=es&region=CO"></script>
@endif
</body>
</html>
