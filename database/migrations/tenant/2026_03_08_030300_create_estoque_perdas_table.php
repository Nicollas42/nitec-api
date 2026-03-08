<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_perdas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade'); // Quem registrou
            $table->integer('quantidade');
            $table->string('motivo'); // Quebra, Vencimento, Roubo, Consumo Interno
            $table->decimal('custo_total_perda', 10, 2); // qtd * preco_custo
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('estoque_perdas');
    }
};
