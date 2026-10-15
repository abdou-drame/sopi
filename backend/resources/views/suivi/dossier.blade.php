@extends('suivi.gabarit')

@section('titre', 'Suivi de votre dossier')

@section('contenu')
    <h1>{{ $entreprise }}</h1>
    <p class="meta">{{ $metier }}@if ($date) · Dossier déposé le {{ $date }}@endif</p>

    <div class="bandeau {{ $etat['classe'] }}" role="status">
        <strong>{{ $etat['titre'] }}</strong>
        <p>{{ $etat['texte'] }}</p>
        @if ($motif)
            <p><strong>Motif :</strong> {{ $motif }}</p>
        @endif
    </div>

    <h2>Vos justificatifs</h2>
    <ul>
        @forelse ($justificatifs as $justificatif)
            <li>
                <span>{{ $justificatif['document'] }}
                    @if ($justificatif['motif'])<span class="motif">Motif : {{ $justificatif['motif'] }}</span>@endif
                </span>
                <span class="pastille {{ $justificatif['classe'] }}">{{ $justificatif['statut'] }}</span>
            </li>
        @empty
            <li>Aucun justificatif.</li>
        @endforelse
    </ul>

    <p class="pied">Cette page est personnelle : ne partagez pas son adresse.</p>
@endsection
