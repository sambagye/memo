<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('soutenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained()->onDelete('cascade');
            $table->foreignId('jury_id')->constrained()->onDelete('cascade');
            $table->foreignId('dossier_soutenance_id')->constrained()->onDelete('cascade');
            $table->datetime('date_heure_soutenance');
            $table->string('salle');
            $table->integer('duree_minutes')->default(60);
            $table->enum('statut', ['programmee', 'en_cours', 'terminee', 'reportee'])->default('programmee');

            // Évaluations
            $table->decimal('note_president', 4, 2)->nullable();
            $table->decimal('note_rapporteur', 4, 2)->nullable();
            $table->decimal('note_examinateur', 4, 2)->nullable();
            $table->decimal('note_encadreur', 4, 2)->nullable();
            $table->decimal('note_finale', 4, 2)->nullable();

            $table->enum('mention', ['passable', 'assez_bien', 'bien', 'tres_bien', 'excellent'])->nullable();
            $table->text('appreciation_generale')->nullable();
            $table->text('recommandations')->nullable();
            $table->timestamp('date_deliberation')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('soutenances');
    }
};
