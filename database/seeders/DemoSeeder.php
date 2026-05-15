<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Pigeon;
use App\Models\Couple;
use App\Models\Reproduction;
use App\Models\Cage;
use App\Models\Sortie;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer un utilisateur de démonstration
        $user = User::create([
            'name' => 'Utilisateur Demo',
            'email' => 'demo@voliere.com',
            'password' => Hash::make('password'),
        ]);

        echo "✓ Utilisateur créé: demo@voliere.com / password\n";

        // Créer des pigeons
        $pigeons = [];
        
        // Mâles
        for ($i = 1; $i <= 5; $i++) {
            $pigeons[] = Pigeon::create([
                'bague' => 'M-2024-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'sexe' => 'male',
                'race' => ['Voyageur', 'Mondain', 'Texan', 'King'][array_rand(['Voyageur', 'Mondain', 'Texan', 'King'])],
                'couleur' => ['Bleu', 'Rouge', 'Blanc', 'Noir', 'Gris'][array_rand(['Bleu', 'Rouge', 'Blanc', 'Noir', 'Gris'])],
                'date_naissance' => now()->subMonths(rand(6, 24))->format('Y-m-d'),
                'statut' => 'actif',
                'user_id' => $user->id,
            ]);
        }

        // Femelles
        for ($i = 1; $i <= 5; $i++) {
            $pigeons[] = Pigeon::create([
                'bague' => 'F-2024-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'sexe' => 'femelle',
                'race' => ['Voyageur', 'Mondain', 'Texan', 'King'][array_rand(['Voyageur', 'Mondain', 'Texan', 'King'])],
                'couleur' => ['Bleu', 'Rouge', 'Blanc', 'Noir', 'Gris'][array_rand(['Bleu', 'Rouge', 'Blanc', 'Noir', 'Gris'])],
                'date_naissance' => now()->subMonths(rand(6, 24))->format('Y-m-d'),
                'statut' => 'actif',
                'user_id' => $user->id,
            ]);
        }

        echo "✓ " . count($pigeons) . " pigeons créés\n";

        // Créer des couples
        $couples = [];
        for ($i = 0; $i < 3; $i++) {
            $male = $pigeons[$i]; // Mâles
            $femelle = $pigeons[$i + 5]; // Femelles
            
            $couples[] = Couple::create([
                'male_id' => $male->id,
                'femelle_id' => $femelle->id,
                'date_formation' => now()->subMonths(rand(1, 6))->format('Y-m-d'),
                'actif' => true,
                'user_id' => $user->id,
            ]);
        }

        echo "✓ " . count($couples) . " couples créés\n";

        // Créer des reproductions
        $reproductions = [];
        foreach ($couples as $index => $couple) {
            $datePonte = now()->subDays(rand(20, 40));
            $dateEclosion = $index < 2 ? $datePonte->copy()->addDays(18) : null;
            
            $reproduction = Reproduction::create([
                'couple_id' => $couple->id,
                'date_ponte' => $datePonte->format('Y-m-d'),
                'date_eclosion' => $dateEclosion ? $dateEclosion->format('Y-m-d') : null,
                'nb_jeunes' => rand(1, 3),
                'user_id' => $user->id,
            ]);
            
            $reproductions[] = $reproduction;

            // Créer des pigeonneaux pour les reproductions écloses
            if ($dateEclosion && $index < 2) {
                for ($j = 0; $j < $reproduction->nb_jeunes; $j++) {
                    Pigeon::create([
                        'bague' => 'J-2024-' . str_pad(($index * 10 + $j + 1), 3, '0', STR_PAD_LEFT),
                        'sexe' => ['male', 'femelle'][array_rand(['male', 'femelle'])],
                        'race' => $couple->male->race,
                        'couleur' => ['Bleu', 'Rouge', 'Blanc'][array_rand(['Bleu', 'Rouge', 'Blanc'])],
                        'date_naissance' => $dateEclosion->format('Y-m-d'),
                        'statut' => 'actif',
                        'pere_id' => $couple->male_id,
                        'mere_id' => $couple->femelle_id,
                        'user_id' => $user->id,
                    ]);
                }
            }
        }

        echo "✓ " . count($reproductions) . " reproductions créées\n";

        // Créer des cages
        $cages = [];
        for ($i = 1; $i <= 8; $i++) {
            $cage = Cage::create([
                'numero' => 'A' . $i,
                'nom' => 'Cage ' . $i,
                'superficie' => rand(15, 30) / 10, // 1.5 à 3.0 m²
                'statut' => 'libre',
                'user_id' => $user->id,
            ]);
            $cages[] = $cage;
        }

        // Affecter quelques pigeons et couples aux cages
        $cages[0]->update([
            'statut' => 'occupe',
            'pigeon_id' => $pigeons[9]->id, // Un pigeon seul
        ]);

        $cages[1]->update([
            'statut' => 'occupe',
            'couple_id' => $couples[0]->id,
        ]);

        $cages[2]->update([
            'statut' => 'occupe',
            'couple_id' => $couples[1]->id,
        ]);

        echo "✓ " . count($cages) . " cages créées\n";

        // Créer quelques sorties
        $sortie = Sortie::create([
            'pigeon_id' => $pigeons[8]->id,
            'type' => 'vente',
            'date_sortie' => now()->subDays(10)->format('Y-m-d'),
            'prix' => 5000,
            'acheteur' => 'Jean Dupont',
            'user_id' => $user->id,
        ]);

        // Mettre à jour le statut du pigeon
        $pigeons[8]->update(['statut' => 'vendu']);

        echo "✓ 1 sortie créée\n";

        echo "\n✅ Données de démonstration créées avec succès!\n";
        echo "📧 Email: demo@voliere.com\n";
        echo "🔑 Mot de passe: password\n";
    }
}
