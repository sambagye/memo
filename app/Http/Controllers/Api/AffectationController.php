<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Affectation;
use App\Models\Etudiant;
use App\Models\Sujet;
use App\Models\Encadreur;
use Illuminate\Support\Facades\Mail;
use App\Mail\AffectationNotificationMail;

class AffectationController extends Controller
{
    /**
     * Liste des affectations avec filtres
     */
    public function index(Request $request)
    {
        $query = Affectation::with([
            'etudiant.user',
            'sujet',
            'encadreur.user'
        ]);

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('encadreur_id') && $request->encadreur_id !== 'all') {
            $query->where('encadreur_id', $request->encadreur_id);
        }

        if ($request->has('niveau') && $request->niveau !== 'all') {
            $query->whereHas('etudiant', function($q) use ($request) {
                $q->where('niveau', $request->niveau);
            });
        }

        if ($request->has('annee_academique') && $request->annee_academique !== 'all') {
            $query->whereHas('etudiant', function($q) use ($request) {
                $q->where('annee_academique', $request->annee_academique);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('etudiant.user', function($subQ) use ($search) {
                    $subQ->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%");
                })
                    ->orWhereHas('sujet', function($subQ) use ($search) {
                        $subQ->where('titre', 'like', "%{$search}%");
                    })
                    ->orWhereHas('encadreur.user', function($subQ) use ($search) {
                        $subQ->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    });
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortBy, ['created_at', 'date_affectation', 'ordre_preference_etudiant'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest();
        }

        $affectations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $affectations
        ], 200);
    }

