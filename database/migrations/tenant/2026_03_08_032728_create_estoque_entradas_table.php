<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_entradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users'); // Quem registrou a compra
            $table->integer('quantidade_adicionada');
            $table->decimal('custo_unitario_compra', 10, 2); // Congela o valor pago no dia da compra
            $table->string('fornecedor')->nullable(); // Ex: "Ambev", "Mercado Local"
            $table->timestamps();
        });
    }
};
