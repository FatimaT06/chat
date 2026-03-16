<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        $miId = auth()->check()
            ? auth()->user()->id_usuario
            : session('chat_user')['id_usuario'] ?? null;

        if (!$miId) {
            return $request->expectsJson()
                ? response()->json(['error' => 'No autenticado'], 401)
                : redirect()->route('login');
        }

        $mensajes = Mensaje::where(function ($q) use ($miId, $id) {
                $q->where('id_emisor', $miId)->where('id_receptor', $id);
            })
            ->orWhere(function ($q) use ($miId, $id) {
                $q->where('id_emisor', $id)->where('id_receptor', $miId);
            })
            ->orderBy('id_mensaje', 'asc')
            ->get();

        foreach ($mensajes as $m) {
            if ($m->archivo) {
                $m->archivo_url = str_starts_with($m->archivo, 'http')
                    ? $m->archivo
                    : url(str_replace('public/', 'storage/', $m->archivo));
            } else {
                $m->archivo_url = null;
            }
        }

        return response()->json($mensajes);
    }

    public function enviar(Request $request, $id)
    {
        $miId = auth()->check()
            ? auth()->user()->id_usuario
            : session('chat_user')['id_usuario'] ?? null;

        if (!$miId) {
            return $request->expectsJson()
                ? response()->json(['error' => 'No autenticado'], 401)
                : redirect()->route('login');
        }

        $request->validate([
            'mensaje' => 'nullable|string|max:5000',
            'archivo' => 'nullable|file|max:20480',
        ]);

        $archivoUrl = null;

        if ($request->hasFile('archivo')) {
            $archivoUrl = $this->subirACloudinary($request->file('archivo'), $miId, $id);

            if (!$archivoUrl) {
                return $request->expectsJson()
                    ? response()->json(['error' => 'Error al subir el archivo'], 500)
                    : back()->with('error', 'Error al subir el archivo');
            }
        }

        if (!$request->mensaje && !$archivoUrl) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Mensaje vacío'], 400)
                : back()->with('error', 'Mensaje vacío');
        }

        $mensaje = Mensaje::create([
            'id_emisor'         => $miId,
            'id_receptor'       => (int) $id,
            'contenido_cifrado' => $request->mensaje ?? '',
            'archivo'           => $archivoUrl, // URL completa de Cloudinary
        ]);

        $mensaje->archivo_url = $archivoUrl;

        return $request->expectsJson()
            ? response()->json($mensaje, 201)
            : back()->with('success', 'Mensaje enviado');
    }

    private function subirACloudinary($file, $miId, $receptorId): ?string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $timestamp  = time();
        $folder     = 'chat/' . min($miId, $receptorId) . '_' . max($miId, $receptorId);
        $publicId   = $timestamp . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // Firma requerida por Cloudinary
        $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}";
        $signature    = sha1($paramsToSign . $apiSecret);

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload";

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post($endpoint, [
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder'    => $folder,
            'public_id' => $publicId,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url'); // URL HTTPS directa
        }

        \Log::error('Cloudinary upload error: ' . $response->body());
        return null;
    }
}