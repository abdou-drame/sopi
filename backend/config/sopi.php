<?php

return [

    /*
    | Premier administrateur, utilisé uniquement par la commande `php artisan sopi:creer-admin`.
    | Les valeurs viennent des variables d'environnement du serveur (jamais du dépôt).
    */
    'admin' => [
        'nom' => env('ADMIN_NOM'),
        'prenom' => env('ADMIN_PRENOM'),
        'telephone' => env('ADMIN_TELEPHONE'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
