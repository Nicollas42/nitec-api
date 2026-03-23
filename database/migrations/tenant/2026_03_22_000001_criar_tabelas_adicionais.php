<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_adicionais', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->integer('maximo_selecoes')->default(0); // 0 = ilimitado
            $table->timestamps();
        });

        Schema::create('itens_adicionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_adicional_id')->constrained('grupos_adicionais')->cascadeOnDelete();
            $table->string('nome');
            $table->decimal('preco', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('produto_grupos_adicionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('grupo_adicional_id')->constrained('grupos_adicionais')->cascadeOnDelete();
            $table->unique(['produto_id', 'grupo_adicional_id']);
        });

        Schema::create('comanda_item_adicionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_item_id')->constrained('comanda_itens')->cascadeOnDelete();
            $table->foreignId('item_adicional_id')->constrained('itens_adicionais');
            $table->integer('quantidade')->default(1);
            $table->decimal('preco_unitario', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_item_adicionais');
        Schema::dropIfExists('produto_grupos_adicionais');
        Schema::dropIfExists('itens_adicionais');
        Schema::dropIfExists('grupos_adicionais');
    }
};
