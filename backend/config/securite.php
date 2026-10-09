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

];
