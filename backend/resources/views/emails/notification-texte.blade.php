Bonjour {{ $prenom }},

{{ $accroche }}

{{ $titre }}
{{ $corps }}

@if (! empty($lien))
{{ $libelleLien }} : {{ $lien }}

{{ $noteLien }}
@else
Ouvrez l'application Sopi pour voir le détail.
@endif

Ce message est envoyé automatiquement, merci de ne pas y répondre.
L'équipe Sopi
