<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table      = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps    = false;     

    protected $fillable = [
        'nombre',
        'apellido_p',
        'apellido_m',
        'correo',
        'password_hash',
        'fecha_nacimiento',
        'foto',
        'firebase_token',
        'biometric_credential'
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function mensajesEnviados()
    {
        return $this->hasMany(Mensaje::class, 'sender_id', 'id_usuario');
    }

    public function mensajesRecibidos()
    {
        return $this->hasMany(Mensaje::class, 'receiver_id', 'id_usuario');
    }

    protected $casts = [
        'biometric_credential' => 'array', // Laravel lo convierte a array automáticamente
    ];
}