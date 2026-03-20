<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            if (!Schema::hasColumn('produtos', 'unidade_medida')) {
                $table->string('unidade_medida', 10)->default('UN')->after('codigo_interno');
            }
            if (!Schema::hasColumn('produtos', 'margem_lucro_percentual')) {
                $table->decimal('margem_lucro_percentual', 8, 2)->nullable()->default(0)->after('preco_custo_medio');
            }
        });

        Schema::table('produto_fornecedor', function (Blueprint $table): void {
            if (!Schema::hasColumn('produto_fornecedor', 'unidade_embalagem')) {
                $table->string('unidade_embalagem', 10)->default('CX')->after('codigo_sku_fornecedor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            if (Schema::hasColumn('produtos', 'margem_lucro_percentual')) {
                $table->dropColumn('margem_lucro_percentual');
            }
        });

        Schema::table('produto_fornecedor', function (Blueprint $table): void {
            if (Schema::hasColumn('produto_fornecedor', 'unidade_embalagem')) {
                $table->dropColumn('unidade_embalagem');
            }
        });
    }
};