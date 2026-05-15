<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pigeons', function (Blueprint $table) {
            $table->id();
            $table->string('bague')->unique();
            $table->enum('sexe', ['male', 'femelle']);
            $table->string('race');
            $table->date('date_naissance')->nullable();
            $table->enum('statut', ['actif', 'vendu', 'mort', 'perdu', 'inactif'])->default('actif');
            $table->foreignId('pere_id')->nullable()->constrained('pigeons')->nullOnDelete();
            $table->foreignId('mere_id')->nullable()->constrained('pigeons')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pigeons');
    }
};