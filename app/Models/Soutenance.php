<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Soutenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'jury_id',
        'dossier_soutenance_id',
        'date_heure_soutenance',
        'salle',
        'duree_minutes',
        'statut',
        'note_president',
        'note_rapporteur',
        'note_examinateur',
        'note_encadreur',
        'note_finale',
        'mention',
        'appreciation_generale',
        'recommandations',
        'date_deliberation',
    ];

    protected $casts = [
        'date_heure_soutenance' => 'datetime',
        'date_deliberation' => 'datetime',
        'note_president' => 'decimal:2',
        'note_rapporteur' => 'decimal:2',
        'note_examinateur' => 'decimal:2',
        'note_encadreur' => 'decimal:2',
        'note_finale' => 'decimal:2',
    ];

    // Relations
    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function jury()
    {
        return $this->belongsTo(Jury::class);
    }

    public function dossierSoutenance()
    {
        return $this->belongsTo(DossierSoutenance::class);
    }

    public function memoireArchive()
    {
        return $this->hasOne(MemoireArchive::class);
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
        return $query->where('date_heure_soutenance', '>', now());
    }

    public function scopeAujourdhui($query)
    {
        return $query->whereDate('date_heure_soutenance', today());
    }

    // Accesseurs
    public function getDateFinSoutenanceAttribute()
    {
        return $this->date_heure_soutenance->addMinutes($this->duree_minutes);
    }

    public function getEstTermineeAttribute()
    {
        return $this->statut === 'terminee';
    }

    public function getToutesNotesComplèteAttribute()
    {
        return !is_null($this->note_president) &&
            !is_null($this->note_rapporteur) &&
            !is_null($this->note_examinateur) &&
            !is_null($this->note_encadreur);
    }

    public function calculerNoteFinalte()
    {
        if ($this->toutes_notes_complete) {
            $this->note_finale = ($this->note_president + $this->note_rapporteur +
                    $this->note_examinateur + $this->note_encadreur) / 4;
            $this->save();
        }
    }

    public function determinerMention()
    {
        if ($this->note_finale >= 18) {
            return 'excellent';
        } elseif ($this->note_finale >= 16) {
            return 'tres_bien';
        } elseif ($this->note_finale >= 14) {
            return 'bien';
        } elseif ($this->note_finale >= 12) {
            return 'assez_bien';
        } else {
            return 'passable';
        }
    }
}
