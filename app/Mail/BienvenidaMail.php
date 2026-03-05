<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BienvenidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombre;
    public string $correo;
    public string $password;

    public function __construct(string $nombre, string $correo, string $password)
    {
        $this->nombre   = $nombre;
        $this->correo   = $correo;
        $this->password = $password;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a A&F Chat! Tus credenciales de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bienvenida',
        );
    }
}