<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithQueryToken
{
    /**
     * Permite autenticación transparente vía parámetro query (?token=...)
     * cuando las descargas o aperturas de documentos se realizan en nueva pestaña.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('token') && !$request->bearerToken()) {
            $token = trim((string) $request->query('token'));
            if ($token !== '') {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
