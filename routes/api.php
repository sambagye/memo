<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\EncadreurController;
use App\Http\Controllers\Api\EtudiantController;
use App\Http\Controllers\Api\SujetController;
use App\Http\Controllers\Api\AffectationController;
use App\Http\Controllers\Api\JuryController;
use App\Http\Controllers\Api\SoutenanceController;
use App\Http\Controllers\Api\ArchiveController;

/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES (sans authentification)
|--------------------------------------------------------------------------
*/

// === AUTHENTIFICATION ===
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    // POST /api/auth/login
    // Body: { "email": "admin@isi.sn", "password": "password123" }
    // Response: { "success": true, "data": { "user": {...}, "token": "...", "token_type": "Bearer" } }
});

// === BIBLIOTHÈQUE PUBLIQUE DES MÉMOIRES ===
Route::prefix('archives')->group(function () {
    Route::get('/', [ArchiveController::class, 'index']);
    // GET /api/archives?search=...&annee=2024&niveau=M2&mention=excellent&sort_by=note_finale&sort_direction=desc
    // Response: Liste paginée des mémoires publics avec filtres



    Route::get('/filtres', [ArchiveController::class, 'filtresDisponibles']);
    // GET /api/archives/filtres
    // Response: { "annees": [2024, 2023, ...], "niveaux": ["L3", "M1", "M2"], ... }

    Route::get('/nouveaux', [ArchiveController::class, 'nouveauxMemoires']);
    // GET /api/archives/nouveaux?limit=8
    // Response: Derniers mémoires archivés

    Route::get('/suggestions', [ArchiveController::class, 'memoiresSuggeres']);
    // GET /api/archives/suggestions?limit=6
    // Response: Mémoires suggérés basés sur popularité et qualité

    Route::get('/excellence', [ArchiveController::class, 'memoiresExcellence']);
    // GET /api/archives/excellence?limit=10
    // Response: Mémoires avec mention excellent/très bien

    Route::get('/recherche-avancee', [ArchiveController::class, 'rechercheAvancee']);
    // GET /api/archives/recherche-avancee?titre=...&auteur=...&encadreur=...&mots_cles=...
    // Response: Résultats de recherche avancée

    Route::get('/{id}', [ArchiveController::class, 'show']);
    // GET /api/archives/123
    // Response: Détails complets d'un mémoire

    Route::get('/{id}/telecharger', [ArchiveController::class, 'telecharger']);
    // GET /api/archives/123/telecharger
    // Response: Téléchargement du fichier PDF du mémoire
});

