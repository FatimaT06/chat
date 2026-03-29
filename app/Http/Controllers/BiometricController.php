<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;

class BiometricController extends Controller
{
    public function viewRegister()
    {
        return view('biometria.registrar');
    }

    public function getChallenge()
    {
        $challenge = random_bytes(32);
        session(['webauthn_challenge' => base64_encode($challenge)]);

        return response()->json([
            'challenge' => base64_encode($challenge),
            'timeout' => 60000,
            'userVerification' => 'preferred'
        ]);
    }

    // Guardar credencial
    public function saveCredential(Request $request)
    {
        try {
            $request->validate([
                'correo' => 'required|email',
                'credential' => 'required'
            ]);

            $usuario = Usuario::where('correo', $request->correo)->first();
            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado']);
            }

            // Guardar credencial como JSON
            $usuario->biometric_credential = $request->credential;
            $usuario->save();

            return response()->json([
                'success' => true,
                'message' => 'Biometría registrada correctamente',
                'sent' => $request->all(),
                'stored' => json_decode($usuario->biometric_credential, true)
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Login biométrico
    public function login(Request $request)
    {
        try {
            $request->validate([
                'correo' => 'required|email',
                'credential' => 'required'
            ]);

            $usuario = Usuario::where('correo', $request->correo)->first();
            if (!$usuario || !$usuario->biometric_credential) {
                return response()->json(['success' => false, 'message' => 'Usuario o biometría no registrada'], 401);
            }

            $stored = json_decode($usuario->biometric_credential, true);
            $received = json_decode($request->credential, true);

            if (!$stored || !$received) {
                return response()->json(['success' => false, 'message' => 'Credencial inválida'], 400);
            }
            $debug = [
                'sent' => $request->all(),
                'stored' => $stored,
                'received' => $received
            ];
            if ($stored['id'] === $received['id'] && $stored['type'] === $received['type']) {
                $usuario->tokens()->delete();
                $token = $usuario->createToken('auth_token')->plainTextToken;
                session(['chat_token' => $token, 'chat_user' => $usuario->toArray()]);

                $debug['success'] = true;
                $debug['message'] = 'Autenticación biométrica correcta';
                return response()->json($debug);
            }

            $debug['success'] = false;
            $debug['message'] = 'Biometría no reconocida';
            return response()->json($debug, 401);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
}
