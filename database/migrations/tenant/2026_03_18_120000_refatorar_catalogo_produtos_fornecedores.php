<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a refatoracao do catalogo de produtos, fornecedores e entradas.
     */
    public function up(): void
    {
        $this->garantir_estrutura_produtos();
        $this->criar_tabela_produto_codigos_barras();
        $this->migrar_codigo_barras_legado();
        $this->criar_tabela_fornecedores();
        $this->criar_tabela_produto_fornecedor();
        $this->ajustar_tabela_estoque_entradas();
        $this->remover_colunas_legadas_produtos();
    }

    /**
     * Reverte a refatoracao do catalogo para o formato anterior.
     */
    public function down(): void
    {
        if (Schema::hasTable('produtos')) {
            Schema::table('produtos', function (Blueprint $table) {
                if (!Schema::hasColumn('produtos', 'codigo_barras')) {
                    $table->string('codigo_barras')->nullable()->after('nome_produto');
                }

                if (!Schema::hasColumn('produtos', 'preco_custo')) {
                    $table->decimal('preco_custo', 10, 2)->nullable()->after('preco_venda');
                }
            });

            if (Schema::hasTable('produto_codigos_barras')) {
                $primeiros_codigos = DB::table('produto_codigos_barras')
                    ->select('produto_id', 'codigo_barras')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('produto_id')
                    ->map(static fn ($registros) => $registros->first());

                foreach ($primeiros_codigos as $produto_id => $registro) {
                    DB::table('produtos')
                        ->where('id', $produto_id)
                        ->update(['codigo_barras' => $registro->codigo_barras]);
                }
            }

            if (Schema::hasColumn('produtos', 'preco_custo_medio')) {
                DB::statement('UPDATE produtos SET preco_custo = preco_custo_medio WHERE preco_custo IS NULL');
            }
        }

        if (Schema::hasTable('estoque_entradas')) {
            Schema::table('estoque_entradas', function (Blueprint $table) {
                if (!Schema::hasColumn('estoque_entradas', 'quantidade_adicionada')) {
                    $table->integer('quantidade_adicionada')->nullable()->after('usuario_id');
                }

                if (!Schema::hasColumn('estoque_entradas', 'fornecedor')) {
                    $table->string('fornecedor')->nullable()->after('custo_unitario_compra');
                }
            });

            if (Schema::hasColumn('estoque_entradas', 'quantidade_comprada')) {
                DB::statement('UPDATE estoque_entradas SET quantidade_adicionada = quantidade_comprada WHERE quantidade_adicionada IS NULL');
            }

            if (Schema::hasColumn('estoque_entradas', 'fornecedor_id') && Schema::hasTable('fornecedores')) {
                $fornecedores = DB::table('fornecedores')->pluck('nome_fantasia', 'id');

                DB::table('estoque_entradas')
                    ->whereNotNull('fornecedor_id')
                    ->orderBy('id')
                    ->get(['id', 'fornecedor_id'])
                    ->each(function ($entrada) use ($fornecedores): void {
                        DB::table('estoque_entradas')
                            ->where('id', $entrada->id)
                            ->update([
                                'fornecedor' => $fornecedores[$entrada->fornecedor_id] ?? null,
                            ]);
                    });
            }

            Schema::table('estoque_entradas', function (Blueprint $table) {
                if (Schema::hasColumn('estoque_entradas', 'fornecedor_id')) {
                    $table->dropConstrainedForeignId('fornecedor_id');
                }

                if (Schema::hasColumn('estoque_entradas', 'quantidade_comprada')) {
                    $table->dropColumn('quantidade_comprada');
                }

                if (Schema::hasColumn('estoque_entradas', 'custo_total_entrada')) {
                    $table->dropColumn('custo_total_entrada');
                }
            });
        }

        Schema::dropIfExists('produto_fornecedor');
        Schema::dropIfExists('produto_codigos_barras');
        Schema::dropIfExists('fornecedores');

        if (Schema::hasTable('produtos')) {
            Schema::table('produtos', function (Blueprint $table) {
                if (Schema::hasColumn('produtos', 'codigo_interno')) {
                    $table->dropUnique('produtos_codigo_interno_unique');
                    $table->dropColumn('codigo_interno');
                }

                if (Schema::hasColumn('produtos', 'preco_custo_medio')) {
                    $table->dropColumn('preco_custo_medio');
                }
            });
        }
    }

    /**
     * Garante as colunas novas da tabela produtos e preenche dados legados.
     */
    private function garantir_estrutura_produtos(): void
    {
        if (!Schema::hasTable('produtos')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $table) {
            if (!Schema::hasColumn('produtos', 'codigo_interno')) {
                $table->string('codigo_interno')->nullable()->unique()->after('nome_produto');
            }

            if (!Schema::hasColumn('produtos', 'preco_venda')) {
                $table->decimal('preco_venda', 10, 2)->default(0)->after('nome_produto');
            }

            if (!Schema::hasColumn('produtos', 'preco_custo_medio')) {
                $table->decimal('preco_custo_medio', 10, 2)->nullable()->after('preco_venda');
            }

            if (!Schema::hasColumn('produtos', 'estoque_atual')) {
                $table->integer('estoque_atual')->default(0)->after('preco_custo_medio');
            }
        });

        DB::statement("UPDATE produtos SET codigo_interno = CAST(id AS CHAR) WHERE codigo_interno IS NULL OR codigo_interno = ''");

        if (Schema::hasColumn('produtos', 'preco_custo')) {
            DB::statement('UPDATE produtos SET preco_custo_medio = COALESCE(preco_custo, 0) WHERE preco_custo_medio IS NULL');
        } else {
            DB::statement('UPDATE produtos SET preco_custo_medio = 0 WHERE preco_custo_medio IS NULL');
        }
    }

    /**
     * Cria a tabela de aliases de codigo de barras por produto.
     */
    private function criar_tabela_produto_codigos_barras(): void
    {
        if (Schema::hasTable('produto_codigos_barras')) {
            return;
        }

        Schema::create('produto_codigos_barras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('codigo_barras')->unique();
            $table->string('descricao_variacao')->nullable();
        });
    }

    /**
     * Migra o codigo de barras legado do produto para a tabela de aliases.
     */
    private function migrar_codigo_barras_legado(): void
    {
        if (!Schema::hasTable('produtos') || !Schema::hasTable('produto_codigos_barras') || !Schema::hasColumn('produtos', 'codigo_barras')) {
            return;
        }

        $produtos_legados = DB::table('produtos')
            ->whereNotNull('codigo_barras')
            ->where('codigo_barras', '!=', '')
            ->get(['id', 'codigo_barras']);

        foreach ($produtos_legados as $produto_legado) {
            DB::table('produto_codigos_barras')->updateOrInsert(
                ['codigo_barras' => $produto_legado->codigo_barras],
                [
                    'produto_id' => $produto_legado->id,
                    'descricao_variacao' => null,
                ]
            );
        }
    }

    /**
     * Cria a tabela de fornecedores.
     */
    private function criar_tabela_fornecedores(): void
    {
        if (Schema::hasTable('fornecedores')) {
            return;
        }

        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->string('nome_fantasia');
            $table->string('razao_social');
            $table->string('cnpj')->unique();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('status_fornecedor')->default('ativo');
            $table->timestamps();
        });
    }

    /**
     * Cria a tabela pivot editavel entre produto e fornecedor.
     */
    private function criar_tabela_produto_fornecedor(): void
    {
        if (Schema::hasTable('produto_fornecedor')) {
            return;
        }

        Schema::create('produto_fornecedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->cascadeOnDelete();
            $table->string('codigo_sku_fornecedor');
            $table->integer('fator_conversao')->default(1);
            $table->decimal('ultimo_preco_compra', 10, 2)->nullable();
            $table->unique(['produto_id', 'fornecedor_id']);
        });
    }

    /**
     * Ajusta a tabela de entradas de estoque para o novo contrato.
     */
    private function ajustar_tabela_estoque_entradas(): void
    {
        if (!Schema::hasTable('estoque_entradas')) {
            Schema::create('estoque_entradas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
                $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
                $table->integer('quantidade_comprada');
                $table->decimal('custo_unitario_compra', 10, 2);
                $table->decimal('custo_total_entrada', 12, 2);
                $table->timestamps();
            });

            return;
        }

        Schema::table('estoque_entradas', function (Blueprint $table) {
            if (!Schema::hasColumn('estoque_entradas', 'fornecedor_id')) {
                $table->foreignId('fornecedor_id')->nullable()->after('produto_id')->constrained('fornecedores')->nullOnDelete();
            }

            if (!Schema::hasColumn('estoque_entradas', 'quantidade_comprada')) {
                $table->integer('quantidade_comprada')->nullable()->after('usuario_id');
            }

            if (!Schema::hasColumn('estoque_entradas', 'custo_total_entrada')) {
                $table->decimal('custo_total_entrada', 12, 2)->nullable()->after('custo_unitario_compra');
            }
        });

        if (Schema::hasColumn('estoque_entradas', 'quantidade_adicionada')) {
            DB::statement('UPDATE estoque_entradas SET quantidade_comprada = quantidade_adicionada WHERE quantidade_comprada IS NULL');
        }

        DB::statement('UPDATE estoque_entradas SET custo_total_entrada = quantidade_comprada * custo_unitario_compra WHERE custo_total_entrada IS NULL');

        Schema::table('estoque_entradas', function (Blueprint $table) {
            if (Schema::hasColumn('estoque_entradas', 'quantidade_adicionada')) {
                $table->dropColumn('quantidade_adicionada');
            }

            if (Schema::hasColumn('estoque_entradas', 'fornecedor')) {
                $table->dropColumn('fornecedor');
            }
        });
    }

    /**
     * Remove as colunas legadas de produtos depois do backfill.
     */
    private function remover_colunas_legadas_produtos(): void
    {
        if (!Schema::hasTable('produtos')) {
            return;
        }

        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'codigo_barras')) {
                $table->dropColumn('codigo_barras');
            }

            if (Schema::hasColumn('produtos', 'preco_custo')) {
                $table->dropColumn('preco_custo');
            }
        });
    }
};
