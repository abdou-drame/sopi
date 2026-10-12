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

        // RG11 : délai laissé au client pour payer après acceptation (compté depuis acceptee_le). Passé ce délai, la
        // réservation ACCEPTEE est annulée par la tâche planifiée.
        'delai_paiement_heures' => (int) env('RESERVATION_DELAI_PAIEMENT_HEURES', 2),

        // RG13 : le client peut annuler jusqu'à ce nombre d'heures avant le début du créneau (borne incluse).
        'annulation_client_heures' => (int) env('RESERVATION_ANNULATION_CLIENT_HEURES', 2),
    ],

    /*
    | Acompte et commission de la plateforme, en fraction du tarif de la prestation. Le serveur calcule toujours
    | les montants à partir de ces taux. Valeurs initiales : un réglage administrable pourra venir plus tard.
    */
    'paiement' => [
        // fake (tests, démonstration) ou dexpay (production, à partir de M6.6).
        'driver' => env('PAYMENT_DRIVER', 'fake'),
        // Secret de signature HMAC des webhooks ; vide = aucune signature acceptée.
        'webhook_secret' => env('DEXPAY_WEBHOOK_SECRET', ''),
        // En-tête HTTP qui porte la signature HMAC-SHA256 du corps brut du webhook.
        'signature_header' => env('PAYMENT_SIGNATURE_HEADER', 'X-Signature'),

        // Reprise des remboursements en échec (M6.5) : essais maximum et délai minimal entre deux essais.
        'remboursement_max_tentatives' => (int) env('PAIEMENT_REMBOURSEMENT_MAX_TENTATIVES', 10),
        'remboursement_delai_minutes' => (int) env('PAIEMENT_REMBOURSEMENT_DELAI_MINUTES', 5),

        'acompte_taux' => (float) env('ACOMPTE_RATE', 0.30),
        'commission_taux' => (float) env('COMMISSION_RATE', 0.10),
    ],

];
