<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pigeon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bague',
        'sexe',
        'race',
        'date_naissance',
        'statut',
        'pere_id',
        'mere_id',
        'user_id',
    ];

    // Relation avec l'utilisateur propriétaire
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Un pigeon peut être le mâle dans un couple
    public function coupleComeMale()
    {
        return $this->hasOne(Couple::class, 'male_id');
    }

    // Un pigeon peut être la femelle dans un couple
    public function coupleComeFemelle()
    {
        return $this->hasOne(Couple::class, 'femelle_id');
    }

    // Un pigeon peut avoir une sortie (vente, décès, perte)
    public function sortie()
    {
        return $this->hasOne(Sortie::class);
    }

    // Un pigeon peut occuper une cage
    public function cage()
    {
        return $this->hasOne(Cage::class);
    }

    // Le père de ce pigeon
    public function pere()
    {
        return $this->belongsTo(Pigeon::class, 'pere_id');
    }

    // La mère de ce pigeon
    public function mere()
    {
        return $this->belongsTo(Pigeon::class, 'mere_id');
    }

    // Les enfants de ce pigeon (comme père)
    public function enfantsComePere()
    {
        return $this->hasMany(Pigeon::class, 'pere_id');
    }

    // Les enfants de ce pigeon (comme mère)
    public function enfantsComeMere()
    {
        return $this->hasMany(Pigeon::class, 'mere_id');
    }
}