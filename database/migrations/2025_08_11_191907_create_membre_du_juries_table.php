<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('membre_du_juries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('grade_academique');
            $table->string('specialite');
            $table->string('etablissement'); // ISI ou externe
            $table->boolean('est_externe')->default(false);
            $table->enum('statut_disponibilite', ['disponible', 'occupe', 'indisponible'])->default('disponible');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('membre_du_juries');
    }
};
