<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'password'         => 'required|string|min:8|confirmed',
            'fecha_nacimiento' => 'required|date',
        ]);

        $user = User::create([
            'nombre'           => $request->nombre,
            'apellido_p'       => $request->apellido_p,
            'apellido_m'       => $request->apellido_m,
            'correo'           => $request->correo,
            'password_hash'    => Hash::make($request->password),
            'fecha_nacimiento' => $request->fecha_nacimiento,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
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