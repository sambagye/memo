<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sujet extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'description',
        'objectifs',
        'prerequis',
        'domaine',
        'niveau',
        'nombre_places_disponibles',
        'nombre_places_occupees',
        'encadreur_id',
        'statut',
        'commentaire_admin',
        'date_validation',
    ];

    protected $casts = [
        'date_validation' => 'datetime',
    ];

    // Relations
    public function encadreur()
    {
        return $this->belongsTo(Encadreur::class);
    }

    public function affectations()
    {
        return $this->hasMany(Affectation::class);
    }

    // Scopes
    public function scopeValides($query)
    {
        return $query->where('statut', 'valide');
    }

    public function scopeDisponibles($query)
    {
        return $query->where('statut', 'valide')
            ->whereColumn('nombre_places_occupees', '<', 'nombre_places_disponibles');
    }

    public function scopeByNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    public function scopeByDomaine($query, $domaine)
    {
        return $query->where('domaine', $domaine);
    }

    // Accesseurs
    public function getPlacesRestantesAttribute()
    {
        return $this->nombre_places_disponibles - $this->nombre_places_occupees;
    }

    public function getEstDisponibleAttribute()
    {
        return $this->statut === 'valide' && $this->places_restantes > 0;
    }

    public function getEstCompletAttribute()
    {
        return $this->nombre_places_occupees >= $this->nombre_places_disponibles;
    }
}
