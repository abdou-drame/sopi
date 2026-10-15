<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Pages où DexPay renvoie le client après le paiement : textes fixes, aucune donnée lue de l'URL.
Route::get('/paiement/succes', fn () => view('paiement.retour', [
    'titre' => 'Paiement reçu',
    'message' => 'Votre acompte a bien été reçu. Retournez dans l\'application Sopi pour voir votre réservation.',
]));

Route::get('/paiement/echec', fn () => view('paiement.retour', [
    'titre' => 'Paiement non abouti',
    'message' => 'Le paiement n\'a pas abouti. Aucun montant ne vous sera prélevé. Retournez dans l\'application Sopi pour réessayer.',
]));
