<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Criar tabela de Perdas (Auditoria)
        if (!Schema::hasTable('estoque_perdas')) {
            Schema::create('estoque_perdas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
                $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
                $table->integer('quantidade');
                $table->string('motivo');
                $table->decimal('custo_total_perda', 10, 2);
                $table->timestamps();
            });
        }

        // 2. Criar tabela de Entradas (Auditoria)
        if (!Schema::hasTable('estoque_entradas')) {
            Schema::create('estoque_entradas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
                $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
                $table->integer('quantidade_adicionada');
                $table->decimal('custo_unitario_compra', 10, 2);
                $table->string('fornecedor')->nullable();
                $table->timestamps();
            });
        }

        // 3. Atualizar tabela de Produtos (Adicionar Código de Barras, Validade e SoftDeletes)
        Schema::table('produtos', function (Blueprint $table) {
            if (!Schema::hasColumn('produtos', 'codigo_barras')) {
                $table->string('codigo_barras')->nullable()->after('categoria');
            }
            if (!Schema::hasColumn('produtos', 'data_validade')) {
                $table->date('data_validade')->nullable()->after('estoque_atual');
            }
            if (!Schema::hasColumn('produtos', 'deleted_at')) {
                $table->softDeletes(); // Necessário para o seu BI funcionar sem quebrar histórico
            }
        });

        // 4. Atualizar tabela de Comandas (Adicionar campo Desconto)
        Schema::table('comandas', function (Blueprint $table) {
            if (!Schema::hasColumn('comandas', 'desconto')) {
                $table->decimal('desconto', 10, 2)->default(0)->after('valor_total');
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('estoque_perdas');
        Schema::dropIfExists('estoque_entradas');
        
        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'codigo_barras')) $table->dropColumn('codigo_barras');
            if (Schema::hasColumn('produtos', 'data_validade')) $table->dropColumn('data_validade');
            if (Schema::hasColumn('produtos', 'deleted_at')) $table->dropSoftDeletes();
        });

        Schema::table('comandas', function (Blueprint $table) {
            if (Schema::hasColumn('comandas', 'desconto')) $table->dropColumn('desconto');
        });
    }
};