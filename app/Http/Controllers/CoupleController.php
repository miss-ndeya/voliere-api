<?php

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\Pigeon;
use Illuminate\Http\Request;

class CoupleController extends Controller
{
    // Liste tous les couples (actifs et rompus)
    public function index()
    {
        $couples = Couple::where('user_id', auth()->id())
            ->with(['male', 'femelle'])
            ->orderBy('actif', 'desc') // Actifs en premier
            ->orderBy('date_formation', 'desc')
            ->get();

        return response()->json($couples);
    }

    // Créer un couple
    public function store(Request $request)
    {
        $request->validate([
            'male_id' => 'required|exists:pigeons,id',
            'femelle_id' => 'required|exists:pigeons,id',
            'date_formation' => 'required|date|before_or_equal:today',
        ], [
            'male_id.required' => 'Le mâle est requis',
            'male_id.exists' => 'Le mâle sélectionné n\'existe pas',
            'femelle_id.required' => 'La femelle est requise',
            'femelle_id.exists' => 'La femelle sélectionnée n\'existe pas',
            'date_formation.required' => 'La date de formation est requise',
            'date_formation.date' => 'La date de formation doit être une date valide',
            'date_formation.before_or_equal' => 'La date de formation ne peut pas être dans le futur',
        ]);

        // Vérifier que les pigeons appartiennent à l'utilisateur connecté
        $male = Pigeon::where('user_id', auth()->id())->find($request->male_id);
        $femelle = Pigeon::where('user_id', auth()->id())->find($request->femelle_id);

        if (!$male || !$femelle) {
            return response()->json([
                'message' => 'Un ou plusieurs pigeons n\'appartiennent pas à cet utilisateur'
            ], 403);
        }

        // Vérifier que le mâle est bien un mâle
        if ($male->sexe !== 'male') {
            return response()->json([
                'message' => 'Le pigeon sélectionné comme mâle n\'est pas un mâle'
            ], 422);
        }

        // Vérifier que la femelle est bien une femelle
        if ($femelle->sexe !== 'femelle') {
            return response()->json([
                'message' => 'Le pigeon sélectionné comme femelle n\'est pas une femelle'
            ], 422);
        }

        // Vérifier que le mâle est actif
        if ($male->statut !== 'actif') {
            return response()->json([
                'message' => 'Le mâle sélectionné n\'est pas actif (statut: ' . $male->statut . ')'
            ], 422);
        }

        // Vérifier que la femelle est active
        if ($femelle->statut !== 'actif') {
            return response()->json([
                'message' => 'La femelle sélectionnée n\'est pas active (statut: ' . $femelle->statut . ')'
            ], 422);
        }

        // Vérifier que le mâle n'est pas déjà dans un couple actif
        $maleDejaEnCouple = Couple::where('male_id', $request->male_id)
            ->where('actif', true)
            ->exists();
        if ($maleDejaEnCouple) {
            return response()->json([
                'message' => 'Ce mâle est déjà dans un couple actif'
            ], 422);
        }

        // Vérifier que la femelle n'est pas déjà dans un couple actif
        $femelleDejaEnCouple = Couple::where('femelle_id', $request->femelle_id)
            ->where('actif', true)
            ->exists();
        if ($femelleDejaEnCouple) {
            return response()->json([
                'message' => 'Cette femelle est déjà dans un couple actif'
            ], 422);
        }

        $couple = Couple::create([
            ...$request->all(),
            'user_id' => auth()->id(),
        ]);

        return response()->json($couple->load(['male', 'femelle']), 201);
    }

