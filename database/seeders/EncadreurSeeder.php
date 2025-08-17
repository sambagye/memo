<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Encadreur;
use App\Models\User;

class EncadreurSeeder extends Seeder
{
    public function run(): void
    {
        $encadreursData = [
            [
                'email' => 'fatou.ndiaye@isi.sn',
                'specialite' => 'Intelligence Artificielle',
                'grade_academique' => 'Professeur',
                'nombre_max_etudiants' => 8,
                'bio' => 'Spécialiste en IA et Machine Learning avec 15 ans d\'expérience.',
            ],
            [
                'email' => 'moussa.fall@isi.sn',
                'specialite' => 'Développement Web',
                'grade_academique' => 'Maître de Conférences',
                'nombre_max_etudiants' => 6,
                'bio' => 'Expert en technologies web modernes et architectures distribuées.',
            ],
            [
                'email' => 'aminata.sarr@isi.sn',
                'specialite' => 'Sécurité Informatique',
                'grade_academique' => 'Docteur',
                'nombre_max_etudiants' => 5,
                'bio' => 'Experte en cybersécurité et cryptographie appliquée.',
            ],
            [
                'email' => 'ousmane.ba@isi.sn',
                'specialite' => 'Systèmes et Réseaux',
                'grade_academique' => 'Professeur Associé',
                'nombre_max_etudiants' => 7,
                'bio' => 'Spécialiste des systèmes distribués et administration réseaux.',
            ],
            [
                'email' => 'aissatou.wade@isi.sn',
                'specialite' => 'Bases de Données',
                'grade_academique' => 'Maître de Conférences',
                'nombre_max_etudiants' => 6,
                'bio' => 'Experte en conception et optimisation de bases de données.',
            ],
        ];

        foreach ($encadreursData as $data) {
            $user = User::where('email', $data['email'])->first();
            if ($user) {
                Encadreur::create([
                    'user_id' => $user->id,
                    'specialite' => $data['specialite'],
                    'grade_academique' => $data['grade_academique'],
                    'nombre_max_etudiants' => $data['nombre_max_etudiants'],
                    'bio' => $data['bio'],
                ]);
            }
        }
    }
}
