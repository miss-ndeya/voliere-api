<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relations avec les entités de l'élevage
    public function pigeons()
    {
        return $this->hasMany(Pigeon::class);
    }

    public function cages()
    {
        return $this->hasMany(Cage::class);
    }

    public function couples()
    {
        return $this->hasMany(Couple::class);
    }

    public function reproductions()
    {
        return $this->hasMany(Reproduction::class);
    }

    public function sorties()
    {
        return $this->hasMany(Sortie::class);
    }
}