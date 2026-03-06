<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Mail\BienvenidaMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nombre'           => 'required|string|max:100',
            'apellido_p'       => 'required|string|max:100',
            'apellido_m'       => 'required|string|max:100',
            'correo'           => 'required|email|unique:usuarios,correo',
            'fecha_nacimiento' => 'required|date',
        ]);

        $passwordPlano = Str::random(8);

        $user = Usuario::create([
            'nombre'           => $request->nombre,
            'apellido_p'       => $request->apellido_p,
            'apellido_m'       => $request->apellido_m,
            'correo'           => $request->correo,
            'password_hash'    => Hash::make($passwordPlano),
            'fecha_nacimiento' => $request->fecha_nacimiento,
        ]);

        Mail::to($user->correo)->send(
            new BienvenidaMail($user->nombre, $user->correo, $passwordPlano)
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        session(['chat_token' => $token, 'chat_user' => $user]);

        return redirect()->route('chat');
    }

    public function login(Request $request)
    {
        $request->validate([
            'correo'   => 'required|email',
            'password' => 'required|string',
        ]);

        $user = Usuario::where('correo', $request->correo)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['correo' => 'Las credenciales son incorrectas.'])->withInput();
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        session(['chat_token' => $token, 'chat_user' => $user]);

        return redirect()->route('chat');
    }

    public function logout(Request $request)
    {
        $user = Usuario::find(session('chat_user')['id_usuario'] ?? null);
        if ($user) $user->tokens()->delete();

        session()->forget(['chat_token', 'chat_user']);

        return redirect()->route('login');
    }
}