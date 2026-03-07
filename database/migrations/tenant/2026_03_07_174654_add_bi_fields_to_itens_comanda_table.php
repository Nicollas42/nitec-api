<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 🟢 Alterado para 'comanda_itens' (nome comum em migrações manuais em PT-BR)
        Schema::table('comanda_itens', function (Blueprint $table) {
            $table->timestamp('data_hora_lancamento')->useCurrent()->after('preco_unitario'); 
        });
    }

    public function down()
    {
        Schema::table('comanda_itens', function (Blueprint $table) {
            $table->dropColumn('data_hora_lancamento');
        });
    }
};