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

        // Alertes du tableau de bord (M11.3) : calculées à la demande, rien n'est envoyé à l'administrateur (RG35).
        'alertes' => [
            // Dossiers, litiges et avis signalés : gravité « attention » puis « critique » quand le plus ancien attend depuis ces heures.
            'age_attention_heures' => (int) env('ADMIN_ALERTE_AGE_ATTENTION_HEURES', 24),
            'age_critique_heures' => (int) env('ADMIN_ALERTE_AGE_CRITIQUE_HEURES', 72),
            // Retrait EN_COURS au-delà de retraits.delai_blocage_minutes : « critique » quand le plus ancien dépasse cette durée.
            'retraits_critique_minutes' => (int) env('ADMIN_ALERTE_RETRAITS_CRITIQUE_MINUTES', 60),
            // Remboursement dû et non effectué depuis ces minutes (hors échec définitif) : gravité « attention ».
            'remboursements_retard_minutes' => (int) env('ADMIN_ALERTE_REMBOURSEMENTS_RETARD_MINUTES', 30),
            // Notifications en échec définitif : « critique » à partir de ce nombre.
            'notifications_critique_seuil' => (int) env('ADMIN_ALERTE_NOTIFICATIONS_CRITIQUE_SEUIL', 20),
            // Suspensions temporaires qui prennent fin dans ces heures.
            'suspension_expiration_heures' => (int) env('ADMIN_ALERTE_SUSPENSION_EXPIRATION_HEURES', 24),
            // Nombre maximal d'identifiants renvoyés par alerte (les plus anciens d'abord).
            'ids_max' => (int) env('ADMIN_ALERTE_IDS_MAX', 20),
        ],
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
        // fake (tests, démonstration) ou dexpay (production).
        'driver' => env('PAYMENT_DRIVER', 'fake'),
        // Secret de signature HMAC des webhooks : chez DexPay, c'est la clé secrète de l'API (aucun secret de webhook distinct).
        // Vide = aucune signature acceptée.
        'webhook_secret' => env('DEXPAY_SECRET_KEY', ''),
        // En-tête HTTP qui porte la signature HMAC-SHA256 (hexadécimal) du corps brut du webhook.
        'signature_header' => env('PAYMENT_SIGNATURE_HEADER', 'X-Webhook-Signature'),

        // Pilote DexPay (PAYMENT_DRIVER=dexpay). Les clés sont des secrets : variables d'environnement du serveur uniquement.
        'dexpay' => [
            'base_url' => env('DEXPAY_BASE_URL', 'https://api.dexpay.africa/api/v1'),
            'public_key' => env('DEXPAY_PUBLIC_KEY', ''),
            'secret_key' => env('DEXPAY_SECRET_KEY', ''),
            'country' => env('DEXPAY_COUNTRY', 'SN'),
            // Pages où DexPay renvoie le client après le paiement (vides : APP_URL + /paiement/succes ou /paiement/echec).
            'success_url' => env('DEXPAY_SUCCESS_URL', ''),
            'failure_url' => env('DEXPAY_FAILURE_URL', ''),
            'timeout_seconds' => (int) env('DEXPAY_TIMEOUT_SECONDS', 15),
            // TEMPORAIRE, à désactiver après les essais : ajoute un champ « diagnostic » (sans secret) aux réponses 503 liées à DexPay.
            'diagnostic_reponse' => (bool) env('DEXPAY_DIAGNOSTIC_REPONSE', false),
            // Facteur appliqué au montant en FCFA avant l'envoi (1 = FCFA entiers ; 100 si le bac à sable attend des centimes).
            'amount_factor' => (int) env('DEXPAY_AMOUNT_FACTOR', 1),
            // Champs de destination_details qui portent l'opérateur du versement (liste séparée par des virgules).
            'payout_operator_fields' => env('DEXPAY_PAYOUT_OPERATOR_FIELDS', 'operator'),
            // Opérateur de Sopi => code d'opérateur de versement DexPay.
            'operateurs' => [
                'wave' => env('DEXPAY_OPERATEUR_WAVE', 'wave_sn_payout'),
                'orange_money' => env('DEXPAY_OPERATEUR_ORANGE_MONEY', 'orange_money_sn_payout'),
            ],
        ],

        // Reprise des remboursements en échec (M6.5) : essais maximum et délai minimal entre deux essais.
        'remboursement_max_tentatives' => (int) env('PAIEMENT_REMBOURSEMENT_MAX_TENTATIVES', 10),
        'remboursement_delai_minutes' => (int) env('PAIEMENT_REMBOURSEMENT_DELAI_MINUTES', 5),

        'acompte_taux' => (float) env('ACOMPTE_RATE', 0.30),
        'commission_taux' => (float) env('COMMISSION_RATE', 0.10),
    ],

    /*
    | Limites d'un retrait de gains (RG29), en FCFA. Contrôlées par le service de retrait (M7.4), pas par la base.
    */
    'retraits' => [
        'montant_minimum' => (int) env('RETRAIT_MONTANT_MINIMUM', 1000),
        'montant_maximum' => (int) env('RETRAIT_MONTANT_MAXIMUM', 500000),
        // Au-delà de ce délai, un retrait encore EN_COURS est signalé dans le journal (sopi:retraits:signaler-bloques).
        'delai_blocage_minutes' => (int) env('RETRAIT_DELAI_BLOCAGE_MINUTES', 15),
    ],

    /*
    | Notifications (M10) : pilotes d'envoi, essais et lot de la commande planifiée. Pilote « log » par défaut : rien n'est envoyé.
    | e-mail : log ou smtp (transport et accès via les variables MAIL_* du serveur). push : log ou fcm (pas encore disponible).
    */
    'notifications' => [
        'email_driver' => env('NOTIFICATION_EMAIL_DRIVER', 'log'),
        'push_driver' => env('NOTIFICATION_PUSH_DRIVER', 'log'),
        'max_tentatives' => (int) env('NOTIFICATION_MAX_TENTATIVES', 5),
        'delai_essai_minutes' => (int) env('NOTIFICATION_DELAI_ESSAI_MINUTES', 5),
        'taille_lot' => (int) env('NOTIFICATION_TAILLE_LOT', 100),
    ],
];
