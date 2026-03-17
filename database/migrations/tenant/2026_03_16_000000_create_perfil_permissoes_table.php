<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('perfil_permissoes', function (Blueprint $table) {
            $table->id();
            $table->string('perfil')->unique(); // 'caixa', 'garcom', 'gerente', etc.
            $table->json('permissoes'); // JSON com { "acessar_pdv": true, "acessar_mesas": false... }
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfil_permissoes');
    }
};
