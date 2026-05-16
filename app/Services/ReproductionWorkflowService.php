<?php

namespace App\Services;

use App\Models\Pigeon;
use App\Models\Reproduction;
use Carbon\Carbon;

class ReproductionWorkflowService
{
    public const STATUT_INCUBATION = 'incubation';
    public const STATUT_ECLOSION_PREVUE = 'eclosion_prevue';
    public const STATUT_A_BAGUER = 'a_baguer';
    public const STATUT_TERMINEE = 'terminee';

    public static function countPigeonneaux(Reproduction $reproduction): int
    {
        $couple = $reproduction->couple;
        if (!$couple || !$reproduction->date_eclosion) {
            return 0;
        }

        return Pigeon::where('pere_id', $couple->male_id)
            ->where('mere_id', $couple->femelle_id)
            ->where('date_naissance', $reproduction->date_eclosion)
            ->count();
    }

    public static function computeStatut(Reproduction $reproduction, ?int $pigeonneauxCount = null): string
    {
        $count = $pigeonneauxCount ?? self::countPigeonneaux($reproduction);
        $nbJeunes = (int) $reproduction->nb_jeunes;
        $today = Carbon::today();

        if (!$reproduction->date_eclosion) {
            return self::STATUT_INCUBATION;
        }

        $eclosion = Carbon::parse($reproduction->date_eclosion)->startOfDay();

        if ($eclosion->gt($today)) {
            return self::STATUT_ECLOSION_PREVUE;
        }

        if ($nbJeunes <= 0) {
            return self::STATUT_TERMINEE;
        }

        if ($count >= $nbJeunes) {
            return self::STATUT_TERMINEE;
        }

        return self::STATUT_A_BAGUER;
    }

    public static function isActive(Reproduction $reproduction, ?int $pigeonneauxCount = null): bool
    {
        return self::computeStatut($reproduction, $pigeonneauxCount) !== self::STATUT_TERMINEE;
    }

    public static function canCreatePigeonneaux(Reproduction $reproduction, ?int $pigeonneauxCount = null): bool
    {
        $count = $pigeonneauxCount ?? self::countPigeonneaux($reproduction);

        if (!$reproduction->date_eclosion || (int) $reproduction->nb_jeunes <= 0) {
            return false;
        }

        if (Carbon::parse($reproduction->date_eclosion)->startOfDay()->gt(Carbon::today())) {
            return false;
        }

        return $count < min(2, (int) $reproduction->nb_jeunes);
    }

    /**
     * @return Reproduction|null Reproduction active bloquante, ou null
     */
    public static function findActiveForCouple(int $coupleId, int $userId, ?int $exceptReproductionId = null): ?Reproduction
    {
        $query = Reproduction::where('couple_id', $coupleId)
            ->where('user_id', $userId)
            ->with('couple');

        if ($exceptReproductionId) {
            $query->where('id', '!=', $exceptReproductionId);
        }

        foreach ($query->get() as $reproduction) {
            if (self::isActive($reproduction)) {
                return $reproduction;
            }
        }

        return null;
    }

    public static function enrich(Reproduction $reproduction): Reproduction
    {
        $count = self::countPigeonneaux($reproduction);
        $statut = self::computeStatut($reproduction, $count);

        $reproduction->pigeonneaux_count = $count;
        $reproduction->statut = $statut;
        $reproduction->is_active = $statut !== self::STATUT_TERMINEE;
        $reproduction->can_create_pigeonneaux = self::canCreatePigeonneaux($reproduction, $count);

        return $reproduction;
    }

    public static function statutLabel(string $statut): string
    {
        return match ($statut) {
            self::STATUT_INCUBATION => 'En incubation',
            self::STATUT_ECLOSION_PREVUE => 'Éclosion prévue',
            self::STATUT_A_BAGUER => 'À baguer',
            self::STATUT_TERMINEE => 'Terminée',
            default => $statut,
        };
    }
}
