<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MembreDuJury;
use App\Models\User;

class MembreDuJurySeeder extends Seeder
{
    public function run(): void
    {
        $membresJuryData = [
            [
                'email' => 'alioune.cisse@isi.sn',
                'grade_academique' => 'Professeur Titulaire',
                'specialite' => 'Intelligence Artificielle',
                'etablissement' => 'ISI',
                'est_externe' => false,
            ],
            [
                'email' => 'bineta.mbaye@isi.sn',
                'grade_academique' => 'Docteur',
                'specialite' => 'Génie Logiciel',
                'etablissement' => 'ISI',
                'est_externe' => false,
            ],
            [
                'email' => 'omar.toure@isi.sn',
                'grade_academique' => 'Professeur',
                'specialite' => 'Systèmes Distribués',
                'etablissement' => 'ISI',
                'est_externe' => false,
            ],
            [
                'email' => 'mame.ndour@isi.sn',
                'grade_academique' => 'Docteur HDR',
                'specialite' => 'Sécurité Informatique',
                'etablissement' => 'ISI',
                'est_externe' => false,
            ],
        ];

        foreach ($membresJuryData as $data) {
            $user = User::where('email', $data['email'])->first();
            if ($user) {
                MembreDuJury::create([
                    'user_id' => $user->id,
                    'grade_academique' => $data['grade_academique'],
                    'specialite' => $data['specialite'],
                    'etablissement' => $data['etablissement'],
                    'est_externe' => $data['est_externe'],
                ]);
            }
        }

        // Ajouter quelques membres externes
        $membresExternes = [
            [
                'nom' => 'NGOM',
                'prenom' => 'Professeur Saliou',
                'email' => 'saliou.ngom@ucad.sn',
                'grade_academique' => 'Professeur Titulaire',
                'specialite' => 'Intelligence Artificielle',
                'etablissement' => 'UCAD - FST',
                'est_externe' => true,
            ],
            [
                'nom' => 'FAYE',
                'prenom' => 'Dr. Awa',
                'email' => 'awa.faye@esp.sn',
                'grade_academique' => 'Docteur',
                'specialite' => 'Cybersécurité',
                'etablissement' => 'ESP Thiès',
                'est_externe' => true,
            ],
        ];

        foreach ($membresExternes as $membre) {
            // Créer l'utilisateur externe
            $user = User::create([
                'nom' => $membre['nom'],
                'prenom' => $membre['prenom'],
                'email' => $membre['email'],
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'membre_jury',
                'telephone' => '77' . rand(1000000, 9999999),
                'email_verified_at' => now(),
            ]);

            // Créer le membre du jury
            MembreDuJury::create([
                'user_id' => $user->id,
                'grade_academique' => $membre['grade_academique'],
                'specialite' => $membre['specialite'],
                'etablissement' => $membre['etablissement'],
                'est_externe' => $membre['est_externe'],
            ]);
        }
    }
}
