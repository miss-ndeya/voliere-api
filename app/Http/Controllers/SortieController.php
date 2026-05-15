<?php

namespace App\Http\Controllers;

use App\Models\Sortie;
use App\Models\Pigeon;
use Illuminate\Http\Request;

class SortieController extends Controller
{
    // Liste toutes les sorties
    public function index()
    {
        $sorties = Sortie::where('user_id', auth()->id())
            ->with('pigeon')
            ->get();

        return response()->json($sorties);
    }

    // Enregistrer une sortie
    public function store(Request $request)
    {
        // Validation de base
        $rules = [
            'pigeon_id' => 'required|exists:pigeons,id',
            'type' => 'required|in:vente,deces,perte',
            'date_sortie' => 'required|date|before_or_equal:today',
        ];

        // Validation conditionnelle selon le type
        if ($request->type === 'vente') {
            $rules['prix'] = 'required|numeric|min:0';
            $rules['acheteur'] = 'required|string|max:255';
        } elseif ($request->type === 'deces') {
            $rules['cause'] = 'required|string|max:500';
        } elseif ($request->type === 'perte') {
            $rules['circonstance'] = 'required|string|max:500';
        }

        $messages = [
            'pigeon_id.required' => 'Le pigeon est requis',
            'pigeon_id.exists' => 'Le pigeon sélectionné n\'existe pas',
            'type.required' => 'Le type de sortie est requis',
            'type.in' => 'Le type de sortie doit être vente, décès ou perte',
            'date_sortie.required' => 'La date de sortie est requise',
            'date_sortie.date' => 'La date de sortie doit être une date valide',
            'date_sortie.before_or_equal' => 'La date de sortie ne peut pas être dans le futur',
            'prix.required' => 'Le prix est requis pour une vente',
            'prix.numeric' => 'Le prix doit être un nombre',
            'prix.min' => 'Le prix doit être supérieur ou égal à 0',
            'acheteur.required' => 'Le nom de l\'acheteur est requis pour une vente',
            'acheteur.string' => 'Le nom de l\'acheteur doit être du texte',
            'acheteur.max' => 'Le nom de l\'acheteur ne peut pas dépasser 255 caractères',
            'cause.required' => 'La cause du décès est requise',
            'cause.string' => 'La cause doit être du texte',
            'cause.max' => 'La cause ne peut pas dépasser 500 caractères',
            'circonstance.required' => 'La circonstance de la perte est requise',
            'circonstance.string' => 'La circonstance doit être du texte',
            'circonstance.max' => 'La circonstance ne peut pas dépasser 500 caractères',
        ];

        $request->validate($rules, $messages);

        // Vérifier que le pigeon appartient à l'utilisateur connecté
        $pigeon = Pigeon::where('user_id', auth()->id())->find($request->pigeon_id);
        if (!$pigeon) {
            return response()->json([
                'message' => 'Ce pigeon n\'appartient pas à cet utilisateur'
            ], 403);
        }

        // Vérifier que le pigeon est encore actif
        if ($pigeon->statut !== 'actif') {
            return response()->json([
                'message' => 'Ce pigeon n\'est plus actif. Seuls les pigeons actifs peuvent être enregistrés en sortie.'
            ], 422);
        }

        // Vérifier que le pigeon n'a pas déjà une sortie enregistrée
        $sortieExistante = Sortie::where('pigeon_id', $request->pigeon_id)->first();
        if ($sortieExistante) {
            return response()->json([
                'message' => 'Ce pigeon a déjà une sortie enregistrée. Un pigeon ne peut avoir qu\'une seule sortie.'
            ], 422);
        }

        // Créer la sortie
        $sortie = Sortie::create([
            ...$request->all(),
            'user_id' => auth()->id(),
        ]);

        // Mettre à jour le statut du pigeon
        $pigeon->update(['statut' => $request->type === 'vente' ? 'vendu' : ($request->type === 'deces' ? 'mort' : 'perdu')]);

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

        return response()->json($sortie->load('pigeon'), 201);
    }

    // Voir une sortie
    public function show($id)
    {
        // Récupérer la sortie manuellement
        $sortie = Sortie::where('user_id', auth()->id())->findOrFail($id);
        $sortie->load('pigeon');

        return response()->json($sortie);
    }

    // Modifier une sortie
    public function update(Request $request, $id)
    {
        // Récupérer la sortie manuellement au lieu d'utiliser le route model binding
        $sortie = Sortie::where('user_id', auth()->id())->findOrFail($id);

        // Validation de base
        $rules = [
            'type' => 'sometimes|in:vente,deces,perte',
            'date_sortie' => 'sometimes|date|before_or_equal:today',
        ];

        // Validation conditionnelle selon le type
        $type = $request->has('type') ? $request->type : $sortie->type;
        
        if ($type === 'vente') {
            $rules['prix'] = 'required|numeric|min:0';
            $rules['acheteur'] = 'required|string|max:255';
        } elseif ($type === 'deces') {
            $rules['cause'] = 'required|string|max:500';
        } elseif ($type === 'perte') {
            $rules['circonstance'] = 'required|string|max:500';
        }

        $messages = [
            'type.in' => 'Le type de sortie doit être vente, décès ou perte',
            'date_sortie.date' => 'La date de sortie doit être une date valide',
            'date_sortie.before_or_equal' => 'La date de sortie ne peut pas être dans le futur',
            'prix.required' => 'Le prix est requis pour une vente',
            'prix.numeric' => 'Le prix doit être un nombre',
            'prix.min' => 'Le prix doit être supérieur ou égal à 0',
            'acheteur.required' => 'Le nom de l\'acheteur est requis pour une vente',
            'acheteur.string' => 'Le nom de l\'acheteur doit être du texte',
            'acheteur.max' => 'Le nom de l\'acheteur ne peut pas dépasser 255 caractères',
            'cause.required' => 'La cause du décès est requise',
            'cause.string' => 'La cause doit être du texte',
            'cause.max' => 'La cause ne peut pas dépasser 500 caractères',
            'circonstance.required' => 'La circonstance de la perte est requise',
            'circonstance.string' => 'La circonstance doit être du texte',
            'circonstance.max' => 'La circonstance ne peut pas dépasser 500 caractères',
        ];

        $request->validate($rules, $messages);

        // Ne pas permettre la modification du pigeon_id
        $sortie->update($request->except('pigeon_id'));

        return response()->json($sortie->load('pigeon'));
    }

    // Supprimer une sortie
    public function destroy($id)
    {
        // Récupérer la sortie manuellement
        $sortie = Sortie::where('user_id', auth()->id())->findOrFail($id);
        $sortie->delete();

        return response()->json([
            'message' => 'Sortie supprimée'
        ]);
    }
}