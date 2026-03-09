<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;

class BienvenidaMail
{
    public function __construct(
        public string $nombre,
        public string $correo,
        public string $password
    ) {}

    public function send(): void
    {
        Http::withHeaders([
            'api-key' => config('services.brevo.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name'  => 'A&F Chat',
                'email' => 'fatima.torres061102@gmail.com',
            ],
            'to' => [[
                'email' => $this->correo,
                'name'  => $this->nombre,
            ]],
            'subject' => 'Bienvenido a A&F Chat',
            'htmlContent' => view('emails.bienvenida', [
                'nombre'   => $this->nombre,
                'correo'   => $this->correo,
                'password' => $this->password,
            ])->render(),
        ]);
    }
}