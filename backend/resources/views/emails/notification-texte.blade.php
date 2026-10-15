Bonjour {{ $prenom }},

{{ $accroche }}

{{ $titre }}
{{ $corps }}

@if (! empty($lien))
Suivre mon dossier : {{ $lien }}

Ce lien est personnel : ne le partagez pas. Vous y suivrez la validation de votre compte (en attente, validé ou refusé).
@else
Ouvrez l'application Sopi pour voir le détail.
@endif

Ce message est envoyé automatiquement, merci de ne pas y répondre.
L'équipe Sopi
