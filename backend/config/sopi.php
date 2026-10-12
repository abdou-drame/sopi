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

    /*
    | Durée du verrou temporaire d'un créneau pendant qu'un client réserve (RG08, RG09).
    */
    'creneaux' => [
        'verrou_minutes' => (int) env('SLOT_LOCK_MINUTES', 5),
    ],

    /*
    | Délai laissé au prestataire pour répondre à une demande de réservation EN_ATTENTE (RG10). Passé ce délai,
    | la demande ne peut plus être acceptée ni refusée (elle sera annulée par la tâche planifiée).
    */
    'reservations' => [
        'delai_reponse_heures' => (int) env('RESERVATION_DELAI_REPONSE_HEURES', 12),

        // RG13 : le client peut annuler jusqu'à ce nombre d'heures avant le début du créneau (borne incluse).
        'annulation_client_heures' => (int) env('RESERVATION_ANNULATION_CLIENT_HEURES', 2),
    ],

];
