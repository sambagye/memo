<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Sujet;
use App\Models\Affectation;
use App\Models\SeanceEncadrement;
use App\Models\DossierSoutenance;
use App\Models\Soutenance;

class EtudiantController extends Controller
{
    /**
     * Dashboard étudiant
     */
    public function dashboard(Request $request)
    {
        $etudiant = $request->user()->etudiant;

        if (!$etudiant) {
            return response()->json([
                'success' => false,
                'message' => 'Profil étudiant non trouvé'
            ], 404);
        }

        $data = [
            'statut_memoire' => $etudiant->statut_memoire,
            'affectation' => null,
            'sujet' => null,
            'encadreur' => null,
            'prochaines_seances' => collect(),
            'dossier_soutenance' => null,
            'soutenance' => null,
        ];

        // Charger les données selon le statut
        if ($etudiant->affectation) {
            $data['affectation'] = $etudiant->affectation->load(['sujet', 'encadreur.user']);
            $data['sujet'] = $etudiant->affectation->sujet;
            $data['encadreur'] = $etudiant->affectation->encadreur;

            // Prochaines séances
            $data['prochaines_seances'] = SeanceEncadrement::where('affectation_id', $etudiant->affectation->id)
                ->where('statut', 'programmee')
                ->where('date_heure', '>', now())
                ->orderBy('date_heure')
                ->take(3)
                ->get();

            // Dossier de soutenance
            if ($etudiant->dossierSoutenance) {
                $data['dossier_soutenance'] = $etudiant->dossierSoutenance;
            }

            // Soutenance
            if ($etudiant->soutenance) {
                $data['soutenance'] = $etudiant->soutenance->load('jury');
            }
        }

        // Statistiques
        $stats = [
            'nombre_seances_suivies' => $etudiant->affectation ?
                SeanceEncadrement::where('affectation_id', $etudiant->affectation->id)
                    ->where('statut', 'terminee')->count() : 0,
            'pourcentage_dossier' => $etudiant->dossierSoutenance ?
                $etudiant->dossierSoutenance->pourcentage_completion : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($data, ['statistiques' => $stats])
        ], 200);
    }

    /**
     * Liste des sujets disponibles pour choix
     */
    public function sujetsDisponibles(Request $request)
    {
        $etudiant = $request->user()->etudiant;

        if ($etudiant->statut_memoire !== 'en_attente_sujet') {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez plus choisir de sujet'
            ], 400);
        }

        $query = Sujet::disponibles()
            ->where('niveau', $etudiant->niveau)
            ->with(['encadreur.user']);

        // Filtres
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

        $sujets = $query->latest()->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $sujets
        ], 200);
    }

    /**
     * Détails d'un sujet
     */
    public function detailsSujet($sujetId)
    {
        $sujet = Sujet::with(['encadreur.user'])->find($sujetId);

        if (!$sujet) {
            return response()->json([
                'success' => false,
                'message' => 'Sujet non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sujet
        ], 200);
    }

    /**
     * Choisir des sujets (préférences)
     */
    public function choisirSujets(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sujets' => 'required|array|min:1|max:3',
            'sujets.*' => 'required|exists:sujets,id',
        ], [
            'sujets.required' => 'Vous devez choisir au moins un sujet',
            'sujets.max' => 'Vous pouvez choisir au maximum 3 sujets',
            'sujets.*.exists' => 'Un des sujets sélectionnés n\'existe pas',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $etudiant = $request->user()->etudiant;

        if ($etudiant->statut_memoire !== 'en_attente_sujet') {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà fait votre choix'
            ], 400);
        }

        try {
            // Vérifier que tous les sujets sont disponibles et du bon niveau
            $sujets = Sujet::whereIn('id', $request->sujets)
                ->where('niveau', $etudiant->niveau)
                ->where('statut', 'valide')
                ->get();

            if ($sujets->count() !== count($request->sujets)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un ou plusieurs sujets ne sont pas disponibles'
                ], 400);
            }

            // Supprimer les anciennes préférences s'il y en a
            Affectation::where('etudiant_id', $etudiant->id)->delete();

            // Créer les nouvelles préférences
            foreach ($request->sujets as $ordre => $sujetId) {
                $sujet = $sujets->find($sujetId);

                Affectation::create([
                    'etudiant_id' => $etudiant->id,
                    'sujet_id' => $sujetId,
                    'encadreur_id' => $sujet->encadreur_id,
                    'ordre_preference_etudiant' => $ordre + 1,
                    'statut' => 'en_attente',
                ]);
            }

            // Mettre à jour le statut de l'étudiant
            $etudiant->update(['statut_memoire' => 'sujet_choisi']);

            return response()->json([
                'success' => true,
                'message' => 'Vos préférences ont été enregistrées avec succès. En attente d\'affectation par l\'administration.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mes séances d'encadrement
     */
    public function mesSeances(Request $request)
    {
        $etudiant = $request->user()->etudiant;

        if (!$etudiant->affectation) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas encore d\'affectation'
            ], 400);
        }

        $query = SeanceEncadrement::where('affectation_id', $etudiant->affectation->id);

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('periode')) {
            switch ($request->periode) {
                case 'a_venir':
                    $query->where('date_heure', '>', now());
                    break;
                case 'passees':
                    $query->where('date_heure', '<', now());
                    break;
                case 'cette_semaine':
                    $query->whereBetween('date_heure', [now()->startOfWeek(), now()->endOfWeek()]);
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
     * Créer ou mettre à jour le dossier de soutenance
     */
    public function creerDossierSoutenance(Request $request)
    {
        $etudiant = $request->user()->etudiant;

        if (!$etudiant->affectation) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez être affecté à un sujet pour créer un dossier'
            ], 400);
        }

        // Vérifier si le dossier existe déjà
        $dossier = $etudiant->dossierSoutenance;
        if (!$dossier) {
            $dossier = DossierSoutenance::create([
                'etudiant_id' => $etudiant->id,
                'affectation_id' => $etudiant->affectation->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dossier de soutenance créé avec succès',
            'data' => $dossier
        ], 201);
    }
    /**
     * Télécharger un document du dossier
     */
    public function uploaderDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type_document' => 'required|in:memoire_pdf,resume_francais,resume_anglais,attestation_plagiat,fiche_evaluation_encadreur',
            'document' => 'required|file|max:20480', // 20MB max
        ], [
            'type_document.required' => 'Le type de document est requis',
            'type_document.in' => 'Type de document invalide',
            'document.required' => 'Le fichier est requis',
            'document.max' => 'Le fichier ne doit pas dépasser 20MB',
        ]);

        // Validation spécifique selon le type
        $rules = [];
        switch ($request->type_document) {
            case 'memoire_pdf':
                $rules['document'] = 'mimes:pdf';
                break;
            case 'resume_francais':
            case 'resume_anglais':
                $rules['document'] = 'mimes:pdf,doc,docx';
                break;
            case 'attestation_plagiat':
            case 'fiche_evaluation_encadreur':
                $rules['document'] = 'mimes:pdf,jpg,jpeg,png';
                break;
        }

        $validator->addRules($rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $etudiant = $request->user()->etudiant;
        $dossier = $etudiant->dossierSoutenance;

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord créer votre dossier de soutenance'
            ], 400);
        }

        if ($dossier->date_soumission) {
            return response()->json([
                'success' => false,
                'message' => 'Votre dossier a déjà été soumis. Modifications impossibles.'
            ], 400);
        }

        try {
            // Supprimer l'ancien fichier s'il existe
            if ($dossier->{$request->type_document}) {
                Storage::disk('public')->delete($dossier->{$request->type_document});
            }

            // Stocker le nouveau fichier
            $path = $request->file('document')->store(
                "dossiers-soutenance/{$etudiant->id}/{$request->type_document}",
                'public'
            );

            // Mettre à jour le dossier
            $dossier->update([
                $request->type_document => $path
            ]);

            // Vérifier si le dossier est complet
            $this->verifierCompletudeDossier($dossier);

            return response()->json([
                'success' => true,
                'message' => 'Document téléchargé avec succès',
                'data' => [
                    'dossier' => $dossier->fresh(),
                    'pourcentage_completion' => $dossier->pourcentage_completion,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soumettre le dossier complet
     */
    public function soumetteDossier(Request $request)
    {
        $etudiant = $request->user()->etudiant;
        $dossier = $etudiant->dossierSoutenance;

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun dossier trouvé'
            ], 404);
        }

        if ($dossier->date_soumission) {
            return response()->json([
                'success' => false,
                'message' => 'Votre dossier a déjà été soumis'
            ], 400);
        }

        // Vérifier que tous les documents sont présents
        $documentsManquants = $dossier->documents_manquants;
        if (count($documentsManquants) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Documents manquants: ' . implode(', ', $documentsManquants),
                'documents_manquants' => $documentsManquants
            ], 400);
        }

        try {
            $dossier->update([
                'dossier_complet' => true,
                'date_soumission' => now(),
            ]);

            // Mettre à jour le statut de l'étudiant
            $etudiant->update(['statut_memoire' => 'dossier_soumis']);

            return response()->json([
                'success' => true,
                'message' => 'Dossier soumis avec succès. En attente d\'autorisation de l\'encadreur.',
                'data' => $dossier->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la soumission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mon dossier de soutenance
     */
    public function monDossier(Request $request)
    {
        $etudiant = $request->user()->etudiant;
        $dossier = $etudiant->dossierSoutenance;

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun dossier trouvé. Créez d\'abord votre dossier.',
                'data' => null
            ], 404);
        }

        $dossier->load(['affectation.sujet', 'affectation.encadreur.user']);

        return response()->json([
            'success' => true,
            'data' => [
                'dossier' => $dossier,
                'documents_manquants' => $dossier->documents_manquants,
                'pourcentage_completion' => $dossier->pourcentage_completion,
            ]
        ], 200);
    }

    /**
     * Télécharger un document
     */
    public function telechargerDocument($dossierSoutenanceId, $typeDocument)
    {
        $etudiant = request()->user()->etudiant;

        $dossier = DossierSoutenance::where('etudiant_id', $etudiant->id)
            ->where('id', $dossierSoutenanceId)
            ->first();

        if (!$dossier) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier non trouvé'
            ], 404);
        }

        if (!$dossier->{$typeDocument}) {
            return response()->json([
                'success' => false,
                'message' => 'Document non trouvé'
            ], 404);
        }

        if (!Storage::disk('public')->exists($dossier->{$typeDocument})) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé sur le serveur'
            ], 404);
        }

        return Storage::disk('public')->download($dossier->{$typeDocument});
    }

    /**
     * Ma soutenance
     */
    public function maSoutenance(Request $request)
    {
        $etudiant = $request->user()->etudiant;
        $soutenance = $etudiant->soutenance;

        if (!$soutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune soutenance programmée',
                'data' => null
            ], 404);
        }

        $soutenance->load([
            'jury.president.user',
            'jury.rapporteur.user',
            'jury.examinateur.user',
            'jury.encadreur.user',
            'dossierSoutenance'
        ]);

        return response()->json([
            'success' => true,
            'data' => $soutenance
        ], 200);
    }

    /**
     * Calendrier personnel
     */
    public function calendrier(Request $request)
    {
        $etudiant = $request->user()->etudiant;

        if (!$etudiant->affectation) {
            return response()->json([
                'success' => true,
                'data' => []
            ], 200);
        }

        $dateDebut = $request->get('date_debut', now()->startOfMonth());
        $dateFin = $request->get('date_fin', now()->endOfMonth());

        $evenements = collect();

        // Séances d'encadrement
        $seances = SeanceEncadrement::where('affectation_id', $etudiant->affectation->id)
            ->whereBetween('date_heure', [$dateDebut, $dateFin])
            ->get();

        foreach ($seances as $seance) {
            $evenements->push([
                'id' => "seance_{$seance->id}",
                'title' => "Séance: {$seance->titre}",
                'start' => $seance->date_heure->format('Y-m-d H:i:s'),
                'end' => $seance->date_fin->format('Y-m-d H:i:s'),
                'type' => 'seance',
                'statut' => $seance->statut,
                'lieu' => $seance->lieu,
                'description' => $seance->description,
            ]);
        }

        // Soutenance
        if ($etudiant->soutenance &&
            $etudiant->soutenance->date_heure_soutenance >= $dateDebut &&
            $etudiant->soutenance->date_heure_soutenance <= $dateFin) {

            $soutenance = $etudiant->soutenance;
            $evenements->push([
                'id' => "soutenance_{$soutenance->id}",
                'title' => "Soutenance de mémoire",
                'start' => $soutenance->date_heure_soutenance->format('Y-m-d H:i:s'),
                'end' => $soutenance->date_fin_soutenance->format('Y-m-d H:i:s'),
                'type' => 'soutenance',
                'statut' => $soutenance->statut,
                'salle' => $soutenance->salle,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $evenements->sortBy('start')->values()
        ], 200);
    }

    /**
     * Vérifier la complétude du dossier
     */
    private function verifierCompletudeDossier($dossier)
    {
        $documentsRequis = ['memoire_pdf', 'resume_francais', 'resume_anglais', 'attestation_plagiat', 'fiche_evaluation_encadreur'];
        $complet = true;

        foreach ($documentsRequis as $doc) {
            if (empty($dossier->{$doc})) {
                $complet = false;
                break;
            }
        }

        if ($complet && !$dossier->dossier_complet) {
            $dossier->update(['dossier_complet' => true]);
        } elseif (!$complet && $dossier->dossier_complet) {
            $dossier->update(['dossier_complet' => false]);
        }
    }
}
