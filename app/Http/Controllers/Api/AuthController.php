<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Autentica al usuario y genera un token de Sanctum.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Usuario o contraseña incorrectos.'
            ], 401);
        }

        // Crear token de autenticación
        $token = $user->createToken('sistema-cajasalud')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'regional' => $user->regional,
            ],
        ]);
    }

    /**
     * Devuelve los datos del usuario asociado al token actual.
     * Se utiliza para validar que la sesión guardada en el navegador
     * siga siendo válida antes de mostrar la aplicación.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'regional' => $user->regional,
        ]);
    }

    /**
     * Invalida el token actual del usuario.
     */
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Permite al usuario autenticado cambiar su propia contraseña.
     */
    public function changePassword(Request $request)
    {
        $datos = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($datos['current_password'], $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($datos['password']),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

}
