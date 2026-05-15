<?php

namespace App\Http\Controllers;

use App\Models\Reproduction;
use App\Models\Pigeon;
use App\Models\Couple;
use Illuminate\Http\Request;

class ReproductionController extends Controller
{
    // Liste toutes les reproductions
    public function index()
    {
        $reproductions = Reproduction::where('user_id', auth()->id())
            ->with(['couple.male', 'couple.femelle'])
            ->get();

        // Ajouter le nombre de pigeonneaux pour chaque reproduction
        $reproductions->each(function ($reproduction) {
            $couple = $reproduction->couple;
            if ($couple && $reproduction->date_eclosion) {
                $pigeonneauxCount = Pigeon::where('pere_id', $couple->male_id)
                    ->where('mere_id', $couple->femelle_id)
                    ->where('date_naissance', $reproduction->date_eclosion)
                    ->count();
                $reproduction->pigeonneaux_count = $pigeonneauxCount;
            } else {
                $reproduction->pigeonneaux_count = 0;
            }
        });

        return response()->json($reproductions);
    }

    // Enregistrer une reproduction
    public function store(Request $request)
    {
        $request->validate([
            'couple_id' => 'required|exists:couples,id',
            'date_ponte' => 'required|date|before_or_equal:today',
            'date_eclosion' => 'nullable|date|after:date_ponte',
            'nb_jeunes' => 'required|integer|min:0|max:4',
        ], [
            'couple_id.required' => 'Le couple est requis',
            'couple_id.exists' => 'Le couple sélectionné n\'existe pas',
            'date_ponte.required' => 'La date de ponte est requise',
            'date_ponte.date' => 'La date de ponte doit être une date valide',
            'date_ponte.before_or_equal' => 'La date de ponte ne peut pas être dans le futur',
            'date_eclosion.date' => 'La date d\'éclosion doit être une date valide',
            'date_eclosion.after' => 'La date d\'éclosion doit être au moins 17 jours après la date de ponte',
            'nb_jeunes.required' => 'Le nombre de jeunes est requis',
            'nb_jeunes.integer' => 'Le nombre de jeunes doit être un nombre entier',
            'nb_jeunes.min' => 'Le nombre de jeunes doit être au minimum 0',
            'nb_jeunes.max' => 'Le nombre de jeunes ne peut pas dépasser 4 (biologiquement réaliste)',
        ]);

        // Vérifier que le couple appartient à l'utilisateur connecté
        $couple = Couple::where('user_id', auth()->id())->find($request->couple_id);
        if (!$couple) {
            return response()->json([
                'message' => 'Ce couple n\'appartient pas à cet utilisateur'
            ], 403);
        }

        // Vérifier que le couple est actif
        if (!$couple->actif) {
            return response()->json([
                'message' => 'Le couple doit être actif pour enregistrer une reproduction'
            ], 422);
        }

        // Vérifier que la date de ponte est après la date de formation du couple
        if ($request->date_ponte < $couple->date_formation) {
            return response()->json([
                'message' => 'La date de ponte ne peut pas être antérieure à la date de formation du couple (' . date('d/m/Y', strtotime($couple->date_formation)) . ')'
            ], 422);
        }

        // Vérifier que la date d'éclosion est au moins 17 jours après la date de ponte
        if ($request->date_eclosion) {
            $datePonte = new \DateTime($request->date_ponte);
            $dateEclosion = new \DateTime($request->date_eclosion);
            $diff = $datePonte->diff($dateEclosion)->days;
            
            if ($diff < 17) {
                return response()->json([
                    'message' => 'La date d\'éclosion doit être au minimum 17 jours après la date de ponte (période d\'incubation)'
                ], 422);
            }
        }

        $reproduction = Reproduction::create([
            ...$request->all(),
            'user_id' => auth()->id(),
        ]);

        return response()->json($reproduction->load(['couple.male', 'couple.femelle']), 201);
    }

    // Voir une reproduction
    public function show(Reproduction $reproduction)
    {
        // Vérifier que la reproduction appartient à l'utilisateur connecté
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $reproduction->load(['couple.male', 'couple.femelle']);

        return response()->json($reproduction);
    }

