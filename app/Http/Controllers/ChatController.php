<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Mensaje;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function usuarios(Request $request)
    {
        $usuarios = User::where('id_usuario', '!=', $request->user()->id_usuario)
            ->select('id_usuario', 'nombre', 'apellido_p', 'apellido_m', 'correo')
            ->orderBy('nombre')
            ->get();

        return response()->json($usuarios);
    }

    public function conversacion(Request $request, $id)
    {
        $miId = $request->user()->id_usuario;

        User::findOrFail($id);

        $mensajes = Mensaje::where(function ($q) use ($miId, $id) {
                $q->where('id_emisor', $miId)->where('id_receptor', $id);
            })
            ->orWhere(function ($q) use ($miId, $id) {
                $q->where('id_emisor', $id)->where('id_receptor', $miId);
            })
            ->orderBy('fecha_envio', 'asc')
            ->get();

        return response()->json($mensajes);
    }

    public function enviar(Request $request, $id)
    {
        $request->validate([
            'mensaje' => 'required|string|max:5000',
        ]);

        $destinatario = User::findOrFail($id);

        $mensaje = Mensaje::create([
            'id_emisor'         => $request->user()->id_usuario,
            'id_receptor'       => $destinatario->id_usuario,
            'contenido_cifrado' => $request->mensaje,
        ]);

        return response()->json($mensaje, 201);
    }
}