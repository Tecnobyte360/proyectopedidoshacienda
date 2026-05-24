<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $resetUrl) {}

    public function envelope(): Envelope
    {
        $cfg = \App\Models\ConfiguracionPlataforma::actual();
        $brand = $cfg->nombre ?: 'Kivox';
        return new Envelope(
            subject: "🔑 Restablecer contraseña · {$brand}",
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address', 'no-reply@kivox.co'),
                $brand
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'user'     => $this->user,
                'resetUrl' => $this->resetUrl,
                'cfg'      => \App\Models\ConfiguracionPlataforma::actual(),
            ],
        );
    }
}
