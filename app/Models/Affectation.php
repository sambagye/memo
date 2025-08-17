<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affectation extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'sujet_id',
        'encadreur_id',
        'ordre_preference_etudiant',
        'statut',
        'date_affectation',
        'commentaire_admin',
    ];

    protected $casts = [
        'date_affectation' => 'datetime',
    ];

    // Relations
    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function sujet()
    {
        return $this->belongsTo(Sujet::class);
    }

    public function encadreur()
    {
        return $this->belongsTo(Encadreur::class);
    }

    public function seances()
    {
        return $this->hasMany(SeanceEncadrement::class);
    }

    public function dossierSoutenance()
    {
        return $this->hasOne(DossierSoutenance::class);
    }

    // Scopes
    public function scopeAffectees($query)
    {
        return $query->where('statut', 'affecte');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeByEncadreur($query, $encadreurId)
    {
        return $query->where('encadreur_id', $encadreurId);
    }

    // Accesseurs
    public function getEstAffecteeAttribute()
    {
        return $this->statut === 'affecte';
    }

    public function getNombreSeancesAttribute()
    {
        return $this->seances()->count();
    }

    public function getDernierSeanceAttribute()
    {
        return $this->seances()->latest('date_heure')->first();
    }
}
