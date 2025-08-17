<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeanceEncadrement extends Model
{
    use HasFactory;

    protected $fillable = [
        'affectation_id',
        'titre',
        'description',
        'date_heure',
        'duree_minutes',
        'lieu',
        'lien_meeting',
        'statut',
        'compte_rendu',
        'travail_a_faire',
        'date_realisation',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'date_realisation' => 'datetime',
    ];

    // Relations
    public function affectation()
    {
        return $this->belongsTo(Affectation::class);
    }

    // Scopes
    public function scopeTerminees($query)
    {
        return $query->where('statut', 'terminee');
    }

    public function scopeProgrammees($query)
    {
        return $query->where('statut', 'programmee');
    }

    public function scopeAVenir($query)
    {
        return $query->where('date_heure', '>', now());
    }

    public function scopePassees($query)
    {
        return $query->where('date_heure', '<', now());
    }

    // Accesseurs
    public function getEstTermineeAttribute()
    {
        return $this->statut === 'terminee';
    }

    public function getDateFinAttribute()
    {
        return $this->date_heure->addMinutes($this->duree_minutes);
    }

    public function getEstEnRetatdAttribute()
    {
        return $this->statut === 'programmee' && $this->date_heure < now();
    }
}
