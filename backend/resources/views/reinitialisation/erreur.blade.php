@extends('reinitialisation.gabarit')

@section('titre', $titre ?? 'Lien invalide')

@section('contenu')
    <h1>{{ $titre ?? 'Lien invalide ou expiré' }}</h1>
    <div class="erreurs" role="alert">
        <p>{{ $message ?? 'Ce lien de réinitialisation est inconnu, a expiré (il est valable 60 minutes) ou a déjà été utilisé.' }}</p>
    </div>
    @if (! isset($message))
        <p class="meta" style="margin-top:16px">Pour en recevoir un nouveau, ouvrez l'application Sopi, appuyez sur « Mot de passe oublié » et saisissez votre adresse e-mail.</p>
    @endif
@endsection
