<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jury extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_jury',
        'president_id',
        'rapporteur_id',
        'examinateur_id',
        'encadreur_id',
        'date_creation',
        'statut',
        'commentaire',
    ];

    protected $casts = [
        'date_creation' => 'date',
    ];

    // Relations
    public function president()
    {
        return $this->belongsTo(MembreDuJury::class, 'president_id');
    }

    public function rapporteur()
    {
        return $this->belongsTo(MembreDuJury::class, 'rapporteur_id');
    }

    public function examinateur()
    {
        return $this->belongsTo(MembreDuJury::class, 'examinateur_id');
    }

    public function encadreur()
    {
        return $this->belongsTo(Encadreur::class);
    }

    public function soutenances()
    {
        return $this->hasMany(Soutenance::class);
    }

    // Scopes
    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeTermines($query)
    {
        return $query->where('statut', 'termine');
    }

    // Accesseurs
    public function getMembresAttribute()
    {
        return collect([
            'president' => $this->president,
            'rapporteur' => $this->rapporteur,
            'examinateur' => $this->examinateur,
            'encadreur' => $this->encadreur,
        ]);
    }

    public function getNombreSoutenancesAttribute()
    {
        return $this->soutenances()->count();
    }

    public function getNombreSoutenancesTermineesAttribute()
    {
        return $this->soutenances()->where('statut', 'terminee')->count();
    }
}
