<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardapio_config', function (Blueprint $table) {
            $table->id();
            $table->string('nome_exibicao')->default('Nosso Cardapio');
            $table->string('subtitulo')->nullable();
            $table->text('mensagem_boas_vindas')->nullable();
            $table->string('cor_primaria')->default('#3B82F6');
            $table->string('cor_destaque')->default('#10B981');
            $table->string('cor_fundo')->default('#FFF7ED');
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardapio_config');
    }
};
