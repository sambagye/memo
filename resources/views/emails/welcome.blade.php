@extends('emails.layout')

@section('title', 'Bienvenue sur la plateforme ISI')

@section('content')
    <h2>Bienvenue {{ $user->prenom }} {{ $user->nom }} !</h2>

    <p>Votre compte a été créé avec succès sur la plateforme de gestion des mémoires de l'ISI.</p>

    <div class="info-box">
        <h3>Vos identifiants de connexion :</h3>
        <p><strong>Email :</strong> {{ $user->email }}</p>
        <p><strong>Mot de passe temporaire :</strong> {{ $temporaryPassword }}</p>
        <p><strong>Rôle :</strong> {{ ucfirst($user->role) }}</p>
    </div>

    <div class="warning-box">
        <p><strong>Important :</strong> Pour des raisons de sécurité, nous vous recommandons fortement de changer votre mot de passe lors de votre première connexion.</p>
    </div>

    <p>Vous pouvez maintenant accéder à la plateforme en cliquant sur le bouton ci-dessous :</p>

    <div style="text-align: center;">
        <a href="{{ $loginUrl }}" class="button">Se connecter à la plateforme</a>
    </div>

    @if($user->role === 'etudiant')
        <h3>En tant qu'étudiant, vous pourrez :</h3>
        <ul>
            <li>Consulter les sujets de mémoire disponibles</li>
            <li>Choisir vos préférences de sujets</li>
            <li>Suivre votre encadrement</li>
            <li>Soumettre votre dossier de soutenance</li>
            <li>Consulter les informations sur votre soutenance</li>
        </ul>
    @elseif($user->role === 'encadreur')
        <h3>En tant qu'encadreur, vous pourrez :</h3>
        <ul>
            <li>Proposer des sujets de mémoire</li>
            <li>Gérer vos étudiants encadrés</li>
            <li>Programmer et suivre les séances d'encadrement</li>
            <li>Autoriser les soutenances</li>
            <li>Participer aux jurys de soutenance</li>
        </ul>
    @elseif($user->role === 'membre_jury')
        <h3>En tant que membre de jury, vous pourrez :</h3>
        <ul>
            <li>Consulter votre planning de jurys</li>
            <li>Accéder aux mémoires à évaluer</li>
            <li>Saisir vos notes d'évaluation</li>
            <li>Participer aux délibérations</li>
        </ul>
    @elseif($user->role === 'admin')
        <h3>En tant qu'administrateur, vous pourrez :</h3>
        <ul>
            <li>Gérer tous les utilisateurs</li>
            <li>Valider les sujets proposés</li>
            <li>Effectuer les affectations</li>
            <li>Organiser les soutenances</li>
            <li>Gérer la bibliothèque des mémoires</li>
        </ul>
    @endif

    <p>Si vous rencontrez des difficultés ou avez des questions, n'hésitez pas à contacter l'administration.</p>

    <p>Bonne utilisation de la plateforme !</p>
@endsection
