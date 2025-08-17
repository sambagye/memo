<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DossierSoutenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'etudiant_id',
        'affectation_id',
        'autorisation_encadreur',
        'date_autorisation',
        'memoire_pdf',
        'resume_francais',
        'resume_anglais',
        'attestation_plagiat',
        'fiche_evaluation_encadreur',
        'dossier_complet',
        'date_soumission',
        'statut_verification',
        'commentaire_admin',
    ];

    protected $casts = [
        'autorisation_encadreur' => 'boolean',
        'dossier_complet' => 'boolean',
        'date_autorisation' => 'datetime',
        'date_soumission' => 'datetime',
    ];

    // Relations
    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function affectation()
    {
        return $this->belongsTo(Affectation::class);
    }

    public function soutenance()
    {
        return $this->hasOne(Soutenance::class);
    }

    // Scopes
    public function scopeAutorises($query)
    {
        return $query->where('autorisation_encadreur', true);
    }

    public function scopeComplets($query)
    {
        return $query->where('dossier_complet', true);
    }

    public function scopeVerifies($query)
    {
        return $query->where('statut_verification', 'verifie');
    }

    // Accesseurs
    public function getDocumentsManquantsAttribute()
    {
        $documents = [
            'memoire_pdf' => 'Mémoire PDF',
            'resume_francais' => 'Résumé français',
            'resume_anglais' => 'Résumé anglais',
            'attestation_plagiat' => 'Attestation anti-plagiat',
            'fiche_evaluation_encadreur' => 'Fiche d\'évaluation encadreur',
        ];

        $manquants = [];
        foreach ($documents as $field => $label) {
            if (empty($this->$field)) {
                $manquants[] = $label;
            }
        }

        return $manquants;
    }

    public function getPourcentageCompletionAttribute()
    {
        $totalDocuments = 5;
        $documentsPresents = 0;

        $documents = ['memoire_pdf', 'resume_francais', 'resume_anglais', 'attestation_plagiat', 'fiche_evaluation_encadreur'];

        foreach ($documents as $doc) {
            if (!empty($this->$doc)) {
                $documentsPresents++;
            }
        }

        return ($documentsPresents / $totalDocuments) * 100;
    }
}
