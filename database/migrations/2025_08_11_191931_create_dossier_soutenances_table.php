<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dossier_soutenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained()->onDelete('cascade');
            $table->foreignId('affectation_id')->constrained()->onDelete('cascade');
            $table->boolean('autorisation_encadreur')->default(false);
            $table->timestamp('date_autorisation')->nullable();

            // Les 5 documents obligatoires
            $table->string('memoire_pdf')->nullable();
            $table->string('resume_francais')->nullable();
            $table->string('resume_anglais')->nullable();
            $table->string('attestation_plagiat')->nullable();
            $table->string('fiche_evaluation_encadreur')->nullable();

            $table->boolean('dossier_complet')->default(false);
            $table->timestamp('date_soumission')->nullable();
            $table->enum('statut_verification', ['en_attente', 'verifie', 'incomplet'])->default('en_attente');
            $table->text('commentaire_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dossier_soutenances');
    }
};
