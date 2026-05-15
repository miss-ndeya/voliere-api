<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use Illuminate\Http\Request;

class PigeonController extends Controller
{
    // Liste tous les pigeons (sauf ceux soft deleted)
    public function index()
    {
        $pigeons = Pigeon::where('user_id', auth()->id())
            ->with(['pere', 'mere'])
            ->get();

        return response()->json($pigeons);
    }

    // Ajouter un pigeon
    public function store(Request $request)
    {
        $request->validate([
            'bague' => 'required|string|unique:pigeons',
            'sexe' => 'required|in:male,femelle',
            'race' => 'required|string',
            'date_naissance' => 'nullable|date|before_or_equal:today',
            'pere_id' => 'nullable|exists:pigeons,id',
            'mere_id' => 'nullable|exists:pigeons,id',
        ], [
            'bague.required' => 'Le numéro de bague est requis',
            'bague.unique' => 'Ce numéro de bague est déjà utilisé',
            'sexe.required' => 'Le sexe est requis',
            'sexe.in' => 'Le sexe doit être "male" ou "femelle"',
            'race.required' => 'La race est requise',
            'date_naissance.date' => 'La date de naissance doit être une date valide',
            'date_naissance.before_or_equal' => 'La date de naissance ne peut pas être dans le futur',
            'pere_id.exists' => 'Le père sélectionné n\'existe pas',
            'mere_id.exists' => 'La mère sélectionnée n\'existe pas',
        ]);

        $pigeon = Pigeon::create([
            ...$request->all(),
            'user_id' => auth()->id(),
        ]);

        return response()->json($pigeon, 201);
    }

    // Voir un pigeon
    public function show(Pigeon $pigeon)
    {
        // Vérifier que le pigeon appartient à l'utilisateur connecté
        if ($pigeon->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $pigeon->load(['pere', 'mere', 'enfantsComePere', 'enfantsComeMere', 'cage', 'sortie']);

        return response()->json($pigeon);
    }

    // Historique complet d'un pigeon
    public function history(Pigeon $pigeon)
    {
        // Vérifier que le pigeon appartient à l'utilisateur connecté
        if ($pigeon->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Charger toutes les relations nécessaires
        $pigeon->load([
            'pere',
            'mere',
            'enfantsComePere.mere',
            'enfantsComeMere.pere',
            'coupleComeMale.femelle',
            'coupleComeFemelle.male',
            'sortie'
        ]);

        // Récupérer les reproductions où ce pigeon est parent
        $reproductions = \App\Models\Reproduction::where(function($query) use ($pigeon) {
            $query->whereHas('couple', function($q) use ($pigeon) {
                $q->where('male_id', $pigeon->id)
                  ->orWhere('femelle_id', $pigeon->id);
            });
        })
        ->with(['couple.male', 'couple.femelle', 'pigeonneaux'])
        ->orderBy('date_ponte', 'desc')
        ->get();

        // Récupérer l'historique des cages via les métadonnées
        // On cherche dans les actions d'affectation de pigeon
        $cageHistory = \App\Models\CageHistory::where('action', 'affectation_pigeon')
            ->whereRaw("JSON_EXTRACT(metadata, '$.pigeon_id') = ?", [$pigeon->id])
            ->with('cage')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pigeon' => $pigeon,
            'reproductions' => $reproductions,
            'cage_history' => $cageHistory,
            'enfants' => [
                'comme_pere' => $pigeon->enfantsComePere,
                'comme_mere' => $pigeon->enfantsComeMere
            ]
        ]);
    }

    // Modifier un pigeon
    public function update(Request $request, Pigeon $pigeon)
    {
        // Vérifier que le pigeon appartient à l'utilisateur connecté
        if ($pigeon->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'bague' => 'sometimes|string|unique:pigeons,bague,' . $pigeon->id,
            'sexe' => 'sometimes|in:male,femelle',
            'race' => 'sometimes|string',
            'date_naissance' => 'nullable|date|before_or_equal:today',
            'pere_id' => 'nullable|exists:pigeons,id',
            'mere_id' => 'nullable|exists:pigeons,id',
        ], [
            'bague.unique' => 'Ce numéro de bague est déjà utilisé',
            'sexe.in' => 'Le sexe doit être "male" ou "femelle"',
            'date_naissance.date' => 'La date de naissance doit être une date valide',
            'date_naissance.before_or_equal' => 'La date de naissance ne peut pas être dans le futur',
            'pere_id.exists' => 'Le père sélectionné n\'existe pas',
            'mere_id.exists' => 'La mère sélectionnée n\'existe pas',
        ]);

