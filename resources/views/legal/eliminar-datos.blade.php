@extends('legal.layout')

@section('titulo', 'Eliminación de datos')
@section('actualizado', '25 de mayo de 2026')

@section('contenido')

@if(request('code'))
    <div class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
        <p class="text-emerald-800 font-semibold mb-1">
            ✅ Solicitud recibida
        </p>
        <p class="text-emerald-700 text-sm">
            Hemos procesado tu solicitud de eliminación. Código de confirmación:
            <code class="font-mono bg-white px-2 py-0.5 rounded border border-emerald-200">{{ request('code') }}</code>
        </p>
    </div>
@endif

<p>
    Tienes derecho a solicitar la eliminación de tus datos personales almacenados en Kivox.
    Esta página explica las dos vías disponibles según el tipo de usuario.
</p>

<h2>1. Si eres un cliente final (consumidor)</h2>
<p>
    Si interactuaste con un comercio que usa Kivox (por WhatsApp Business o Instagram Direct) y quieres que
    se eliminen tus mensajes, foto de perfil, dirección y demás datos personales:
</p>
<ul>
    <li>
        <strong>Vía Instagram</strong>: ve a tu cuenta IG → ⚙️ Configuración → Aplicaciones y sitios web →
        encuentra <strong>Kivox</strong> → click <em>Suprimir</em>. Instagram enviará automáticamente la solicitud de
        eliminación a Kivox y procesaremos el borrado en máximo 30 días.
    </li>
    <li>
        <strong>Vía email</strong>: envía un correo a
        <a href="mailto:soporte@kivox.co?subject=Eliminaci%C3%B3n%20de%20datos">soporte@kivox.co</a>
        con el asunto <strong>"Eliminación de datos"</strong>, incluyendo:
        <ul class="mt-2">
            <li>Tu número de teléfono o usuario de Instagram</li>
            <li>El nombre del comercio con el que interactuaste</li>
            <li>Una confirmación de que eres el titular de esos datos</li>
        </ul>
    </li>
</ul>

<h2>2. Si eres un comercio (tenant)</h2>
<p>
    Si tu empresa contrata Kivox y quieres dar de baja tu cuenta y eliminar TODO el histórico:
</p>
<ul>
    <li>Entra al panel de administración del tenant → Configuración → <em>Eliminar cuenta</em></li>
    <li>O envía un correo a <a href="mailto:soporte@kivox.co?subject=Baja%20de%20tenant">soporte@kivox.co</a> desde
        un correo registrado como administrador del tenant solicitando la baja.</li>
</ul>

<h2>3. ¿Qué se elimina?</h2>
<ul>
    <li>Mensajes (WhatsApp, Instagram, Web)</li>
    <li>Datos de contacto del cliente final (nombre, teléfono, foto, dirección)</li>
    <li>Pedidos asociados (en cumplimiento de la normativa fiscal, los registros contables se conservan
        anonimizados durante el período legal exigido)</li>
    <li>Si eres tenant: usuarios operadores, roles, configuración del bot y plantillas</li>
</ul>

<h2>4. Plazo</h2>
<p>
    El borrado se completa en un máximo de <strong>30 días</strong> desde recibida la solicitud,
    salvo obligaciones legales de retención (mínimo 2 años para registros transaccionales en Colombia,
    en cuyo caso solo se anonimizan los datos personales asociados).
</p>

<h2>5. Confirmación</h2>
<p>
    Te enviaremos un correo de confirmación con un código único cuando el borrado se complete.
    Si Meta nos notifica la solicitud (vía Instagram), también respondemos con un código de confirmación
    visible en esta página al cargarla con el parámetro <code>?code=XYZ</code>.
</p>

<h2>6. Contacto</h2>
<p>
    Dudas o problemas: <a href="mailto:soporte@kivox.co">soporte@kivox.co</a>
</p>

@endsection
