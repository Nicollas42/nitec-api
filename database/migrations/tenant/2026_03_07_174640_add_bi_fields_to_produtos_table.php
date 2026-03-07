<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('categoria')->default('Geral')->after('nome_produto');
            $table->decimal('preco_custo', 10, 2)->nullable()->after('preco_venda');
            $table->softDeletes(); // Adiciona a coluna 'deleted_at'
        });
    }

    public function down()
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'preco_custo']);
            $table->dropSoftDeletes();
        });
    }
};