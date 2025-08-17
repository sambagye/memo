<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seance_encadrements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affectation_id')->constrained()->onDelete('cascade');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->datetime('date_heure');
            $table->integer('duree_minutes');
            $table->string('lieu')->nullable();
            $table->string('lien_meeting')->nullable(); // Pour les séances en ligne
            $table->enum('statut', ['programmee', 'en_cours', 'terminee', 'annulee'])->default('programmee');
            $table->text('compte_rendu')->nullable();
            $table->text('travail_a_faire')->nullable();
            $table->timestamp('date_realisation')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('seance_encadrements');
    }
};
