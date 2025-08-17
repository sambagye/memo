@extends('emails.layout')

@section('title', 'Validation de votre sujet de mémoire')

@section('content')
    <h2>Bonjour {{ $encadreur->user->prenom }} {{ $encadreur->user->nom }},</h2>

    @if($isApproved)
        <div class="success-box">
            <h3>✅ Votre sujet a été validé !</h3>
            <p>Félicitations ! Votre proposition de sujet de mémoire a été approuvée par l'administration.</p>
        </div>
    @else
        <div class="warning-box">
            <h3>❌ Votre sujet a été refusé</h3>
            <p>Malheureusement, votre proposition de sujet de mémoire n'a pas été approuvée par l'administration.</p>
        </div>
    @endif

    <div class="info-box">
        <h3>Détails du sujet :</h3>
        <p><strong>Titre :</strong> {{ $sujet->titre }}</p>
        <p><strong>Niveau :</strong> {{ $sujet->niveau }}</p>
        <p><strong>Domaine :</strong> {{ $sujet->domaine }}</p>
        <p><strong>Places disponibles :</strong> {{ $sujet->nombre_places_disponibles }}</p>
        <p><strong>Date de soumission :</strong> {{ $sujet->created_at->format('d/m/Y à H:i') }}</p>
        <p><strong>Date de traitement :</strong> {{ $sujet->date_validation->format('d/m/Y à H:i') }}</p>
    </div>

    @if($sujet->commentaire_admin)
        <div class="info-box">
            <h3>Commentaire de l'administration :</h3>
            <p>{{ $sujet->commentaire_admin }}</p>
        </div>
    @endif

    @if($isApproved)
        <p>Votre sujet est maintenant visible par les étudiants et ils peuvent le choisir dans leurs préférences. Vous recevrez une notification lorsque des étudiants vous seront affectés.</p>

        <div style="text-align: center;">
            <a href="{{ $platformUrl }}/encadreur/mes-sujets" class="button">Voir mes sujets</a>
        </div>
    @else
        <p>Vous pouvez proposer un nouveau sujet en tenant compte des commentaires de l'administration ou modifier votre sujet existant si cela est possible.</p>

        <div style="text-align: center;">
            <a href="{{ $platformUrl }}/encadreur/sujets/nouveau" class="button">Proposer un nouveau sujet</a>
        </div>
    @endif

    <p>Merci pour votre participation à la formation de nos étudiants.</p>
@endsection
