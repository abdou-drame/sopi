<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Page dont l'adresse contient un secret : jamais mise en cache ni indexée, aucune adresse de provenance transmise. */
class EntetesPagePrivee
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);
        $reponse->headers->set('Cache-Control', 'no-store, private');
        $reponse->headers->set('Referrer-Policy', 'no-referrer');
        $reponse->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $reponse;
    }
}
