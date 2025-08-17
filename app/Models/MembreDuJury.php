<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembreDuJury extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'grade_academique',
        'specialite',
        'etablissement',
        'est_externe',
        'statut_disponibilite',
    ];

    protected $casts = [
        'est_externe' => 'boolean',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jurysCommePrecident()
    {
        return $this->hasMany(Jury::class, 'president_id');
    }

    public function jurysCommeRapporteur()
    {
        return $this->hasMany(Jury::class, 'rapporteur_id');
    }

    public function jurysCommeExaminateur()
    {
        return $this->hasMany(Jury::class, 'examinateur_id');
    }

    // Scopes
    public function scopeDisponibles($query)
    {
        return $query->where('statut_disponibilite', 'disponible');
    }

    public function scopeInternes($query)
    {
        return $query->where('est_externe', false);
    }

    public function scopeExternes($query)
    {
        return $query->where('est_externe', true);
    }

    public function scopeBySpecialite($query, $specialite)
    {
        return $query->where('specialite', $specialite);
    }

    // Accesseurs
    public function getEstDisponibleAttribute()
    {
        return $this->statut_disponibilite === 'disponible';
    }

    public function getNombreJurysActifsAttribute()
    {
        return $this->jurysCommePrecident()
                ->where('statut', 'actif')
                ->count() +
            $this->jurysCommeRapporteur()
                ->where('statut', 'actif')
                ->count() +
            $this->jurysCommeExaminateur()
                ->where('statut', 'actif')
                ->count();
    }
}
