<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reproduction extends Model
{
    protected $fillable = [
        'couple_id',
        'date_ponte',
        'date_eclosion',
        'nb_jeunes',
        'user_id',
    ];

    // Relation avec l'utilisateur propriétaire
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Le couple qui a produit cette reproduction
    public function couple()
    {
        return $this->belongsTo(Couple::class);
    }

    // Les pigeonneaux issus de cette reproduction (via père et mère)
    public function pigeonneaux()
    {
        // On ne peut pas utiliser une relation dynamique avec where complexe
        // On va plutôt créer une méthode pour récupérer les pigeonneaux
        return $this->hasMany(Pigeon::class, 'id', 'id')->whereRaw('1 = 0'); // Relation vide par défaut
    }

    // Méthode pour récupérer les pigeonneaux du couple
    public function getPigeonneauxAttribute()
    {
        $couple = $this->couple;
        if (!$couple) {
            return collect([]);
        }
        
        return Pigeon::where(function($query) use ($couple) {
            $query->where('pere_id', $couple->male_id)
                  ->where('mere_id', $couple->femelle_id);
        })->orWhere(function($query) use ($couple) {
            $query->where('pere_id', $couple->femelle_id)
                  ->where('mere_id', $couple->male_id);
        })->get();
    }
}