    // Voir un couple
    public function show(Couple $couple)
    {
        // Vérifier que le couple appartient à l'utilisateur connecté
        if ($couple->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $couple->load(['male', 'femelle', 'reproductions', 'cage']);

        return response()->json($couple);
    }

    // Historique d'un couple
    public function history(Couple $couple)
    {
        // Vérifier que le couple appartient à l'utilisateur connecté
        if ($couple->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Charger les relations
        $couple->load(['male', 'femelle', 'cage']);

        // Récupérer les reproductions avec les pigeonneaux
        $reproductions = $couple->reproductions()
            ->orderBy('date_ponte', 'desc')
            ->get()
            ->map(function ($reproduction) {
                return [
                    'id' => $reproduction->id,
                    'date_ponte' => $reproduction->date_ponte,
                    'date_eclosion' => $reproduction->date_eclosion,
                    'nb_jeunes' => $reproduction->nb_jeunes,
                    'pigeonneaux' => $reproduction->pigeonneaux, // Utilise l'accessor
                    'created_at' => $reproduction->created_at->toIso8601String(),
                ];
            });

        // Calculer le nombre total de pigeonneaux
        $totalPigeonneaux = 0;
        foreach ($couple->reproductions as $reproduction) {
            $totalPigeonneaux += $reproduction->pigeonneaux->count();
        }

        return response()->json([
            'couple' => [
                'id' => $couple->id,
                'male' => $couple->male,
                'femelle' => $couple->femelle,
                'date_formation' => $couple->date_formation,
                'actif' => $couple->actif,
                'cage' => $couple->cage,
            ],
            'reproductions' => $reproductions,
            'stats' => [
                'total_reproductions' => $reproductions->count(),
                'total_jeunes' => $reproductions->sum('nb_jeunes'),
                'total_pigeonneaux' => $totalPigeonneaux,
            ],
        ]);
    }

    // Modifier un couple
    public function update(Request $request, Couple $couple)
    {
        // Vérifier que le couple appartient à l'utilisateur connecté
        if ($couple->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Empêcher la réactivation d'un couple rompu
        if (!$couple->actif && $request->has('actif') && $request->actif) {
            return response()->json([
                'message' => 'Un couple rompu ne peut pas être réactivé. Veuillez créer un nouveau couple.'
            ], 422);
        }

        // Vérifier si le couple a des reproductions (descendants)
        $hasReproductions = $couple->reproductions()->exists();

        // Si le couple a des descendants, seule la date de formation peut être modifiée
        if ($hasReproductions) {
            if ($request->has('male_id') || $request->has('femelle_id')) {
                return response()->json([
                    'message' => 'Les pigeons d\'un couple avec descendants ne peuvent pas être modifiés. Seule la date de formation peut être changée.'
                ], 422);
            }

            // Valider la date de formation si elle est modifiée
            if ($request->has('date_formation')) {
                $request->validate([
                    'date_formation' => 'date|before_or_equal:today',
                ], [
                    'date_formation.date' => 'La date de formation doit être une date valide',
                    'date_formation.before_or_equal' => 'La date de formation ne peut pas être dans le futur',
                ]);
            }

            $couple->update($request->only('date_formation'));
            return response()->json($couple->load(['male', 'femelle']));
        }

        // Si le couple n'a pas de descendants, on peut modifier les pigeons
        if ($request->has('male_id') || $request->has('femelle_id')) {
            $request->validate([
                'male_id' => 'sometimes|exists:pigeons,id',
                'femelle_id' => 'sometimes|exists:pigeons,id',
                'date_formation' => 'sometimes|date|before_or_equal:today',
            ], [
                'male_id.exists' => 'Le mâle sélectionné n\'existe pas',
                'femelle_id.exists' => 'La femelle sélectionnée n\'existe pas',
                'date_formation.date' => 'La date de formation doit être une date valide',
                'date_formation.before_or_equal' => 'La date de formation ne peut pas être dans le futur',
            ]);

            // Vérifier que les pigeons appartiennent à l'utilisateur
            if ($request->has('male_id')) {
                $male = Pigeon::where('user_id', auth()->id())->find($request->male_id);
                if (!$male) {
                    return response()->json(['message' => 'Le mâle n\'appartient pas à cet utilisateur'], 403);
                }
                if ($male->sexe !== 'male') {
                    return response()->json(['message' => 'Le pigeon sélectionné comme mâle n\'est pas un mâle'], 422);
                }
                if ($male->statut !== 'actif') {
                    return response()->json(['message' => 'Le mâle sélectionné n\'est pas actif'], 422);
                }
                // Vérifier qu'il n'est pas déjà en couple (sauf s'il est déjà dans ce couple)
                $maleDejaEnCouple = Couple::where('male_id', $request->male_id)
                    ->where('actif', true)
                    ->where('id', '!=', $couple->id)
                    ->exists();
                if ($maleDejaEnCouple) {
                    return response()->json(['message' => 'Ce mâle est déjà dans un autre couple actif'], 422);
                }
            }

            if ($request->has('femelle_id')) {
                $femelle = Pigeon::where('user_id', auth()->id())->find($request->femelle_id);
                if (!$femelle) {
                    return response()->json(['message' => 'La femelle n\'appartient pas à cet utilisateur'], 403);
                }
                if ($femelle->sexe !== 'femelle') {
                    return response()->json(['message' => 'Le pigeon sélectionné comme femelle n\'est pas une femelle'], 422);
                }
                if ($femelle->statut !== 'actif') {
                    return response()->json(['message' => 'La femelle sélectionnée n\'est pas active'], 422);
                }
                // Vérifier qu'elle n'est pas déjà en couple (sauf si elle est déjà dans ce couple)
                $femelleDejaEnCouple = Couple::where('femelle_id', $request->femelle_id)
                    ->where('actif', true)
                    ->where('id', '!=', $couple->id)
                    ->exists();
                if ($femelleDejaEnCouple) {
                    return response()->json(['message' => 'Cette femelle est déjà dans un autre couple actif'], 422);
                }
            }
        }

        // Valider la date de formation si elle est modifiée
        if ($request->has('date_formation')) {
            $request->validate([
                'date_formation' => 'date|before_or_equal:today',
            ], [
                'date_formation.date' => 'La date de formation doit être une date valide',
                'date_formation.before_or_equal' => 'La date de formation ne peut pas être dans le futur',
            ]);
        }

        // Mettre à jour le couple
        $couple->update($request->only(['male_id', 'femelle_id', 'date_formation']));

        return response()->json($couple->load(['male', 'femelle']));
    }

    // Rompre un couple
    public function rompre(Couple $couple)
    {
        // Vérifier que le couple appartient à l'utilisateur connecté
        if ($couple->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $couple->update(['actif' => false]);

        // Libérer la cage si le couple en occupait une
        if ($couple->cage) {
            $couple->cage->update([
                'statut' => 'libre',
                'couple_id' => null,
            ]);
        }

        return response()->json([
            'message' => 'Couple rompu avec succès'
        ]);
    }

    // Supprimer un couple
    public function destroy(Couple $couple)
    {
        // Vérifier que le couple appartient à l'utilisateur connecté
        if ($couple->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $couple->delete();

        return response()->json([
            'message' => 'Couple supprimé'
        ]);
    }
}