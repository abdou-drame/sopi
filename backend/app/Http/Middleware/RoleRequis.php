<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Réserve une route à un ou plusieurs rôles. À placer après auth:sanctum.
 * Alias : « admin » (administrateur), « prestataire », ou « role:client,prestataire » pour en combiner plusieurs.
 * Le 403 en français est produit par le gestionnaire d'erreurs de bootstrap/app.php.
 */
class RoleRequis
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $autorises = array_map(fn (string $role) => Role::from($role), $roles);

        if (! in_array($request->user()?->role, $autorises, true)) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
