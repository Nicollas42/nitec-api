<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    // Verifica se a coluna NÃO existe antes de tentar criar
    if (!Schema::hasColumn('produtos', 'codigo_barras')) {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('codigo_barras')->nullable()->after('nome_produto');
        });
    }
}

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('codigo_barras');
        });
    }
};