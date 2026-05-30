<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InformeNegocioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public array $data,
        public array $incluir,
    ) {}

    public function envelope(): Envelope
    {
        $freq = ucfirst($this->data['rango']['frecuencia'] ?? 'semanal');
        $nombre = $this->tenant->nombre;
        $hasta = $this->data['rango']['hasta']->format('d/m/Y');

        // 🧠 Si la IA generó un titular potente, lo usamos como asunto
        $titularIa = trim($this->data['analisis']['titular'] ?? '');
        $subject = $titularIa
            ? "📊 {$nombre}: {$titularIa}"
            : "📊 Informe {$freq} — {$nombre} ({$hasta})";

        return new Envelope(
            subject: $subject,
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                'Kivox'
            ),
        );
    }

    public function content(): Content
    {
        $cfgPlataforma = null;
        try {
            $cfgPlataforma = \App\Models\ConfiguracionPlataforma::actual();
        } catch (\Throwable $e) {
            // fallback con valores defaults
        }
        return new Content(
            view: 'emails.informe-negocio',
            with: [
                'tenant'  => $this->tenant,
                'data'    => $this->data,
                'inc'     => $this->incluir,
                'cfg'     => $cfgPlataforma,
            ],
        );
    }
}
