<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cage extends Model
{
    protected $fillable = [
        'numero',
        'nom',
        'superficie',
        'statut',
        'pigeon_id',
        'couple_id',
        'user_id',
    ];

    // Relation avec l'utilisateur propriétaire
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Le pigeon qui occupe cette cage
    public function pigeon()
    {
        return $this->belongsTo(Pigeon::class);
    }

    // Le couple qui occupe cette cage
    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }
}