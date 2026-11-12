<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!session()->has('usuario')) {
            return redirect()->route('login');
        }

        $usuario = session('usuario');
        $rol = $usuario['role'] ?? null;

        if (!$rol || !in_array($rol, $roles, true)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
