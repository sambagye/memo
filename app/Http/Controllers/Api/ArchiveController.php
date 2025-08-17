<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\MemoireArchive;

class ArchiveController extends Controller
{
    /**
     * Bibliothèque publique des mémoires
     */
    public function index(Request $request)
    {
        $query = MemoireArchive::where('visible_public', true);

        // Filtres de recherche
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre_memoire', 'like', "%{$search}%")
                    ->orWhere('nom_etudiant', 'like', "%{$search}%")
                    ->orWhere('prenom_etudiant', 'like', "%{$search}%")
                    ->orWhere('nom_encadreur', 'like', "%{$search}%")
                    ->orWhere('mots_cles', 'like', "%{$search}%");
            });
        }

        // Filtre par année
        if ($request->has('annee') && $request->annee !== 'all') {
            $query->where('annee_soutenance', $request->annee);
        }

        // Filtre par niveau
        if ($request->has('niveau') && $request->niveau !== 'all') {
            $query->where('niveau', $request->niveau);
        }

        // Filtre par filière
        if ($request->has('filiere') && $request->filiere !== 'all') {
            $query->where('filiere', $request->filiere);
        }

        // Filtre par mention
        if ($request->has('mention') && $request->mention !== 'all') {
            $query->where('mention', $request->mention);
        }

        // Filtre par encadreur
        if ($request->has('encadreur') && !empty($request->encadreur)) {
            $query->where('nom_encadreur', 'like', "%{$request->encadreur}%");
        }

        // Filtre par note minimale
        if ($request->has('note_min')) {
            $query->where('note_finale', '>=', $request->note_min);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'date_archivage');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSorts = [
            'titre_memoire', 'nom_etudiant', 'nom_encadreur',
            'annee_soutenance', 'note_finale', 'mention',
            'date_archivage', 'nombre_telechargements'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest('date_archivage');
        }

        $memoires = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $memoires
        ], 200);
    }

    /**
     * Détails d'un mémoire archivé
     */
    public function show($id)
    {
        $memoire = MemoireArchive::where('visible_public', true)->find($id);

        if (!$memoire) {
            return response()->json([
                'success' => false,
                'message' => 'Mémoire non trouvé ou non accessible'
            ], 404);
        }

        // Charger les informations de la soutenance si nécessaire
        $memoire->load('soutenance.jury');

        return response()->json([
            'success' => true,
            'data' => $memoire
        ], 200);
    }

    /**
     * Télécharger un mémoire
     */
    public function telecharger($id)
    {
        $memoire = MemoireArchive::where('visible_public', true)->find($id);

        if (!$memoire) {
            return response()->json([
                'success' => false,
                'message' => 'Mémoire non trouvé'
            ], 404);
        }

        if (!$memoire->fichier_memoire || !Storage::disk('public')->exists($memoire->fichier_memoire)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non disponible'
            ], 404);
        }

        // Incrémenter le compteur de téléchargements
        $memoire->incrementerTelechargements();

        // Générer un nom de fichier lisible
        $nomFichier = $this->genererNomFichier($memoire);

        return Storage::disk('public')->download($memoire->fichier_memoire, $nomFichier);
    }

    /**
     * Recherche avancée
     */
    public function rechercheAvancee(Request $request)
    {
        $query = MemoireArchive::where('visible_public', true);

        // Recherche dans le titre
        if ($request->has('titre') && !empty($request->titre)) {
            $query->where('titre_memoire', 'like', "%{$request->titre}%");
        }

        // Recherche par auteur
        if ($request->has('auteur') && !empty($request->auteur)) {
            $auteur = $request->auteur;
            $query->where(function($q) use ($auteur) {
                $q->where('nom_etudiant', 'like', "%{$auteur}%")
                    ->orWhere('prenom_etudiant', 'like', "%{$auteur}%");
            });
        }

        // Recherche par encadreur
        if ($request->has('encadreur') && !empty($request->encadreur)) {
            $query->where('nom_encadreur', 'like', "%{$request->encadreur}%");
        }

        // Recherche par mots-clés
        if ($request->has('mots_cles') && !empty($request->mots_cles)) {
            $motsCles = explode(',', $request->mots_cles);
            $query->where(function($q) use ($motsCles) {
                foreach ($motsCles as $mot) {
                    $mot = trim($mot);
                    $q->orWhere('mots_cles', 'like', "%{$mot}%")
                        ->orWhere('titre_memoire', 'like', "%{$mot}%");
                }
            });
        }

        // Plage d'années
        if ($request->has('annee_debut') && $request->has('annee_fin')) {
            $query->whereBetween('annee_soutenance', [
                $request->annee_debut,
                $request->annee_fin
            ]);
        }

        // Plage de notes
        if ($request->has('note_min') && $request->has('note_max')) {
            $query->whereBetween('note_finale', [
                $request->note_min,
                $request->note_max
            ]);
        }

        // Filtres multiples
        if ($request->has('niveaux') && is_array($request->niveaux)) {
            $query->whereIn('niveau', $request->niveaux);
        }

        if ($request->has('mentions') && is_array($request->mentions)) {
            $query->whereIn('mention', $request->mentions);
        }

        if ($request->has('filieres') && is_array($request->filieres)) {
            $query->whereIn('filiere', $request->filieres);
        }

        // Tri personnalisé
        if ($request->has('tri_pertinence') && $request->tri_pertinence === 'true') {
            // Tri par pertinence (basé sur les téléchargements et notes)
            $query->orderByRaw('(nombre_telechargements * 0.3 + note_finale * 0.7) DESC');
        } else {
            $sortBy = $request->get('sort_by', 'date_archivage');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);
        }

        $memoires = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $memoires
        ], 200);
    }

    /**
     * Statistiques de la bibliothèque
     */
    public function statistiques()
    {
        $stats = [
            'total_memoires' => MemoireArchive::where('visible_public', true)->count(),
            'total_telechargements' => MemoireArchive::where('visible_public', true)->sum('nombre_telechargements'),
            'memoires_par_annee' => MemoireArchive::where('visible_public', true)
                ->selectRaw('annee_soutenance, COUNT(*) as total')
                ->groupBy('annee_soutenance')
                ->orderBy('annee_soutenance', 'desc')
                ->get(),
            'memoires_par_niveau' => MemoireArchive::where('visible_public', true)
                ->selectRaw('niveau, COUNT(*) as total')
                ->groupBy('niveau')
                ->get(),
            'memoires_par_mention' => MemoireArchive::where('visible_public', true)
                ->selectRaw('mention, COUNT(*) as total')
                ->groupBy('mention')
                ->orderByRaw("FIELD(mention, 'excellent', 'tres_bien', 'bien', 'assez_bien', 'passable')")
                ->get(),
            'memoires_par_filiere' => MemoireArchive::where('visible_public', true)
                ->selectRaw('filiere, COUNT(*) as total')
                ->groupBy('filiere')
                ->orderBy('total', 'desc')
                ->get(),
        ];

        // Top 10 des mémoires les plus téléchargés
        $topMemoires = MemoireArchive::where('visible_public', true)
            ->orderBy('nombre_telechargements', 'desc')
            ->take(10)
            ->get(['id', 'titre_memoire', 'nom_etudiant', 'prenom_etudiant', 'nombre_telechargements', 'note_finale', 'mention']);

        // Encadreurs les plus productifs
        $topEncadreurs = MemoireArchive::where('visible_public', true)
            ->selectRaw('nom_encadreur, COUNT(*) as total_memoires, AVG(note_finale) as note_moyenne')
            ->groupBy('nom_encadreur')
            ->orderBy('total_memoires', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques_generales' => $stats,
                'top_memoires' => $topMemoires,
                'top_encadreurs' => $topEncadreurs,
            ]
        ], 200);
    }

    /**
     * Filtres disponibles pour la recherche
     */
    public function filtresDisponibles()
    {
        $filtres = [
            'annees' => MemoireArchive::where('visible_public', true)
                ->distinct()
                ->pluck('annee_soutenance')
                ->sort()
                ->values(),
            'niveaux' => MemoireArchive::where('visible_public', true)
                ->distinct()
                ->pluck('niveau')
                ->sort()
                ->values(),
            'filieres' => MemoireArchive::where('visible_public', true)
                ->distinct()
                ->pluck('filiere')
                ->sort()
                ->values(),
            'mentions' => ['excellent', 'tres_bien', 'bien', 'assez_bien', 'passable'],
            'encadreurs' => MemoireArchive::where('visible_public', true)
                ->distinct()
                ->pluck('nom_encadreur')
                ->sort()
                ->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $filtres
        ], 200);
    }

    /**
     * Mémoires suggérés (basés sur les téléchargements et notes)
     */
    public function memoiresSuggeres(Request $request)
    {
        $limit = $request->get('limit', 6);

        // Algorithme simple de suggestion basé sur popularité et qualité
        $memoires = MemoireArchive::where('visible_public', true)
            ->selectRaw('*, (nombre_telechargements * 0.4 + note_finale * 0.6) as score_suggestion')
            ->orderBy('score_suggestion', 'desc')
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memoires
        ], 200);
    }

    /**
     * Nouveaux mémoires archivés
     */
    public function nouveauxMemoires(Request $request)
    {
        $limit = $request->get('limit', 8);

        $memoires = MemoireArchive::where('visible_public', true)
            ->latest('date_archivage')
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memoires
        ], 200);
    }

    /**
     * Mémoires d'excellence (mention excellent ou très bien avec note > 16)
     */
    public function memoiresExcellence(Request $request)
    {
        $limit = $request->get('limit', 10);

        $memoires = MemoireArchive::where('visible_public', true)
            ->whereIn('mention', ['excellent', 'tres_bien'])
            ->where('note_finale', '>=', 16)
            ->orderBy('note_finale', 'desc')
            ->orderBy('date_archivage', 'desc')
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $memoires
        ], 200);
    }

    /**
     * Administration des archives (pour les admins)
     */
    public function administrationArchives(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux administrateurs'
            ], 403);
        }

        $query = MemoireArchive::query();

        // Inclure les mémoires non publics pour l'admin
        if ($request->has('visible') && $request->visible !== 'all') {
            $query->where('visible_public', $request->visible === 'true');
        }

        // Autres filtres similaires à l'index public
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre_memoire', 'like', "%{$search}%")
                    ->orWhere('nom_etudiant', 'like', "%{$search}%")
                    ->orWhere('nom_encadreur', 'like', "%{$search}%");
            });
        }

        $memoires = $query->with('soutenance')
            ->latest('date_archivage')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $memoires
        ], 200);
    }

    /**
     * Basculer la visibilité d'un mémoire (admin)
     */
    public function basculerVisibilite(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux administrateurs'
            ], 403);
        }

        $memoire = MemoireArchive::find($id);

        if (!$memoire) {
            return response()->json([
                'success' => false,
                'message' => 'Mémoire non trouvé'
            ], 404);
        }

        $memoire->update([
            'visible_public' => !$memoire->visible_public
        ]);

        $message = $memoire->visible_public ?
            'Mémoire rendu public' :
            'Mémoire retiré de la bibliothèque publique';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $memoire
        ], 200);
    }

    /**
     * Générer un nom de fichier lisible
     */
    private function genererNomFichier($memoire)
    {
        $nom = strtoupper($memoire->nom_etudiant);
        $prenom = ucfirst(strtolower($memoire->prenom_etudiant));
        $annee = $memoire->annee_soutenance;
        $niveau = $memoire->niveau;

        return "Memoire_{$niveau}_{$prenom}_{$nom}_{$annee}.pdf";
    }
}
