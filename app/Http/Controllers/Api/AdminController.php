<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Encadreur;
use App\Models\Etudiant;
use App\Models\MembreDuJury;
use App\Models\Sujet;
use App\Models\Affectation;
use App\Models\Soutenance;
use App\Models\Jury;
use App\Models\DossierSoutenance;
use App\Models\MemoireArchive;
use App\Models\SeanceEncadrement;
use Illuminate\Support\Str;
use App\Mail\WelcomeMail;
use App\Mail\SujetValidationMail;
use App\Mail\AffectationNotificationMail;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Dashboard statistiques
     */
    public function dashboard()
    {
        // Statistiques générales
        $stats = [
            'total_utilisateurs' => User::count(),
            'total_encadreurs' => User::byRole('encadreur')->count(),
            'total_etudiants' => User::byRole('etudiant')->count(),
            'total_membres_jury' => User::byRole('membre_jury')->count(),

            // Statistiques des sujets
            'sujets_proposes' => Sujet::where('statut', 'propose')->count(),
            'sujets_valides' => Sujet::where('statut', 'valide')->count(),
            'sujets_refuses' => Sujet::where('statut', 'refuse')->count(),
            'total_sujets' => Sujet::count(),

            // Statistiques des affectations
            'total_affectations' => Affectation::count(),
            'affectations_actives' => Affectation::where('statut', 'affecte')->count(),
            'etudiants_sans_affectation' => Etudiant::where('statut_memoire', 'en_attente_sujet')->count(),
            'etudiants_affectes' => Etudiant::where('statut_memoire', 'affecte')->count(),

            // Statistiques des soutenances
            'soutenances_programmees' => Soutenance::where('statut', 'programmee')->count(),
            'soutenances_terminees' => Soutenance::where('statut', 'terminee')->count(),
            'soutenances_aujourd_hui' => Soutenance::whereDate('date_heure_soutenance', today())->count(),
            'soutenances_cette_semaine' => Soutenance::whereBetween('date_heure_soutenance', [now()->startOfWeek(), now()->endOfWeek()])->count(),

            // Statistiques des dossiers
            'dossiers_complets' => DossierSoutenance::where('dossier_complet', true)->count(),
            'dossiers_en_attente' => DossierSoutenance::where('dossier_complet', false)->count(),
            'dossiers_autorises' => DossierSoutenance::where('autorisation_encadreur', true)->count(),

            // Statistiques des archives
            'total_archives' => MemoireArchive::count(),
            'archives_publiques' => MemoireArchive::where('visible_public', true)->count(),
            'total_telechargements' => MemoireArchive::sum('nombre_telechargements'),

            // Statistiques des séances d'encadrement
            'seances_ce_mois' => SeanceEncadrement::whereMonth('date_heure', now()->month)
                                                  ->whereYear('date_heure', now()->year)
                                                  ->count(),
            'seances_terminees' => SeanceEncadrement::where('statut', 'terminee')->count(),
        ];

        // Sujets en attente de validation
        $sujetsEnAttente = Sujet::where('statut', 'propose')
            ->with(['encadreur.user'])
            ->latest()
            ->take(5)
            ->get();

        // Soutenances à venir
        $soutenancesAVenir = Soutenance::where('statut', 'programmee')
            ->where('date_heure_soutenance', '>', now())
            ->with(['etudiant.user', 'jury'])
            ->orderBy('date_heure_soutenance')
            ->take(5)
            ->get();

        // Dossiers en attente de validation
        $dossiersEnAttente = DossierSoutenance::where('statut_verification', 'en_attente')
            ->with(['etudiant.user', 'affectation.sujet'])
            ->latest('date_soumission')
            ->take(5)
            ->get();

        // Activités récentes
        $activitesRecentes = collect();

        // Nouveaux utilisateurs
        $nouveauxUtilisateurs = User::latest()
            ->take(3)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'nouvel_utilisateur',
                    'message' => "Nouvel utilisateur: {$user->nom_complet} ({$user->role})",
                    'date' => $user->created_at,
                    'icon' => 'user-plus',
                    'color' => 'success'
                ];
            });

        // Nouvelles affectations
        $nouvellesAffectations = Affectation::with(['etudiant.user', 'sujet'])
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($affectation) {
                return [
                    'type' => 'nouvelle_affectation',
                    'message' => "Affectation: {$affectation->etudiant->user->nom_complet} → {$affectation->sujet->titre}",
                    'date' => $affectation->created_at,
                    'icon' => 'link',
                    'color' => 'info'
                ];
            });

        // Soutenances récentes
        $soutenancesRecentes = Soutenance::where('statut', 'terminee')
            ->with(['etudiant.user'])
            ->latest('date_heure_soutenance')
            ->take(2)
            ->get()
            ->map(function ($soutenance) {
                return [
                    'type' => 'soutenance_terminee',
                    'message' => "Soutenance terminée: {$soutenance->etudiant->user->nom_complet} - Note: {$soutenance->note_finale}/20",
                    'date' => $soutenance->date_heure_soutenance,
                    'icon' => 'award',
                    'color' => 'warning'
                ];
            });

        $activitesRecentes = $activitesRecentes
            ->merge($nouveauxUtilisateurs)
            ->merge($nouvellesAffectations)
            ->merge($soutenancesRecentes);

        // Statistiques par mois pour les graphiques
        $statistiquesMensuelles = [];
        for ($i = 5; $i >= 0; $i--) {
            $mois = now()->subMonths($i);
            $statistiquesMensuelles[] = [
                'mois' => $mois->format('M Y'),
                'affectations' => Affectation::whereMonth('created_at', $mois->month)
                                           ->whereYear('created_at', $mois->year)
                                           ->count(),
                'soutenances' => Soutenance::whereMonth('date_heure_soutenance', $mois->month)
                                          ->whereYear('date_heure_soutenance', $mois->year)
                                          ->count(),
                'nouveaux_utilisateurs' => User::whereMonth('created_at', $mois->month)
                                              ->whereYear('created_at', $mois->year)
                                              ->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => $stats,
                'sujets_en_attente' => $sujetsEnAttente,
                'soutenances_a_venir' => $soutenancesAVenir,
                'dossiers_en_attente' => $dossiersEnAttente,
                'activites_recentes' => $activitesRecentes->sortByDesc('date')->take(10)->values(),
                'statistiques_mensuelles' => $statistiquesMensuelles,
            ]
        ], 200);
    }

    /**
     * Créer un utilisateur
     */
    /**
     * Détails d'un utilisateur spécifique
     */
    public function detailsUtilisateur($id)
    {
        $user = User::with(['encadreur', 'etudiant', 'membreDuJury'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }
    public function creerUtilisateur(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:admin,encadreur,etudiant,membre_jury',
            'telephone' => 'nullable|string|max:20',

            // Champs spécifiques selon le rôle
//            Encadreur et ou menbre du jury
            'specialite' => 'required_if:role,encadreur,membre_jury|string|max:255',
            'grade_academique' => 'required_if:role,encadreur,membre_jury|string|max:255',
            'nombre_max_etudiants' => 'required_if:role,encadreur|integer|min:1|max:20',
            'bio' => 'nullable|string',
            'etablissement' => 'required_if:role,membre_jury|string|max:255',
            'est_externe' => 'required_if:role,membre_jury|boolean',
//           Etudiant
            'numero_etudiant' => 'required_if:role,etudiant|string|max:50|unique:etudiants',
            'niveau' => 'required_if:role,etudiant|in:L3,M1,M2',
            'filiere' => 'required_if:role,etudiant|string|max:255',
            'annee_academique' => 'required_if:role,etudiant|integer|min:2020|max:2030',
        ], [
            'nom.required' => 'Le nom est requis',
            'prenom.required' => 'Le prénom est requis',
            'email.required' => 'L\'email est requis',
            'email.unique' => 'Cet email existe déjà',
            'role.required' => 'Le rôle est requis',
            'numero_etudiant.unique' => 'Ce numéro étudiant existe déjà',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Générer un mot de passe temporaire
            $motDePasseTemporaire = Str::random(10);

            // Créer l'utilisateur
            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($motDePasseTemporaire),
                'role' => $request->role,
                'telephone' => $request->telephone,
            ]);

            // Créer les données spécifiques selon le rôle
            $this->creerDonneesSpecifiques($user, $request);

            // Envoyer l'email avec les identifiants (simulation)
            Mail::to($user->email)->send(new WelcomeMail($user, $motDePasseTemporaire));

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès. Les identifiants ont été envoyés par email.',
                'data' => $user->load($this->getRelationsByRole($user->role))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Liste des utilisateurs avec pagination
     */
    public function listeUtilisateurs(Request $request)
    {
        $query = User::query();

        // Filtres
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Chargement des relations
        $query->with(['encadreur', 'etudiant', 'membreDuJury']);

        $utilisateurs = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $utilisateurs
        ], 200);
    }

    /**
     * Valider un sujet
     */
    public function validerSujet(Request $request, $sujetId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:valider,refuser',
            'commentaire' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $sujet = Sujet::find($sujetId);

        if (!$sujet) {
            return response()->json([
                'success' => false,
                'message' => 'Sujet non trouvé'
            ], 404);
        }

        if ($sujet->statut !== 'propose') {
            return response()->json([
                'success' => false,
                'message' => 'Ce sujet a déjà été traité'
            ], 400);
        }

        $sujet->update([
            'statut' => $request->action === 'valider' ? 'valide' : 'refuse',
            'commentaire_admin' => $request->commentaire,
            'date_validation' => now(),
        ]);

        // Notifier l'encadreur (simulation)
        Mail::to($sujet->encadreur->user->email)->send(new SujetValidationMail($sujet));

        $message = $request->action === 'valider' ? 'Sujet validé avec succès' : 'Sujet refusé';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $sujet->load('encadreur.user')
        ], 200);
    }

    /**
     * Liste des sujets avec filtres
     */
    public function listeSujets(Request $request)
    {
        $query = Sujet::with(['encadreur.user']);

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('niveau') && $request->niveau !== 'all') {
            $query->where('niveau', $request->niveau);
        }

        if ($request->has('domaine') && $request->domaine !== 'all') {
            $query->where('domaine', $request->domaine);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('domaine', 'like', "%{$search}%");
            });
        }

        $sujets = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $sujets
        ], 200);
    }

    public function listerAffectations(Request $request)
    {
        $query = Affectation::with(['etudiant.user', 'sujet', 'encadreur.user']);

        // Filtres optionnels
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('encadreur_id')) {
            $query->where('encadreur_id', $request->encadreur_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('etudiant.user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%");
            });
        }

        $affectations = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $affectations
        ], 200);
    }
    /**
     * Effectuer les affectations étudiants-sujets
     */
    public function effectuerAffectations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affectations' => 'required|array',
            'affectations.*.etudiant_id' => 'required|exists:etudiants,id',
            'affectations.*.sujet_id' => 'required|exists:sujets,id',
            'affectations.*.encadreur_id' => 'required|exists:encadreurs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $affectationsCreees = [];
           foreach ($request->affectations as $affectationData) {
                // Vérifier si l'étudiant n'est pas déjà affecté
                $etudiant = Etudiant::find($affectationData['etudiant_id']);
                if ($etudiant->affectation) {
                    continue; // Passer au suivant
                }

                // Vérifier si le sujet a encore des places
                $sujet = Sujet::find($affectationData['sujet_id']);
                if ($sujet->nombre_places_occupees >= $sujet->nombre_places_disponibles) {
                    continue; // Passer au suivant
                }

                // Créer l'affectation
                $affectation = \App\Models\Affectation::create([
                    'etudiant_id' => $affectationData['etudiant_id'],
                    'sujet_id' => $affectationData['sujet_id'],
                    'encadreur_id' => $affectationData['encadreur_id'],
                    'ordre_preference_etudiant' => 1, // À améliorer selon les préférences réelles
                    'statut' => 'affecte',
                    'date_affectation' => now(),
                ]);

                // Mettre à jour les compteurs
                $sujet->increment('nombre_places_occupees');
                $encadreur = Encadreur::find($affectationData['encadreur_id']);
                $encadreur->increment('nombre_etudiants_actuels');

                // Mettre à jour le statut de l'étudiant
                $etudiant->update(['statut_memoire' => 'affecte']);

                $affectationsCreees[] = $affectation->load(['etudiant.user', 'sujet', 'encadreur.user']);
               Mail::to($affectation->etudiant->user->email)->send(new AffectationNotificationMail($affectation, 'etudiant'));
               Mail::to($affectation->encadreur->user->email)->send(new AffectationNotificationMail($affectation, 'encadreur'));
            }

            return response()->json([
                'success' => true,
                'message' => count($affectationsCreees) . ' affectation(s) créée(s) avec succès',
                'data' => $affectationsCreees
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors des affectations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer les données spécifiques selon le rôle
     */
    private function creerDonneesSpecifiques($user, $request)
    {
        switch ($user->role) {
            case 'encadreur':
                Encadreur::create([
                    'user_id' => $user->id,
                    'specialite' => $request->specialite,
                    'grade_academique' => $request->grade_academique,
                    'nombre_max_etudiants' => $request->nombre_max_etudiants,
                    'bio' => $request->bio,
                ]);
                break;

            case 'etudiant':
                Etudiant::create([
                    'user_id' => $user->id,
                    'numero_etudiant' => $request->numero_etudiant,
                    'niveau' => $request->niveau,
                    'filiere' => $request->filiere,
                    'annee_academique' => $request->annee_academique,
                ]);
                break;

            case 'membre_jury':
                MembreDuJury::create([
                    'user_id' => $user->id,
                    'grade_academique' => $request->grade_academique,
                    'specialite' => $request->specialite,
                    'etablissement' => $request->etablissement,
                    'est_externe' => $request->est_externe ?? false,
                ]);
                break;
        }
    }

    /**
     * Obtenir les relations selon le rôle
     */
    private function getRelationsByRole($role)
    {
        switch ($role) {
            case 'encadreur':
                return 'encadreur';
            case 'etudiant':
                return 'etudiant';
            case 'membre_jury':
                return 'membreDuJury';
            default:
                return '';
        }
    }

    /**
     * Liste des soutenances avec filtres
     */
    public function listeSoutenances(Request $request)
    {
        $query = Soutenance::with(['etudiant.user', 'jury.president.user', 'jury.rapporteur.user', 'jury.examinateur.user']);

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('date_debut') && $request->has('date_fin')) {
            $query->whereBetween('date_heure_soutenance', [$request->date_debut, $request->date_fin]);
        }

        if ($request->has('mention') && $request->mention !== 'all') {
            $query->where('mention', $request->mention);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('etudiant.user', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%");
            });
        }

        $soutenances = $query->orderBy('date_heure_soutenance', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $soutenances
        ], 200);
    }

    /**
     * Programmer une soutenance
     */
    public function programmerSoutenance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'etudiant_id' => 'required|exists:etudiants,id',
            'jury_id' => 'required|exists:juries,id',
            'dossier_soutenance_id' => 'required|exists:dossier_soutenances,id',
            'date_heure_soutenance' => 'required|date|after:now',
            'salle' => 'required|string|max:100',
            'duree_minutes' => 'required|integer|min:30|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifier que l'étudiant n'a pas déjà une soutenance programmée
            $soutenanceExistante = Soutenance::where('etudiant_id', $request->etudiant_id)
                ->whereIn('statut', ['programmee', 'en_cours'])
                ->first();

            if ($soutenanceExistante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet étudiant a déjà une soutenance programmée'
                ], 400);
            }

            $soutenance = Soutenance::create([
                'etudiant_id' => $request->etudiant_id,
                'jury_id' => $request->jury_id,
                'dossier_soutenance_id' => $request->dossier_soutenance_id,
                'date_heure_soutenance' => $request->date_heure_soutenance,
                'salle' => $request->salle,
                'duree_minutes' => $request->duree_minutes,
                'statut' => 'programmee',
            ]);

            // Mettre à jour le statut de l'étudiant
            $etudiant = Etudiant::find($request->etudiant_id);
            $etudiant->update(['statut_memoire' => 'soutenance_programmee']);

            return response()->json([
                'success' => true,
                'message' => 'Soutenance programmée avec succès',
                'data' => $soutenance->load(['etudiant.user', 'jury'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la programmation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valider les notes d'une soutenance
     */
    public function validerNotesSoutenance(Request $request, $soutenanceId)
    {
        $validator = Validator::make($request->all(), [
            'note_president' => 'required|numeric|min:0|max:20',
            'note_rapporteur' => 'required|numeric|min:0|max:20',
            'note_examinateur' => 'required|numeric|min:0|max:20',
            'note_encadreur' => 'required|numeric|min:0|max:20',
            'mention' => 'required|in:Très Bien,Bien,Assez Bien,Passable,Ajourné',
            'appreciation_generale' => 'nullable|string',
            'recommandations' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $soutenance = Soutenance::find($soutenanceId);

        if (!$soutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Soutenance non trouvée'
            ], 404);
        }

        // Calculer la note finale (moyenne pondérée)
        $noteFinal = (
            $request->note_president * 0.3 +
            $request->note_rapporteur * 0.3 +
            $request->note_examinateur * 0.2 +
            $request->note_encadreur * 0.2
        );

        $soutenance->update([
            'note_president' => $request->note_president,
            'note_rapporteur' => $request->note_rapporteur,
            'note_examinateur' => $request->note_examinateur,
            'note_encadreur' => $request->note_encadreur,
            'note_finale' => round($noteFinal, 2),
            'mention' => $request->mention,
            'appreciation_generale' => $request->appreciation_generale,
            'recommandations' => $request->recommandations,
            'statut' => 'terminee',
            'date_deliberation' => now(),
        ]);

        // Mettre à jour le statut de l'étudiant
        $etudiant = $soutenance->etudiant;
        $etudiant->update(['statut_memoire' => 'soutenu']);

        return response()->json([
            'success' => true,
            'message' => 'Notes validées avec succès',
            'data' => $soutenance->fresh()
        ], 200);
    }

    /**
     * Liste des jurys
     */
    public function listeJurys(Request $request)
    {
        $query = Jury::with(['president.user', 'rapporteur.user', 'examinateur.user', 'encadreur.user']);

        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nom_jury', 'like', "%{$search}%");
        }

        $jurys = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $jurys
        ], 200);
    }

    /**
     * Créer un jury
     */
    public function creerJury(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_jury' => 'required|string|max:255',
            'president_id' => 'required|exists:membres_jury,id',
            'rapporteur_id' => 'required|exists:membres_jury,id',
            'examinateur_id' => 'required|exists:membres_jury,id',
            'encadreur_id' => 'required|exists:encadreurs,id',
            'commentaire' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que les membres sont différents
        $membres = [$request->president_id, $request->rapporteur_id, $request->examinateur_id];
        if (count($membres) !== count(array_unique($membres))) {
            return response()->json([
                'success' => false,
                'message' => 'Les membres du jury doivent être différents'
            ], 400);
        }

        try {
            $jury = Jury::create([
                'nom_jury' => $request->nom_jury,
                'president_id' => $request->president_id,
                'rapporteur_id' => $request->rapporteur_id,
                'examinateur_id' => $request->examinateur_id,
                'encadreur_id' => $request->encadreur_id,
                'date_creation' => now(),
                'statut' => 'actif',
                'commentaire' => $request->commentaire,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jury créé avec succès',
                'data' => $jury->load(['president.user', 'rapporteur.user', 'examinateur.user', 'encadreur.user'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des archives avec filtres
     */
    public function listeArchives(Request $request)
    {
        $query = MemoireArchive::query();

        // Filtres
        if ($request->has('annee') && $request->annee !== 'all') {
            $query->where('annee_soutenance', $request->annee);
        }

        if ($request->has('niveau') && $request->niveau !== 'all') {
            $query->where('niveau', $request->niveau);
        }

        if ($request->has('mention') && $request->mention !== 'all') {
            $query->where('mention', $request->mention);
        }

        if ($request->has('filiere') && $request->filiere !== 'all') {
            $query->where('filiere', $request->filiere);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre_memoire', 'like', "%{$search}%")
                  ->orWhere('nom_etudiant', 'like', "%{$search}%")
                  ->orWhere('prenom_etudiant', 'like', "%{$search}%")
                  ->orWhere('nom_encadreur', 'like', "%{$search}%")
                  ->orWhere('mots_cles', 'like', "%{$search}%");
            });
        }

        $archives = $query->latest('date_archivage')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $archives
        ], 200);
    }

    /**
     * Archiver un mémoire
     */
    public function archiverMemoire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'soutenance_id' => 'required|exists:soutenances,id',
            'titre_memoire' => 'required|string|max:500',
            'fichier_memoire' => 'required|string|max:255',
            'resume_francais' => 'required|string',
            'resume_anglais' => 'required|string',
            'mots_cles' => 'required|string|max:500',
            'visible_public' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $soutenance = Soutenance::with(['etudiant.user'])->find($request->soutenance_id);

        if (!$soutenance || $soutenance->statut !== 'terminee') {
            return response()->json([
                'success' => false,
                'message' => 'Soutenance non trouvée ou non terminée'
            ], 400);
        }

        // Vérifier si déjà archivé
        if ($soutenance->memoireArchive) {
            return response()->json([
                'success' => false,
                'message' => 'Ce mémoire est déjà archivé'
            ], 400);
        }

        try {
            $archive = MemoireArchive::create([
                'soutenance_id' => $request->soutenance_id,
                'titre_memoire' => $request->titre_memoire,
                'nom_etudiant' => $soutenance->etudiant->user->nom,
                'prenom_etudiant' => $soutenance->etudiant->user->prenom,
                'nom_encadreur' => $soutenance->jury->encadreur->user->nom_complet ?? 'Non défini',
                'annee_soutenance' => $soutenance->date_heure_soutenance->year,
                'niveau' => $soutenance->etudiant->niveau,
                'filiere' => $soutenance->etudiant->filiere,
                'mention' => $soutenance->mention,
                'note_finale' => $soutenance->note_finale,
                'fichier_memoire' => $request->fichier_memoire,
                'resume_francais' => $request->resume_francais,
                'resume_anglais' => $request->resume_anglais,
                'mots_cles' => $request->mots_cles,
                'nombre_telechargements' => 0,
                'visible_public' => $request->visible_public,
                'date_archivage' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mémoire archivé avec succès',
                'data' => $archive
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'archivage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques détaillées
     */
    public function statistiquesDetaillees(Request $request)
    {
        $annee = $request->get('annee', now()->year);

        $stats = [
            // Statistiques par niveau
            'par_niveau' => [
                'L3' => [
                    'etudiants' => Etudiant::where('niveau', 'L3')->count(),
                    'affectes' => Etudiant::where('niveau', 'L3')->where('statut_memoire', 'affecte')->count(),
                    'soutenus' => Etudiant::where('niveau', 'L3')->where('statut_memoire', 'soutenu')->count(),
                ],
                'M1' => [
                    'etudiants' => Etudiant::where('niveau', 'M1')->count(),
                    'affectes' => Etudiant::where('niveau', 'M1')->where('statut_memoire', 'affecte')->count(),
                    'soutenus' => Etudiant::where('niveau', 'M1')->where('statut_memoire', 'soutenu')->count(),
                ],
                'M2' => [
                    'etudiants' => Etudiant::where('niveau', 'M2')->count(),
                    'affectes' => Etudiant::where('niveau', 'M2')->where('statut_memoire', 'affecte')->count(),
                    'soutenus' => Etudiant::where('niveau', 'M2')->where('statut_memoire', 'soutenu')->count(),
                ],
            ],

            // Statistiques par mention
            'par_mention' => [
                'Très Bien' => Soutenance::where('mention', 'Très Bien')->whereYear('date_heure_soutenance', $annee)->count(),
                'Bien' => Soutenance::where('mention', 'Bien')->whereYear('date_heure_soutenance', $annee)->count(),
                'Assez Bien' => Soutenance::where('mention', 'Assez Bien')->whereYear('date_heure_soutenance', $annee)->count(),
                'Passable' => Soutenance::where('mention', 'Passable')->whereYear('date_heure_soutenance', $annee)->count(),
                'Ajourné' => Soutenance::where('mention', 'Ajourné')->whereYear('date_heure_soutenance', $annee)->count(),
            ],

            // Top encadreurs
            'top_encadreurs' => Encadreur::withCount('affectations')
                ->with('user')
                ->orderBy('affectations_count', 'desc')
                ->take(10)
                ->get(),

            // Évolution mensuelle
            'evolution_mensuelle' => $this->getEvolutionMensuelle($annee),

            // Statistiques des téléchargements
            'telechargements' => [
                'total' => MemoireArchive::sum('nombre_telechargements'),
                'cette_annee' => MemoireArchive::where('annee_soutenance', $annee)->sum('nombre_telechargements'),
                'top_memoires' => MemoireArchive::orderBy('nombre_telechargements', 'desc')->take(5)->get(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }

    /**
     * Obtenir l'évolution mensuelle pour une année
     */
    private function getEvolutionMensuelle($annee)
    {
        $evolution = [];
        for ($mois = 1; $mois <= 12; $mois++) {
            $evolution[] = [
                'mois' => Carbon::create($annee, $mois, 1)->format('M'),
                'affectations' => Affectation::whereMonth('created_at', $mois)
                                           ->whereYear('created_at', $annee)
                                           ->count(),
                'soutenances' => Soutenance::whereMonth('date_heure_soutenance', $mois)
                                          ->whereYear('date_heure_soutenance', $annee)
                                          ->count(),
                'archives' => MemoireArchive::whereMonth('date_archivage', $mois)
                                           ->whereYear('date_archivage', $annee)
                                           ->count(),
            ];
        }
        return $evolution;
    }
}
