<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API seule : pas de page de connexion vers laquelle rediriger (401 JSON).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // L'API ne répond qu'en JSON, y compris sans en-tête Accept: application/json.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Messages d'erreur en français, au format { "message": "..." }.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Authentification requise.'], 401) : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Trop de tentatives. Réessayez dans quelques instants.'], 429, $e->getHeaders()) : null);
        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => "Vous n'avez pas le droit d'effectuer cette action."], 403) : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Ressource introuvable.'], 404) : null);
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Méthode non autorisée.'], 405) : null);

        // Ne jamais journaliser les champs sensibles d'une requête.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token']);
    })->create();
