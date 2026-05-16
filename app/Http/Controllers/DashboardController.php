<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Models\Couple;
use App\Models\Cage;
use App\Models\Reproduction;
use App\Models\Sortie;
use App\Services\ReproductionWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $pigeons = Pigeon::where('user_id', $userId)->get();
        $couples = Couple::where('user_id', $userId)
            ->with(['male.cage', 'femelle.cage', 'cage'])
            ->get();
        $cages = Cage::where('user_id', $userId)->get();
        $allReproductions = Reproduction::where('user_id', $userId)
            ->with(['couple.male', 'couple.femelle'])
            ->orderByDesc('date_ponte')
            ->get()
            ->map(fn ($r) => ReproductionWorkflowService::enrich($r));

        $sorties = Sortie::where('user_id', $userId)->get();

        $activePigeons = $pigeons->where('statut', 'actif');
        $activeCouples = $couples->where('actif', true);
        $freeCages = $cages->where('statut', 'libre');
        $occupiedCages = $cages->whereIn('statut', ['occupe', 'couple']);
        $ventes = $sorties->where('type', 'vente');
        $totalCages = $cages->count();
        $occupancyRate = $totalCages > 0
            ? round(($occupiedCages->count() / $totalCages) * 100)
            : 0;

        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(7);

        $incubation = $allReproductions->whereIn('statut', [
            ReproductionWorkflowService::STATUT_INCUBATION,
            ReproductionWorkflowService::STATUT_ECLOSION_PREVUE,
        ]);

        $aBaguer = $allReproductions->where('statut', ReproductionWorkflowService::STATUT_A_BAGUER);

        $eclosionsSemaine = $allReproductions->filter(function ($r) use ($today, $weekEnd) {
            if (!$r->date_eclosion) {
                return false;
            }
            $d = Carbon::parse($r->date_eclosion);
            return $d->between($today, $weekEnd);
        });

        $couplesSansCage = $activeCouples->filter(fn ($c) => !$c->cage);

        $couplesCagesSeparees = $activeCouples->filter(function ($c) {
            if ($c->cage) {
                return false;
            }
            $cMale = $c->male?->cage;
            $cFem = $c->femelle?->cage;
            return $cMale && $cFem && $cMale->id !== $cFem->id;
        });

        return response()->json([
            'stats' => [
                'pigeons' => [
                    'actifs' => $activePigeons->count(),
                    'total' => $pigeons->count(),
                ],
                'couples' => [
                    'actifs' => $activeCouples->count(),
                    'total' => $couples->count(),
                ],
                'cages' => [
                    'libres' => $freeCages->count(),
                    'occupees' => $occupiedCages->count(),
                    'total' => $totalCages,
                    'tauxOccupation' => $occupancyRate,
                ],
                'ventes' => [
                    'nombre' => $ventes->count(),
                    'revenu' => $ventes->sum('prix'),
                ],
                'reproductions' => [
                    'en_incubation' => $incubation->count(),
                    'a_baguer' => $aBaguer->count(),
                    'eclosions_semaine' => $eclosionsSemaine->count(),
                ],
            ],
            'alertes' => [
                [
                    'type' => 'a_baguer',
                    'count' => $aBaguer->count(),
                    'message' => $aBaguer->count() > 0
                        ? "{$aBaguer->count()} couvée(s) à baguer après éclosion"
                        : null,
                    'lien' => '/reproductions',
                ],
                [
                    'type' => 'eclosion',
                    'count' => $eclosionsSemaine->count(),
                    'message' => $eclosionsSemaine->count() > 0
                        ? "{$eclosionsSemaine->count()} éclosion(s) prévue(s) cette semaine"
                        : null,
                    'lien' => '/reproductions',
                ],
                [
                    'type' => 'couple_sans_cage',
                    'count' => $couplesSansCage->count(),
                    'message' => $couplesSansCage->count() > 0
                        ? "{$couplesSansCage->count()} couple(s) actif(s) sans cage"
                        : null,
                    'lien' => '/visualisation',
                ],
                [
                    'type' => 'cages_separees',
                    'count' => $couplesCagesSeparees->count(),
                    'message' => $couplesCagesSeparees->count() > 0
                        ? "{$couplesCagesSeparees->count()} couple(s) dans des cages séparées"
                        : null,
                    'lien' => '/visualisation',
                ],
            ],
            'recentReproductions' => $allReproductions->take(5)->map(function ($reproduction) {
                return [
                    'id' => $reproduction->id,
                    'date_ponte' => $reproduction->date_ponte,
                    'date_eclosion' => $reproduction->date_eclosion,
                    'nb_jeunes' => $reproduction->nb_jeunes,
                    'statut' => $reproduction->statut,
                    'statut_label' => ReproductionWorkflowService::statutLabel($reproduction->statut),
                    'male' => [
                        'id' => $reproduction->couple->male->id ?? null,
                        'bague' => $reproduction->couple->male->bague ?? '?',
                    ],
                    'femelle' => [
                        'id' => $reproduction->couple->femelle->id ?? null,
                        'bague' => $reproduction->couple->femelle->bague ?? '?',
                    ],
                ];
            })->values(),
        ]);
    }
}
