<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        // Generar contraseña aleatoria de 8 caracteres
        $passwordPlano = Str::random(8);

        $user = User::create([
            'nombre'           => $request->nombre,
            'apellido_p'       => $request->apellido_p,
            'apellido_m'       => $request->apellido_m,
            'correo'           => $request->correo,
            'password_hash'    => Hash::make($passwordPlano),
            'fecha_nacimiento' => $request->fecha_nacimiento,
        ]);

        // Enviar correo con la contraseña generada
        Mail::to($user->correo)->send(
            new BienvenidaMail($user->nombre, $user->correo, $passwordPlano)
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'mensaje' => 'Usuario registrado. Se envió la contraseña al correo.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'correo'   => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('correo', $request->correo)->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'correo' => ['Las credenciales son incorrectas.'],
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.']);
    }
}