<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Asistente de Ventas · SAP</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@verbatim
<style>
:root{--ink:#0D120E;--green:#0EA26B;--green-d:#0B8659;--forest:#07301F;--lime:#C6F45F;--cream:#FBFBF7;--line:#E4E6DC;--gray:#6C7A70}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Plus Jakarta Sans',system-ui,sans-serif}
body{background:var(--cream);color:var(--ink);height:100vh;display:flex;flex-direction:column}
header{background:var(--forest);color:#fff;padding:16px 22px;display:flex;align-items:center;gap:13px}
header .ic{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--green),var(--green-d));display:grid;place-items:center;font-size:18px}
header b{font-family:'Space Grotesk',sans-serif;font-size:16px;display:block;line-height:1.2}
header small{font-size:12px;color:#bfe9d3}
header .live{margin-left:auto;font-size:11px;font-weight:800;color:var(--lime);background:rgba(198,244,95,.14);border:1px solid rgba(198,244,95,.35);border-radius:99px;padding:5px 12px}
main{flex:1;overflow-y:auto;padding:24px 16px}
.wrap{max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:14px}
.msg{display:flex;gap:11px;max-width:88%}
.msg .av{width:32px;height:32px;border-radius:9px;flex:none;display:grid;place-items:center;font-size:13px;color:#fff}
.msg.bot .av{background:var(--green-d)}
.msg.user{margin-left:auto;flex-direction:row-reverse}
.msg.user .av{background:var(--ink)}
.msg .bub{background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px 15px;font-size:14.5px;line-height:1.55;white-space:pre-wrap}
.msg.user .bub{background:var(--ink);color:#fff;border-color:var(--ink)}
.msg .tools{font-size:11px;color:var(--gray);margin-top:6px}
.msg .tools i{color:var(--green)}
.hint{text-align:center;color:var(--gray);font-size:13px;margin:22px 0}
.chips{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:12px}
.chip{background:#fff;border:1px solid var(--line);border-radius:99px;padding:8px 15px;font-size:12.5px;font-weight:600;cursor:pointer;transition:.15s}
.chip:hover{border-color:var(--green);color:var(--green-d)}
footer{border-top:1px solid var(--line);background:#fff;padding:14px 16px}
.inbar{max-width:820px;margin:0 auto;display:flex;gap:10px}
.inbar input{flex:1;border:1.5px solid var(--line);border-radius:12px;padding:13px 16px;font-size:14.5px;outline:none}
.inbar input:focus{border-color:var(--green)}
.inbar button{background:var(--green-d);color:#fff;border:none;border-radius:12px;width:50px;font-size:16px;cursor:pointer;transition:.15s}
.inbar button:hover{background:var(--green)}
.inbar button:disabled{opacity:.5;cursor:not-allowed}
.typing{display:inline-flex;gap:4px}
.typing i{width:7px;height:7px;border-radius:50%;background:var(--gray);animation:tp 1s infinite}
.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}
@keyframes tp{50%{opacity:.3;transform:translateY(-3px)}}
</style>
@endverbatim
</head>
<body>
<header>
  <span class="ic"><i class="fa-solid fa-chart-line"></i></span>
  <div><b>Asistente de Ventas</b><small>Integrado con SAP Business One · en tiempo real</small></div>
  <span class="live">VENTAS</span>
</header>

<main id="main">
  <div class="wrap" id="chat">
    <div class="hint">
      Pregúntame sobre <b>cotizaciones</b> y <b>estados de pedidos</b> directamente de SAP.
      <div class="chips">
        <span class="chip" data-q="¿Cómo van las cotizaciones de los últimos 30 días? ¿Cuántas se ganaron y cuántas se perdieron?">Cotizaciones del mes</span>
        <span class="chip" data-q="¿Qué pedidos abiertos están vencidos con cantidades pendientes por despachar?">Pedidos vencidos</span>
        <span class="chip" data-q="Muéstrame los pedidos abiertos con cantidades retrasadas por despachar o facturar">Atrasos de despacho</span>
      </div>
    </div>
  </div>
</main>

<footer>
  <form class="inbar" id="form">
    <input id="inp" type="text" placeholder="Escribe tu pregunta sobre ventas…" autocomplete="off">
    <button type="submit" id="send"><i class="fa-solid fa-arrow-up"></i></button>
  </form>
</footer>

@verbatim
<script>
const chat = document.getElementById('chat');
const form = document.getElementById('form');
const inp  = document.getElementById('inp');
const send = document.getElementById('send');
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const historial = [];

function burbuja(role, texto, tools){
  const m = document.createElement('div');
  m.className = 'msg ' + (role === 'user' ? 'user' : 'bot');
  const av = role === 'user' ? '<i class="fa-solid fa-user"></i>' : '<i class="fa-solid fa-robot"></i>';
  let toolsHtml = '';
  if (tools && tools.length) {
    toolsHtml = '<div class="tools"><i class="fa-solid fa-plug"></i> Consultó SAP: ' +
      tools.map(t => t.tool).join(', ') + '</div>';
  }
  m.innerHTML = '<span class="av">'+av+'</span><div><div class="bub">'+texto+'</div>'+toolsHtml+'</div>';
  chat.appendChild(m);
  document.getElementById('main').scrollTop = 1e9;
  return m;
}

async function enviar(texto){
  if(!texto.trim()) return;
  const hint = chat.querySelector('.hint'); if(hint) hint.remove();
  burbuja('user', texto);
  historial.push({role:'user', content:texto});
  inp.value=''; send.disabled=true;

  const t = burbuja('bot', '<span class="typing"><i></i><i></i><i></i></span>');
  try{
    const r = await fetch('/asistente-sap/mensaje', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
      body: JSON.stringify({mensaje:texto, historial})
    });
    const data = await r.json();
    const resp = (data && data.respuesta) ? data.respuesta : 'No obtuve respuesta.';
    t.querySelector('.bub').textContent = resp;
    if(data.tools_usadas && data.tools_usadas.length){
      const d=document.createElement('div'); d.className='tools';
      d.innerHTML='<i class="fa-solid fa-plug"></i> Consultó SAP: '+data.tools_usadas.map(x=>x.tool).join(', ');
      t.querySelector('div').appendChild(d);
    }
    historial.push({role:'assistant', content:resp});
  }catch(e){
    t.querySelector('.bub').textContent = 'Ocurrió un error de conexión. Intenta de nuevo.';
  }finally{
    send.disabled=false; inp.focus();
    document.getElementById('main').scrollTop = 1e9;
  }
}

form.addEventListener('submit', e => { e.preventDefault(); enviar(inp.value); });
document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => enviar(c.dataset.q)));
</script>
@endverbatim
</body>
</html>
