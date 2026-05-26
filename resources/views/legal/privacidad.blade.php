@extends('legal.layout')

@section('titulo', 'Política de Privacidad')
@section('actualizado', '25 de mayo de 2026')

@section('contenido')

<p>
    En <strong>Kivox</strong> (operado por <strong>TecnoByte360 S.A.S.</strong>, domiciliada en Colombia)
    respetamos tu privacidad. Esta política explica qué datos personales recopilamos, cómo los usamos, con quién los
    compartimos y qué derechos tienes sobre ellos, en cumplimiento de la Ley 1581 de 2012 de Colombia y el
    Reglamento General de Protección de Datos (GDPR) cuando aplique.
</p>

<h2>1. ¿Qué es Kivox?</h2>
<p>
    Kivox es una plataforma SaaS multi-tenant que permite a comercios y restaurantes recibir y responder mensajes
    de <strong>WhatsApp Business</strong>, <strong>Instagram Direct</strong> y <strong>chat web</strong>
    en un solo inbox, automatizar atención al cliente con inteligencia artificial y gestionar pedidos.
    Cada cliente (tenant) opera con sus propias cuentas conectadas; Kivox es el procesador técnico.
</p>

<h2>2. Datos que recopilamos</h2>

<h3>2.1 De los usuarios operadores (empleados del comercio)</h3>
<ul>
    <li>Nombre, correo electrónico, número de teléfono</li>
    <li>Rol, permisos y sede asignada</li>
    <li>Registros de actividad (login, acciones, IP, navegador)</li>
    <li>Configuración de doble factor de autenticación (TOTP)</li>
</ul>

<h3>2.2 De los clientes finales del comercio (consumidores)</h3>
<ul>
    <li>Nombre y número telefónico (cuando el cliente lo comparte vía WhatsApp)</li>
    <li>Instagram-Scoped ID (IGSID) y nombre de usuario público (cuando escribe por Instagram Direct)</li>
    <li>Foto de perfil pública</li>
    <li>Contenido de los mensajes intercambiados con el comercio</li>
    <li>Dirección de entrega, sede preferida y pedidos realizados</li>
    <li>Ubicación geográfica aproximada (solo si la comparte para entrega a domicilio)</li>
</ul>

<h3>2.3 Datos técnicos automáticos</h3>
<ul>
    <li>Logs de la API (requests, response codes, timestamps)</li>
    <li>Métricas de uso de IA (tokens consumidos, costos)</li>
    <li>Eventos de webhooks de Meta (WhatsApp Cloud API, Instagram Graph API) y Wompi (pagos)</li>
</ul>

<h2>3. Finalidad del tratamiento</h2>
<p>Usamos los datos para:</p>
<ul>
    <li>Operar el servicio de mensajería e inbox unificado del comercio</li>
    <li>Responder automáticamente con asistentes de IA cuando el comercio así lo configure</li>
    <li>Procesar pedidos, asignar domiciliarios y gestionar despachos</li>
    <li>Generar reportes y analítica interna del comercio</li>
    <li>Facturar al comercio (no al consumidor final) por el uso del SaaS</li>
    <li>Cumplir obligaciones legales y de seguridad</li>
</ul>

<h2>4. Base legal</h2>
<p>
    El tratamiento se basa en: (a) el consentimiento que el cliente final otorga al iniciar conversación con el
    comercio por WhatsApp/Instagram, (b) la ejecución del contrato entre Kivox y el comercio (tenant), y
    (c) intereses legítimos para la seguridad y mejora del servicio.
</p>

<h2>5. Con quién compartimos datos</h2>
<ul>
    <li><strong>Meta Platforms, Inc.</strong> — operador de WhatsApp Cloud API e Instagram Graph API</li>
    <li><strong>Anthropic / OpenAI</strong> — modelos de IA usados para respuestas automáticas (sin reentrenamiento)</li>
    <li><strong>Wompi (Bancolombia)</strong> — procesamiento de pagos cuando el comercio lo habilita</li>
    <li><strong>Google Cloud / Maps</strong> — geocodificación y rutas de entrega</li>
    <li><strong>TecnoByteApp</strong> — proveedor alterno de mensajería WhatsApp</li>
    <li><strong>El comercio dueño del tenant</strong> — accede a las conversaciones de sus propios clientes</li>
</ul>
<p>
    No vendemos datos personales a terceros. No usamos los datos para publicidad fuera de la plataforma.
</p>

<h2>6. Almacenamiento y retención</h2>
<p>
    Los datos se almacenan en infraestructura cloud cifrados en tránsito (TLS) y con secretos cifrados en reposo.
    Las conversaciones se conservan mientras el comercio mantenga su cuenta activa o cumpla obligaciones legales
    (mínimo 2 años para transacciones, según normativa colombiana).
    Al cancelar la cuenta, los datos se eliminan en un plazo máximo de 90 días.
</p>

<h2>7. Tus derechos</h2>
<p>Como titular de datos personales tienes derecho a:</p>
<ul>
    <li>Acceder a tus datos</li>
    <li>Rectificarlos si son incorrectos</li>
    <li>Solicitar su eliminación (ver <a href="{{ route('legal.eliminar-datos') }}">página de eliminación</a>)</li>
    <li>Oponerte al tratamiento o revocar el consentimiento</li>
    <li>Presentar quejas ante la Superintendencia de Industria y Comercio (SIC) de Colombia</li>
</ul>

<h2>8. Datos de menores</h2>
<p>
    Kivox no está dirigido a menores de 14 años. Si detectas que un menor está usando la plataforma sin
    autorización parental, contáctanos para eliminar sus datos.
</p>

<h2>9. Cookies</h2>
<p>
    Usamos cookies estrictamente necesarias para autenticación (sesiones Laravel/Livewire). No usamos cookies
    de publicidad ni de tracking de terceros.
</p>

<h2>10. Cambios</h2>
<p>
    Podemos actualizar esta política. La fecha de última actualización está en la parte superior. Cambios
    materiales serán notificados por correo a los administradores del tenant.
</p>

<h2>11. Contacto</h2>
<p>
    <strong>Responsable del tratamiento</strong>: TecnoByte360 S.A.S.<br>
    <strong>Correo</strong>: <a href="mailto:soporte@kivox.co">soporte@kivox.co</a><br>
    <strong>Sitio</strong>: <a href="https://kivox.co">https://kivox.co</a>
</p>

@endsection
