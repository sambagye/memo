<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Sujet;
use App\Models\Encadreur;

class SujetController extends Controller
{
    /**
     * Liste de tous les sujets avec filtres
     */
    public function index(Request $request)
    {
        $query = Sujet::with(['encadreur.user', 'affectations.etudiant.user']);

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

        if ($request->has('encadreur_id') && $request->encadreur_id !== 'all') {
            $query->where('encadreur_id', $request->encadreur_id);
        }

        if ($request->has('disponible') && $request->disponible === 'true') {
            $query->disponibles();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('domaine', 'like', "%{$search}%")
                    ->orWhere('objectifs', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortBy, ['titre', 'domaine', 'niveau', 'created_at', 'date_validation'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }

        $sujets = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $sujets
        ], 200);
    }

    /**
     * Détails d'un sujet spécifique
     */
    public function show($id)
    {
        $sujet = Sujet::with([
            'encadreur.user',
            'affectations.etudiant.user' => function($query) {
                $query->where('statut', 'affecte');
            }
        ])->find($id);

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
     * Créer un nouveau sujet (pour les encadreurs)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'description' => 'required|string|min:100',
            'objectifs' => 'required|string|min:50',
            'prerequis' => 'nullable|string',
            'domaine' => 'required|string|max:255',
            'niveau' => 'required|in:L3,M1,M2',
            'nombre_places_disponibles' => 'required|integer|min:1|max:10',
        ], [
            'titre.required' => 'Le titre est requis',
            'titre.max' => 'Le titre ne doit pas dépasser 255 caractères',
            'description.required' => 'La description est requise',
            'description.min' => 'La description doit contenir au moins 100 caractères',
            'objectifs.required' => 'Les objectifs sont requis',
            'objectifs.min' => 'Les objectifs doivent contenir au moins 50 caractères',
            'domaine.required' => 'Le domaine est requis',
            'niveau.required' => 'Le niveau est requis',
            'niveau.in' => 'Le niveau doit être L3, M1 ou M2',
            'nombre_places_disponibles.required' => 'Le nombre de places disponibles est requis',
            'nombre_places_disponibles.min' => 'Il doit y avoir au moins 1 place disponible',
            'nombre_places_disponibles.max' => 'Maximum 10 places par sujet',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que l'utilisateur est un encadreur
        $user = $request->user();
        if ($user->role !== 'encadreur' || !$user->encadreur) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les encadreurs peuvent proposer des sujets'
            ], 403);
        }

        $encadreur = $user->encadreur;

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
                'data' => $sujet->load('encadreur.user')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un sujet
     */
    public function update(Request $request, $id)
    {
        $sujet = Sujet::find($id);

        if (!$sujet) {
            return response()->json([
                'success' => false,
                'message' => 'Sujet non trouvé'
            ], 404);
        }

        // Vérifier les permissions
        $user = $request->user();

        // Admin peut tout modifier
        if ($user->role === 'admin') {
            // OK
        }
        // Encadreur ne peut modifier que ses propres sujets et seulement s'ils ne sont pas validés
        elseif ($user->role === 'encadreur' && $user->encadreur && $user->encadreur->id === $sujet->encadreur_id) {
            if ($sujet->statut !== 'propose') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier un sujet déjà validé ou refusé'
                ], 403);
            }
        }
        else {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à modifier ce sujet'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'description' => 'required|string|min:100',
            'objectifs' => 'required|string|min:50',
            'prerequis' => 'nullable|string',
            'domaine' => 'required|string|max:255',
            'niveau' => 'required|in:L3,M1,M2',
            'nombre_places_disponibles' => 'required|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $sujet->update([
                'titre' => $request->titre,
                'description' => $request->description,
                'objectifs' => $request->objectifs,
                'prerequis' => $request->prerequis,
                'domaine' => $request->domaine,
                'niveau' => $request->niveau,
                'nombre_places_disponibles' => $request->nombre_places_disponibles,
            ]);

            // Si c'est un admin qui modifie, on peut aussi mettre à jour le statut
            if ($user->role === 'admin' && $request->has('statut')) {
                $sujet->update([
                    'statut' => $request->statut,
                    'commentaire_admin' => $request->commentaire_admin,
                    'date_validation' => in_array($request->statut, ['valide', 'refuse']) ? now() : null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sujet mis à jour avec succès',
                'data' => $sujet->load('encadreur.user')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un sujet
     */
    public function destroy(Request $request, $id)
    {
        $sujet = Sujet::find($id);

        if (!$sujet) {
            return response()->json([
                'success' => false,
                'message' => 'Sujet non trouvé'
            ], 404);
        }

        $user = $request->user();

        // Seul l'admin ou l'encadreur propriétaire peut supprimer
        if ($user->role !== 'admin' &&
            !($user->role === 'encadreur' && $user->encadreur && $user->encadreur->id === $sujet->encadreur_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé à supprimer ce sujet'
            ], 403);
        }

        // Vérifier qu'il n'y a pas d'étudiants affectés
        if ($sujet->affectations()->where('statut', 'affecte')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un sujet avec des étudiants affectés'
            ], 400);
        }

        try {
            $sujet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sujet supprimé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques sur les sujets
     */
    public function statistiques(Request $request)
    {
        $stats = [
            'total_sujets' => Sujet::count(),
            'sujets_proposes' => Sujet::where('statut', 'propose')->count(),
            'sujets_valides' => Sujet::where('statut', 'valide')->count(),
            'sujets_refuses' => Sujet::where('statut', 'refuse')->count(),
            'sujets_complets' => Sujet::where('statut', 'complet')->count(),
        ];

        // Répartition par niveau
        $repartitionNiveau = Sujet::selectRaw('niveau, COUNT(*) as total')
            ->groupBy('niveau')
            ->get();

        // Répartition par domaine
        $repartitionDomaine = Sujet::selectRaw('domaine, COUNT(*) as total')
            ->groupBy('domaine')
            ->orderBy('total', 'desc')
            ->take(10)
            ->get();

        // Encadreurs les plus actifs
        $encadreursActifs = Encadreur::withCount(['sujets' => function($query) {
            $query->where('statut', 'valide');
        }])
            ->with('user:id,nom,prenom')
            ->orderBy('sujets_count', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => $stats,
                'repartition_niveau' => $repartitionNiveau,
                'repartition_domaine' => $repartitionDomaine,
                'encadreurs_actifs' => $encadreursActifs,
            ]
        ], 200);
    }

    /**
     * Liste des domaines disponibles
     */
    public function domaines()
    {
        $domaines = Sujet::distinct()
            ->pluck('domaine')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $domaines
        ], 200);
    }

    /**
     * Sujets populaires (les plus demandés)
     */
    public function sujetsPopulaires(Request $request)
    {
        $limit = $request->get('limit', 10);

        $sujets = Sujet::withCount(['affectations' => function($query) {
            $query->where('statut', '!=', 'refuse');
        }])
            ->where('statut', 'valide')
            ->with('encadreur.user')
            ->orderBy('affectations_count', 'desc')
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sujets
        ], 200);
    }
}
