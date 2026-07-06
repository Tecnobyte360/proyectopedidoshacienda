<footer>
  <div class="foot">
    <div>
      <a class="logo" href="/site"><img src="https://kivox.co/storage/plataforma/plataforma-logo-1779417616.png" alt="KIVOX" style="height:40px" onerror="this.style.display='none'"></a>
      <p style="font-size:14px;line-height:1.7;margin-top:14px;max-width:330px;color:#8fa094">Plataforma omnicanal empresarial con inteligencia artificial. Un producto de TecnoByte360.</p>
    </div>
    <div><h4>Plataforma</h4><a href="/comunicacion">Omnicanalidad &amp; Marketing</a><a href="/operacion">Pedidos &amp; Domicilios</a><a href="/site#erp">Integraciones</a><a href="/site#nosotros">Nosotros</a></div>
    <div><h4>Empresa</h4><a href="https://admin.kivox.co/login">Acceder</a><a href="mailto:comercial@tecnobyte360.com">Contacto</a><a href="https://portafolio.tecnobyte360.com/" target="_blank" rel="noopener">TecnoByte360</a></div>
  </div>
  <div class="foot-copy">© {{ date('Y') }} KIVOX · Un producto de TecnoByte360 · Hecho en Colombia 🇨🇴</div>
</footer>
@verbatim
<script>
const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}}),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
/* barras que se llenan al aparecer */
const bo=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.style.width=e.target.dataset.w;bo.unobserve(e.target)}}),{threshold:.4});
document.querySelectorAll('[data-w]').forEach(el=>bo.observe(el));
</script>
<script src="https://kivox.co/widget.js?token=NaNuLzK7DWESYvSFwEPBpCqQ3qG65Xq3&v=4" defer></script>
@endverbatim
