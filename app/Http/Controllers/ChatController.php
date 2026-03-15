<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat.index');
    }

    public function usuarios()
    {
        $miId = session('chat_user')['id_usuario'];

        // Cache de usuarios por 60 segundos
        $usuarios = Cache::remember("usuarios_lista_{$miId}", 60, function () use ($miId) {
            return Usuario::where('id_usuario', '!=', $miId)
                ->select('id_usuario', 'nombre', 'apellido_p', 'apellido_m', 'correo', 'foto')
                ->orderBy('nombre')
                ->get();
        });

        return response()->json($usuarios);
    }

    public function conversacion(Request $request, $id)
    {
        $miId = session('chat_user')['id_usuario'];

        // Solo traer los últimos 50 mensajes
        $mensajes = Mensaje::where(function ($q) use ($miId, $id) {
                $q->where('id_emisor', $miId)->where('id_receptor', $id);
            })
            ->orWhere(function ($q) use ($miId, $id) {
                $q->where('id_emisor', $id)->where('id_receptor', $miId);
            })
            ->orderBy('fecha_envio', 'asc')
            ->limit(50)
            ->get(['id_mensaje', 'id_emisor', 'id_receptor', 'contenido_cifrado', 'fecha_envio', 'archivo']);

        return response()->json($mensajes);
    }

    public function enviar(Request $request, $id)
    {
        $request->validate([
            'mensaje' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|max:10240'
        ]);

        $miId = session('chat_user')['id_usuario'];

        $archivoPath = null;

        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $nombre = time().'_'.$archivo->getClientOriginalName();
            $archivoPath = $archivo->storeAs('chat', $nombre, 'public');
        }

        if (!$request->mensaje && !$archivoPath) {
            return response()->json(['error'=>'Mensaje vacío'],400);
        }

        $mensaje = Mensaje::create([
            'id_emisor' => $miId,
            'id_receptor' => (int) $id,
            'contenido_cifrado' => $request->mensaje ?? '',
            'archivo' => $archivoPath,
        ]);

        return response()->json($mensaje, 201);
    }

}