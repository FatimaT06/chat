<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class UsuarioController extends Controller
{
    public function modificarUsuario(Request $request)
    {
        $userId = auth()->check()
            ? auth()->user()->id_usuario
            : session('chat_user')['id_usuario'] ?? null;

        if (!$userId) {
            return $request->expectsJson()
                ? response()->json(['error' => 'No autenticado'], 401)
                : redirect()->route('login');
        }

        $user = Usuario::find($userId);

        $request->validate([
            'nombre'      => 'nullable|string|max:100',
            'apellido_p'  => 'nullable|string|max:100',
            'apellido_m'  => 'nullable|string|max:100',
            'password'    => 'nullable|string|min:8|confirmed',
            'foto'        => 'nullable|image|max:4096',
        ]);

        $user->nombre     = $request->nombre     ?? $user->nombre;
        $user->apellido_p = $request->apellido_p ?? $user->apellido_p;
        $user->apellido_m = $request->apellido_m ?? $user->apellido_m;

        if ($request->password) {
            $user->password_hash = Hash::make($request->password);
        }

        if ($request->hasFile('foto')) {
            $fotoUrl = $this->subirFotoACloudinary($request->file('foto'), $userId);
            if ($fotoUrl) {
                $user->foto = $fotoUrl;
            }
        }

        $user->save();

        if (!$request->expectsJson()) {
            session(['chat_user' => $user->toArray()]);
            return redirect('/chat')->with('success', 'Perfil actualizado');
        }

        return response()->json([
            'success'  => true,
            'user'     => $user,
            'foto_url' => $user->foto,
        ]);
    }

    private function subirFotoACloudinary($file, $userId): ?string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $timestamp = time();
        $folder    = 'perfiles';
        $publicId  = 'usuario_' . $userId . '_' . $timestamp;

        $transformation = 'w_300,h_300,c_fill,g_face,q_auto,f_auto';

        $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}&transformation={$transformation}";
        $signature    = sha1($paramsToSign . $apiSecret);

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post($endpoint, [
            'api_key'        => $apiKey,
            'timestamp'      => $timestamp,
            'signature'      => $signature,
            'folder'         => $folder,
            'public_id'      => $publicId,
            'transformation' => $transformation,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url');
        }

        \Log::error('Cloudinary foto error: ' . $response->body());
        return null;
    }
}