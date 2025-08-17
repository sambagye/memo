<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemoireArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'soutenance_id',
        'titre_memoire',
        'nom_etudiant',
        'prenom_etudiant',
        'nom_encadreur',
        'annee_soutenance',
        'niveau',
        'filiere',
        'mention',
        'note_finale',
        'fichier_memoire',
        'resume_francais',
        'resume_anglais',
        'mots_cles',
        'nombre_telechargements',
        'visible_public',
        'date_archivage',
    ];

    protected $casts = [
        'annee_soutenance' => 'integer',
        'note_finale' => 'decimal:2',
        'nombre_telechargements' => 'integer',
        'visible_public' => 'boolean',
        'date_archivage' => 'datetime',
    ];

    // Relations
    public function soutenance()
    {
        return $this->belongsTo(Soutenance::class);
    }

    // Scopes
    public function scopeVisibles($query)
    {
        return $query->where('visible_public', true);
    }

    public function scopeByAnnee($query, $annee)
    {
        return $query->where('annee_soutenance', $annee);
    }

    public function scopeByMention($query, $mention)
    {
        return $query->where('mention', $mention);
    }

    public function scopeByNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    public function scopeRecherche($query, $terme)
    {
        return $query->where(function ($q) use ($terme) {
            $q->where('titre_memoire', 'like', "%{$terme}%")
                ->orWhere('nom_etudiant', 'like', "%{$terme}%")
                ->orWhere('prenom_etudiant', 'like', "%{$terme}%")
                ->orWhere('nom_encadreur', 'like', "%{$terme}%")
                ->orWhere('mots_cles', 'like', "%{$terme}%");
        });
    }

    // Accesseurs
    public function getNomCompletEtudiantAttribute()
    {
        return $this->prenom_etudiant . ' ' . $this->nom_etudiant;
    }

    public function incrementerTelechargements()
    {
        $this->increment('nombre_telechargements');
    }
}
