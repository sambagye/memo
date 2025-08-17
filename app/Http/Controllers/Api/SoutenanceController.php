<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Soutenance;
use App\Models\DossierSoutenance;
use App\Models\Jury;
use App\Models\MemoireArchive;
use Illuminate\Support\Facades\Mail;
use App\Mail\SoutenanceNotificationMail;

class SoutenanceController extends Controller
{
    /**
     * Liste des soutenances avec filtres
     */
    public function index(Request $request)
    {
        $query = Soutenance::with([
            'etudiant.user',
            'jury.president.user',
            'jury.rapporteur.user',
            'jury.examinateur.user',
            'jury.encadreur.user'
        ]);

        // Filtres
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('jury_id') && $request->jury_id !== 'all') {
            $query->where('jury_id', $request->jury_id);
        }

        if ($request->has('date_debut') && $request->has('date_fin')) {
            $query->whereBetween('date_heure_soutenance', [
                $request->date_debut,
                $request->date_fin
            ]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('etudiant.user', function($subQ) use ($search) {
                    $subQ->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%");
                })
                    ->orWhere('salle', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'date_heure_soutenance');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortBy, ['date_heure_soutenance', 'created_at', 'note_finale'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->latest('date_heure_soutenance');
        }

        $soutenances = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $soutenances
        ], 200);
    }

    /**
     * Détails d'une soutenance
     */
    public function show($id)
    {
        $soutenance = Soutenance::with([
            'etudiant.user',
            'jury.president.user',
            'jury.rapporteur.user',
            'jury.examinateur.user',
            'jury.encadreur.user',
            'dossierSoutenance',
            'memoireArchive'
        ])->find($id);

        if (!$soutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Soutenance non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $soutenance
        ], 200);
    }

    /**
     * Programmer une soutenance
     */
    public function store(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut programmer les soutenances'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'etudiant_id' => 'required|exists:etudiants,id',
            'jury_id' => 'required|exists:juries,id',
            'dossier_soutenance_id' => 'required|exists:dossier_soutenances,id',
            'date_heure_soutenance' => 'required|date|after:now',
            'salle' => 'required|string|max:255',
            'duree_minutes' => 'required|integer|min:30|max:180',
        ], [
            'etudiant_id.required' => 'L\'étudiant est requis',
            'jury_id.required' => 'Le jury est requis',
            'dossier_soutenance_id.required' => 'Le dossier de soutenance est requis',
            'date_heure_soutenance.required' => 'La date et heure sont requises',
            'date_heure_soutenance.after' => 'La date doit être dans le futur',
            'salle.required' => 'La salle est requise',
            'duree_minutes.required' => 'La durée est requise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Vérifications
            $dossier = DossierSoutenance::find($request->dossier_soutenance_id);
            if (!$dossier->autorisation_encadreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le dossier n\'est pas encore autorisé par l\'encadreur'
                ], 400);
            }

            if (!$dossier->dossier_complet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le dossier n\'est pas complet'
                ], 400);
            }

            // Vérifier que l'étudiant n'a pas déjà une soutenance
            $soutenanceExistante = Soutenance::where('etudiant_id', $request->etudiant_id)->first();
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
            // Notifier l'étudiant
            Mail::to($soutenance->etudiant->user->email)->send(new SoutenanceNotificationMail($soutenance, 'etudiant', 'programmee'));

