@extends('emails.layout')

@section('title', 'Notification de soutenance de mémoire')

@section('content')
    @if($recipientType === 'etudiant')
        <h2>Félicitations {{ $etudiant->user->prenom }} !</h2>

        @if($type === 'programmee')
            <div class="success-box">
                <h3>🎓 Votre soutenance de mémoire est programmée !</h3>
                <p>Votre soutenance a été officiellement programmée par l'administration.</p>
            </div>
        @elseif($type === 'rappel')
            <div class="warning-box">
                <h3>⏰ Rappel : Soutenance demain !</h3>
                <p>Votre soutenance de mémoire a lieu demain. Bonne chance !</p>
            </div>
        @elseif($type === 'terminee')
            <div class="success-box">
                <h3>🎉 Soutenance terminée !</h3>
                <p>Félicitations ! Votre soutenance s'est bien déroulée.</p>
            </div>
        @endif

    @else
        <h2>Bonjour,</h2>

        @if($type === 'programmee')
            <div class="info-box">
                <h3>👥 Nouvelle soutenance programmée</h3>
                <p>Une soutenance de mémoire a été programmée et vous faites partie du jury.</p>
            </div>
        @elseif($type === 'rappel')
            <div class="warning-box">
                <h3>⏰ Rappel : Soutenance demain</h3>
                <p>Vous avez une soutenance de mémoire prévue demain en tant que membre du jury.</p>
            </div>
        @elseif($type === 'terminee')
            <div class="success-box">
                <h3>✅ Soutenance terminée</h3>
                <p>La soutenance à laquelle vous avez participé s'est bien déroulée.</p>
            </div>
        @endif
    @endif

    <div class="info-box">
        <h3>Détails de la soutenance :</h3>
        <p><strong>Étudiant :</strong> {{ $etudiant->user->prenom }} {{ $etudiant->user->nom }}</p>
        <p><strong>Niveau :</strong> {{ $etudiant->niveau }} - {{ $etudiant->filiere }}</p>
        <p><strong>Date et heure :</strong> {{ $soutenance->date_heure_soutenance->format('d/m/Y à H:i') }}</p>
        <p><strong>Durée :</strong> {{ $soutenance->duree_minutes }} minutes</p>
        <p><strong>Salle :</strong> {{ $soutenance->salle }}</p>
    </div>

    @if($recipientType !== 'etudiant')
        <div class="info-box">
            <h3>Composition du jury :</h3>
            <p><strong>Président :</strong> {{ $jury->president->user->prenom }} {{ $jury->president->user->nom }}</p>
            <p><strong>Rapporteur :</strong> {{ $jury->rapporteur->user->prenom }} {{ $jury->rapporteur->user->nom }}</p>
            <p><strong>Examinateur :</strong> {{ $jury->examinateur->user->prenom }} {{ $jury->examinateur->user->nom }}</p>
            <p><strong>Encadreur :</strong> {{ $jury->encadreur->user->prenom }} {{ $jury->encadreur->user->nom }}</p>
        </div>
    @endif

    @if($type === 'terminee' && $soutenance->note_finale)
        <div class="success-box">
            <h3>Résultats :</h3>
            <p><strong>Note finale :</strong> {{ $soutenance->note_finale }}/20</p>
            <p><strong>Mention :</strong> {{ ucfirst(str_replace('_', ' ', $soutenance->mention)) }}</p>
        </div>
    @endif

    @if($type === 'programmee' || $type === 'rappel')
        @if($recipientType === 'etudiant')
            <h3>Conseils pour votre soutenance :</h3>
            <ul>
                <li>Préparez une présentation de 15-20 minutes</li>
                <li>Relisez votre mémoire et préparez-vous aux questions</li>
                <li>Arrivez 15 minutes avant l'heure prévue</li>
                <li>Apportez une version imprimée de votre mémoire</li>
                <li>Préparez votre support de présentation (PowerPoint, etc.)</li>
            </ul>
        @else
            <h3>Informations importantes :</h3>
            <ul>
                <li>Le mémoire est disponible sur la plateforme</li>
                <li>Merci de prendre connaissance du travail avant la soutenance</li>
                <li>Préparez vos questions pour l'étudiant</li>
                <li>La grille d'évaluation sera fournie le jour J</li>
            </ul>
        @endif
    @endif

    <div style="text-align: center;">
        @if($recipientType === 'etudiant')
            <a href="{{ $platformUrl }}/etudiant/ma-soutenance" class="button">Voir ma soutenance</a>
        @else
            <a href="{{ $platformUrl }}/soutenances/mes-soutenances" class="button">Voir mes soutenances</a>
        @endif
    </div>

    @if($type === 'terminee')
        <p>Le mémoire sera prochainement archivé dans la bibliothèque numérique de l'ISI.</p>
    @endif
@endsection
