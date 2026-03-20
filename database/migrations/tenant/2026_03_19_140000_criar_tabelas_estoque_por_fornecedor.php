<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as tabelas auxiliares de saldo por lote e consumo FIFO por referencia.
     */
    public function up(): void
    {
        $this->criar_tabela_produto_estoque_lotes();
        $this->criar_tabela_produto_estoque_consumos();
        $this->popular_saldos_legados();
    }

    /**
     * Remove as tabelas auxiliares de saldo por lote e consumo FIFO.
     */
    public function down(): void
    {
        Schema::dropIfExists('produto_estoque_consumos');
        Schema::dropIfExists('produto_estoque_lotes');
    }

    /**
     * Cria a tabela que guarda os saldos atuais do produto por lote de origem.
     */
    private function criar_tabela_produto_estoque_lotes(): void
    {
        if (Schema::hasTable('produto_estoque_lotes')) {
            return;
        }

        Schema::create('produto_estoque_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('estoque_entrada_id')->nullable()->constrained('estoque_entradas')->nullOnDelete();
            $table->string('modo_origem')->default('saldo_legado');
            $table->date('data_validade')->nullable();
            $table->integer('quantidade_inicial')->default(0);
            $table->integer('quantidade_atual')->default(0);
            $table->decimal('custo_unitario_medio', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['produto_id', 'quantidade_atual'], 'produto_estoque_lotes_produto_quantidade_idx');
            $table->index(['produto_id', 'fornecedor_id'], 'produto_estoque_lotes_produto_fornecedor_idx');
        });
    }

    /**
     * Cria a tabela que rastreia o consumo FIFO por item de venda, perda ou ajuste.
     */
    private function criar_tabela_produto_estoque_consumos(): void
    {
        if (Schema::hasTable('produto_estoque_consumos')) {
            return;
        }

        Schema::create('produto_estoque_consumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('produto_estoque_lote_id')->constrained('produto_estoque_lotes')->cascadeOnDelete();
            $table->string('referencia_tipo');
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->integer('quantidade');
            $table->decimal('custo_unitario_medio', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['referencia_tipo', 'referencia_id'], 'produto_estoque_consumos_referencia_idx');
            $table->index(['produto_id', 'produto_estoque_lote_id'], 'produto_estoque_consumos_produto_lote_idx');
        });
    }

    /**
     * Cria um lote legado para o saldo atual de cada produto ja existente.
     */
    private function popular_saldos_legados(): void
    {
        if (!Schema::hasTable('produtos') || !Schema::hasTable('produto_estoque_lotes')) {
            return;
        }

        $produtos = DB::table('produtos')
            ->whereNull('deleted_at')
            ->where('estoque_atual', '>', 0)
            ->get([
                'id',
                'estoque_atual',
                'preco_custo_medio',
                'data_validade',
                'created_at',
                'updated_at',
            ]);

        foreach ($produtos as $produto) {
            $lote_existente = DB::table('produto_estoque_lotes')
                ->where('produto_id', $produto->id)
                ->exists();

            if ($lote_existente) {
                continue;
            }

            DB::table('produto_estoque_lotes')->insert([
                'produto_id' => $produto->id,
                'fornecedor_id' => null,
                'estoque_entrada_id' => null,
                'modo_origem' => 'saldo_legado',
                'data_validade' => $produto->data_validade,
                'quantidade_inicial' => (int) $produto->estoque_atual,
                'quantidade_atual' => (int) $produto->estoque_atual,
                'custo_unitario_medio' => (float) ($produto->preco_custo_medio ?? 0),
                'created_at' => $produto->created_at ?? now(),
                'updated_at' => $produto->updated_at ?? now(),
            ]);
        }
    }
};
