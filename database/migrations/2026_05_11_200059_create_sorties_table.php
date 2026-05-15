<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pigeon_id')->constrained('pigeons')->cascadeOnDelete();
            $table->enum('type', ['vente', 'deces', 'perte']);
            $table->date('date_sortie');
            $table->decimal('prix', 10, 2)->nullable();
            $table->string('acheteur')->nullable();
            $table->string('cause')->nullable();
            $table->text('circonstance')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorties');
    }
};