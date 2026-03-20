<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_entradas', function (Blueprint $table): void {
            if (!Schema::hasColumn('estoque_entradas', 'numero_nf')) {
                $table->string('numero_nf', 50)->nullable()->after('fornecedor_id');
            }
            if (!Schema::hasColumn('estoque_entradas', 'data_emissao_nf')) {
                $table->date('data_emissao_nf')->nullable()->after('numero_nf');
            }
            if (!Schema::hasColumn('estoque_entradas', 'chave_nfe')) {
                $table->string('chave_nfe', 44)->nullable()->unique()->after('data_emissao_nf');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estoque_entradas', function (Blueprint $table): void {
            $table->dropColumn(array_filter([
                Schema::hasColumn('estoque_entradas', 'numero_nf')      ? 'numero_nf'      : null,
                Schema::hasColumn('estoque_entradas', 'data_emissao_nf') ? 'data_emissao_nf' : null,
                Schema::hasColumn('estoque_entradas', 'chave_nfe')      ? 'chave_nfe'      : null,
            ]));
        });
    }
};