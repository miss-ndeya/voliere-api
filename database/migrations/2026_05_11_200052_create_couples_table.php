<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('male_id')->constrained('pigeons')->cascadeOnDelete();
            $table->foreignId('femelle_id')->constrained('pigeons')->cascadeOnDelete();
            $table->date('date_formation');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couples');
    }
};