    // Modifier une reproduction
    public function update(Request $request, Reproduction $reproduction)
    {
        // Vérifier que la reproduction appartient à l'utilisateur connecté
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'date_ponte' => 'sometimes|date|before_or_equal:today',
            'date_eclosion' => 'nullable|date',
            'nb_jeunes' => 'sometimes|integer|min:0|max:4',
        ], [
            'date_ponte.date' => 'La date de ponte doit être une date valide',
            'date_ponte.before_or_equal' => 'La date de ponte ne peut pas être dans le futur',
            'date_eclosion.date' => 'La date d\'éclosion doit être une date valide',
            'nb_jeunes.integer' => 'Le nombre de jeunes doit être un nombre entier',
            'nb_jeunes.min' => 'Le nombre de jeunes doit être au minimum 0',
            'nb_jeunes.max' => 'Le nombre de jeunes ne peut pas dépasser 4 (biologiquement réaliste)',
        ]);

        // Si on modifie la date de ponte, vérifier qu'elle est après la date de formation du couple
        if ($request->has('date_ponte')) {
            $couple = $reproduction->couple;
            if ($request->date_ponte < $couple->date_formation) {
                return response()->json([
                    'message' => 'La date de ponte ne peut pas être antérieure à la date de formation du couple (' . date('d/m/Y', strtotime($couple->date_formation)) . ')'
                ], 422);
            }
        }

        // Vérifier que la date d'éclosion est au moins 17 jours après la date de ponte
        $datePonte = $request->has('date_ponte') ? $request->date_ponte : $reproduction->date_ponte;
        $dateEclosion = $request->has('date_eclosion') ? $request->date_eclosion : $reproduction->date_eclosion;

        if ($dateEclosion && $datePonte) {
            $ponteDt = new \DateTime($datePonte);
            $eclosionDt = new \DateTime($dateEclosion);
            $diff = $ponteDt->diff($eclosionDt)->days;
            
            if ($diff < 17) {
                return response()->json([
                    'message' => 'La date d\'éclosion doit être au minimum 17 jours après la date de ponte (période d\'incubation)'
                ], 422);
            }
        }

        $reproduction->update($request->all());

        return response()->json($reproduction->load(['couple.male', 'couple.femelle']));
    }

    // Supprimer une reproduction
    public function destroy(Reproduction $reproduction)
    {
        // Vérifier que la reproduction appartient à l'utilisateur connecté
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $reproduction->delete();

        return response()->json([
            'message' => 'Reproduction supprimée'
        ]);
    }

    // Créer les pigeonneaux nés d'une reproduction
    public function creerPigeonneaux(Request $request, Reproduction $reproduction)
    {
        // Vérifier que la reproduction appartient à l'utilisateur connecté
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Vérifier que la date d'éclosion est renseignée
        if (!$reproduction->date_eclosion) {
            return response()->json([
                'message' => 'Impossible de créer des pigeonneaux sans date d\'éclosion. Veuillez d\'abord renseigner la date d\'éclosion.'
            ], 422);
        }

        // Valider chaque bague individuellement avec la règle unique par utilisateur
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'pigeonneaux' => 'required|array|min:1',
            'pigeonneaux.*.sexe' => 'required|in:male,femelle',
        ], [
            'pigeonneaux.required' => 'Au moins un pigeonneau est requis',
            'pigeonneaux.array' => 'Les pigeonneaux doivent être un tableau',
            'pigeonneaux.min' => 'Au moins un pigeonneau est requis',
            'pigeonneaux.*.sexe.required' => 'Le sexe est requis pour chaque pigeonneau',
            'pigeonneaux.*.sexe.in' => 'Le sexe doit être "male" ou "femelle"',
        ]);

        // Valider les bagues manuellement
        foreach ($request->pigeonneaux as $index => $pigeonneau) {
            if (empty($pigeonneau['bague'])) {
                $validator->errors()->add("pigeonneaux.{$index}.bague", 'Le numéro de bague est requis pour chaque pigeonneau');
            } else {
                // Vérifier l'unicité par utilisateur
                $exists = Pigeon::where('bague', $pigeonneau['bague'])
                    ->where('user_id', auth()->id())
                    ->exists();
                
                if ($exists) {
                    $validator->errors()->add("pigeonneaux.{$index}.bague", 'Ce numéro de bague est déjà utilisé');
                }
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Compter les pigeonneaux déjà créés pour cette reproduction
        $couple = $reproduction->couple;
        $pigeonneauxExistants = Pigeon::where('pere_id', $couple->male_id)
            ->where('mere_id', $couple->femelle_id)
            ->where('date_naissance', $reproduction->date_eclosion)
            ->count();

        // Vérifier qu'on ne dépasse pas le nombre de jeunes déclaré
        $nouveauxPigeonneaux = count($request->pigeonneaux);
        $totalPigeonneaux = $pigeonneauxExistants + $nouveauxPigeonneaux;

        if ($totalPigeonneaux > $reproduction->nb_jeunes) {
            return response()->json([
                'message' => "Impossible de créer {$nouveauxPigeonneaux} pigeonneau(x). Le nombre total de pigeonneaux ({$totalPigeonneaux}) dépasserait le nombre de jeunes déclaré ({$reproduction->nb_jeunes}). Il reste " . ($reproduction->nb_jeunes - $pigeonneauxExistants) . " place(s) disponible(s)."
            ], 422);
        }

        $pigeonneauxCrees = [];

        foreach ($request->pigeonneaux as $data) {
            $pigeonneau = Pigeon::create([
                'bague' => $data['bague'],
                'sexe' => $data['sexe'],
                'race' => $data['race'] ?? $couple->male->race, // même race que les parents par défaut
                'date_naissance' => $reproduction->date_eclosion,
                'statut' => 'actif',
                'pere_id' => $couple->male_id,
                'mere_id' => $couple->femelle_id,
                'user_id' => auth()->id(),
            ]);

            $pigeonneauxCrees[] = $pigeonneau;
        }

        return response()->json([
            'message' => count($pigeonneauxCrees) . ' pigeonneau(x) créé(s) avec succès',
            'pigeonneaux' => $pigeonneauxCrees
        ], 201);
    }
}