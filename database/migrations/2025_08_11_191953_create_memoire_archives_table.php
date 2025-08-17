<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('memoire_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soutenance_id')->constrained()->onDelete('cascade');
            $table->string('titre_memoire');
            $table->string('nom_etudiant');
            $table->string('prenom_etudiant');
            $table->string('nom_encadreur');
            $table->year('annee_soutenance');
            $table->string('niveau');
            $table->string('filiere');
            $table->enum('mention', ['passable', 'assez_bien', 'bien', 'tres_bien', 'excellent']);
            $table->decimal('note_finale', 4, 2);
            $table->string('fichier_memoire'); // Chemin vers le PDF
            $table->text('resume_francais')->nullable();
            $table->text('resume_anglais')->nullable();
            $table->string('mots_cles')->nullable();
            $table->integer('nombre_telechargements')->default(0);
            $table->boolean('visible_public')->default(true);
            $table->timestamp('date_archivage');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('memoire_archives');
    }
};