// Notifier les membres du jury
            Mail::to($soutenance->jury->president->user->email)->send(new SoutenanceNotificationMail($soutenance, 'jury', 'programmee'));
            Mail::to($soutenance->jury->rapporteur->user->email)->send(new SoutenanceNotificationMail($soutenance, 'jury', 'programmee'));
            Mail::to($soutenance->jury->examinateur->user->email)->send(new SoutenanceNotificationMail($soutenance, 'jury', 'programmee'));
            Mail::to($soutenance->jury->encadreur->user->email)->send(new SoutenanceNotificationMail($soutenance, 'encadreur', 'programmee'));

            // Mettre à jour le statut du jury et de l'étudiant
            $jury = Jury::find($request->jury_id);
            $jury->update(['statut' => 'actif']);

            $dossier->etudiant->update(['statut_memoire' => 'soutenance_programmee']);

            return response()->json([
                'success' => true,
                'message' => 'Soutenance programmée avec succès',
                'data' => $soutenance->load([
                    'etudiant.user',
                    'jury',
                    'dossierSoutenance'
                ])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la programmation: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Mettre à jour une soutenance
     */
    public function update(Request $request, $id)
    {
        $soutenance = Soutenance::find($id);

        if (!$soutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Soutenance non trouvée'
            ], 404);
        }

        $user = $request->user();

        // Seul l'admin peut modifier les détails de programmation
        if ($user->role === 'admin') {
            $validator = Validator::make($request->all(), [
                'date_heure_soutenance' => 'sometimes|date',
                'salle' => 'sometimes|string|max:255',
                'duree_minutes' => 'sometimes|integer|min:30|max:180',
                'statut' => 'sometimes|in:programmee,en_cours,terminee,reportee',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $soutenance->update($request->only([
                'date_heure_soutenance',
                'salle',
                'duree_minutes',
                'statut'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Soutenance mise à jour avec succès',
                'data' => $soutenance->fresh()
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Non autorisé'
        ], 403);
    }

    /**
     * Saisir les notes d'évaluation
     */
    public function saisirNotes(Request $request, $id)
    {
        $soutenance = Soutenance::find($id);

        if (!$soutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Soutenance non trouvée'
            ], 404);
        }

        $user = $request->user();

        // Vérifier que l'utilisateur est membre du jury
        $estMembreDuJury = false;
        $roleJury = null;

        if ($user->role === 'membre_jury' && $user->membreDuJury) {
            $membreDuJury = $user->membreDuJury;
            if ($soutenance->jury->president_id === $membreDuJury->id) {
                $estMembreDuJury = true;
                $roleJury = 'president';
            } elseif ($soutenance->jury->rapporteur_id === $membreDuJury->id) {
                $estMembreDuJury = true;
                $roleJury = 'rapporteur';
            } elseif ($soutenance->jury->examinateur_id === $membreDuJury->id) {
                $estMembreDuJury = true;
                $roleJury = 'examinateur';
            }
        } elseif ($user->role === 'encadreur' && $user->encadreur) {
            if ($soutenance->jury->encadreur_id === $user->encadreur->id) {
                $estMembreDuJury = true;
                $roleJury = 'encadreur';
            }
        }

        if (!$estMembreDuJury) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas membre de ce jury'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|numeric|min:0|max:20',
            'appreciation' => 'nullable|string',
        ], [
            'note.required' => 'La note est requise',
            'note.numeric' => 'La note doit être un nombre',
            'note.min' => 'La note minimum est 0',
            'note.max' => 'La note maximum est 20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Saisir la note selon le rôle
            $champNote = "note_{$roleJury}";
            $soutenance->update([
                $champNote => $request->note
            ]);

            // Si c'est le président, il peut aussi saisir l'appréciation générale
            if ($roleJury === 'president' && $request->has('appreciation')) {
                $soutenance->update([
                    'appreciation_generale' => $request->appreciation
                ]);
            }

            // Calculer la note finale si toutes les notes sont saisies
            $this->calculerNoteFinalte($soutenance);

            return response()->json([
                'success' => true,
                'message' => 'Note saisie avec succès',
                'data' => $soutenance->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la saisie: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminer une soutenance avec délibération
     */
    public function terminerSoutenance(Request $request, $id)
    {
        $soutenance = Soutenance::find($id);

        if (!$soutenance) {
            return response()->json([
                'success' => false,
                'message' => 'Soutenance non trouvée'
            ], 404);
        }

        $user = $request->user();

        // Seul le président du jury peut terminer la soutenance
        $estPresident = ($user->role === 'membre_jury' &&
            $user->membreDuJury &&
            $soutenance->jury->president_id === $user->membreDuJury->id);

        if (!$estPresident && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul le président du jury peut terminer la soutenance'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'appreciation_generale' => 'required|string',
            'recommandations' => 'nullable|string',
        ], [
            'appreciation_generale.required' => 'L\'appréciation générale est requise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que toutes les notes sont saisies
        if (!$soutenance->toutes_notes_complete) {
            return response()->json([
                'success' => false,
                'message' => 'Toutes les notes doivent être saisies avant de terminer la soutenance'
            ], 400);
        }

        try {
            DB::transaction(function() use ($request, $soutenance) {
                // Mettre à jour la soutenance
                $soutenance->update([
                    'statut' => 'terminee',
                    'appreciation_generale' => $request->appreciation_generale,
                    'recommandations' => $request->recommandations,
                    'date_deliberation' => now(),
                ]);

                // Calculer la note finale et la mention
                $this->calculerNoteFinalte($soutenance);
                $mention = $this->determinerMention($soutenance->note_finale);

                $soutenance->update(['mention' => $mention]);

                // Mettre à jour le statut de l'étudiant
                $soutenance->etudiant->update(['statut_memoire' => 'soutenu']);

                // Archiver le mémoire
                $this->archiverMemoire($soutenance);
            });
// Notifier l'étudiant de la fin de soutenance
            Mail::to($soutenance->etudiant->user->email)->send(new SoutenanceNotificationMail($soutenance, 'etudiant', 'terminee'));
            return response()->json([
                'success' => true,
                'message' => 'Soutenance terminée avec succès. Le mémoire a été archivé.',
                'data' => $soutenance->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Planning des soutenances
     */
    public function planning(Request $request)
    {
        $dateDebut = $request->get('date_debut', now()->startOfMonth());
        $dateFin = $request->get('date_fin', now()->endOfMonth());

        $soutenances = Soutenance::whereBetween('date_heure_soutenance', [$dateDebut, $dateFin])
            ->with([
                'etudiant.user',
                'jury.president.user'
            ])
            ->orderBy('date_heure_soutenance')
            ->get();

        // Formater pour le calendrier
        $evenements = $soutenances->map(function($soutenance) {
            return [
                'id' => $soutenance->id,
                'title' => "Soutenance - " . $soutenance->etudiant->user->nom_complet,
                'start' => $soutenance->date_heure_soutenance->format('Y-m-d H:i:s'),
                'end' => $soutenance->date_fin_soutenance->format('Y-m-d H:i:s'),
                'salle' => $soutenance->salle,
                'statut' => $soutenance->statut,
                'president' => $soutenance->jury->president->user->nom_complet,
                'backgroundColor' => $this->getCouleurStatut($soutenance->statut),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $evenements
        ], 200);
    }

    /**
     * Mes soutenances (pour les membres de jury)
     */
    public function mesSoutenances(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['membre_jury', 'encadreur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux membres de jury et encadreurs'
            ], 403);
        }

        $query = Soutenance::with([
            'etudiant.user',
            'jury.president.user',
            'jury.rapporteur.user',
            'jury.examinateur.user',
            'jury.encadreur.user',
            'dossierSoutenance'
        ]);

        if ($user->role === 'membre_jury' && $user->membreDuJury) {
            $membreDuJury = $user->membreDuJury;
            $query->whereHas('jury', function($q) use ($membreDuJury) {
                $q->where('president_id', $membreDuJury->id)
                    ->orWhere('rapporteur_id', $membreDuJury->id)
                    ->orWhere('examinateur_id', $membreDuJury->id);
            });
        } elseif ($user->role === 'encadreur' && $user->encadreur) {
            $query->whereHas('jury', function($q) use ($user) {
                $q->where('encadreur_id', $user->encadreur->id);
            });
        }

        $soutenances = $query->orderBy('date_heure_soutenance', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $soutenances
        ], 200);
    }

    /**
     * Statistiques des soutenances
     */
    public function statistiques()
    {
        $stats = [
            'total_soutenances' => Soutenance::count(),
            'programmees' => Soutenance::where('statut', 'programmee')->count(),
            'terminees' => Soutenance::where('statut', 'terminee')->count(),
            'en_cours' => Soutenance::where('statut', 'en_cours')->count(),
            'reportees' => Soutenance::where('statut', 'reportee')->count(),
        ];

        // Répartition des mentions
        $mentions = Soutenance::where('statut', 'terminee')
            ->whereNotNull('mention')
            ->selectRaw('mention, COUNT(*) as total')
            ->groupBy('mention')
            ->get()
            ->pluck('total', 'mention');

        // Notes moyennes
        $notesMoyennes = [
            'note_moyenne_generale' => Soutenance::where('statut', 'terminee')
                ->whereNotNull('note_finale')
                ->avg('note_finale'),
            'note_moyenne_presidents' => Soutenance::where('statut', 'terminee')
                ->whereNotNull('note_president')
                ->avg('note_president'),
            'note_moyenne_rapporteurs' => Soutenance::where('statut', 'terminee')
                ->whereNotNull('note_rapporteur')
                ->avg('note_rapporteur'),
            'note_moyenne_examinateurs' => Soutenance::where('statut', 'terminee')
                ->whereNotNull('note_examinateur')
                ->avg('note_examinateur'),
            'note_moyenne_encadreurs' => Soutenance::where('statut', 'terminee')
                ->whereNotNull('note_encadreur')
                ->avg('note_encadreur'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => $stats,
                'repartition_mentions' => $mentions,
                'notes_moyennes' => $notesMoyennes,
            ]
        ], 200);
    }

    /**
     * Calculer la note finale
     */
    private function calculerNoteFinalte($soutenance)
    {
        if ($soutenance->toutes_notes_complete) {
            $soutenance->note_finale = ($soutenance->note_president +
                    $soutenance->note_rapporteur +
                    $soutenance->note_examinateur +
                    $soutenance->note_encadreur) / 4;
            $soutenance->save();
        }
    }

    /**
     * Déterminer la mention
     */
    private function determinerMention($noteFinal)
    {
        if ($noteFinal >= 18) {
            return 'excellent';
        } elseif ($noteFinal >= 16) {
            return 'tres_bien';
        } elseif ($noteFinal >= 14) {
            return 'bien';
        } elseif ($noteFinal >= 12) {
            return 'assez_bien';
        } else {
            return 'passable';
        }
    }

    /**
     * Archiver le mémoire
     */
    private function archiverMemoire($soutenance)
    {
        $dossier = $soutenance->dossierSoutenance;
        $etudiant = $soutenance->etudiant;

        MemoireArchive::create([
            'soutenance_id' => $soutenance->id,
            'titre_memoire' => $soutenance->dossierSoutenance->affectation->sujet->titre,
            'nom_etudiant' => $etudiant->user->nom,
            'prenom_etudiant' => $etudiant->user->prenom,
            'nom_encadreur' => $soutenance->jury->encadreur->user->nom_complet,
            'annee_soutenance' => now()->year,
            'niveau' => $etudiant->niveau,
            'filiere' => $etudiant->filiere,
            'mention' => $soutenance->mention,
            'note_finale' => $soutenance->note_finale,
            'fichier_memoire' => $dossier->memoire_pdf,
            'resume_francais' => $dossier->resume_francais,
            'resume_anglais' => $dossier->resume_anglais,
            'date_archivage' => now(),
            'visible_public' => true,
        ]);

        // Mettre à jour le statut de l'étudiant
        $etudiant->update(['statut_memoire' => 'archive']);
    }

    /**
     * Obtenir la couleur selon le statut
     */
    private function getCouleurStatut($statut)
    {
        switch ($statut) {
            case 'programmee':
                return '#007bff';
            case 'en_cours':
                return '#28a745';
            case 'terminee':
                return '#6c757d';
            case 'reportee':
                return '#ffc107';
            default:
                return '#007bff';
        }
    }
}
