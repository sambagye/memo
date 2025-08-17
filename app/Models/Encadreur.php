<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encadreur extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialite',
        'grade_academique',
        'nombre_max_etudiants',
        'nombre_etudiants_actuels',
        'bio',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sujets()
    {
        return $this->hasMany(Sujet::class);
    }

    public function affectations()
    {
        return $this->hasMany(Affectation::class);
    }

    public function juries()
    {
        return $this->hasMany(Jury::class);
    }

    // Scopes
    public function scopeDisponible($query)
    {
        return $query->whereColumn('nombre_etudiants_actuels', '<', 'nombre_max_etudiants');
    }

    // Accesseurs
    public function getPeutPrendreEtudiantAttribute()
    {
        return $this->nombre_etudiants_actuels < $this->nombre_max_etudiants;
    }

    public function getNombreEtudiantsRestantsAttribute()
    {
        return $this->nombre_max_etudiants - $this->nombre_etudiants_actuels;
    }
}
