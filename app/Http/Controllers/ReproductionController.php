<?php

namespace App\Http\Controllers;

use App\Models\Reproduction;
use App\Models\Pigeon;
use App\Models\Couple;
use App\Services\ReproductionWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReproductionController extends Controller
{
    public function index()
    {
        $reproductions = Reproduction::where('user_id', auth()->id())
            ->with(['couple.male', 'couple.femelle'])
            ->orderByDesc('date_ponte')
            ->get()
            ->map(fn ($reproduction) => ReproductionWorkflowService::enrich($reproduction));

        return response()->json($reproductions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'couple_id' => 'required|exists:couples,id',
            'date_ponte' => 'required|date|before_or_equal:today',
            'date_eclosion' => 'nullable|date|after:date_ponte',
            'nb_jeunes' => 'required|integer|min:0|max:2',
        ], [
            'couple_id.required' => 'Le couple est requis',
            'couple_id.exists' => 'Le couple sélectionné n\'existe pas',
            'date_ponte.required' => 'La date de ponte est requise',
            'date_ponte.date' => 'La date de ponte doit être une date valide',
            'date_ponte.before_or_equal' => 'La date de ponte ne peut pas être dans le futur',
            'date_eclosion.date' => 'La date d\'éclosion doit être une date valide',
            'date_eclosion.after' => 'La date d\'éclosion doit être postérieure à la date de ponte',
            'nb_jeunes.required' => 'Le nombre de jeunes est requis',
            'nb_jeunes.integer' => 'Le nombre de jeunes doit être un nombre entier',
            'nb_jeunes.min' => 'Le nombre de jeunes doit être au minimum 0',
            'nb_jeunes.max' => 'Le nombre de jeunes ne peut pas dépasser 2 par reproduction',
        ]);

        $couple = Couple::where('user_id', auth()->id())->find($request->couple_id);
        if (!$couple) {
            return response()->json(['message' => 'Ce couple n\'appartient pas à cet utilisateur'], 403);
        }

        if (!$couple->actif) {
            return response()->json(['message' => 'Le couple doit être actif pour enregistrer une reproduction'], 422);
        }

        $active = ReproductionWorkflowService::findActiveForCouple($couple->id, auth()->id());
        if ($active) {
            return response()->json([
                'message' => 'Ce couple a déjà une couvée en cours (ponte du ' . date('d/m/Y', strtotime($active->date_ponte)) . '). Clôturez-la avant d\'en enregistrer une nouvelle.',
            ], 422);
        }

        if ($request->date_ponte < $couple->date_formation) {
            return response()->json([
                'message' => 'La date de ponte ne peut pas être antérieure à la date de formation du couple (' . date('d/m/Y', strtotime($couple->date_formation)) . ')',
            ], 422);
        }

        if ($request->date_eclosion) {
            $diff = (new \DateTime($request->date_ponte))->diff(new \DateTime($request->date_eclosion))->days;
            if ($diff < 17) {
                return response()->json([
                    'message' => 'La date d\'éclosion doit être au minimum 17 jours après la date de ponte (période d\'incubation)',
                ], 422);
            }
        }

        $reproduction = Reproduction::create([
            ...$request->all(),
            'user_id' => auth()->id(),
        ]);

        return response()->json(
            ReproductionWorkflowService::enrich($reproduction->load(['couple.male', 'couple.femelle'])),
            201
        );
    }

    public function show(Reproduction $reproduction)
    {
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json(
            ReproductionWorkflowService::enrich($reproduction->load(['couple.male', 'couple.femelle']))
        );
    }

    public function update(Request $request, Reproduction $reproduction)
    {
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'date_ponte' => 'sometimes|date|before_or_equal:today',
            'date_eclosion' => 'nullable|date',
            'nb_jeunes' => 'sometimes|integer|min:0|max:2',
        ], [
            'date_ponte.date' => 'La date de ponte doit être une date valide',
            'date_ponte.before_or_equal' => 'La date de ponte ne peut pas être dans le futur',
            'date_eclosion.date' => 'La date d\'éclosion doit être une date valide',
            'nb_jeunes.integer' => 'Le nombre de jeunes doit être un nombre entier',
            'nb_jeunes.min' => 'Le nombre de jeunes doit être au minimum 0',
            'nb_jeunes.max' => 'Le nombre de jeunes ne peut pas dépasser 2 par reproduction',
        ]);

        if ($request->has('date_ponte')) {
            $couple = $reproduction->couple;
            if ($request->date_ponte < $couple->date_formation) {
                return response()->json([
                    'message' => 'La date de ponte ne peut pas être antérieure à la date de formation du couple (' . date('d/m/Y', strtotime($couple->date_formation)) . ')',
                ], 422);
            }
        }

        $datePonte = $request->has('date_ponte') ? $request->date_ponte : $reproduction->date_ponte;
        $dateEclosion = $request->has('date_eclosion') ? $request->date_eclosion : $reproduction->date_eclosion;

        if ($dateEclosion && $datePonte) {
            $diff = (new \DateTime($datePonte))->diff(new \DateTime($dateEclosion))->days;
            if ($diff < 17) {
                return response()->json([
                    'message' => 'La date d\'éclosion doit être au minimum 17 jours après la date de ponte (période d\'incubation)',
                ], 422);
            }
        }

        $count = ReproductionWorkflowService::countPigeonneaux($reproduction);
        $newNbJeunes = $request->has('nb_jeunes') ? (int) $request->nb_jeunes : (int) $reproduction->nb_jeunes;
        if ($newNbJeunes < $count) {
            return response()->json([
                'message' => "Impossible de réduire le nombre de jeunes à {$newNbJeunes} : {$count} pigeonneau(x) déjà enregistré(s).",
            ], 422);
        }

        $reproduction->update($request->all());

        return response()->json(
            ReproductionWorkflowService::enrich($reproduction->load(['couple.male', 'couple.femelle']))
        );
    }

    public function destroy(Reproduction $reproduction)
    {
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $count = ReproductionWorkflowService::countPigeonneaux($reproduction);
        if ($count > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer : des pigeonneaux sont déjà liés à cette reproduction.',
            ], 422);
        }

        $reproduction->delete();

        return response()->json(['message' => 'Reproduction supprimée']);
    }

    public function creerPigeonneaux(Request $request, Reproduction $reproduction)
    {
        if ($reproduction->user_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$reproduction->date_eclosion) {
            return response()->json([
                'message' => 'Renseignez d\'abord la date d\'éclosion réelle.',
            ], 422);
        }

        if (Carbon::parse($reproduction->date_eclosion)->startOfDay()->gt(Carbon::today())) {
            return response()->json([
                'message' => 'Les pigeonneaux ne peuvent être enregistrés qu\'après l\'éclosion (date prévue : ' . date('d/m/Y', strtotime($reproduction->date_eclosion)) . ').',
            ], 422);
        }

        if ((int) $reproduction->nb_jeunes <= 0) {
            return response()->json([
                'message' => 'Cette reproduction indique 0 jeune. Modifiez le nombre de jeunes si des pigeonneaux sont nés.',
            ], 422);
        }

        if ($reproduction->nb_jeunes > 2) {
            return response()->json([
                'message' => 'Cette reproduction déclare plus de 2 jeunes. Corrigez le nombre avant de créer les fiches.',
            ], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'pigeonneaux' => 'required|array|min:1|max:2',
            'pigeonneaux.*.sexe' => 'required|in:male,femelle',
        ], [
            'pigeonneaux.required' => 'Au moins un pigeonneau est requis',
            'pigeonneaux.max' => 'Maximum 2 pigeonneaux par reproduction',
            'pigeonneaux.*.sexe.required' => 'Le sexe est requis pour chaque pigeonneau',
            'pigeonneaux.*.sexe.in' => 'Le sexe doit être "male" ou "femelle"',
        ]);

        foreach ($request->pigeonneaux as $index => $pigeonneau) {
            if (empty($pigeonneau['bague'])) {
                $validator->errors()->add("pigeonneaux.{$index}.bague", 'Le numéro de bague est requis pour chaque pigeonneau');
            } else {
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $couple = $reproduction->couple;
        $pigeonneauxExistants = ReproductionWorkflowService::countPigeonneaux($reproduction);
        $nouveauxPigeonneaux = count($request->pigeonneaux);
        $maxJeunes = min(2, (int) $reproduction->nb_jeunes);

        if ($pigeonneauxExistants + $nouveauxPigeonneaux > $maxJeunes) {
            return response()->json([
                'message' => "Maximum {$maxJeunes} jeune(s) pour cette reproduction (déjà créé(s) : {$pigeonneauxExistants}).",
            ], 422);
        }

        $pigeonneauxCrees = [];
        foreach ($request->pigeonneaux as $data) {
            $pigeonneauxCrees[] = Pigeon::create([
                'bague' => $data['bague'],
                'sexe' => $data['sexe'],
                'race' => $data['race'] ?? $couple->male->race,
                'date_naissance' => $reproduction->date_eclosion,
                'statut' => 'actif',
                'pere_id' => $couple->male_id,
                'mere_id' => $couple->femelle_id,
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'message' => count($pigeonneauxCrees) . ' pigeonneau(x) créé(s) avec succès',
            'pigeonneaux' => $pigeonneauxCrees,
            'reproduction' => ReproductionWorkflowService::enrich($reproduction->fresh()->load(['couple.male', 'couple.femelle'])),
        ], 201);
    }
}
