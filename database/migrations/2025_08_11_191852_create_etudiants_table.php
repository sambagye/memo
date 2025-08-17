<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('numero_etudiant')->unique();
            $table->string('niveau'); // L3, M1, M2, etc.
            $table->string('filiere');
            $table->year('annee_academique');
            $table->enum('statut_memoire', [
                'en_attente_sujet',
                'sujet_choisi',
                'affecte',
                'en_cours_encadrement',
                'autorise_soutenance',
                'dossier_soumis',
                'soutenance_programmee',
                'soutenu',
                'archive'
            ])->default('en_attente_sujet');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('etudiants');
    }
};
