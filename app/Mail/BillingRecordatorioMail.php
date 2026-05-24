<?php

namespace App\Mail;

use App\Models\Pago;
use App\Models\Suscripcion;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 💌 Email branded de recordatorio de cobro Kivox.
 *
 * Soporta múltiples etapas (factura/preaviso/vence_hoy/vencio_ayer/urgencia/suspendido)
 * con asuntos y copys distintos según la urgencia.
 */
class BillingRecordatorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Suscripcion $suscripcion,
        public ?Pago $pago,
        public string $etapa,         // factura | preaviso | vence_hoy | vencio_ayer | urgencia | suspendido
        public ?string $linkPago = null,
    ) {}

    public function envelope(): Envelope
    {
        $asuntos = [
            'factura'     => '🧾 Tu factura mensual de Kivox',
            'preaviso'    => '📅 Tu suscripción vence en 3 días',
            'vence_hoy'   => '⏰ Tu suscripción vence HOY',
            'vencio_ayer' => '⚠️ Tu suscripción Kivox venció',
            'urgencia'    => '🚨 Tu acceso será suspendido pronto',
            'suspendido'  => '🔒 Tu acceso a Kivox fue suspendido',
        ];

        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        $brand = $cfg->nombre ?: 'Kivox';

        return new Envelope(
            subject: ($asuntos[$this->etapa] ?? 'Recordatorio de pago') . " · {$brand}",
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address', 'no-reply@kivox.co'),
                $brand
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.billing-recordatorio',
            with: [
                'tenant'      => $this->tenant,
                'suscripcion' => $this->suscripcion,
                'pago'        => $this->pago,
                'etapa'       => $this->etapa,
                'linkPago'    => $this->linkPago,
                'cfg'         => \App\Models\ConfiguracionPlataforma::actual(),
            ],
        );
    }
}
