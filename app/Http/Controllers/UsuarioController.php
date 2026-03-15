<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function modificarUsuario(Request $request)
    {
        // Obtener ID del usuario actual
        $userId = auth()->check() ? auth()->user()->id_usuario : session('chat_user')['id_usuario'] ?? null;

        if (!$userId) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No autenticado'], 401);
            } else {
                return redirect()->route('login');
            }
        }

        $user = Usuario::find($userId);

        $request->validate([
            'nombre' => 'nullable|string|max:100',
            'apellido_p' => 'nullable|string|max:100',
            'apellido_m' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:8|confirmed',
            'foto' => 'nullable|image|max:2048',
        ]);

        // Actualizar campos si vienen en la petición
        $user->nombre = $request->nombre ?? $user->nombre;
        $user->apellido_p = $request->apellido_p ?? $user->apellido_p;
        $user->apellido_m = $request->apellido_m ?? $user->apellido_m;

        if ($request->password) {
            $user->password_hash = Hash::make($request->password);
        }

        // Subir foto si viene archivo
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('perfiles', 'public');
            $user->foto = $path;
        }

        $user->save();

        // Actualizar sesión web
        if (! $request->expectsJson()) {
            session(['chat_user' => $user->toArray()]);
            return redirect('/chat')->with('success', 'Perfil actualizado');
        }

        // Para API Flutter, devolver JSON con datos actualizados
        return response()->json([
            'success' => true,
            'user' => $user,
            'foto_url' => $user->foto ? url(str_replace('public/', 'storage/', $user->foto)) : null
        ]);
    }
}
