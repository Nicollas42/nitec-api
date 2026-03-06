<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            // Cria a coluna e diz que por padrão toda conta nasce como 'geral'
            $table->string('tipo_conta')->default('geral')->after('status_comanda');
        });
    }

    public function down(): void
    {
        Schema::table('comandas', function (Blueprint $table) {
            $table->dropColumn('tipo_conta');
        });
    }
};