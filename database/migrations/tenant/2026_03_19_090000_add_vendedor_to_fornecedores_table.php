<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona os campos de vendedor e contato do vendedor na tabela de fornecedores.
     */
    public function up(): void
    {
        if (!Schema::hasTable('fornecedores')) {
            return;
        }

        Schema::table('fornecedores', function (Blueprint $table) {
            if (!Schema::hasColumn('fornecedores', 'vendedor')) {
                $table->string('vendedor')->nullable()->after('email');
            }

            if (!Schema::hasColumn('fornecedores', 'contato_vendedor')) {
                $table->string('contato_vendedor')->nullable()->after('vendedor');
            }
        });
    }

    /**
     * Remove os campos de vendedor e contato do vendedor da tabela de fornecedores.
     */
    public function down(): void
    {
        if (!Schema::hasTable('fornecedores')) {
            return;
        }

        Schema::table('fornecedores', function (Blueprint $table) {
            if (Schema::hasColumn('fornecedores', 'contato_vendedor')) {
                $table->dropColumn('contato_vendedor');
            }

            if (Schema::hasColumn('fornecedores', 'vendedor')) {
                $table->dropColumn('vendedor');
            }
        });
    }
};
