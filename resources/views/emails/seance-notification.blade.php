@extends('emails.layout')

@section('title', 'Notification de séance d\'encadrement')

@section('content')
    <h2>Bonjour {{ $etudiant->user->prenom }} {{ $etudiant->user->nom }},</h2>

    @if($type === 'programmee')
        <div class="info-box">
            <h3>📅 Nouvelle séance d'encadrement programmée</h3>
            <p>Votre encadreur a programmé une nouvelle séance d'encadrement.</p>
        </div>
    @elseif($type === 'rappel')
        <div class="warning-box">
            <h3>⏰ Rappel : Séance d'encadrement demain</h3>
            <p>N'oubliez pas votre séance d'encadrement prévue demain !</p>
        </div>
    @elseif($type === 'annulee')
        <div class="warning-box">
            <h3>❌ Séance d'encadrement annulée</h3>
            <p>Votre séance d'encadrement a été annulée.</p>
        </div>
    @elseif($type === 'terminee')
        <div class="success-box">
            <h3>✅ Compte-rendu de séance disponible</h3>
            <p>Le compte-rendu de votre dernière séance d'encadrement est disponible.</p>
        </div>
    @endif

    <div class="info-box">
        <h3>Détails de la séance :</h3>
        <p><strong>Titre :</strong> {{ $seance->titre }}</p>
        <p><strong>Date et heure :</strong> {{ $seance->date_heure->format('d/m/Y à H:i') }}</p>
        <p><strong>Durée :</strong> {{ $seance->duree_minutes }} minutes</p>
        @if($seance->lieu)
            <p><strong>Lieu :</strong> {{ $seance->lieu }}</p>
        @endif
        @if($seance->lien_meeting)
            <p><strong>Lien de réunion :</strong> <a href="{{ $seance->lien_meeting }}">{{ $seance->lien_meeting }}</a></p>
        @endif
        <p><strong>Encadreur :</strong> {{ $encadreur->user->prenom }} {{ $encadreur->user->nom }}</p>
        <p><strong>Sujet :</strong> {{ $sujet->titre }}</p>
    </div>

    @if($seance->description && $type !== 'terminee')
        <div class="info-box">
            <h3>Description :</h3>
            <p>{{ $seance->description }}</p>
        </div>
    @endif

    @if($type === 'terminee' && $seance->compte_rendu)
        <div class="info-box">
            <h3>Compte-rendu de la séance :</h3>
            <p>{{ $seance->compte_rendu }}</p>
        </div>
    @endif

    @if($type === 'terminee' && $seance->travail_a_faire)
        <div class="warning-box">
            <h3>Travail à faire pour la prochaine fois :</h3>
            <p>{{ $seance->travail_a_faire }}</p>
        </div>
    @endif

    @if($type === 'programmee' || $type === 'rappel')
        <h3>Préparation recommandée :</h3>
        <ul>
            <li>Préparez vos questions et points à discuter</li>
            <li>Rassemblez les documents de travail pertinents</li>
            <li>Préparez un bref résumé de votre avancement</li>
            <li>Notez les difficultés rencontrées</li>
        </ul>
    @endif

    <div style="text-align: center;">
        <a href="{{ $platformUrl }}/etudiant/mes-seances" class="button">Voir mes séances</a>
    </div>

    @if($type === 'annulee')
        <p>Votre encadreur vous contactera pour reprogrammer cette séance.</p>
    @endif
@endsection
