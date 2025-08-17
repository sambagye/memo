<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'nom' => 'DIOP',
            'prenom' => 'Amadou',
            'email' => 'admin@isi.sn',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'telephone' => '77123456789',
            'email_verified_at' => now(),
        ]);

        // Encadreurs
        $encadreurs = [
            ['nom' => 'NDIAYE', 'prenom' => 'Fatou', 'email' => 'fatou.ndiaye@isi.sn'],
            ['nom' => 'FALL', 'prenom' => 'Moussa', 'email' => 'moussa.fall@isi.sn'],
            ['nom' => 'SARR', 'prenom' => 'Aminata', 'email' => 'aminata.sarr@isi.sn'],
            ['nom' => 'BA', 'prenom' => 'Ousmane', 'email' => 'ousmane.ba@isi.sn'],
            ['nom' => 'WADE', 'prenom' => 'Aïssatou', 'email' => 'aissatou.wade@isi.sn'],
        ];

        foreach ($encadreurs as $encadreur) {
            User::create([
                'nom' => $encadreur['nom'],
                'prenom' => $encadreur['prenom'],
                'email' => $encadreur['email'],
                'password' => Hash::make('password123'),
                'role' => 'encadreur',
                'telephone' => '77' . rand(1000000, 9999999),
                'email_verified_at' => now(),
            ]);
        }

        // Étudiants
        $etudiants = [
            ['nom' => 'SECK', 'prenom' => 'Mamadou', 'email' => 'mamadou.seck@etud.isi.sn'],
            ['nom' => 'KANE', 'prenom' => 'Khadija', 'email' => 'khadija.kane@etud.isi.sn'],
            ['nom' => 'GUEYE', 'prenom' => 'Ibrahima', 'email' => 'ibrahima.gueye@etud.isi.sn'],
            ['nom' => 'SOW', 'prenom' => 'Mariama', 'email' => 'mariama.sow@etud.isi.sn'],
            ['nom' => 'DIOUF', 'prenom' => 'Cheikh', 'email' => 'cheikh.diouf@etud.isi.sn'],
            ['nom' => 'THIAM', 'prenom' => 'Ndeye', 'email' => 'ndeye.thiam@etud.isi.sn'],
            ['nom' => 'LY', 'prenom' => 'Abdoulaye', 'email' => 'abdoulaye.ly@etud.isi.sn'],
            ['nom' => 'DIALLO', 'prenom' => 'Aissata', 'email' => 'aissata.diallo@etud.isi.sn'],
        ];

        foreach ($etudiants as $etudiant) {
            User::create([
                'nom' => $etudiant['nom'],
                'prenom' => $etudiant['prenom'],
                'email' => $etudiant['email'],
                'password' => Hash::make('password123'),
                'role' => 'etudiant',
                'telephone' => '70' . rand(1000000, 9999999),
                'email_verified_at' => now(),
            ]);
        }

        // Membres du jury
        $membresJury = [
            ['nom' => 'CISSE', 'prenom' => 'Professeur Alioune', 'email' => 'alioune.cisse@isi.sn'],
            ['nom' => 'MBAYE', 'prenom' => 'Dr. Bineta', 'email' => 'bineta.mbaye@isi.sn'],
            ['nom' => 'TOURE', 'prenom' => 'Professeur Omar', 'email' => 'omar.toure@isi.sn'],
            ['nom' => 'NDOUR', 'prenom' => 'Dr. Mame', 'email' => 'mame.ndour@isi.sn'],
        ];

        foreach ($membresJury as $membre) {
            User::create([
                'nom' => $membre['nom'],
                'prenom' => $membre['prenom'],
                'email' => $membre['email'],
                'password' => Hash::make('password123'),
                'role' => 'membre_jury',
                'telephone' => '76' . rand(1000000, 9999999),
                'email_verified_at' => now(),
            ]);
        }
    }
}
