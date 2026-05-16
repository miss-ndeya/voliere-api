<?php

namespace App\Http\Controllers;

use App\Models\Cage;
use App\Models\CageHistory;
use App\Models\Pigeon;
use App\Models\Couple;
use Illuminate\Http\Request;

class CageController extends Controller
{
    // Liste toutes les cages de l'utilisateur
    public function index()
    {
        $cages = Cage::where('user_id', auth()->id())
            ->with(['pigeon', 'couple.male', 'couple.femelle'])
            ->orderBy('numero')
            ->get();

        return response()->json($cages);
    }

    // Route spéciale pour la visualisation de la volière avec filtrage et pagination
    public function visualisation(Request $request)
    {
        $query = Cage::where('user_id', auth()->id())
            ->with(['pigeon', 'couple.male', 'couple.femelle']);

        // Filtrage par statut
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }

        // Recherche par numéro ou bague
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('nom', 'like', "%{$search}%")
                  ->orWhereHas('pigeon', function ($q) use ($search) {
                      $q->where('bague', 'like', "%{$search}%");
                  })
                  ->orWhereHas('couple.male', function ($q) use ($search) {
                      $q->where('bague', 'like', "%{$search}%");
                  })
                  ->orWhereHas('couple.femelle', function ($q) use ($search) {
                      $q->where('bague', 'like', "%{$search}%");
                  });
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 50);
        $cages = $query->paginate($perPage);

        // Formater les données
        $cages->getCollection()->transform(function ($cage) {
            return [
                'id' => $cage->id,
                'numero' => $cage->numero,
                'nom' => $cage->nom,
                'statut' => $cage->statut,
                'occupants' => $cage->statut === 'libre' ? null : (
                    $cage->statut === 'occupe' ? [
                        'pigeon' => $cage->pigeon,
                    ] : [
                        'male' => $cage->couple->male,
                        'femelle' => $cage->couple->femelle,
                    ]
                ),
            ];
        });

        // Ajouter les compteurs
        $counts = [
            'total' => Cage::where('user_id', auth()->id())->count(),
            'libre' => Cage::where('user_id', auth()->id())->where('statut', 'libre')->count(),
            'occupe' => Cage::where('user_id', auth()->id())->where('statut', 'occupe')->count(),
            'couple' => Cage::where('user_id', auth()->id())->where('statut', 'couple')->count(),
        ];

        return response()->json([
            'data' => $cages->items(),
            'meta' => [
                'current_page' => $cages->currentPage(),
                'last_page' => $cages->lastPage(),
                'per_page' => $cages->perPage(),
                'total' => $cages->total(),
            ],
            'counts' => $counts,
        ]);
    }

    // Historique d'une cage
    public function history(Cage $cage)
    {
        // Vérifier que la cage appartient à l'utilisateur connecté
        if ($cage->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Paramètre pour limiter le nombre de résultats (par défaut 2 pour le sidebar)
        $limit = request()->get('limit', 2);

        $history = CageHistory::where('cage_id', $cage->id)
            ->orderBy('created_at', 'desc')
            ->when($limit > 0, function ($query) use ($limit) {
                return $query->limit($limit);
            })
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'details' => $entry->description,
                    'metadata' => $entry->metadata,
                    'created_at' => $entry->created_at->toIso8601String(),
                ];
            });

        return response()->json($history);
    }

    // Ajouter une cage
    public function store(Request $request)
    {
        $request->validate([
            'numero' => [
                'required',
                'string',
                \Illuminate\Validation\Rule::unique('cages')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                })
            ],
            'nom' => 'required|string|max:255',
            'superficie' => 'nullable|numeric|min:0',
        ], [
            'numero.required' => 'Le numéro de cage est requis',
            'numero.string' => 'Le numéro de cage doit être du texte',
            'numero.unique' => 'Ce numéro de cage est déjà utilisé',
            'nom.required' => 'Le nom de la cage est requis',
            'nom.string' => 'Le nom de la cage doit être du texte',
            'nom.max' => 'Le nom de la cage ne peut pas dépasser 255 caractères',
            'superficie.numeric' => 'La superficie doit être un nombre',
            'superficie.min' => 'La superficie doit être supérieure ou égale à 0',
        ]);

        $cage = Cage::create([
            ...$request->all(),
            'user_id' => auth()->id(),
        ]);

        // Enregistrer dans l'historique
        CageHistory::create([
            'cage_id' => $cage->id,
            'user_id' => auth()->id(),
            'action' => 'creation',
            'description' => "Cage {$cage->numero} créée",
        ]);

        return response()->json($cage, 201);
    }

    // Voir une cage
    public function show(Cage $cage)
    {
        // Vérifier que la cage appartient à l'utilisateur connecté
        if ($cage->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $cage->load(['pigeon', 'couple.male', 'couple.femelle']);

        return response()->json($cage);
    }

    // Modifier une cage
    public function update(Request $request, Cage $cage)
    {
        // Vérifier que la cage appartient à l'utilisateur connecté
        if ($cage->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'numero' => [
                'sometimes',
                'string',
                \Illuminate\Validation\Rule::unique('cages')->where(function ($query) {
                    return $query->where('user_id', auth()->id());
                })->ignore($cage->id)
            ],
            'nom' => 'sometimes|string|max:255',
            'superficie' => 'nullable|numeric|min:0',
        ], [
            'numero.string' => 'Le numéro de cage doit être du texte',
            'numero.unique' => 'Ce numéro de cage est déjà utilisé',
            'nom.string' => 'Le nom de la cage doit être du texte',
            'nom.max' => 'Le nom de la cage ne peut pas dépasser 255 caractères',
            'superficie.numeric' => 'La superficie doit être un nombre',
            'superficie.min' => 'La superficie doit être supérieure ou égale à 0',
        ]);

        $cage->update($request->all());

        return response()->json($cage);
    }

    // Affecter un pigeon ou un couple à une cage
    public function affecter(Request $request, Cage $cage)
    {
        // Vérifier que la cage appartient à l'utilisateur connecté
        if ($cage->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'type' => 'required|in:pigeon,couple',
            'id' => 'required|integer',
        ], [
            'type.required' => 'Le type d\'affectation est requis',
            'type.in' => 'Le type doit être pigeon ou couple',
            'id.required' => 'L\'identifiant est requis',
            'id.integer' => 'L\'identifiant doit être un nombre',
        ]);

        if ($request->type === 'pigeon') {
            if ($cage->statut !== 'libre') {
                return response()->json([
                    'message' => 'Cette cage est déjà occupée. Veuillez d\'abord la libérer.'
                ], 422);
            }

            $pigeon = Pigeon::where('user_id', auth()->id())->findOrFail($request->id);

            // Vérifier que le pigeon est actif
            if ($pigeon->statut !== 'actif') {
                return response()->json([
                    'message' => 'Impossible d\'affecter ce pigeon. Seuls les pigeons actifs peuvent être affectés à une cage.'
                ], 422);
            }

            // Vérifier que le pigeon n'est pas déjà dans une cage seul
            if ($pigeon->cage) {
                return response()->json([
                    'message' => 'Ce pigeon est déjà affecté à une cage. Veuillez d\'abord le retirer de sa cage actuelle.'
                ], 422);
            }

            // Vérifier que le pigeon ne fait pas partie d'un couple actif
            $coupleActif = $pigeon->coupleComeMale()->where('actif', true)->first() 
                        ?? $pigeon->coupleComeFemelle()->where('actif', true)->first();
            
            if ($coupleActif) {
                if ($coupleActif->cage) {
                    return response()->json([
                        'message' => 'Ce pigeon fait partie d\'un couple qui occupe déjà une cage. Veuillez d\'abord libérer la cage du couple.'
                    ], 422);
                } else {
                    return response()->json([
                        'message' => 'Ce pigeon fait partie d\'un couple actif. Pour l\'affecter seul, vous devez d\'abord dissoudre le couple.'
                    ], 422);
                }
            }

            $cage->update([
                'statut' => 'occupe',
                'pigeon_id' => $pigeon->id,
                'couple_id' => null,
            ]);

            // Enregistrer dans l'historique
            CageHistory::create([
                'cage_id' => $cage->id,
                'user_id' => auth()->id(),
                'action' => 'affectation_pigeon',
                'description' => "Pigeon {$pigeon->bague} affecté",
                'metadata' => ['pigeon_id' => $pigeon->id, 'bague' => $pigeon->bague],
            ]);

        } else {
            $couple = Couple::where('user_id', auth()->id())
                ->with(['male.cage', 'femelle.cage'])
                ->findOrFail($request->id);

            if (!$couple->actif) {
                return response()->json([
                    'message' => 'Impossible d\'affecter ce couple. Seuls les couples actifs peuvent être affectés à une cage.'
                ], 422);
            }

            if ($couple->cage) {
                return response()->json([
                    'message' => 'Ce couple est déjà affecté à une cage. Veuillez d\'abord le retirer de sa cage actuelle.'
                ], 422);
            }

            $result = $this->affecterCoupleDansCage($couple, $cage);
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result;
            }

            ['cage' => $cage, 'cages_liberes' => $cagesLiberees, 'fusion' => $fusion] = $result;
            $male = $couple->male;
            $femelle = $couple->femelle;

            $message = 'Couple affecté avec succès';
            if (count($cagesLiberees) > 0) {
                $numeros = implode(', ', $cagesLiberees);
                $message .= ". Cage(s) libérée(s) : {$numeros}. Les deux pigeons sont maintenant dans la cage {$cage->numero}.";
            } elseif ($fusion) {
                $message .= ". Le pigeon déjà présent dans cette cage a été regroupé avec son partenaire.";
            }

            return response()->json([
                'message' => $message,
                'cages_liberes' => $cagesLiberees,
                'cage' => $cage->load(['pigeon', 'couple.male', 'couple.femelle']),
            ]);
        }

        return response()->json([
            'message' => 'Cage affectée avec succès',
            'cage' => $cage->load(['pigeon', 'couple.male', 'couple.femelle']),
        ]);
    }

    /**
     * Regroupe un couple dans une seule cage (libère les cages solo des partenaires si besoin).
     *
     * @return array{cage: Cage, cages_liberes: array, fusion: bool}|\Illuminate\Http\JsonResponse
     */
    private function affecterCoupleDansCage(Couple $couple, Cage $cage)
    {
        $male = $couple->male;
        $femelle = $couple->femelle;
        $cagesLiberees = [];
        $fusion = false;

        if (!$male || !$femelle) {
            return response()->json(['message' => 'Couple incomplet (mâle ou femelle manquant)'], 422);
        }

        $cageMale = $male->cage;
        $cageFemelle = $femelle->cage;

        // Cage occupée par un pigeon qui n'appartient pas au couple
        if ($cage->statut === 'occupe' && $cage->pigeon_id !== $male->id && $cage->pigeon_id !== $femelle->id) {
            return response()->json([
                'message' => 'Cette cage est occupée par un autre pigeon. Choisissez une cage libre ou celle d\'un des deux partenaires.'
            ], 422);
        }

        if ($cage->statut === 'couple' && $cage->couple_id !== $couple->id) {
            return response()->json([
                'message' => 'Cette cage est déjà occupée par un autre couple.'
            ], 422);
        }

        // Fusion sur la cage où un partenaire est déjà seul
        if ($cage->statut === 'occupe' && ($cage->pigeon_id === $male->id || $cage->pigeon_id === $femelle->id)) {
            $fusion = true;
            $autreCage = ($cage->pigeon_id === $male->id) ? $cageFemelle : $cageMale;
            if ($autreCage && $autreCage->id !== $cage->id) {
                $cagesLiberees[] = $this->libererCageSolo($autreCage);
            }
        } else {
            if ($cage->statut !== 'libre') {
                return response()->json([
                    'message' => 'Cette cage n\'est pas disponible pour ce couple.'
                ], 422);
            }

            if ($cageMale && $cageMale->id !== $cage->id) {
                $cagesLiberees[] = $this->libererCageSolo($cageMale);
            }
            if ($cageFemelle && $cageFemelle->id !== $cage->id) {
                $cagesLiberees[] = $this->libererCageSolo($cageFemelle);
            }
        }

        $cage->update([
            'statut' => 'couple',
            'couple_id' => $couple->id,
            'pigeon_id' => null,
        ]);

        CageHistory::create([
            'cage_id' => $cage->id,
            'user_id' => auth()->id(),
            'action' => 'affectation_couple',
            'description' => "Couple {$male->bague} × {$femelle->bague} affecté",
            'metadata' => [
                'couple_id' => $couple->id,
                'male_bague' => $male->bague,
                'femelle_bague' => $femelle->bague,
                'cages_liberes' => $cagesLiberees,
            ],
        ]);

        $cage->refresh();

        return [
            'cage' => $cage,
            'cages_liberes' => array_filter($cagesLiberees),
            'fusion' => $fusion,
        ];
    }

    /**
     * Libère une cage occupée par un seul pigeon. Retourne le numéro de la cage.
     */
    private function libererCageSolo(Cage $cage): string
    {
        $cage->load('pigeon');
        $numero = $cage->numero;
        $bague = $cage->pigeon?->bague ?? '?';

        $cage->update([
            'statut' => 'libre',
            'pigeon_id' => null,
            'couple_id' => null,
        ]);

        CageHistory::create([
            'cage_id' => $cage->id,
            'user_id' => auth()->id(),
            'action' => 'liberation',
            'description' => "Pigeon {$bague} retiré (regroupement du couple)",
            'metadata' => ['pigeon_bague' => $bague, 'motif' => 'regroupement_couple'],
        ]);

        return $numero;
    }

    // Libérer une cage
    public function liberer(Cage $cage)
    {
        // Vérifier que la cage appartient à l'utilisateur connecté
        if ($cage->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Charger les relations avant de libérer
        $cage->load(['pigeon', 'couple.male', 'couple.femelle']);

        // Sauvegarder les infos avant libération pour l'historique
        $oldStatut = $cage->statut;
        $description = 'Cage libérée';
        $metadata = [];

        if ($oldStatut === 'occupe' && $cage->pigeon) {
            $description = "Pigeon {$cage->pigeon->bague} retiré";
            $metadata = ['pigeon_bague' => $cage->pigeon->bague];
        } elseif ($oldStatut === 'couple' && $cage->couple) {
            $male = $cage->couple->male;
            $femelle = $cage->couple->femelle;
            if ($male && $femelle) {
                $description = "Couple {$male->bague} × {$femelle->bague} retiré";
                $metadata = ['male_bague' => $male->bague, 'femelle_bague' => $femelle->bague];
            }
        }

        $cage->update([
            'statut' => 'libre',
            'pigeon_id' => null,
            'couple_id' => null,
        ]);

        // Enregistrer dans l'historique
        CageHistory::create([
            'cage_id' => $cage->id,
            'user_id' => auth()->id(),
            'action' => 'liberation',
            'description' => $description,
            'metadata' => $metadata,
        ]);

        return response()->json(['message' => 'Cage libérée avec succès']);
    }

    // Supprimer une cage
    public function destroy(Cage $cage)
    {
        // Vérifier que la cage appartient à l'utilisateur connecté
        if ($cage->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // On ne peut pas supprimer une cage occupée
        if ($cage->statut !== 'libre') {
            return response()->json([
                'message' => 'Impossible de supprimer une cage occupée. Veuillez d\'abord la libérer.'
            ], 422);
        }

        $cage->delete();

        return response()->json(['message' => 'Cage supprimée avec succès']);
    }

    // Récupérer les pigeons disponibles pour affectation (actifs, sans cage, et sans couple actif)
    public function pigeonsDisponibles()
    {
        $pigeons = Pigeon::where('user_id', auth()->id())
            ->where('statut', 'actif')
            // Exclure les pigeons qui occupent une cage seuls
            ->whereDoesntHave('cage')
            // Exclure les pigeons mâles qui font partie d'un couple actif (avec ou sans cage)
            ->whereDoesntHave('coupleComeMale', function($query) {
                $query->where('actif', true);
            })
            // Exclure les pigeons femelles qui font partie d'un couple actif (avec ou sans cage)
            ->whereDoesntHave('coupleComeFemelle', function($query) {
                $query->where('actif', true);
            })
            ->orderBy('bague')
            ->get(['id', 'bague', 'race', 'sexe']);

        return response()->json($pigeons);
    }

    // Couples actifs sans cage « couple » (les partenaires peuvent être dans des cages séparées — regroupement à l'affectation)
    public function couplesDisponibles()
    {
        $couples = Couple::where('user_id', auth()->id())
            ->where('actif', true)
            ->whereDoesntHave('cage')
            ->with(['male:id,bague', 'femelle:id,bague', 'male.cage:id,numero', 'femelle.cage:id,numero'])
            ->orderBy('id')
            ->get();

        return response()->json($couples);
    }
}
