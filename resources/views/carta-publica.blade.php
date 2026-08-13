<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<title>Carta · {{ $tenant->nombre }}</title>
<style>
  :root{
    --paper:#f4ede0; --paper-2:#efe6d6; --card:#fffaf2;
    --ink:#2a211b; --ink-soft:#6b5d50; --line:#e2d5c1;
    --brand:{{ $colorPrim }}; --brand-2:{{ $colorSec }};
    --amber:#d68643; --amber-soft:#f6e3cf;
    --green:#1f7a3d; --green-2:#155f2e; --gold:#b98a2e;
    --shadow:0 8px 30px rgba(60,35,15,.10);
    --serif:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,"Times New Roman",serif;
    --sans:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{background:var(--paper);font-family:var(--sans);color:var(--ink);-webkit-font-smoothing:antialiased;max-width:640px;margin:0 auto}
  header.brand{
    background:radial-gradient(120% 90% at 50% -20%, rgba(214,134,67,.28), transparent 60%),
      linear-gradient(180deg,var(--brand-2) 0%, var(--brand) 120%);
    color:#fff;padding:26px 22px 22px;text-align:center;
  }
  header.brand .kicker{font-size:11px;letter-spacing:.32em;text-transform:uppercase;color:#ffe6cf;font-weight:700;margin-bottom:8px;opacity:.9}
  header.brand h1{font-family:var(--serif);font-weight:600;font-size:30px;line-height:1.04;margin:0;text-wrap:balance}
  header.brand .rule{width:52px;height:3px;border-radius:3px;margin:12px auto 10px;background:linear-gradient(90deg,var(--amber),#f7d3ab)}
  header.brand p{margin:0;font-size:13px;color:#ffeede;opacity:.95}

  .search-wrap{padding:14px 16px 10px;background:var(--paper);position:sticky;top:0;z-index:5}
  .search{display:flex;align-items:center;gap:9px;background:var(--card);border:1.5px solid var(--line);border-radius:13px;padding:11px 13px;box-shadow:var(--shadow)}
  .search svg{width:17px;height:17px;color:var(--ink-soft);flex-shrink:0}
  .search input{border:0;outline:0;background:transparent;flex:1;font-size:16px;color:var(--ink);font-family:var(--sans)}
  .search input::placeholder{color:#a99a89}

  .chips{display:flex;gap:8px;overflow-x:auto;padding:4px 16px 14px;background:var(--paper);position:sticky;top:64px;z-index:4;scrollbar-width:none}
  .chips::-webkit-scrollbar{display:none}
  .chip{flex:0 0 auto;border:1.5px solid var(--line);background:var(--card);color:var(--ink-soft);font-weight:600;font-size:13px;padding:8px 14px;border-radius:999px;cursor:pointer;white-space:nowrap;transition:.16s;display:flex;align-items:center;gap:6px}
  .chip svg{width:15px;height:15px}
  .chip[aria-selected="true"]{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.18)}

  main{padding:4px 16px 120px}
  .cat{margin-top:22px}
  .cat-head{display:flex;align-items:center;gap:10px;margin-bottom:2px}
  .cat-head .ic{width:34px;height:34px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--amber-soft);color:var(--brand-2)}
  .cat-head .ic svg{width:20px;height:20px}
  .cat-head h2{font-family:var(--serif);font-weight:600;font-size:20px;margin:0}
  .cat-head .count{margin-left:auto;font-size:11.5px;color:var(--ink-soft);background:var(--paper-2);padding:3px 9px;border-radius:999px;font-weight:600}

  .grid{display:flex;flex-direction:column;gap:10px;margin-top:12px}
  .item{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px 14px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 10px rgba(60,35,15,.05)}
  .item.incart{border-color:var(--green);box-shadow:0 0 0 1.5px rgba(31,122,61,.25)}
  .item .thumb{width:44px;height:44px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(150deg,var(--amber-soft),#fff);color:var(--brand-2);border:1px solid var(--line);overflow:hidden}
  .item .thumb svg{width:24px;height:24px;opacity:.85}
  .item .thumb img{width:100%;height:100%;object-fit:cover}
  .item .body{flex:1;min-width:0}
  .item .name{font-weight:650;font-size:14.5px;line-height:1.2}
  .item .desc{font-size:12px;color:var(--ink-soft);margin-top:2px}
  .item .tag{display:inline-block;margin-top:5px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gold)}
  .item .price{font-variant-numeric:tabular-nums;font-weight:750;font-size:15px;color:var(--brand);white-space:nowrap;text-align:right}
  .item .price small{display:block;font-size:9.5px;color:var(--ink-soft);font-weight:600}
  .item .plus{width:32px;height:32px;border-radius:9px;flex-shrink:0;border:0;cursor:pointer;background:var(--green);color:#fff;font-size:20px;line-height:0;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(21,95,46,.3)}
  .stepper{display:flex;align-items:center;gap:8px;flex-shrink:0}
  .stepper button{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--line);background:#fff;color:var(--brand);font-size:18px;line-height:0;cursor:pointer;display:flex;align-items:center;justify-content:center}
  .stepper .qty{font-weight:750;font-variant-numeric:tabular-nums;min-width:20px;text-align:center;font-size:15px}

  .empty{text-align:center;color:var(--ink-soft);padding:40px 20px;font-size:14px;display:none}

  .cartbar{position:fixed;left:0;right:0;bottom:0;max-width:640px;margin:0 auto;padding:12px 16px calc(14px + env(safe-area-inset-bottom));background:linear-gradient(180deg,transparent,var(--paper) 26%);transform:translateY(120%);transition:transform .25s ease;z-index:20}
  .cartbar.show{transform:translateY(0)}
  .cartbar button{width:100%;border:0;border-radius:14px;padding:14px 18px;cursor:pointer;background:linear-gradient(180deg,var(--green),var(--green-2));color:#fff;font-weight:750;font-size:15px;font-family:var(--sans);display:flex;align-items:center;justify-content:space-between;gap:10px;box-shadow:0 10px 24px rgba(21,95,46,.4)}
  .cartbar .left{display:flex;align-items:center;gap:10px}
  .cartbar .badge{background:rgba(255,255,255,.25);border-radius:8px;padding:3px 9px;font-size:13px}
  .cartbar .total{font-variant-numeric:tabular-nums}
  .cartbar svg{width:19px;height:19px}
  footer.sig{text-align:center;font-size:11px;color:var(--ink-soft);padding:14px 0 100px;opacity:.7}
  .disabled-note{position:fixed;bottom:0;left:0;right:0;max-width:640px;margin:0 auto;background:#f6e3cf;color:#8a5a1e;font-size:12px;text-align:center;padding:8px;display:none}
</style>
</head>
<body>
  <header class="brand">
    <div class="kicker">Distribuidora de Alimentos</div>
    <h1>{{ $tenant->nombre }}</h1>
    <div class="rule"></div>
    <p>Elige tus productos y envía tu pedido por WhatsApp</p>
  </header>

  <div class="search-wrap">
    <label class="search">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.2-3.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <input id="q" type="text" placeholder="Buscar producto…" autocomplete="off">
    </label>
  </div>

  <nav class="chips" id="chips"></nav>
  <main id="list"></main>
  <div class="empty" id="empty">Sin resultados 🥩</div>
  <footer class="sig">Catálogo en vivo · {{ $tenant->nombre }}</footer>

  <div class="cartbar" id="cartbar">
    <button onclick="enviarPedido()">
      <span class="left">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.76.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 004.79 1.22c5.46 0 9.9-4.45 9.9-9.91C21.95 6.45 17.5 2 12.04 2z"/></svg>
        <span class="badge" id="cartCount">0</span> Enviar pedido
      </span>
      <span class="total" id="cartTotal">$0</span>
    </button>
  </div>
  <div class="disabled-note" id="waNote">Este catálogo aún no tiene número de WhatsApp configurado.</div>

<script>
const TENANT = @json($tenant->nombre);
const WA = @json($waNumero);
const CATS = @json($categorias);
const PRODS = @json($productos);

const ICONS = {
  RES:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 8c0-2 2-3 4-3 1.5 0 2 1 4 1s2.5-1 4-1c2 0 4 1 4 3 0 1.5-1.2 2-1.2 3.5S22 15 22 17c0 2-2 3-4 3-1.5 0-2-1-6-1s-4.5 1-6 1c-2 0-4-1-4-3 0-2 1.2-2 1.2-5.5C3.4 10 4 9.5 4 8z"/></svg>',
  CERDO:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12c0-3.3 3.6-6 8-6 1.3 0 2.5.2 3.6.6L18 5l1 3.2c1.2 1 2 2.3 2 3.8 0 3.3-3.6 6-8 6s-10-2.7-10-6z"/><circle cx="9" cy="12" r="1"/><circle cx="14" cy="12" r="1"/></svg>',
  POLLO:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 4a3 3 0 013 3c1.7.5 3 2 3 4 0 2.5-2 4.5-5 5l-1 4H8l-1-3c-2.5-.6-4-2.4-4-4.5C3 9 6 6 10 6c0-1.1 1.8-2 4-2z"/></svg>',
  PESCADO:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12c3-5 9-6 13-4s5 4 5 4-1 2-5 4-10 1-13-4z"/><path d="M16 12h.01"/></svg>',
  EMBUTIDOS:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20c8 0 16-8 16-16"/><path d="M4 20c-1 0-2-1-2-2s1-2 2-2 2 1 2 2-1 2-2 2z"/></svg>',
  DEFAULT:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V6a3 3 0 016 0v2"/></svg>'
};
function catIcon(name){
  const u=(name||'').toUpperCase();
  for(const k of Object.keys(ICONS)){ if(k!=='DEFAULT' && u.includes(k)) return ICONS[k]; }
  return ICONS.DEFAULT;
}
const fmt = n => '$'+Math.round(n).toLocaleString('es-CO');
const cart = {};              // id -> qty
const prodById = {}; PRODS.forEach(p=>prodById[p.id]=p);

// ---- chips ----
const chipsEl=document.getElementById('chips');
let active = CATS.length ? CATS[0].id : null;
const DEST = PRODS.some(p=>p.d);
function buildChips(){
  chipsEl.innerHTML='';
  const all=[];
  if(DEST) all.push({id:'DEST', n:'Destacados'});
  CATS.forEach(c=>all.push({id:c.id, n:c.n}));
  if(DEST && active===CATS[0]?.id) active='DEST';
  all.forEach(c=>{
    const b=document.createElement('button');
    b.className='chip'; b.setAttribute('aria-selected', c.id===active);
    b.innerHTML=catIcon(c.n)+'<span>'+c.n+'</span>';
    b.onclick=()=>{active=c.id;document.getElementById('q').value='';render();[...chipsEl.children].forEach((el,i)=>el.setAttribute('aria-selected', all[i].id===active));window.scrollTo({top:document.querySelector('main').offsetTop-8,behavior:'smooth'});};
    chipsEl.appendChild(b);
  });
}

const listEl=document.getElementById('list');
const emptyEl=document.getElementById('empty');
function itemHTML(p){
  const inc = cart[p.id]>0;
  const thumb = p.img ? `<img src="${p.img}" alt="">` : catIcon((CATS.find(c=>c.id===p.cid)||{}).n);
  const ctrl = inc
    ? `<div class="stepper"><button onclick="dec(${p.id})">−</button><span class="qty" id="q${p.id}">${cart[p.id]}</span><button onclick="inc(${p.id})">+</button></div>`
    : `<button class="plus" onclick="inc(${p.id})">+</button>`;
  return `<div class="item ${inc?'incart':''}" id="it${p.id}">
    <div class="thumb">${thumb}</div>
    <div class="body">
      <div class="name">${p.n}</div>
      ${p.ds?`<div class="desc">${p.ds}</div>`:''}
      ${p.d?`<span class="tag">★ Destacado</span>`:''}
    </div>
    <div class="price">${fmt(p.pr)}<small>x ${p.u}</small></div>
    ${ctrl}
  </div>`;
}
function render(){
  const q=(document.getElementById('q').value||'').trim().toLowerCase();
  let html='';
  if(q){
    const res=PRODS.filter(p=>p.n.toLowerCase().includes(q));
    emptyEl.style.display=res.length?'none':'block';
    html=`<section class="cat"><div class="cat-head"><div class="ic">${ICONS.DEFAULT}</div><h2>Resultados</h2><span class="count">${res.length}</span></div><div class="grid">${res.map(itemHTML).join('')}</div></section>`;
    listEl.innerHTML=html; return;
  }
  emptyEl.style.display='none';
  let items, titulo, cnt;
  if(active==='DEST'){ items=PRODS.filter(p=>p.d); titulo='Destacados'; cnt=items.length;
    html=`<section class="cat"><div class="cat-head"><div class="ic">${ICONS.RES}</div><h2>Destacados</h2><span class="count">${cnt}</span></div><div class="grid">${items.map(itemHTML).join('')}</div></section>`;
  } else {
    const cat=CATS.find(c=>c.id===active)||CATS[0];
    items=PRODS.filter(p=>p.cid===cat.id);
    html=`<section class="cat"><div class="cat-head"><div class="ic">${catIcon(cat.n)}</div><h2>${cat.n}</h2><span class="count">${items.length}</span></div><div class="grid">${items.map(itemHTML).join('')}</div></section>`;
  }
  listEl.innerHTML=html;
}
function inc(id){ cart[id]=(cart[id]||0)+1; updateItem(id); updateCart(); }
function dec(id){ cart[id]=(cart[id]||0)-1; if(cart[id]<=0) delete cart[id]; updateItem(id); updateCart(); }
function updateItem(id){ const el=document.getElementById('it'+id); if(el) el.outerHTML=itemHTML(prodById[id]); }
function updateCart(){
  const ids=Object.keys(cart);
  const count=ids.reduce((s,id)=>s+cart[id],0);
  const total=ids.reduce((s,id)=>s+cart[id]*prodById[id].pr,0);
  document.getElementById('cartCount').textContent=count;
  document.getElementById('cartTotal').textContent=fmt(total);
  document.getElementById('cartbar').classList.toggle('show', count>0);
}
function enviarPedido(){
  const ids=Object.keys(cart);
  if(!ids.length) return;
  if(!WA){ document.getElementById('waNote').style.display='block'; return; }
  let total=0;
  const lineas=ids.map(id=>{const p=prodById[id];const q=cart[id];total+=q*p.pr;return `• ${q} ${p.u} — ${p.n}`;});
  const texto=`Hola ${TENANT} 🥩, quiero hacer un pedido:\n\n${lineas.join('\n')}\n\n*Total aprox: ${fmt(total)}*`;
  window.location.href=`https://wa.me/${WA}?text=${encodeURIComponent(texto)}`;
}
document.getElementById('q').addEventListener('input',render);
buildChips(); render();
</script>
</body>
</html>
