@extends('reinitialisation.gabarit')

@section('titre', 'Nouveau mot de passe')

@section('contenu')
    <h1>Choisissez un nouveau mot de passe</h1>
    <p class="meta">Ce lien ne fonctionne qu'une seule fois. Une fois le mot de passe changé, vous serez déconnecté de tous vos appareils.</p>

    @if (! empty($erreurs))
        <div class="erreurs" role="alert">
            @foreach ($erreurs as $erreur)
                <p>{{ $erreur }}</p>
            @endforeach
        </div>
    @endif

    <form method="post" action="" id="formulaire" novalidate>
        @csrf
        <label for="mot_de_passe">Nouveau mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="new-password" minlength="8" maxlength="255" required autofocus>

        <label for="mot_de_passe_confirmation">Confirmez le mot de passe</label>
        <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" autocomplete="new-password" maxlength="255" required>

        <ul class="regles" aria-live="polite">
            <li id="regle-longueur">Au moins 8 caractères</li>
            <li id="regle-identique">Les deux mots de passe sont identiques</li>
        </ul>

        <button type="submit" id="envoyer">Changer mon mot de passe</button>
    </form>

    <p class="pied">Vous n'êtes pas à l'origine de cette demande ? Fermez simplement cette page : votre mot de passe reste inchangé.</p>

    <script>
        (function () {
            var mdp = document.getElementById('mot_de_passe');
            var conf = document.getElementById('mot_de_passe_confirmation');
            var longueur = document.getElementById('regle-longueur');
            var identique = document.getElementById('regle-identique');
            function maj() {
                longueur.className = mdp.value.length >= 8 ? 'ok' : '';
                identique.className = mdp.value !== '' && mdp.value === conf.value ? 'ok' : '';
            }
            mdp.addEventListener('input', maj);
            conf.addEventListener('input', maj);
            document.getElementById('formulaire').addEventListener('submit', function () {
                document.getElementById('envoyer').disabled = true;
            });
            maj();
        })();
    </script>
@endsection
