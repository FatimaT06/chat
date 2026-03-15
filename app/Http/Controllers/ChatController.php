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

    public function usuarios(Request $request)
    {
        $miId = session('chat_user')['id_usuario'] ?? null;

        if (!$miId && !$request->expectsJson()) {
            return redirect()->route('login');
        }

        $usuarios = Cache::remember("usuarios_lista_{$miId}", 60, function () use ($miId) {
            return Usuario::where('id_usuario', '!=', $miId)
                ->select('id_usuario', 'nombre', 'apellido_p', 'apellido_m', 'correo', 'foto')
                ->orderBy('nombre')
                ->get();
        });

        if ($request->expectsJson()) {
            return response()->json($usuarios);
        } else {
            return view('chat.usuarios', compact('usuarios'));
        }
    }

public function conversacion(Request $request, $id)
{
    // Obtener ID del usuario que hace la petición
    $miId = auth()->check() ? auth()->user()->id_usuario : session('chat_user')['id_usuario'] ?? null;

    if (!$miId) {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'No autenticado'], 401);
        } else {
            return redirect()->route('login');
        }
    }

    // Traer últimos 50 mensajes entre $miId y $id
    $mensajes = Mensaje::where(function ($q) use ($miId, $id) {
            $q->where('id_emisor', $miId)->where('id_receptor', $id);
        })
        ->orWhere(function ($q) use ($miId, $id) {
            $q->where('id_emisor', $id)->where('id_receptor', $miId);
        })
        ->orderBy('id_mensaje', 'asc')
        ->get();

    // Agregar URL completa del archivo para Flutter
    foreach ($mensajes as $m) {
        $m->archivo_url = $m->archivo ? url(str_replace('public/', 'storage/', $m->archivo)) : null;
    }

    return response()->json($mensajes);
}

    public function enviar(Request $request, $id)
    {
        // Obtener ID del usuario que envía el mensaje (desde sesión web o token API)
        $miId = auth()->check() ? auth()->user()->id_usuario : session('chat_user')['id_usuario'] ?? null;

        if (!$miId) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No autenticado'], 401);
            } else {
                return redirect()->route('login');
            }
        }

        $request->validate([
            'mensaje' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|max:10240'
        ]);

        $archivoPath = null;
        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');
            $nombre = time().'_'.$archivo->getClientOriginalName();
            $archivoPath = $archivo->storeAs('chat', $nombre, 'public');
        }

        if (!$request->mensaje && !$archivoPath) {
            if ($request->expectsJson()) {
                return response()->json(['error'=>'Mensaje vacío'],400);
            } else {
                return back()->with('error', 'Mensaje vacío');
            }
        }

        $mensaje = Mensaje::create([
            'id_emisor' => $miId,
            'id_receptor' => (int) $id,
            'contenido_cifrado' => $request->mensaje ?? '',
            'archivo' => $archivoPath,
        ]);

        // URL completa del archivo
        $mensaje->archivo_url = $archivoPath ? url(str_replace('public/', 'storage/', $archivoPath)) : null;

        if ($request->expectsJson()) {
            return response()->json($mensaje, 201);
        } else {
            return back()->with('success', 'Mensaje enviado');
        }
    }

}