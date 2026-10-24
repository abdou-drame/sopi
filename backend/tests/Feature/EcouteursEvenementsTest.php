<?php

use App\Events\Reservation\RappelRendezVous;
use Illuminate\Support\Facades\Event;

/**
 * Garde-fou des notifications (mémoire, RF20 et 3.3.2). Un import `use` oublié dans AppServiceProvider enregistre l'écouteur
 * sous un nom de classe qui n'existe pas, sans aucune erreur : l'événement est bien publié mais personne n'est notifié.
 * C'était le cas du rappel de rendez-vous, qui n'est donc jamais parti tant que l'import manquait.
 */
it('enregistre un écouteur pour chaque événement métier de app/Events', function () {
    $dossier = app_path('Events');
    $sansEcouteur = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS)) as $fichier) {
        if ($fichier->getExtension() !== 'php') {
            continue;
        }

        $relatif = substr($fichier->getPathname(), strlen($dossier) + 1, -strlen('.php'));
        $classe = 'App\\Events\\'.str_replace('/', '\\', str_replace('\\', '/', $relatif));

        if (! Event::hasListeners($classe)) {
            $sansEcouteur[] = $classe;
        }
    }

    expect($sansEcouteur)->toBe([]);
});

it('écoute le rappel de rendez-vous', function () {
    expect(Event::hasListeners(RappelRendezVous::class))->toBeTrue();
});