/*
|--------------------------------------------------------------------------
| ROUTES AUTHENTIFIÉES (avec middleware auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // === AUTHENTIFICATION (utilisateurs connectés) ===
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        // POST /api/auth/logout
        // Headers: Authorization: Bearer {token}
        // Response: { "success": true, "message": "Déconnexion réussie" }

        Route::get('/me', [AuthController::class, 'me']);
        // GET /api/auth/me
        // Response: Informations de l'utilisateur connecté avec ses relations

        Route::put('/change-password', [AuthController::class, 'changePassword']);
        // PUT /api/auth/change-password
        // Body: { "current_password": "...", "new_password": "...", "new_password_confirmation": "..." }
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES ADMINISTRATEUR
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        // GET /api/admin/dashboard
        // Response: { "statistiques": {...}, "sujets_en_attente": [...], "activites_recentes": [...] }

        // === GESTION DES UTILISATEURS ===
        Route::get('/utilisateurs', [AdminController::class, 'listeUtilisateurs']);
        // GET /api/admin/utilisateurs?role=encadreur&statut=actif&search=nom&page=1
        // Response: Liste paginée des utilisateurs avec filtres
        Route::get('/admin/utilisateurs/{id}', [AdminController::class, 'detailsUtilisateur']);
        Route::post('/utilisateurs', [AdminController::class, 'creerUtilisateur']);
        // POST /api/admin/utilisateurs
        // Body: { "nom": "...", "prenom": "...", "email": "...", "role": "encadreur", ... }

        // === GESTION DES SUJETS ===
        Route::get('/sujets', [AdminController::class, 'listeSujets']);
        // GET /api/admin/sujets?statut=propose&niveau=M2&search=titre
        // Response: Liste des sujets avec filtres

        Route::put('/sujets/{id}/validation', [AdminController::class, 'validerSujet']);
        // PUT /api/admin/sujets/123/validation
        // Body: { "action": "valider", "commentaire": "Sujet approuvé" }

        // === AFFECTATIONS ===
        Route::post('/affectations', [AdminController::class, 'effectuerAffectations']);
        // POST /api/admin/affectations
        // Body: { "affectations": [{"etudiant_id": 1, "sujet_id": 2, "encadreur_id": 3}] }
        Route::get('/affectations', [AdminController::class, 'listerAffectations']);

        // === GESTION DES SOUTENANCES ===
        Route::get('/soutenances', [AdminController::class, 'listeSoutenances']);
        // GET /api/admin/soutenances?statut=programmee&date_debut=2024-01-01&date_fin=2024-12-31&search=nom
        // Response: Liste des soutenances avec filtress

        Route::post('/soutenances', [AdminController::class, 'programmerSoutenance']);
        // POST /api/admin/soutenances
        // Body: { "etudiant_id": 1, "jury_id": 2, "dossier_soutenance_id": 3, "date_heure_soutenance": "2024-12-15 14:00:00", "salle": "Amphi A", "duree_minutes": 90 }

        Route::put('/soutenances/{id}/notes', [AdminController::class, 'validerNotesSoutenance']);
        // PUT /api/admin/soutenances/123/notes
        // Body: { "note_president": 16, "note_rapporteur": 15, "note_examinateur": 14, "note_encadreur": 17, "mention": "Bien", "appreciation_generale": "...", "recommandations": "..." }

        // === GESTION DES JURYS ===
        Route::get('/jurys', [AdminController::class, 'listeJurys']);
        // GET /api/admin/jurys?statut=actif&search=nom
        // Response: Liste des jurys avec filtres

        Route::post('/jurys', [AdminController::class, 'creerJury']);
        // POST /api/admin/jurys
        // Body: { "nom_jury": "Jury M2 IA 2024", "president_id": 1, "rapporteur_id": 2, "examinateur_id": 3, "encadreur_id": 4, "commentaire": "..." }

        // === GESTION DES ARCHIVES ===
        Route::get('/archives', [AdminController::class, 'listeArchives']);
        // GET /api/admin/archives?annee=2024&niveau=M2&mention=Bien&search=titre
        // Response: Liste des mémoires archivés avec filtres

        Route::post('/archives', [AdminController::class, 'archiverMemoire']);
        // POST /api/admin/archives
        // Body: { "soutenance_id": 1, "titre_memoire": "...", "fichier_memoire": "memoire.pdf", "resume_francais": "...", "resume_anglais": "...", "mots_cles": "...", "visible_public": true }

        // === STATISTIQUES DÉTAILLÉES ===
        Route::get('/statistiques', [AdminController::class, 'statistiquesDetaillees']);
        // GET /api/admin/statistiques?annee=2024
        // Response: Statistiques détaillées par niveau, mention, évolution mensuelle, top encadreurs, téléchargements
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES ENCADREUR
    |--------------------------------------------------------------------------
    */
    Route::prefix('encadreur')->middleware('role:encadreur')->group(function () {

        Route::get('/dashboard', [EncadreurController::class, 'dashboard']);
        // GET /api/encadreur/dashboard
        // Response: Dashboard avec statistiques, étudiants, séances à venir, dossiers en attente

        // === GESTION DES SUJETS ===
        Route::post('/sujets', [EncadreurController::class, 'proposerSujet']);
        // POST /api/encadreur/sujets
        // Body: { "titre": "...", "description": "...", "objectifs": "...", "domaine": "...", "niveau": "M2", "nombre_places_disponibles": 2 }

        Route::get('/mes-sujets', [EncadreurController::class, 'mesSujets']);
        // GET /api/encadreur/mes-sujets?statut=valide&niveau=M2
        // Response: Liste des sujets de l'encadreur

        // === GESTION DES ÉTUDIANTS ===
        Route::get('/mes-etudiants', [EncadreurController::class, 'mesEtudiants']);
        // GET /api/encadreur/mes-etudiants
        // Response: Liste des étudiants encadrés avec leurs affectations

        // === SÉANCES D'ENCADREMENT ===
        Route::post('/seances', [EncadreurController::class, 'programmerSeance']);
        // POST /api/encadreur/seances
        // Body: { "affectation_id": 1, "titre": "...", "date_heure": "2024-12-01 14:00:00", "duree_minutes": 60, "lieu": "Bureau 101" }

        Route::get('/mes-seances', [EncadreurController::class, 'mesSeances']);
        // GET /api/encadreur/mes-seances?statut=programmee&periode=cette_semaine

        Route::put('/seances/{id}/terminer', [EncadreurController::class, 'terminerSeance']);
        // PUT /api/encadreur/seances/123/terminer
        // Body: { "compte_rendu": "...", "travail_a_faire": "..." }

        Route::get('/calendrier', [EncadreurController::class, 'calendrierSeances']);
        // GET /api/encadreur/calendrier?date_debut=2024-12-01&date_fin=2024-12-31
        // Response: Événements pour calendrier

        // === AUTORISATION DE SOUTENANCE ===
        Route::put('/dossiers/{id}/autorisation', [EncadreurController::class, 'autoriserSoutenance']);
        // PUT /api/encadreur/dossiers/123/autorisation
        // Body: { "autoriser": true, "commentaire": "Dossier complet et travail de qualité" }

        // === STATISTIQUES ===
        Route::get('/statistiques', [EncadreurController::class, 'statistiques']);
        // GET /api/encadreur/statistiques
        // Response: Statistiques détaillées de l'encadreur
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES ÉTUDIANT
    |--------------------------------------------------------------------------
    */
    Route::prefix('etudiant')->middleware('role:etudiant')->group(function () {

        Route::get('/dashboard', [EtudiantController::class, 'dashboard']);
        // GET /api/etudiant/dashboard
        // Response: Dashboard avec statut, affectation, séances, dossier, soutenance

        // === CHOIX DES SUJETS ===
        Route::get('/sujets-disponibles', [EtudiantController::class, 'sujetsDisponibles']);
        // GET /api/etudiant/sujets-disponibles?domaine=IA&search=chatbot
        // Response: Sujets disponibles pour le niveau de l'étudiant

        Route::get('/sujets/{id}', [EtudiantController::class, 'detailsSujet']);
        // GET /api/etudiant/sujets/123
        // Response: Détails complets d'un sujet

        Route::post('/choix-sujets', [EtudiantController::class, 'choisirSujets']);
        // POST /api/etudiant/choix-sujets
        // Body: { "sujets": [123, 456, 789] } // max 3 sujets par ordre de préférence

        // === SÉANCES D'ENCADREMENT ===
        Route::get('/mes-seances', [EtudiantController::class, 'mesSeances']);
        // GET /api/etudiant/mes-seances?statut=terminee&periode=passees

        Route::get('/calendrier', [EtudiantController::class, 'calendrier']);
        // GET /api/etudiant/calendrier?date_debut=2024-12-01&date_fin=2024-12-31
        // Response: Calendrier personnel avec séances et soutenance

        // === DOSSIER DE SOUTENANCE ===
        Route::post('/dossier-soutenance', [EtudiantController::class, 'creerDossierSoutenance']);
        // POST /api/etudiant/dossier-soutenance
        // Response: Création du dossier de soutenance

        Route::get('/mon-dossier', [EtudiantController::class, 'monDossier']);
        // GET /api/etudiant/mon-dossier
        // Response: Détails du dossier avec pourcentage de completion et documents manquants

        Route::post('/documents', [EtudiantController::class, 'uploaderDocument']);
        // POST /api/etudiant/documents (multipart/form-data)
        // Body: type_document=memoire_pdf&document=file.pdf

        Route::post('/soumettre-dossier', [EtudiantController::class, 'soumetteDossier']);
        // POST /api/etudiant/soumettre-dossier
        // Response: Soumission définitive du dossier complet

        Route::get('/dossiers/{id}/documents/{type}', [EtudiantController::class, 'telechargerDocument']);
        // GET /api/etudiant/dossiers/123/documents/memoire_pdf
        // Response: Téléchargement du document

        // === SOUTENANCE ===
        Route::get('/ma-soutenance', [EtudiantController::class, 'maSoutenance']);
        // GET /api/etudiant/ma-soutenance
        // Response: Détails de la soutenance programmée avec jury
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES SUJETS (accessible à tous les rôles connectés)
    |--------------------------------------------------------------------------
    */
    Route::prefix('sujets')->group(function () {
        Route::get('/', [SujetController::class, 'index']);
        // GET /api/sujets?statut=valide&niveau=M2&domaine=IA&encadreur_id=123&disponible=true&search=...

        Route::get('/statistiques', [SujetController::class, 'statistiques']);
        // GET /api/sujets/statistiques
        // Response: Statistiques des sujets avec répartitions

        Route::get('/domaines', [SujetController::class, 'domaines']);
        // GET /api/sujets/domaines
        // Response: Liste des domaines disponibles

        Route::get('/populaires', [SujetController::class, 'sujetsPopulaires']);
        // GET /api/sujets/populaires?limit=10
        // Response: Sujets les plus demandés

        Route::get('/{id}', [SujetController::class, 'show']);
        // GET /api/sujets/123
        // Response: Détails d'un sujet avec encadreur et étudiants affectés

        // Routes pour créer/modifier/supprimer (selon permissions)
        Route::post('/', [SujetController::class, 'store']);
        Route::put('/{id}', [SujetController::class, 'update']);
        Route::delete('/{id}', [SujetController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES AFFECTATIONS (Admin principalement)
    |--------------------------------------------------------------------------
    */
    Route::prefix('affectations')->group(function () {
        Route::get('/', [AffectationController::class, 'index']);
        // GET /api/affectations?statut=affecte&encadreur_id=123&niveau=M2&search=...

        Route::get('/statistiques', [AffectationController::class, 'statistiques']);
        // GET /api/affectations/statistiques

        Route::get('/{id}', [AffectationController::class, 'show']);
        // GET /api/affectations/123

        // Routes Admin
        Route::post('/', [AffectationController::class, 'store'])->middleware('role:admin');
        Route::post('/automatiques', [AffectationController::class, 'affectationsAutomatiques'])->middleware('role:admin');
        Route::put('/{id}', [AffectationController::class, 'update'])->middleware('role:admin');
        Route::delete('/{id}', [AffectationController::class, 'destroy'])->middleware('role:admin');
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES JURYS (Admin et Membres de jury)
    |--------------------------------------------------------------------------
    */
    Route::prefix('juries')->group(function () {
        Route::get('/', [JuryController::class, 'index']);
        // GET /api/juries?statut=actif&search=...

        Route::get('/statistiques', [JuryController::class, 'statistiques']);
        // GET /api/juries/statistiques

        Route::get('/membres-disponibles', [JuryController::class, 'membresDisponibles']);
        // GET /api/juries/membres-disponibles
        // Response: Membres de jury et encadreurs disponibles

        Route::get('/mes-juries', [JuryController::class, 'mesJurys'])->middleware('role:membre_jury');
        // GET /api/juries/mes-juries
        // Response: Jurys auxquels participe le membre connecté

        Route::get('/{id}', [JuryController::class, 'show']);
        // GET /api/juries/123

        // Routes Admin
        Route::post('/', [JuryController::class, 'store'])->middleware('role:admin');
        Route::put('/{id}', [JuryController::class, 'update'])->middleware('role:admin');
        Route::delete('/{id}', [JuryController::class, 'destroy'])->middleware('role:admin');
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES SOUTENANCES
    |--------------------------------------------------------------------------
    */
    Route::prefix('soutenances')->group(function () {
        Route::get('/', [SoutenanceController::class, 'index']);
        // GET /api/soutenances?statut=programmee&jury_id=123&date_debut=...&date_fin=...&search=...

        Route::get('/statistiques', [SoutenanceController::class, 'statistiques']);
        // GET /api/soutenances/statistiques

        Route::get('/planning', [SoutenanceController::class, 'planning']);
        // GET /api/soutenances/planning?date_debut=2024-12-01&date_fin=2024-12-31
        // Response: Planning pour calendrier

        Route::get('/mes-soutenances', [SoutenanceController::class, 'mesSoutenances']);
        // GET /api/soutenances/mes-soutenances (pour membres de jury et encadreurs)

        Route::get('/{id}', [SoutenanceController::class, 'show']);
        // GET /api/soutenances/123

        // Routes Admin
        Route::post('/', [SoutenanceController::class, 'store'])->middleware('role:admin');
        Route::put('/{id}', [SoutenanceController::class, 'update'])->middleware('role:admin');

        // Routes pour membres de jury
        Route::put('/{id}/notes', [SoutenanceController::class, 'saisirNotes']);
        // PUT /api/soutenances/123/notes
        // Body: { "note": 16.5, "appreciation": "..." }

        Route::put('/{id}/terminer', [SoutenanceController::class, 'terminerSoutenance']);
        // PUT /api/soutenances/123/terminer (Président du jury)
        // Body: { "appreciation_generale": "...", "recommandations": "..." }
    });

    /*
    |--------------------------------------------------------------------------
    | ROUTES ARCHIVES (Admin)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin/archives')->middleware('role:admin')->group(function () {
        Route::get('/', [ArchiveController::class, 'administrationArchives']);
        // GET /api/admin/archives?visible=false&search=...

        Route::put('/{id}/visibilite', [ArchiveController::class, 'basculerVisibilite']);
        // PUT /api/admin/archives/123/visibilite
        // Response: Bascule la visibilité publique du mémoire
    });
});

/*
|--------------------------------------------------------------------------
| MIDDLEWARE PERSONNALISÉS UTILISÉS
|--------------------------------------------------------------------------

Middleware role:admin -> Vérifie que l'utilisateur connecté a le rôle 'admin'
Middleware role:encadreur -> Vérifie que l'utilisateur connecté a le rôle 'encadreur'
Middleware role:etudiant -> Vérifie que l'utilisateur connecté a le rôle 'etudiant'
Middleware role:membre_jury -> Vérifie que l'utilisateur connecté a le rôle 'membre_jury'

|--------------------------------------------------------------------------
| CODES DE RÉPONSE STANDARDS
|--------------------------------------------------------------------------

200: Succès
201: Créé avec succès
400: Erreur de requête (données manquantes/invalides)
401: Non authentifié
403: Non autorisé (pas les bonnes permissions)
404: Ressource non trouvée
422: Erreur de validation
500: Erreur serveur

|--------------------------------------------------------------------------
| FORMAT DE RÉPONSE STANDARD
|--------------------------------------------------------------------------

Succès:
{
    "success": true,
    "message": "Message de succès",
    "data": { ... }
}

Erreur:
{
    "success": false,
    "message": "Message d'erreur",
    "errors": { ... } // Pour les erreurs de validation
}

|--------------------------------------------------------------------------
| AUTHENTIFICATION
|--------------------------------------------------------------------------

Pour toutes les routes protégées, inclure dans les headers:
Authorization: Bearer {token}

Le token est obtenu via POST /api/auth/login

*/
