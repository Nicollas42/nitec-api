<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->timestamp('data_hora_abertura')->nullable()->after('status_comanda');
            $table->timestamp('data_hora_fechamento')->nullable()->after('data_hora_abertura');
            $table->softDeletes(); // Adiciona a coluna 'deleted_at'
        });
    }

    public function down()
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn(['data_hora_abertura', 'data_hora_fechamento']);
            $table->dropSoftDeletes();
        });
    }
};
