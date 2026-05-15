<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Supprimer les contraintes unique existantes et les remplacer par des contraintes composites
        
        // Pour la table pigeons
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropUnique(['bague']); // Supprimer l'ancienne contrainte unique
        });
        
        Schema::table('pigeons', function (Blueprint $table) {
            $table->unique(['bague', 'user_id']); // Ajouter contrainte unique composite
        });

        // Pour la table cages
        Schema::table('cages', function (Blueprint $table) {
            $table->dropUnique(['numero']); // Supprimer l'ancienne contrainte unique
        });
        
        Schema::table('cages', function (Blueprint $table) {
            $table->unique(['numero', 'user_id']); // Ajouter contrainte unique composite
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revenir aux contraintes unique simples
        
        Schema::table('pigeons', function (Blueprint $table) {
            $table->dropUnique(['bague', 'user_id']);
        });
        
        Schema::table('pigeons', function (Blueprint $table) {
            $table->unique('bague');
        });

        Schema::table('cages', function (Blueprint $table) {
            $table->dropUnique(['numero', 'user_id']);
        });
        
        Schema::table('cages', function (Blueprint $table) {
            $table->unique('numero');
        });
    }
};
