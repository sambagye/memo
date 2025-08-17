<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Jury;
use App\Models\MembreDuJury;
use App\Models\Encadreur;
use App\Models\Soutenance;

class JuryController extends Controller
{
    /**
     * Liste des jurys avec filtres
     */
    public function index(Request $request)
    {
        $query = Jury::with([
            'president.user',
            'rapporteur.user',
            'examinateur.user',
            'encadreur.user'
        ])->withCount('soutenances');

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom_jury', 'like', "%{$search}%")
                    ->orWhereHas('president.user', function($subQ) use ($search) {
                        $subQ->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    });
            });
        }

        $jurys = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $jurys
        ], 200);
    }

    /**
     * Détails d'un jury
     */
    public function show($id)
    {
        $jury = Jury::with([
            'president.user',
            'rapporteur.user',
            'examinateur.user',
            'encadreur.user',
            'soutenances.etudiant.user',
            'soutenances.dossierSoutenance'
        ])->find($id);

        if (!$jury) {
            return response()->json([
                'success' => false,
                'message' => 'Jury non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $jury
        ], 200);
    }

    /**
     * Créer un nouveau jury
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut créer des jurys'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nom_jury' => 'required|string|max:255|unique:juries,nom_jury',
            'president_id' => 'required|exists:membre_du_juries,id',
            'rapporteur_id' => 'required|exists:membre_du_juries,id|different:president_id',
            'examinateur_id' => 'required|exists:membre_du_juries,id|different:president_id|different:rapporteur_id',
            'encadreur_id' => 'required|exists:encadreurs,id',
            'commentaire' => 'nullable|string',
        ], [
            'nom_jury.required' => 'Le nom du jury est requis',
            'nom_jury.unique' => 'Ce nom de jury existe déjà',
            'president_id.required' => 'Le président est requis',
            'rapporteur_id.required' => 'Le rapporteur est requis',
            'rapporteur_id.different' => 'Le rapporteur doit être différent du président',
            'examinateur_id.required' => 'L\'examinateur est requis',
            'examinateur_id.different' => 'L\'examinateur doit être différent des autres membres',
            'encadreur_id.required' => 'L\'encadreur est requis',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifier la disponibilité des membres
            $membresIds = [$request->president_id, $request->rapporteur_id, $request->examinateur_id];
            $membresOccupes = MembreDuJury::whereIn('id', $membresIds)
                ->where('statut_disponibilite', '!=', 'disponible')
                ->with('user')
                ->get();

            if ($membresOccupes->isNotEmpty()) {
                $nomsOccupes = $membresOccupes->pluck('user.nom_complet')->implode(', ');
                return response()->json([
                    'success' => false,
                    'message' => "Membre(s) non disponible(s): {$nomsOccupes}"
                ], 400);
            }

            $jury = Jury::create([
                'nom_jury' => $request->nom_jury,
                'president_id' => $request->president_id,
                'rapporteur_id' => $request->rapporteur_id,
                'examinateur_id' => $request->examinateur_id,
                'encadreur_id' => $request->encadreur_id,
                'date_creation' => now(),
                'statut' => 'constitue',
                'commentaire' => $request->commentaire,
            ]);

            // Marquer les membres comme occupés
            MembreDuJury::whereIn('id', $membresIds)
                ->update(['statut_disponibilite' => 'occupe']);

            return response()->json([
                'success' => true,
                'message' => 'Jury créé avec succès',
                'data' => $jury->load([
                    'president.user',
                    'rapporteur.user',
                    'examinateur.user',
                    'encadreur.user'
                ])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un jury
     */
    public function update(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut modifier les jurys'
            ], 403);
        }

        $jury = Jury::find($id);

        if (!$jury) {
            return response()->json([
                'success' => false,
                'message' => 'Jury non trouvé'
            ], 404);
        }

        // Vérifier qu'il n'y a pas de soutenances en cours
        if ($jury->soutenances()->whereIn('statut', ['programmee', 'en_cours'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier un jury avec des soutenances en cours'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'nom_jury' => 'required|string|max:255|unique:juries,nom_jury,' . $id,
            'president_id' => 'required|exists:membre_du_juries,id',
            'rapporteur_id' => 'required|exists:membre_du_juries,id|different:president_id',
            'examinateur_id' => 'required|exists:membre_du_juries,id|different:president_id|different:rapporteur_id',
            'encadreur_id' => 'required|exists:encadreurs,id',
            'statut' => 'nullable|in:constitue,actif,termine',
            'commentaire' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function() use ($request, $jury) {
                $anciensMembres = [$jury->president_id, $jury->rapporteur_id, $jury->examinateur_id];
                $nouveauxMembres = [$request->president_id, $request->rapporteur_id, $request->examinateur_id];

                // Libérer les anciens membres s'ils changent
                $membresALiberer = array_diff($anciensMembres, $nouveauxMembres);
                if (!empty($membresALiberer)) {
                    MembreDuJury::whereIn('id', $membresALiberer)
                        ->update(['statut_disponibilite' => 'disponible']);
                }

                // Occuper les nouveaux membres
                $nouveauxMembresAOccuper = array_diff($nouveauxMembres, $anciensMembres);
                if (!empty($nouveauxMembresAOccuper)) {
                    // Vérifier leur disponibilité
                    $membresOccupes = MembreDuJury::whereIn('id', $nouveauxMembresAOccuper)
                        ->where('statut_disponibilite', '!=', 'disponible')
                        ->exists();

                    if ($membresOccupes) {
                        throw new \Exception('Un ou plusieurs nouveaux membres ne sont pas disponibles');
                    }

                    MembreDuJury::whereIn('id', $nouveauxMembresAOccuper)
                        ->update(['statut_disponibilite' => 'occupe']);
                }

                // Mettre à jour le jury
                $jury->update([
                    'nom_jury' => $request->nom_jury,
                    'president_id' => $request->president_id,
                    'rapporteur_id' => $request->rapporteur_id,
                    'examinateur_id' => $request->examinateur_id,
                    'encadreur_id' => $request->encadreur_id,
                    'statut' => $request->statut ?? $jury->statut,
                    'commentaire' => $request->commentaire,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Jury modifié avec succès',
                'data' => $jury->fresh()->load([
                    'president.user',
                    'rapporteur.user',
                    'examinateur.user',
                    'encadreur.user'
                ])
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un jury
     */
    public function destroy(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut supprimer les jurys'
            ], 403);
        }

        $jury = Jury::find($id);

        if (!$jury) {
            return response()->json([
                'success' => false,
                'message' => 'Jury non trouvé'
            ], 404);
        }

        // Vérifier qu'il n'y a pas de soutenances
        if ($jury->soutenances()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un jury avec des soutenances'
            ], 400);
        }

        try {
            DB::transaction(function() use ($jury) {
                // Libérer les membres
                $membresIds = [$jury->president_id, $jury->rapporteur_id, $jury->examinateur_id];
                MembreDuJury::whereIn('id', $membresIds)
                    ->update(['statut_disponibilite' => 'disponible']);

                // Supprimer le jury
                $jury->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Jury supprimé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Membres disponibles pour constituer un jury
     */
    public function membresDisponibles()
    {
        $membres = MembreDuJury::where('statut_disponibilite', 'disponible')
            ->with('user')
            ->orderBy('grade_academique')
            ->orderBy('specialite')
            ->get()
            ->groupBy(['est_externe', 'specialite']);

        $encadreurs = Encadreur::with('user')
            ->get()
            ->map(function($encadreur) {
                return [
                    'id' => $encadreur->id,
                    'nom_complet' => $encadreur->user->nom_complet,
                    'specialite' => $encadreur->specialite,
                    'grade_academique' => $encadreur->grade_academique,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'membres_jury' => $membres,
                'encadreurs' => $encadreurs
            ]
        ], 200);
    }

    /**
     * Jury d'un membre connecté
     */
    public function mesJurys(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'membre_jury') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les membres de jury peuvent accéder à cette fonction'
            ], 403);
        }

        $membreDuJury = $user->membreDuJury;

        if (!$membreDuJury) {
            return response()->json([
                'success' => false,
                'message' => 'Profil membre de jury non trouvé'
            ], 404);
        }

        $jurys = Jury::where(function($query) use ($membreDuJury) {
            $query->where('president_id', $membreDuJury->id)
                ->orWhere('rapporteur_id', $membreDuJury->id)
                ->orWhere('examinateur_id', $membreDuJury->id);
        })
            ->with([
                'president.user',
                'rapporteur.user',
                'examinateur.user',
                'encadreur.user',
                'soutenances' => function($query) {
                    $query->with(['etudiant.user', 'dossierSoutenance'])
                        ->orderBy('date_heure_soutenance');
                }
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jurys
        ], 200);
    }

    /**
     * Statistiques des jurys
     */
    public function statistiques()
    {
        $stats = [
            'total_jurys' => Jury::count(),
            'jurys_constitues' => Jury::where('statut', 'constitue')->count(),
            'jurys_actifs' => Jury::where('statut', 'actif')->count(),
            'jurys_termines' => Jury::where('statut', 'termine')->count(),
            'membres_disponibles' => MembreDuJury::where('statut_disponibilite', 'disponible')->count(),
            'membres_occupes' => MembreDuJury::where('statut_disponibilite', 'occupe')->count(),
        ];

        // Membres les plus actifs
        $membresActifs = MembreDuJury::withCount([
            'jurysCommePrecident',
            'jurysCommeRapporteur',
            'jurysCommeExaminateur'
        ])
            ->with('user')
            ->get()
            ->map(function($membre) {
                return [
                    'nom_complet' => $membre->user->nom_complet,
                    'total_jurys' => $membre->jurys_comme_precident_count +
                        $membre->jurys_comme_rapporteur_count +
                        $membre->jurys_comme_examinateur_count,
                    'comme_president' => $membre->jurys_comme_precident_count,
                    'comme_rapporteur' => $membre->jurys_comme_rapporteur_count,
                    'comme_examinateur' => $membre->jurys_comme_examinateur_count,
                ];
            })
            ->sortByDesc('total_jurys')
            ->take(10);

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => $stats,
                'membres_actifs' => $membresActifs->values(),
            ]
        ], 200);
    }
}
