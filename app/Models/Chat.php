<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $table = 'chats';
    protected $primaryKey = 'id_chat';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario_1',
        'id_usuario_2'
    ];
}
