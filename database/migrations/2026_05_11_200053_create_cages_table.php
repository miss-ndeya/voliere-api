<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cages', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->string('nom');
            $table->float('superficie')->nullable();
            $table->enum('statut', ['libre', 'occupe', 'couple'])->default('libre');
            $table->foreignId('pigeon_id')->nullable()->constrained('pigeons')->nullOnDelete();
            $table->foreignId('couple_id')->nullable()->constrained('couples')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cages');
    }
};