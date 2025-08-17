<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Etudiant;
use App\Models\User;

class EtudiantSeeder extends Seeder
{
    public function run(): void
    {
        $etudiantsData = [
            [
                'email' => 'mamadou.seck@etud.isi.sn',
                'numero_etudiant' => 'ISI2024001',
                'niveau' => 'M2',
                'filiere' => 'Génie Logiciel',
            ],
            [
                'email' => 'khadija.kane@etud.isi.sn',
                'numero_etudiant' => 'ISI2024002',
                'niveau' => 'M2',
                'filiere' => 'Intelligence Artificielle',
            ],
            [
                'email' => 'ibrahima.gueye@etud.isi.sn',
                'numero_etudiant' => 'ISI2024003',
                'niveau' => 'M1',
                'filiere' => 'Sécurité Informatique',
            ],
            [
                'email' => 'mariama.sow@etud.isi.sn',
                'numero_etudiant' => 'ISI2024004',
                'niveau' => 'M2',
                'filiere' => 'Systèmes et Réseaux',
            ],
            [
                'email' => 'cheikh.diouf@etud.isi.sn',
                'numero_etudiant' => 'ISI2024005',
                'niveau' => 'L3',
                'filiere' => 'Informatique Générale',
            ],
            [
                'email' => 'ndeye.thiam@etud.isi.sn',
                'numero_etudiant' => 'ISI2024006',
                'niveau' => 'M1',
                'filiere' => 'Développement Web',
            ],
            [
                'email' => 'abdoulaye.ly@etud.isi.sn',
                'numero_etudiant' => 'ISI2024007',
                'niveau' => 'M2',
                'filiere' => 'Bases de Données',
            ],
            [
                'email' => 'aissata.diallo@etud.isi.sn',
                'numero_etudiant' => 'ISI2024008',
                'niveau' => 'L3',
                'filiere' => 'Informatique Générale',
            ],
        ];

        foreach ($etudiantsData as $data) {
            $user = User::where('email', $data['email'])->first();
            if ($user) {
                Etudiant::create([
                    'user_id' => $user->id,
                    'numero_etudiant' => $data['numero_etudiant'],
                    'niveau' => $data['niveau'],
                    'filiere' => $data['filiere'],
                    'annee_academique' => 2024,
                ]);
            }
        }
    }
}
