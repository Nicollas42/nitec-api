<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona os campos mestre de unidade e margem ao cadastro de produtos.
     */
    public function up(): void
    {
        if (!Schema::hasTable('produtos')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $table) {
            if (!Schema::hasColumn('produtos', 'unidade_medida')) {
                $table->string('unidade_medida', 10)->default('UN')->after('categoria');
            }

            if (!Schema::hasColumn('produtos', 'margem_lucro_percentual')) {
                $table->decimal('margem_lucro_percentual', 8, 2)->default(0)->after('preco_custo_medio');
            }
        });

        DB::statement("UPDATE produtos SET unidade_medida = 'UN' WHERE unidade_medida IS NULL OR unidade_medida = ''");
        DB::statement('UPDATE produtos SET margem_lucro_percentual = 0 WHERE margem_lucro_percentual IS NULL');
    }

    /**
     * Remove os campos mestre adicionados ao cadastro de produtos.
     */
    public function down(): void
    {
        if (!Schema::hasTable('produtos')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'unidade_medida')) {
                $table->dropColumn('unidade_medida');
            }

            if (Schema::hasColumn('produtos', 'margem_lucro_percentual')) {
                $table->dropColumn('margem_lucro_percentual');
            }
        });
    }
};
