<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sujets', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description');
            $table->text('objectifs');
            $table->text('prerequis')->nullable();
            $table->string('domaine');
            $table->enum('niveau', ['L3', 'M1', 'M2']);
            $table->integer('nombre_places_disponibles');
            $table->integer('nombre_places_occupees')->default(0);
            $table->foreignId('encadreur_id')->constrained()->onDelete('cascade');
            $table->enum('statut', ['propose', 'valide', 'refuse', 'complet'])->default('propose');
            $table->text('commentaire_admin')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sujets');
    }
};
