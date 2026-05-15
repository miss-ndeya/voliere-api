<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Couple extends Model
{
    protected $fillable = [
        'male_id',
        'femelle_id',
        'date_formation',
        'actif',
        'user_id',
    ];

    // Relation avec l'utilisateur propriétaire
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Le mâle du couple
    public function male()
    {
        return $this->belongsTo(Pigeon::class, 'male_id');
    }

    // La femelle du couple
    public function femelle()
    {
        return $this->belongsTo(Pigeon::class, 'femelle_id');
    }

    // Les reproductions de ce couple
    public function reproductions()
    {
        return $this->hasMany(Reproduction::class);
    }

    // La cage occupée par ce couple
    public function cage()
    {
        return $this->hasOne(Cage::class);
    }
}