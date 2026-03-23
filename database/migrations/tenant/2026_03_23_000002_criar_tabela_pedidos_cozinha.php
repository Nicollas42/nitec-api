<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_cozinha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comanda_item_id')->constrained('comanda_itens')->cascadeOnDelete();
            $table->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();
            $table->foreignId('mesa_id')->nullable()->constrained('mesas')->nullOnDelete();
            $table->string('produto_nome');
            $table->text('adicionais_texto')->nullable();
            $table->integer('quantidade');
            $table->enum('status', ['pendente', 'em_preparacao', 'finalizado'])->default('pendente');
            $table->boolean('visto_pelo_garcom')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_cozinha');
    }
};
