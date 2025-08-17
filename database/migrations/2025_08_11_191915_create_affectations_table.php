<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained()->onDelete('cascade');
            $table->foreignId('sujet_id')->constrained()->onDelete('cascade');
            $table->foreignId('encadreur_id')->constrained()->onDelete('cascade');
            $table->integer('ordre_preference_etudiant'); // 1er, 2ème, 3ème choix
            $table->enum('statut', ['en_attente', 'affecte', 'refuse'])->default('en_attente');
            $table->timestamp('date_affectation')->nullable();
            $table->text('commentaire_admin')->nullable();
            $table->timestamps();

            // Un étudiant ne peut être affecté qu'à un seul sujet
            $table->unique('etudiant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('affectations');
    }
};