        // Vérifier si le pigeon a une sortie (vendu, mort, perdu)
        if ($pigeon->sortie) {
            // Si on essaie de modifier le statut d'un pigeon sorti
            if ($request->has('statut') && $request->statut === 'actif') {
                return response()->json([
                    'message' => 'Impossible de remettre un pigeon sorti (vendu/mort/perdu) à l\'état actif. Le statut est géré automatiquement par les sorties.'
                ], 422);
            }
            
            // Empêcher toute modification du statut pour un pigeon sorti
            if ($request->has('statut') && $request->statut !== $pigeon->statut) {
                return response()->json([
                    'message' => 'Le statut d\'un pigeon sorti ne peut pas être modifié manuellement.'
                ], 422);
            }
        }

        $pigeon->update($request->except('statut')); // Ne jamais permettre la modification manuelle du statut

        return response()->json($pigeon);
    }

    // Supprimer logiquement un pigeon (soft delete)
    public function destroy(Pigeon $pigeon)
    {
        // Vérifier que le pigeon appartient à l'utilisateur connecté
        if ($pigeon->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifier si le pigeon a des descendants
        $aDesDescendantsComePere = $pigeon->enfantsComePere()->exists();
        $aDesDescendantsComeMere = $pigeon->enfantsComeMere()->exists();

        if ($aDesDescendantsComePere || $aDesDescendantsComeMere) {
            return response()->json([
                'message' => 'Impossible de supprimer un pigeon qui a des descendants. Seul le changement de statut est autorisé.'
            ], 422);
        }

        // Libérer la cage si le pigeon en occupait une
        if ($pigeon->cage) {
            $pigeon->cage->update([
                'statut' => 'libre',
                'pigeon_id' => null,
            ]);
        }

        // Rompre le couple si le pigeon était en couple
        if ($pigeon->coupleComeMale) {
            $pigeon->coupleComeMale->update(['actif' => false]);
            if ($pigeon->coupleComeMale->cage) {
                $pigeon->coupleComeMale->cage->update([
                    'statut' => 'libre',
                    'couple_id' => null,
                ]);
            }
        }

        if ($pigeon->coupleComeFemelle) {
            $pigeon->coupleComeFemelle->update(['actif' => false]);
            if ($pigeon->coupleComeFemelle->cage) {
                $pigeon->coupleComeFemelle->cage->update([
                    'statut' => 'libre',
                    'couple_id' => null,
                ]);
            }
        }

        $pigeon->delete(); // Utilise softDeletes de Laravel

        return response()->json([
            'message' => 'Pigeon supprimé avec succès'
        ]);
    }

    public function tous()
    {
        $pigeons = Pigeon::where('user_id', auth()->id())
            ->with(['pere', 'mere'])
            ->get();
        return response()->json($pigeons);
    }

    public function disponibles()
    {
        // Récupérer les IDs des pigeons déjà en couple actif
        $pigeonsEnCouple = \App\Models\Couple::where('actif', true)
            ->where('user_id', auth()->id())
            ->get()
            ->flatMap(function ($couple) {
                return [$couple->male_id, $couple->femelle_id];
            })
            ->unique()
            ->values()
            ->toArray();

        // Récupérer les pigeons actifs et pas en couple
        $pigeons = Pigeon::where('user_id', auth()->id())
            ->where('statut', 'actif')
            ->whereNotIn('id', $pigeonsEnCouple)
            ->with(['pere', 'mere'])
            ->orderBy('sexe')
            ->orderBy('bague')
            ->get();

        return response()->json($pigeons);
    }

    public function softDeleted()
    {
        $pigeons = Pigeon::where('user_id', auth()->id())
            ->with(['pere', 'mere'])
            ->onlyTrashed()
            ->get();
        return response()->json($pigeons);
    }

    public function restore($id)
    {
        $pigeon = Pigeon::where('user_id', auth()->id())
            ->withTrashed()
            ->find($id);
        if ($pigeon) {
            $pigeon->restore();
            return response()->json(['message' => 'Pigeon restauré avec succès']);
        }
        return response()->json(['message' => 'Pigeon non trouvé'], 404);
    }
}