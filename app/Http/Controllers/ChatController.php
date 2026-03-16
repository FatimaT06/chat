<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    /**
     * Obtiene el ID del usuario autenticado.
     * Soporta: sesión web, auth()->user() (Sanctum/Passport), y sesión chat_user.
     */
    private function getMiId(): ?int
    {
        if (auth()->check()) {
            return auth()->user()->id_usuario;
        }
        return session('chat_user')['id_usuario'] ?? null;
    }

    public function index()
    {
        return view('chat.index');
    }

    public function usuarios(Request $request)
    {
        $miId = $this->getMiId();

        if (!$miId) {
            return $request->expectsJson()
                ? response()->json(['error' => 'No autenticado'], 401)
                : redirect()->route('login');
        }

        $usuarios = Cache::remember("usuarios_lista_{$miId}", 60, function () use ($miId) {
            return Usuario::where('id_usuario', '!=', $miId)
                ->select('id_usuario', 'nombre', 'apellido_p', 'apellido_m', 'correo', 'foto')
                ->orderBy('nombre')
                ->get()
                ->map(function ($u) {
                    // foto: si ya es URL completa la dejamos, si es ruta local la resolvemos
                    if ($u->foto && !str_starts_with($u->foto, 'http')) {
                        $u->foto_url = url('storage/' . $u->foto);
                    } else {
                        $u->foto_url = $u->foto; // URL de Cloudinary o null
                    }
                    return $u;
                });
        });

        if ($request->expectsJson()) {
            return response()->json($usuarios);
        }

        return view('chat.usuarios', compact('usuarios'));
    }

    public function conversacion(Request $request, $id)
    {
        $miId = $this->getMiId();

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
            ->get()
            ->map(function ($m) {
                if ($m->archivo) {
                    $m->archivo_url = str_starts_with($m->archivo, 'http')
                        ? $m->archivo
                        : url('storage/' . $m->archivo);
                } else {
                    $m->archivo_url = null;
                }
                return $m;
            });

        return response()->json($mensajes);
    }

    public function enviar(Request $request, $id)
    {
        $miId = $this->getMiId();

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
            $file = $request->file('archivo');
            $mime = $file->getMimeType();

            if (str_starts_with($mime, 'image/')) {
                // Imágenes → Cloudinary
                $archivoUrl = $this->subirImagenACloudinary($file, $miId, $id);

                if (!$archivoUrl) {
                    return $request->expectsJson()
                        ? response()->json(['error' => 'Error al subir la imagen'], 500)
                        : back()->with('error', 'Error al subir la imagen');
                }
            } else {
                // PDFs, docs, zip, etc. → Cloudinary carpeta chat/
                $archivoUrl = $this->subirArchivoACloudinary($file, $miId, $id);

                if (!$archivoUrl) {
                    // Fallback: storage local si Cloudinary falla
                    $nombre     = time() . '_' . $file->getClientOriginalName();
                    $archivoUrl = url('storage/' . $file->storeAs('chat', $nombre, 'public'));
                }
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
            'archivo'           => $archivoUrl,
        ]);

        $mensaje->archivo_url = $archivoUrl;

        return $request->expectsJson()
            ? response()->json($mensaje, 201)
            : back()->with('success', 'Mensaje enviado');
    }

    /**
     * Sube archivos (PDF, docs, etc.) a Cloudinary carpeta chat/ vía API REST.
     * Usa resource_type=raw para archivos no-imagen.
     */
    private function subirArchivoACloudinary($file, $miId, $receptorId): ?string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $timestamp = time();
        $folder    = 'chat/' . min($miId, $receptorId) . '_' . max($miId, $receptorId);
        $publicId  = $timestamp . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext       = $file->getClientOriginalExtension();

        // Para raw, Cloudinary NO agrega la extensión automáticamente — la ponemos en public_id
        $publicIdConExt = $publicId . '.' . $ext;

        $paramsToSign = "folder={$folder}&public_id={$publicIdConExt}&timestamp={$timestamp}";
        $signature    = sha1($paramsToSign . $apiSecret);

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$cloudName}/raw/upload", [
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder'    => $folder,
            'public_id' => $publicIdConExt,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url');
        }

        \Log::error('Cloudinary raw upload error: ' . $response->body());
        return null;
    }

    /**
     * Sube imágenes a Cloudinary vía API REST directa (sin SDK).
     */
    private function subirImagenACloudinary($file, $miId, $receptorId): ?string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $timestamp = time();
        $folder    = 'chat/' . min($miId, $receptorId) . '_' . max($miId, $receptorId);
        $publicId  = $timestamp . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}";
        $signature    = sha1($paramsToSign . $apiSecret);

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
            'api_key'   => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder'    => $folder,
            'public_id' => $publicId,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url');
        }

        \Log::error('Cloudinary upload error: ' . $response->body());
        return null;
    }
}