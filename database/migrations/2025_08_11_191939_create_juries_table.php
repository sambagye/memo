<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('juries', function (Blueprint $table) {
            $table->id();
            $table->string('nom_jury');
            $table->foreignId('president_id')->constrained('membre_du_juries')->onDelete('cascade');
            $table->foreignId('rapporteur_id')->constrained('membre_du_juries')->onDelete('cascade');
            $table->foreignId('examinateur_id')->constrained('membre_du_juries')->onDelete('cascade');
            $table->foreignId('encadreur_id')->constrained()->onDelete('cascade'); // Membre invité
            $table->date('date_creation');
            $table->enum('statut', ['constitue', 'actif', 'termine'])->default('constitue');
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('juries');
    }
};
