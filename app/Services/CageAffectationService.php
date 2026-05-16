<?php

namespace App\Services;

use App\Models\Cage;
use App\Models\CageHistory;
use App\Models\Couple;
use App\Models\Pigeon;

class CageAffectationService
{
    /**
     * Affecte un pigeon actif à une cage libre.
     *
     * @return array{ok: bool, message?: string, cage?: Cage}
     */
    public function affecterPigeon(int $cageId, int $pigeonId, int $userId): array
    {
        $cage = Cage::where('user_id', $userId)->find($cageId);
        if (!$cage) {
            return ['ok' => false, 'message' => 'Cage introuvable'];
        }

        if ($cage->statut !== 'libre') {
            return ['ok' => false, 'message' => 'Cette cage est déjà occupée.'];
        }

        $pigeon = Pigeon::where('user_id', $userId)->find($pigeonId);
        if (!$pigeon) {
            return ['ok' => false, 'message' => 'Pigeon introuvable'];
        }

        if ($pigeon->statut !== 'actif') {
            return ['ok' => false, 'message' => 'Seuls les pigeons actifs peuvent être affectés à une cage.'];
        }

        if ($pigeon->cage) {
            return ['ok' => false, 'message' => 'Ce pigeon est déjà dans une cage.'];
        }

        $coupleActif = $pigeon->coupleComeMale()->where('actif', true)->first()
            ?? $pigeon->coupleComeFemelle()->where('actif', true)->first();

        if ($coupleActif) {
            return ['ok' => false, 'message' => 'Ce pigeon fait partie d\'un couple actif. Formez le couple dans une cage ou dissolvez-le d\'abord.'];
        }

        $cage->update([
            'statut' => 'occupe',
            'pigeon_id' => $pigeon->id,
            'couple_id' => null,
        ]);

        CageHistory::create([
            'cage_id' => $cage->id,
            'user_id' => $userId,
            'action' => 'affectation_pigeon',
            'description' => "Pigeon {$pigeon->bague} affecté",
            'metadata' => ['pigeon_id' => $pigeon->id, 'bague' => $pigeon->bague],
        ]);

        return [
            'ok' => true,
            'message' => "Pigeon affecté à la cage {$cage->numero}",
            'cage' => $cage->fresh(['pigeon']),
        ];
    }

    /**
     * Regroupe un couple dans une cage (logique volière).
     *
     * @return array{ok: bool, message?: string, cage?: Cage, cages_liberes?: array}
     */
    public function affecterCouple(int $cageId, int $coupleId, int $userId): array
    {
        $cage = Cage::where('user_id', $userId)->find($cageId);
        if (!$cage) {
            return ['ok' => false, 'message' => 'Cage introuvable'];
        }

        $couple = Couple::where('user_id', $userId)
            ->with(['male.cage', 'femelle.cage'])
            ->find($coupleId);

        if (!$couple) {
            return ['ok' => false, 'message' => 'Couple introuvable'];
        }

        if (!$couple->actif) {
            return ['ok' => false, 'message' => 'Seuls les couples actifs peuvent être affectés.'];
        }

        if ($couple->cage) {
            return ['ok' => false, 'message' => 'Ce couple occupe déjà une cage couple.'];
        }

        $result = $this->regrouperCoupleDansCage($couple, $cage, $userId);
        if (isset($result['ok']) && $result['ok'] === false) {
            return $result;
        }

        $message = 'Couple affecté à la cage ' . $cage->numero;
        if (!empty($result['cages_liberes'])) {
            $message .= '. Cage(s) libérée(s) : ' . implode(', ', $result['cages_liberes']) . '.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'cage' => $cage->fresh(['couple.male', 'couple.femelle']),
            'cages_liberes' => $result['cages_liberes'] ?? [],
        ];
    }

    private function regrouperCoupleDansCage(Couple $couple, Cage $cage, int $userId): array
    {
        $male = $couple->male;
        $femelle = $couple->femelle;
        $cagesLiberees = [];

        if (!$male || !$femelle) {
            return ['ok' => false, 'message' => 'Couple incomplet (mâle ou femelle manquant)'];
        }

        $cageMale = $male->cage;
        $cageFemelle = $femelle->cage;

        if ($cage->statut === 'occupe' && $cage->pigeon_id !== $male->id && $cage->pigeon_id !== $femelle->id) {
            return ['ok' => false, 'message' => 'Cette cage est occupée par un autre pigeon.'];
        }

        if ($cage->statut === 'couple' && $cage->couple_id !== $couple->id) {
            return ['ok' => false, 'message' => 'Cette cage est déjà occupée par un autre couple.'];
        }

        if ($cage->statut === 'occupe' && ($cage->pigeon_id === $male->id || $cage->pigeon_id === $femelle->id)) {
            $autreCage = ($cage->pigeon_id === $male->id) ? $cageFemelle : $cageMale;
            if ($autreCage && $autreCage->id !== $cage->id) {
                $cagesLiberees[] = $this->libererCageSolo($autreCage, $userId);
            }
        } else {
            if ($cage->statut !== 'libre') {
                return ['ok' => false, 'message' => 'Cette cage n\'est pas disponible pour ce couple.'];
            }

            if ($cageMale && $cageMale->id !== $cage->id) {
                $cagesLiberees[] = $this->libererCageSolo($cageMale, $userId);
            }
            if ($cageFemelle && $cageFemelle->id !== $cage->id) {
                $cagesLiberees[] = $this->libererCageSolo($cageFemelle, $userId);
            }
        }

        $cage->update([
            'statut' => 'couple',
            'couple_id' => $couple->id,
            'pigeon_id' => null,
        ]);

        CageHistory::create([
            'cage_id' => $cage->id,
            'user_id' => $userId,
            'action' => 'affectation_couple',
            'description' => "Couple {$male->bague} × {$femelle->bague} affecté",
            'metadata' => [
                'couple_id' => $couple->id,
                'male_bague' => $male->bague,
                'femelle_bague' => $femelle->bague,
                'cages_liberes' => array_filter($cagesLiberees),
            ],
        ]);

        return ['ok' => true, 'cages_liberes' => array_filter($cagesLiberees)];
    }

    private function libererCageSolo(Cage $cage, int $userId): string
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
            'user_id' => $userId,
            'action' => 'liberation',
            'description' => "Pigeon {$bague} retiré (regroupement du couple)",
            'metadata' => ['pigeon_bague' => $bague, 'motif' => 'regroupement_couple'],
        ]);

        return $numero;
    }
}
