@extends('emails.layout')

@section('title', 'Nouvelle affectation de mémoire')

@section('content')
    @if($recipientType === 'etudiants')
        <h2>Félicitations {{ $etudiant->user->prenom }} !</h2>

        <div class="success-box">
            <h3>🎉 Vous avez été affecté(e) à un sujet de mémoire !</h3>
            <p>Votre affectation a été confirmée par l'administration.</p>
        </div>

        <div class="info-box">
            <h3>Détails de votre affectation :</h3>
            <p><strong>Sujet :</strong> {{ $sujet->titre }}</p>
            <p><strong>Domaine :</strong> {{ $sujet->domaine }}</p>
            <p><strong>Niveau :</strong> {{ $sujet->niveau }}</p>
            <p><strong>Encadreur :</strong> {{ $encadreur->user->prenom }} {{ $encadreur->user->nom }}</p>
            <p><strong>Grade :</strong> {{ $encadreur->grade_academique }}</p>
            <p><strong>Spécialité :</strong> {{ $encadreur->specialite }}</p>
            <p><strong>Date d'affectation :</strong> {{ $affectation->date_affectation->format('d/m/Y à H:i') }}</p>
        </div>

        <h3>Prochaines étapes :</h3>
        <ol>
            <li>Connectez-vous à la plateforme pour consulter les détails complets</li>
            <li>Contactez votre encadreur pour planifier votre première séance</li>
            <li>Consultez les objectifs et prérequis du sujet</li>
            <li>Préparez votre plan de travail initial</li>
        </ol>

        <div style="text-align: center;">
            <a href="{{ $platformUrl }}/etudiant/dashboard" class="button">Accéder à mon espace</a>
        </div>

    @else
        <h2>Bonjour {{ $encadreur->user->prenom }} {{ $encadreur->user->nom }},</h2>

        <div class="info-box">
            <h3>👥 Nouvel étudiant affecté !</h3>
            <p>Un nouvel étudiant vous a été affecté pour l'encadrement de mémoire.</p>
        </div>

        <div class="info-box">
            <h3>Détails de l'affectation :</h3>
            <p><strong>Étudiant :</strong> {{ $etudiant->user->prenom }} {{ $etudiant->user->nom }}</p>
            <p><strong>Email :</strong> {{ $etudiant->user->email }}</p>
            <p><strong>Numéro étudiant :</strong> {{ $etudiant->numero_etudiant }}</p>
            <p><strong>Niveau :</strong> {{ $etudiant->niveau }}</p>
            <p><strong>Filière :</strong> {{ $etudiant->filiere }}</p>
            <p><strong>Sujet :</strong> {{ $sujet->titre }}</p>
            <p><strong>Date d'affectation :</strong> {{ $affectation->date_affectation->format('d/m/Y à H:i') }}</p>
        </div>

        <h3>Prochaines étapes :</h3>
        <ol>
            <li>Contactez votre nouvel étudiant pour une première rencontre</li>
            <li>Programmez les séances d'encadrement régulières</li>
            <li>Définissez ensemble le planning et les objectifs</li>
            <li>Partagez les ressources et documents nécessaires</li>
        </ol>

        <div style="text-align: center;">
            <a href="{{ $platformUrl }}/encadreur/mes-etudiants" class="button">Voir mes étudiants</a>
        </div>
    @endif

    <p>Bonne collaboration et bon travail !</p>
@endsection
