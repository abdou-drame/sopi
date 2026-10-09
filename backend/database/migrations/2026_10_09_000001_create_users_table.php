<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('prenom');
            // Numéro sénégalais normalisé au format E.164 (+221XXXXXXXXX).
            $table->string('telephone', 20)->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('role', 20)->default('client');
            $table->string('statut', 30)->default('actif');
            $table->timestamps();

            $table->index(['role', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
