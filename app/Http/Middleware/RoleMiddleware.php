<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Verifica que el usuario tenga uno de los roles permitidos.
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        // Si no existe un usuario autenticado.
        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.'
            ], 401);
        }

        // Verificar si el rol del usuario está permitido.
        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta operación.'
            ], 403);
        }

        return $next($request);
    }
}
