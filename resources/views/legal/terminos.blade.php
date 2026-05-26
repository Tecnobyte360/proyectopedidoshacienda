@extends('legal.layout')

@section('titulo', 'Términos y Condiciones de Uso')
@section('actualizado', '25 de mayo de 2026')

@section('contenido')

<p>
    Bienvenido a <strong>Kivox</strong>. Al registrarte o usar la plataforma aceptas estos términos.
    Si no estás de acuerdo, por favor no uses el servicio.
</p>

<h2>1. Definiciones</h2>
<ul>
    <li><strong>Kivox</strong>: plataforma SaaS de inbox unificado (WhatsApp + Instagram + Web) operada por TecnoByte360 S.A.S.</li>
    <li><strong>Tenant</strong>: el comercio o empresa que contrata Kivox para atender a sus propios clientes.</li>
    <li><strong>Usuario operador</strong>: empleado o agente del tenant con acceso al panel.</li>
    <li><strong>Cliente final</strong>: consumidor que escribe al tenant por WhatsApp/Instagram/Web.</li>
</ul>

<h2>2. Cuenta y registro</h2>
<p>
    Para usar Kivox debes registrarte con datos verídicos y mantener la confidencialidad de tu contraseña.
    Recomendamos habilitar el doble factor de autenticación (2FA). Eres responsable de toda actividad realizada
    desde tu cuenta.
</p>

<h2>3. Uso permitido</h2>
<p>El tenant se compromete a:</p>
<ul>
    <li>Cumplir las <a href="https://www.whatsapp.com/legal/business-policy" target="_blank">Políticas de WhatsApp Business</a> y
        las <a href="https://www.facebook.com/policies/commerce" target="_blank">Políticas comerciales de Meta</a></li>
    <li>No enviar spam, mensajes no solicitados, contenido fraudulento, engañoso o ilegal</li>
    <li>Respetar el opt-out del cliente final cuando solicite no recibir más mensajes</li>
    <li>Obtener consentimiento previo del cliente final cuando lo exija la regulación local</li>
    <li>No usar Kivox para actividades prohibidas: drogas, armas, contenido sexual sin consentimiento, etc.</li>
</ul>

<h2>4. Suscripción y pagos</h2>
<p>
    Kivox es un servicio de suscripción mensual. Los precios y planes se publican en
    <a href="https://kivox.co">kivox.co</a> y pueden actualizarse con 30 días de aviso.
    El pago se realiza vía Wompi (PSE, tarjetas o Nequi).
    En caso de impago, el servicio se suspende tras un periodo de gracia. La eliminación definitiva ocurre
    a los 90 días de mora.
</p>

<h2>5. Propiedad intelectual</h2>
<p>
    El software, marca, logos y diseño de Kivox son propiedad de TecnoByte360 S.A.S.
    El tenant conserva la propiedad de sus datos, mensajes y contenido del catálogo.
    Otorgas a Kivox una licencia limitada y no exclusiva para procesar esos datos con el único fin
    de prestarte el servicio.
</p>

<h2>6. Disponibilidad y garantías</h2>
<p>
    Trabajamos para mantener Kivox disponible 24/7, pero NO garantizamos 100% de uptime.
    Pueden ocurrir interrupciones por mantenimiento o causas externas (Meta, Wompi, proveedor de hosting).
    El servicio se presta "tal cual" sin garantías implícitas más allá de las exigidas por la ley.
</p>

<h2>7. Limitación de responsabilidad</h2>
<p>
    En ningún caso TecnoByte360 será responsable por lucro cesante, pérdida de oportunidades de negocio o
    daños indirectos derivados del uso o imposibilidad de uso de Kivox. La responsabilidad máxima se limita
    al monto pagado por el tenant en los últimos 3 meses.
</p>

<h2>8. Suspensión y terminación</h2>
<p>
    Podemos suspender o terminar la cuenta del tenant si: (a) incumple estos términos, (b) usa Kivox para
    actividades ilegales, (c) las plataformas conectadas (Meta, Wompi) suspenden al tenant, o (d) hay mora
    en pagos. El tenant puede cancelar en cualquier momento desde el panel; su data se elimina a los 90 días.
</p>

<h2>9. Modificaciones</h2>
<p>
    Podemos modificar estos términos. Cambios materiales se notifican por correo a los administradores
    del tenant con 30 días de anticipación. El uso continuado tras la fecha efectiva implica aceptación.
</p>

<h2>10. Ley aplicable y jurisdicción</h2>
<p>
    Estos términos se rigen por las leyes de la República de Colombia. Las disputas se resolverán
    preferentemente por arreglo directo; en su defecto, ante los jueces de la ciudad de domicilio
    de TecnoByte360.
</p>

<h2>11. Contacto</h2>
<p>
    <strong>TecnoByte360 S.A.S.</strong> — <a href="mailto:soporte@kivox.co">soporte@kivox.co</a> — <a href="https://kivox.co">kivox.co</a>
</p>

@endsection
