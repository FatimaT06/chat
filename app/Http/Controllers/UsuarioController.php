<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function modificarUsuario(Request $request)
    {
        $userId = session('chat_user')['id_usuario'];

        $user = Usuario::find($userId);

        $request->validate([
            'nombre' => 'string|max:100',
            'apellido_p' => 'string|max:100',
            'apellido_m' => 'string|max:100',
            'password' => 'nullable|string|min:8|confirmed',
            'foto' => 'nullable|image|max:2048'
        ]);

        // subir foto
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('perfiles', 'public');
            $user->foto = $path;
        }

        $user->nombre = $request->nombre ?? $user->nombre;
        $user->apellido_p = $request->apellido_p ?? $user->apellido_p;
        $user->apellido_m = $request->apellido_m ?? $user->apellido_m;

        if ($request->password) {
            $user->password_hash = Hash::make($request->password);
        }

        $user->save();
        session(['chat_user' => $user->toArray()]);

        return redirect('/chat')->with('success', 'Perfil actualizado');
    }
}
