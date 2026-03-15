<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $table      = 'mensajes';
    protected $primaryKey = 'id_mensaje';
    public $timestamps    = false;        // la tabla no tiene created_at/updated_at

    protected $fillable = [
        'id_emisor',
        'id_receptor',
        'contenido_cifrado',
        'archivo'
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
    ];

    public function emisor()
    {
        return $this->belongsTo(User::class, 'id_emisor', 'id_usuario');
    }

    public function receptor()
    {
        return $this->belongsTo(User::class, 'id_receptor', 'id_usuario');
    }
}