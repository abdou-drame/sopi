<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Réserve une route aux administrateurs (alias « admin »). À placer après auth:sanctum.
 * Le 403 en français est produit par le gestionnaire d'erreurs de bootstrap/app.php.
 */
class RoleAdministrateur
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== Role::Administrateur) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
