<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sortie extends Model
{
    protected $fillable = [
        'pigeon_id',
        'type',
        'date_sortie',
        'prix',
        'acheteur',
        'cause',
        'circonstance',
        'user_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'pigeon_id' => 'integer',
        'prix' => 'decimal:2',
        'date_sortie' => 'date',
    ];

    // Relation avec l'utilisateur propriétaire
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Le pigeon concerné par cette sortie
    public function pigeon()
    {
        return $this->belongsTo(Pigeon::class);
    }
}