<?php

namespace App\Http\Controllers;

use App\Models\Pigeon;
use App\Models\Couple;
use App\Models\Cage;
use App\Models\Reproduction;
use App\Models\Sortie;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Récupérer toutes les statistiques du dashboard
     * Calculs effectués côté serveur pour optimiser les performances
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // Récupérer les données
        $pigeons = Pigeon::where('user_id', $userId)->get();
        $couples = Couple::where('user_id', $userId)
            ->with(['male', 'femelle'])
            ->get();
        $cages = Cage::where('user_id', $userId)->get();
        $reproductions = Reproduction::where('user_id', $userId)
            ->with(['couple.male', 'couple.femelle'])
            ->latest()
            ->take(5)
            ->get();
        $sorties = Sortie::where('user_id', $userId)->get();

        // Calculs des statistiques
        $activePigeons = $pigeons->where('statut', 'actif');
        $activeCouples = $couples->where('actif', true); // Correction: actif au lieu de statut
        $freeCages = $cages->where('statut', 'libre');
        $occupiedCages = $cages->whereIn('statut', ['occupe', 'couple']);

        // Statistiques des ventes
        $ventes = $sorties->where('type', 'vente');
        $totalRevenue = $ventes->sum('prix');

        // Taux d'occupation
        $totalCages = $cages->count();
        $occupancyRate = $totalCages > 0 
            ? round(($occupiedCages->count() / $totalCages) * 100) 
            : 0;

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
                    'revenu' => $totalRevenue,
                ],
            ],
            'recentReproductions' => $reproductions->map(function ($reproduction) {
                return [
                    'id' => $reproduction->id,
                    'date_ponte' => $reproduction->date_ponte,
                    'nombre_jeunes' => $reproduction->nombre_jeunes,
                    'male' => [
                        'id' => $reproduction->couple->male->id ?? null,
                        'bague' => $reproduction->couple->male->bague ?? '?',
                    ],
                    'femelle' => [
                        'id' => $reproduction->couple->femelle->id ?? null,
                        'bague' => $reproduction->couple->femelle->bague ?? '?',
                    ],
                ];
            }),
        ]);
    }
}
