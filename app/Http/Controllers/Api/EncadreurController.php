<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Sujet;
use App\Models\Affectation;
use App\Models\SeanceEncadrement;
use App\Models\DossierSoutenance;
use App\Mail\SeanceNotificationMail;
use Illuminate\Support\Facades\Mail;

class EncadreurController extends Controller
{
    /**
     * Dashboard encadreur
     */
    public function dashboard(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        if (!$encadreur) {
            return response()->json([
                'success' => false,
                'message' => 'Profil encadreur non trouvé'
            ], 404);
        }

        $stats = [
            'nombre_etudiants_encadres' => $encadreur->nombre_etudiants_actuels,
            'nombre_max_etudiants' => $encadreur->nombre_max_etudiants,
            'places_disponibles' => $encadreur->nombre_etudiants_restants,
            'nombre_sujets_proposes' => $encadreur->sujets()->count(),
            'sujets_valides' => $encadreur->sujets()->where('statut', 'valide')->count(),
            'sujets_en_attente' => $encadreur->sujets()->where('statut', 'propose')->count(),
        ];

        // Étudiants encadrés
        $etudiantsEncadres = Affectation::where('encadreur_id', $encadreur->id)
            ->where('statut', 'affecte')
            ->with(['etudiant.user', 'sujet'])
            ->get();

        // Séances à venir
        $seancesAVenir = SeanceEncadrement::whereHas('affectation', function($query) use ($encadreur) {
            $query->where('encadreur_id', $encadreur->id);
        })
            ->where('statut', 'programmee')
            ->where('date_heure', '>', now())
            ->with(['affectation.etudiant.user', 'affectation.sujet'])
            ->orderBy('date_heure')
            ->take(5)
            ->get();

        // Dossiers en attente d'autorisation
        $dossiersEnAttente = DossierSoutenance::whereHas('affectation', function($query) use ($encadreur) {
            $query->where('encadreur_id', $encadreur->id);
        })
            ->where('dossier_complet', true)
            ->where('autorisation_encadreur', false)
            ->with(['etudiant.user', 'affectation.sujet'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => $stats,
                'etudiants_encadres' => $etudiantsEncadres,
                'seances_a_venir' => $seancesAVenir,
                'dossiers_en_attente' => $dossiersEnAttente,
            ]
        ], 200);
    }

    /**
     * Proposer un nouveau sujet
     */
    public function proposerSujet(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'objectifs' => 'required|string',
            'prerequis' => 'nullable|string',
            'domaine' => 'required|string|max:255',
            'niveau' => 'required|in:L3,M1,M2',
            'nombre_places_disponibles' => 'required|integer|min:1|max:10',
        ], [
            'titre.required' => 'Le titre est requis',
            'description.required' => 'La description est requise',
            'objectifs.required' => 'Les objectifs sont requis',
            'domaine.required' => 'Le domaine est requis',
            'niveau.required' => 'Le niveau est requis',
            'nombre_places_disponibles.required' => 'Le nombre de places est requis',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sujet = Sujet::create([
                'titre' => $request->titre,
                'description' => $request->description,
                'objectifs' => $request->objectifs,
                'prerequis' => $request->prerequis,
                'domaine' => $request->domaine,
                'niveau' => $request->niveau,
                'nombre_places_disponibles' => $request->nombre_places_disponibles,
                'encadreur_id' => $encadreur->id,
                'statut' => 'propose',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sujet proposé avec succès. En attente de validation par l\'administration.',
                'data' => $sujet
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des sujets de l'encadreur
     */
    public function mesSujets(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        $query = $encadreur->sujets();

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('niveau') && $request->niveau !== 'all') {
            $query->where('niveau', $request->niveau);
        }

        $sujets = $query->with(['affectations.etudiant.user'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $sujets
        ], 200);
    }

    /**
     * Mes étudiants encadrés
     */
    public function mesEtudiants(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        $affectations = Affectation::where('encadreur_id', $encadreur->id)
            ->where('statut', 'affecte')
            ->with([
                'etudiant.user',
                'sujet',
                'seances' => function($query) {
                    $query->latest('date_heure')->take(3);
                },
                'dossierSoutenance'
            ])
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $affectations
        ], 200);
    }

    /**
     * Programmer une séance d'encadrement
     */
    public function programmerSeance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affectation_id' => 'required|exists:affectations,id',
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date_heure' => 'required|date|after:now',
            'duree_minutes' => 'required|integer|min:15|max:300',
            'lieu' => 'nullable|string|max:255',
            'lien_meeting' => 'nullable|url',
        ], [
            'affectation_id.required' => 'L\'affectation est requise',
            'titre.required' => 'Le titre est requis',
            'date_heure.required' => 'La date et heure sont requises',
            'date_heure.after' => 'La date doit être dans le futur',
            'duree_minutes.required' => 'La durée est requise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $encadreur = $request->user()->encadreur;

        // Vérifier que l'affectation appartient à cet encadreur
        $affectation = Affectation::where('id', $request->affectation_id)
            ->where('encadreur_id', $encadreur->id)
            ->first();

        if (!$affectation) {
            return response()->json([
                'success' => false,
                'message' => 'Affectation non trouvée ou non autorisée'
            ], 404);
        }

        try {
            $seance = SeanceEncadrement::create([
                'affectation_id' => $request->affectation_id,
                'titre' => $request->titre,
                'description' => $request->description,
                'date_heure' => $request->date_heure,
                'duree_minutes' => $request->duree_minutes,
                'lieu' => $request->lieu,
                'lien_meeting' => $request->lien_meeting,
                'statut' => 'programmee',
            ]);
            // Notifier l'étudiant
            Mail::to($affectation->etudiant->user->email)->send(new SeanceNotificationMail($seance, 'programmee'));
            return response()->json([
                'success' => true,
                'message' => 'Séance programmée avec succès',
                'data' => $seance->load('affectation.etudiant.user')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la programmation: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Mes séances d'encadrement
     */
    public function mesSeances(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        $query = SeanceEncadrement::whereHas('affectation', function($q) use ($encadreur) {
            $q->where('encadreur_id', $encadreur->id);
        })->with(['affectation.etudiant.user', 'affectation.sujet']);

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('periode')) {
            switch ($request->periode) {
                case 'aujourd_hui':
                    $query->whereDate('date_heure', today());
                    break;
                case 'cette_semaine':
                    $query->whereBetween('date_heure', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'ce_mois':
                    $query->whereMonth('date_heure', now()->month)
                        ->whereYear('date_heure', now()->year);
                    break;
                case 'passees':
                    $query->where('date_heure', '<', now());
                    break;
                case 'a_venir':
                    $query->where('date_heure', '>', now());
                    break;
            }
        }

        $seances = $query->orderBy('date_heure', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $seances
        ], 200);
    }

    /**
     * Terminer une séance avec compte-rendu
     */
    public function terminerSeance(Request $request, $seanceId)
    {
        $validator = Validator::make($request->all(), [
            'compte_rendu' => 'required|string',
            'travail_a_faire' => 'nullable|string',
        ], [
            'compte_rendu.required' => 'Le compte-rendu est requis',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $encadreur = $request->user()->encadreur;

        $seance = SeanceEncadrement::whereHas('affectation', function($q) use ($encadreur) {
            $q->where('encadreur_id', $encadreur->id);
        })->find($seanceId);

        if (!$seance) {
            return response()->json([
                'success' => false,
                'message' => 'Séance non trouvée'
            ], 404);
        }

        if ($seance->statut === 'terminee') {
            return response()->json([
                'success' => false,
                'message' => 'Cette séance est déjà terminée'
            ], 400);
        }

        $seance->update([
            'statut' => 'terminee',
            'compte_rendu' => $request->compte_rendu,
            'travail_a_faire' => $request->travail_a_faire,
            'date_realisation' => now(),
        ]);
        Mail::to($seance->affectation->etudiant->user->email)->send(new SeanceNotificationMail($seance, 'terminee'));

        return response()->json([
            'success' => true,
            'message' => 'Séance terminée avec succès',
            'data' => $seance->load('affectation.etudiant.user')
        ], 200);
    }

    /**
     * Autoriser la soutenance d'un étudiant
     */
    public function autoriserSoutenance(Request $request, $dossierSoutenanceId)
    {
        $validator = Validator::make($request->all(), [
            'autoriser' => 'required|boolean',
            'commentaire' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $encadreur = $request->user()->encadreur;

        $dossier = DossierSoutenance::whereHas('affectation', function($q) use ($encadreur) {
            $q->where('encadreur_id', $encadreur->id);
        })->find($dossierSoutenanceId);

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier non trouvé'
            ], 404);
        }

        if (!$dossier->dossier_complet) {
            return response()->json([
                'success' => false,
                'message' => 'Le dossier n\'est pas encore complet'
            ], 400);
        }

        $dossier->update([
            'autorisation_encadreur' => $request->autoriser,
            'date_autorisation' => $request->autoriser ? now() : null,
        ]);

        // Mettre à jour le statut de l'étudiant
        if ($request->autoriser) {
            $dossier->etudiant->update([
                'statut_memoire' => 'autorise_soutenance'
            ]);
        }

        $message = $request->autoriser ?
            'Autorisation de soutenance accordée avec succès' :
            'Autorisation de soutenance refusée';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $dossier->load(['etudiant.user', 'affectation.sujet'])
        ], 200);
    }

    /**
     * Calendrier des séances
     */
    public function calendrierSeances(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        $dateDebut = $request->get('date_debut', now()->startOfMonth());
        $dateFin = $request->get('date_fin', now()->endOfMonth());

        $seances = SeanceEncadrement::whereHas('affectation', function($q) use ($encadreur) {
            $q->where('encadreur_id', $encadreur->id);
        })
            ->whereBetween('date_heure', [$dateDebut, $dateFin])
            ->with(['affectation.etudiant.user', 'affectation.sujet'])
            ->orderBy('date_heure')
            ->get();

        // Formater pour le calendrier
        $evenements = $seances->map(function($seance) {
            return [
                'id' => $seance->id,
                'title' => $seance->titre,
                'start' => $seance->date_heure->format('Y-m-d H:i:s'),
                'end' => $seance->date_fin->format('Y-m-d H:i:s'),
                'description' => $seance->description,
                'etudiant' => $seance->affectation->etudiant->user->nom_complet,
                'sujet' => $seance->affectation->sujet->titre,
                'statut' => $seance->statut,
                'lieu' => $seance->lieu,
                'lien_meeting' => $seance->lien_meeting,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $evenements
        ], 200);
    }

    /**
     * Statistiques détaillées
     */
    public function statistiques(Request $request)
    {
        $encadreur = $request->user()->encadreur;

        $stats = [
            'encadrement' => [
                'etudiants_encadres' => $encadreur->nombre_etudiants_actuels,
                'places_disponibles' => $encadreur->nombre_etudiants_restants,
                'taux_occupation' => round(($encadreur->nombre_etudiants_actuels / $encadreur->nombre_max_etudiants) * 100, 1),
            ],
            'sujets' => [
                'total_proposes' => $encadreur->sujets()->count(),
                'valides' => $encadreur->sujets()->where('statut', 'valide')->count(),
                'en_attente' => $encadreur->sujets()->where('statut', 'propose')->count(),
                'refuses' => $encadreur->sujets()->where('statut', 'refuse')->count(),
            ],
            'seances' => [
                'total_programmees' => SeanceEncadrement::whereHas('affectation', function($q) use ($encadreur) {
                    $q->where('encadreur_id', $encadreur->id);
                })->count(),
                'terminees' => SeanceEncadrement::whereHas('affectation', function($q) use ($encadreur) {
                    $q->where('encadreur_id', $encadreur->id);
                })->where('statut', 'terminee')->count(),
                'a_venir' => SeanceEncadrement::whereHas('affectation', function($q) use ($encadreur) {
                    $q->where('encadreur_id', $encadreur->id);
                })->where('statut', 'programmee')->where('date_heure', '>', now())->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }
}
