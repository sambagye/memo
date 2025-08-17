<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'numero_etudiant',
        'niveau',
        'filiere',
        'annee_academique',
        'statut_memoire',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function affectation()
    {
        return $this->hasOne(Affectation::class);
    }

    public function dossierSoutenance()
    {
        return $this->hasOne(DossierSoutenance::class);
    }

    public function soutenance()
    {
        return $this->hasOne(Soutenance::class);
    }

    // Scopes
    public function scopeByNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    public function scopeByStatut($query, $statut)
    {
        return $query->where('statut_memoire', $statut);
    }

    public function scopeByAnneeAcademique($query, $annee)
    {
        return $query->where('annee_academique', $annee);
    }

    // Accesseurs
    public function getPeutChoisirSujetAttribute()
    {
        return $this->statut_memoire === 'en_attente_sujet';
    }

    public function getEstAffecteAttribute()
    {
        return $this->statut_memoire === 'affecte';
    }

    public function getPeutSoutenirAttribute()
    {
        return $this->statut_memoire === 'autorise_soutenance';
    }
}