    /**
     * Détails d'une affectation
     */
    public function show($id)
    {
        $affectation = Affectation::with([
            'etudiant.user',
            'sujet',
            'encadreur.user',
            'seances' => function($query) {
                $query->latest('date_heure')->take(5);
            },
            'dossierSoutenance'
        ])->find($id);

        if (!$affectation) {
            return response()->json([
                'success' => false,
                'message' => 'Affectation non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $affectation
        ], 200);
    }

    /**
     * Créer une affectation manuelle (Admin)
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut créer des affectations'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'etudiant_id' => 'required|exists:etudiants,id',
            'sujet_id' => 'required|exists:sujets,id',
            'encadreur_id' => 'required|exists:encadreurs,id',
            'commentaire_admin' => 'nullable|string',
        ], [
            'etudiant_id.required' => 'L\'étudiant est requis',
            'etudiant_id.exists' => 'Étudiant non trouvé',
            'sujet_id.required' => 'Le sujet est requis',
            'sujet_id.exists' => 'Sujet non trouvé',
            'encadreur_id.required' => 'L\'encadreur est requis',
            'encadreur_id.exists' => 'Encadreur non trouvé',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function() use ($request) {
                $etudiant = Etudiant::find($request->etudiant_id);
                $sujet = Sujet::find($request->sujet_id);
                $encadreur = Encadreur::find($request->encadreur_id);

                // Vérifications
                if ($etudiant->affectation) {
                    throw new \Exception('Cet étudiant est déjà affecté');
                }

                if ($sujet->statut !== 'valide') {
                    throw new \Exception('Le sujet doit être validé');
                }

                if ($sujet->nombre_places_occupees >= $sujet->nombre_places_disponibles) {
                    throw new \Exception('Plus de places disponibles pour ce sujet');
                }

                if ($encadreur->nombre_etudiants_actuels >= $encadreur->nombre_max_etudiants) {
                    throw new \Exception('L\'encadreur a atteint sa limite d\'étudiants');
                }

                // Supprimer les anciennes préférences de l'étudiant
                Affectation::where('etudiant_id', $request->etudiant_id)->delete();

                // Créer la nouvelle affectation
                $affectation = Affectation::create([
                    'etudiant_id' => $request->etudiant_id,
                    'sujet_id' => $request->sujet_id,
                    'encadreur_id' => $request->encadreur_id,
                    'ordre_preference_etudiant' => 1,
                    'statut' => 'affecte',
                    'date_affectation' => now(),
                    'commentaire_admin' => $request->commentaire_admin,
                ]);
                Mail::to($etudiant->user->email)->send(new AffectationNotificationMail($this->affectation, 'etudiant'));
                Mail::to($encadreur->user->email)->send(new AffectationNotificationMail($this->affectation, 'encadreur'));

                // Mettre à jour les compteurs
                $sujet->increment('nombre_places_occupees');
                $encadreur->increment('nombre_etudiants_actuels');

                // Mettre à jour le statut de l'étudiant
                $etudiant->update(['statut_memoire' => 'affecte']);

                $this->affectation = $affectation->load([
                    'etudiant.user',
                    'sujet',
                    'encadreur.user'
                ]);
            });
        // Notifier l'étudiant et l'encadreur
           return response()->json([
                'success' => true,
                'message' => 'Affectation créée avec succès',
                'data' => $this->affectation
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Effectuer les affectations automatiques
     */
    public function affectationsAutomatiques(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut effectuer les affectations automatiques'
            ], 403);
        }

        try {
            $resultats = DB::transaction(function() {
                $affectationsCreees = [];
                $conflits = [];

                // Récupérer tous les étudiants en attente avec leurs préférences
                $etudiants = Etudiant::where('statut_memoire', 'sujet_choisi')
                    ->with(['affectations' => function($query) {
                        $query->where('statut', 'en_attente')
                            ->orderBy('ordre_preference_etudiant');
                    }])
                    ->get();

                foreach ($etudiants as $etudiant) {
                    $affecte = false;

                    foreach ($etudiant->affectations as $preference) {
                        $sujet = Sujet::find($preference->sujet_id);
                        $encadreur = Encadreur::find($preference->encadreur_id);

                        // Vérifier la disponibilité
                        if ($sujet->statut === 'valide' &&
                            $sujet->nombre_places_occupees < $sujet->nombre_places_disponibles &&
                            $encadreur->nombre_etudiants_actuels < $encadreur->nombre_max_etudiants) {

                            // Effectuer l'affectation
                            $preference->update([
                                'statut' => 'affecte',
                                'date_affectation' => now(),
                            ]);

                            // Supprimer les autres préférences de cet étudiant
                            Affectation::where('etudiant_id', $etudiant->id)
                                ->where('id', '!=', $preference->id)
                                ->delete();

                            // Mettre à jour les compteurs
                            $sujet->increment('nombre_places_occupees');
                            $encadreur->increment('nombre_etudiants_actuels');

                            // Mettre à jour le statut de l'étudiant
                            $etudiant->update(['statut_memoire' => 'affecte']);

                            $affectationsCreees[] = $preference->load([
                                'etudiant.user',
                                'sujet',
                                'encadreur.user'
                            ]);

                            $affecte = true;
                            break;
                        }
                    }

                    if (!$affecte) {
                        $conflits[] = [
                            'etudiant' => $etudiant->user->nom_complet,
                            'raison' => 'Aucun sujet disponible parmi les préférences'
                        ];
                    }
                }

                return [
                    'affectations_creees' => $affectationsCreees,
                    'conflits' => $conflits
                ];
            });

            return response()->json([
                'success' => true,
                'message' => count($resultats['affectations_creees']) . ' affectation(s) créée(s) automatiquement',
                'data' => $resultats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors des affectations automatiques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modifier une affectation
     */
    public function update(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut modifier les affectations'
            ], 403);
        }

        $affectation = Affectation::find($id);

        if (!$affectation) {
            return response()->json([
                'success' => false,
                'message' => 'Affectation non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'sujet_id' => 'required|exists:sujets,id',
            'encadreur_id' => 'required|exists:encadreurs,id',
            'commentaire_admin' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::transaction(function() use ($request, $affectation) {
                $ancienSujet = $affectation->sujet;
                $ancienEncadreur = $affectation->encadreur;

                $nouveauSujet = Sujet::find($request->sujet_id);
                $nouvelEncadreur = Encadreur::find($request->encadreur_id);

                // Vérifications
                if ($nouveauSujet->statut !== 'valide') {
                    throw new \Exception('Le nouveau sujet doit être validé');
                }

                if ($nouveauSujet->id !== $ancienSujet->id) {
                    if ($nouveauSujet->nombre_places_occupees >= $nouveauSujet->nombre_places_disponibles) {
                        throw new \Exception('Plus de places disponibles pour le nouveau sujet');
                    }
                }

                if ($nouvelEncadreur->id !== $ancienEncadreur->id) {
                    if ($nouvelEncadreur->nombre_etudiants_actuels >= $nouvelEncadreur->nombre_max_etudiants) {
                        throw new \Exception('Le nouvel encadreur a atteint sa limite d\'étudiants');
                    }
                }

                // Mettre à jour l'affectation
                $affectation->update([
                    'sujet_id' => $request->sujet_id,
                    'encadreur_id' => $request->encadreur_id,
                    'commentaire_admin' => $request->commentaire_admin,
                ]);

                // Mettre à jour les compteurs si changement
                if ($nouveauSujet->id !== $ancienSujet->id) {
                    $ancienSujet->decrement('nombre_places_occupees');
                    $nouveauSujet->increment('nombre_places_occupees');
                }

                if ($nouvelEncadreur->id !== $ancienEncadreur->id) {
                    $ancienEncadreur->decrement('nombre_etudiants_actuels');
                    $nouvelEncadreur->increment('nombre_etudiants_actuels');
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Affectation modifiée avec succès',
                'data' => $affectation->fresh()->load([
                    'etudiant.user',
                    'sujet',
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
     * Supprimer une affectation
     */
    public function destroy(Request $request, $id)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut supprimer les affectations'
            ], 403);
        }

        $affectation = Affectation::find($id);

        if (!$affectation) {
            return response()->json([
                'success' => false,
                'message' => 'Affectation non trouvée'
            ], 404);
        }

        // Vérifier qu'il n'y a pas de séances ou de dossier de soutenance
        if ($affectation->seances()->exists() || $affectation->dossierSoutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une affectation avec des séances ou un dossier de soutenance'
            ], 400);
        }

        try {
            DB::transaction(function() use ($affectation) {
                $sujet = $affectation->sujet;
                $encadreur = $affectation->encadreur;
                $etudiant = $affectation->etudiant;

                // Mettre à jour les compteurs
                $sujet->decrement('nombre_places_occupees');
                $encadreur->decrement('nombre_etudiants_actuels');

                // Mettre à jour le statut de l'étudiant
                $etudiant->update(['statut_memoire' => 'en_attente_sujet']);

                // Supprimer l'affectation
                $affectation->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Affectation supprimée avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des affectations
     */
    public function statistiques()
    {
        $stats = [
            'total_affectations' => Affectation::count(),
            'affectations_confirmees' => Affectation::where('statut', 'affecte')->count(),
            'en_attente' => Affectation::where('statut', 'en_attente')->count(),
            'refusees' => Affectation::where('statut', 'refuse')->count(),
        ];

        // Répartition par encadreur
        $repartitionEncadreur = Affectation::where('statut', 'affecte')
            ->with('encadreur.user')
            ->selectRaw('encadreur_id, COUNT(*) as total')
            ->groupBy('encadreur_id')
            ->orderBy('total', 'desc')
            ->take(10)
            ->get();

        // Taux d'occupation des encadreurs
        $encadreurs = Encadreur::with('user')
            ->withCount(['affectations' => function($query) {
                $query->where('statut', 'affecte');
            }])
            ->get()
            ->map(function($encadreur) {
                return [
                    'nom_complet' => $encadreur->user->nom_complet,
                    'etudiants_actuels' => $encadreur->nombre_etudiants_actuels,
                    'places_totales' => $encadreur->nombre_max_etudiants,
                    'taux_occupation' => round(($encadreur->nombre_etudiants_actuels / $encadreur->nombre_max_etudiants) * 100, 1),
                ];
            })
            ->sortByDesc('taux_occupation');

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => $stats,
                'repartition_encadreur' => $repartitionEncadreur,
                'taux_occupation_encadreurs' => $encadreurs->values(),
            ]
        ], 200);
    }
}
