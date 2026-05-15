<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained('cages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', ['affectation_pigeon', 'affectation_couple', 'liberation', 'nettoyage', 'creation']);
            $table->string('description');
            $table->json('metadata')->nullable(); // Pour stocker des infos supplémentaires
            $table->timestamps();
            
            $table->index(['cage_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cage_history');
    }
};
