<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Mensaje;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat.index');
    }

    public function usuarios(Request $request)
    {
        $miId = session('chat_user')['id_usuario'];

        $usuarios = Usuario::where('id_usuario', '!=', $miId)
            ->select('id_usuario', 'nombre', 'apellido_p', 'apellido_m', 'correo')
            ->orderBy('nombre')
            ->get();

        return response()->json($usuarios);
    }

    public function conversacion(Request $request, $id)
    {
        $miId = session('chat_user')['id_usuario'];

        Usuario::findOrFail($id);

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

        $miId = session('chat_user')['id_usuario'];

        $destinatario = Usuario::findOrFail($id);

        $mensaje = Mensaje::create([
            'id_emisor'         => $miId,
            'id_receptor'       => $destinatario->id_usuario,
            'contenido_cifrado' => $request->mensaje,
        ]);

        return response()->json($mensaje, 201);
    }
}