<?php

return [

    /*
    | Blocage de connexion (règle CLAUDE.md : 5 échecs consécutifs => blocage de 15 minutes).
    | Le compteur est stocké dans le cache de Laravel, par numéro de téléphone saisi.
    */
    'connexion' => [
        'max_echecs' => (int) env('LOGIN_MAX_ECHECS', 5),
        'duree_blocage_minutes' => (int) env('LOGIN_BLOCAGE_MINUTES', 15),
        // Limite de requêtes de connexion par minute et par adresse IP (contre le balayage de numéros).
        'max_par_minute_par_ip' => (int) env('LOGIN_MAX_PAR_MINUTE_IP', 20),
    ],

    /*
    | Réinitialisation du mot de passe : durée de vie du lien (usage unique) et durée minimale de la réponse à la demande,
    | pour que le temps de réponse ne révèle pas si l'adresse correspond à un compte.
    */
    'reinitialisation' => [
        'duree_minutes' => (int) env('RESET_MOT_DE_PASSE_MINUTES', 60),
        'duree_minimale_ms' => (int) env('RESET_DUREE_MINIMALE_MS', 400),
        'max_par_minute_par_ip' => (int) env('RESET_MAX_PAR_MINUTE_IP', 5),
        'max_par_heure_par_email' => (int) env('RESET_MAX_PAR_HEURE_EMAIL', 5),
        // Ouverture de la page et validation du nouveau mot de passe.
        'max_validations_par_minute_par_ip' => (int) env('RESET_MAX_VALIDATIONS_PAR_MINUTE_IP', 10),
        'max_validations_par_heure_par_jeton' => (int) env('RESET_MAX_VALIDATIONS_PAR_HEURE_JETON', 10),
    ],

];
