<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Mesa;
use App\Models\Produto;
use App\Models\Fornecedor;
use App\Models\ProdutoCodigoBarras;
use App\Models\ProdutoFornecedor;
use App\Models\GrupoAdicional;
use App\Models\ItemAdicional;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\EstoqueEntrada;
use App\Models\EstoquePerda;

class TenantFakeDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🧹 Limpando dados antigos...');
        $this->limpar_tabelas();

        $data_inicial = Carbon::now()->subDays(365);

        $this->command->info('👥 Criando funcionários...');
        $equipe      = $this->criar_funcionarios();
        $garcons_ids = array_values(array_map(
            fn($u) => $u->id,
            array_filter($equipe, fn($u) => $u->tipo_usuario === 'garcom')
        ));

        $this->command->info('🪑 Criando 20 mesas...');
        $mesas_ids = $this->criar_mesas();

        $this->command->info('🏭 Criando fornecedor...');
        $fornecedor = $this->criar_fornecedor();

        $this->command->info('➕ Criando grupos e itens de adicionais...');
        [$grupos_por_nome, $itens_por_grupo_id] = $this->criar_grupos_adicionais();

        $this->command->info('📦 Criando 60 produtos...');
        [$produtos, $ids_cozinha, $ids_addon] = $this->criar_produtos(
            $fornecedor, $equipe[0], $grupos_por_nome, $data_inicial
        );

        $this->command->info('⏳ Gerando 365 dias de operação...');
        $this->gerar_historico($mesas_ids, $garcons_ids, $produtos, $data_inicial);

        $this->command->info('🍳 Gerando pedidos de cozinha...');
        $this->gerar_pedidos_cozinha($ids_cozinha);

        $this->command->info('🧂 Gerando adicionais nos itens...');
        $this->gerar_adicionais_itens($ids_addon, $itens_por_grupo_id);

        $this->command->info('📉 Gerando perdas de estoque...');
        $this->gerar_perdas_estoque($data_inicial, $garcons_ids);

        // Ativar mesas com comandas abertas
        $mesas_ocupadas = Comanda::where('status_comanda', 'aberta')->pluck('mesa_id')->unique();
        Mesa::whereIn('id', $mesas_ocupadas)->update(['status_mesa' => 'ocupada']);

        $this->command->info('✅ Banco populado com 1 ano de dados, adicionais e cozinha!');
    }

    // ─────────────────────────────────────────────────────────────
    // LIMPEZA
    // ─────────────────────────────────────────────────────────────

    private function limpar_tabelas(): void
    {
        // SQLite: FK não é forçada por padrão, mas apagamos em ordem segura
        DB::table('pedidos_cozinha')->delete();
        DB::table('comanda_item_adicionais')->delete();
        DB::table('comanda_itens')->delete();
        DB::table('comandas')->delete();
        DB::table('estoque_perdas')->delete();
        DB::table('estoque_entradas')->delete();
        DB::table('produto_grupos_adicionais')->delete();
        DB::table('produto_fornecedores')->delete();
        DB::table('produto_codigos_barras')->delete();
        DB::table('itens_adicionais')->delete();
        DB::table('grupos_adicionais')->delete();
        DB::table('fornecedores')->delete();
        DB::table('produtos')->delete();
        DB::table('mesas')->delete();
        DB::table('users')->delete();
    }

    // ─────────────────────────────────────────────────────────────
    // CADASTROS BASE
    // ─────────────────────────────────────────────────────────────

    private function criar_funcionarios(): array
    {
        return [
            User::create(['name' => 'Dono (Admin)',    'email' => 'admin@restaurante.com',  'password' => Hash::make('123456'), 'tipo_usuario' => 'dono',    'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Carlos Gerente',  'email' => 'gerente@restaurante.com','password' => Hash::make('123456'), 'tipo_usuario' => 'gerente', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Ana Caixa',       'email' => 'ana@restaurante.com',    'password' => Hash::make('123456'), 'tipo_usuario' => 'caixa',   'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Marcos Caixa',    'email' => 'marcos@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'caixa',   'tipo_contrato' => 'temporario', 'expiracao_acesso' => Carbon::now()->addDays(30)]),
            User::create(['name' => 'João Garçom',     'email' => 'joao@restaurante.com',   'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom',  'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Maria Garçom',    'email' => 'maria@restaurante.com',  'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom',  'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Pedro Garçom',    'email' => 'pedro@restaurante.com',  'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom',  'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Lucas Garçom',    'email' => 'lucas@restaurante.com',  'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom',  'tipo_contrato' => 'temporario', 'expiracao_acesso' => Carbon::now()->addHours(8)]),
            User::create(['name' => 'Julia Garçom',    'email' => 'julia@restaurante.com',  'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom',  'tipo_contrato' => 'temporario', 'expiracao_acesso' => Carbon::now()->addDays(2)]),
            User::create(['name' => 'Roberto Garçom',  'email' => 'roberto@restaurante.com','password' => Hash::make('123456'), 'tipo_usuario' => 'garcom',  'tipo_contrato' => 'fixo']),
        ];
    }

    private function criar_mesas(): array
    {
        $ids = [];
        for ($i = 1; $i <= 20; $i++) {
            $ids[] = Mesa::create([
                'nome_mesa'          => 'Mesa ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'status_mesa'        => 'livre',
                'capacidade_pessoas' => rand(2, 6),
            ])->id;
        }
        return $ids;
    }

    private function criar_fornecedor(): Fornecedor
    {
        return Fornecedor::create([
            'nome_fantasia'      => 'Atacadista Master',
            'razao_social'       => 'Atacadista Master LTDA',
            'cnpj'               => '00000000000191',
            'telefone'           => '(11) 99999-0000',
            'email'              => 'compras@atacadistamaster.com',
            'vendedor'           => 'Carlos Henrique',
            'contato_vendedor'   => '(11) 98888-7777',
            'status_fornecedor'  => 'ativo',
        ]);
    }

    /**
     * Cria grupos e itens de adicionais.
     * Retorna: [ grupos_por_nome, itens_por_grupo_id ]
     */
    private function criar_grupos_adicionais(): array
    {
        $definicoes = [
            [
                'nome' => 'Ponto da Carne',
                'max'  => 1,
                'itens' => [
                    ['Mal Passado', 0.00], ['Ao Ponto', 0.00], ['Bem Passado', 0.00],
                ],
            ],
            [
                'nome' => 'Extras do Lanche',
                'max'  => 3,
                'itens' => [
                    ['Bacon Extra', 3.00], ['Queijo Extra', 2.00], ['Ovo Frito', 2.50],
                    ['Cheddar', 2.00], ['Alface Extra', 0.50],
                ],
            ],
            [
                'nome' => 'Molhos',
                'max'  => 2,
                'itens' => [
                    ['Ketchup', 0.00], ['Mostarda', 0.00], ['Maionese', 0.00],
                    ['BBQ', 0.50], ['Chimichurri', 1.00],
                ],
            ],
            [
                'nome' => 'Sabores de Açaí',
                'max'  => 1,
                'itens' => [
                    ['Granola', 0.00], ['Paçoca', 1.00], ['Morango', 1.50], ['Banana', 0.00],
                ],
            ],
        ];

        $grupos_por_nome    = [];  // 'Ponto da Carne' => GrupoAdicional
        $itens_por_grupo_id = [];  // grupo_id => [ ItemAdicional, ... ]

        foreach ($definicoes as $def) {
            $grupo = GrupoAdicional::create([
                'nome'              => $def['nome'],
                'maximo_selecoes'   => $def['max'],
            ]);
            $grupos_por_nome[$def['nome']] = $grupo;
            $itens_por_grupo_id[$grupo->id] = [];

            foreach ($def['itens'] as [$nome, $preco]) {
                $itens_por_grupo_id[$grupo->id][] = ItemAdicional::create([
                    'grupo_adicional_id' => $grupo->id,
                    'nome'               => $nome,
                    'preco'              => $preco,
                ]);
            }
        }

        return [$grupos_por_nome, $itens_por_grupo_id];
    }

    /**
     * Cria os 60 produtos com requer_cozinha, código de barras, vínculo de fornecedor
     * e entrada inicial de estoque.
     *
     * Retorna: [ $produtos[], $ids_cozinha[], $ids_addon[produto_id => [grupo_id => true]] ]
     */
    private function criar_produtos(Fornecedor $fornecedor, User $dono, array $grupos_por_nome, Carbon $data_inicial): array
    {
        // [nome, categoria, custo, venda, requer_cozinha, [grupos de adicionais]]
        $catalogo = [
            // ── Bebidas Alcoólicas ──────────────────────────────────────────
            ['Heineken 600ml',             'Bebidas Alcoólicas',      7.00,  16.00, false, []],
            ['Stella Artois 330ml',        'Bebidas Alcoólicas',      5.50,  12.00, false, []],
            ['Brahma Chopp 600ml',         'Bebidas Alcoólicas',      6.00,  13.00, false, []],
            ['Skol Beats',                 'Bebidas Alcoólicas',      4.50,  10.00, false, []],
            ['Corona Extra',               'Bebidas Alcoólicas',      6.50,  14.00, false, []],
            ['Vinho Tinto Reservado',      'Bebidas Alcoólicas',     35.00,  85.00, false, []],
            ['Caipirinha de Limão',        'Bebidas Alcoólicas',      8.00,  22.00, false, []],
            ['Gin Tônica',                 'Bebidas Alcoólicas',     12.00,  30.00, false, []],
            ['Whisky Dose',                'Bebidas Alcoólicas',     15.00,  35.00, false, []],
            ['Chopp Artesanal 500ml',      'Bebidas Alcoólicas',      5.00,  14.00, false, []],
            // ── Bebidas Não Alcoólicas ──────────────────────────────────────
            ['Coca-Cola Lata',             'Bebidas Não Alcoólicas',  2.50,   6.00, false, []],
            ['Coca-Cola Zero',             'Bebidas Não Alcoólicas',  2.50,   6.00, false, []],
            ['Guaraná Lata',               'Bebidas Não Alcoólicas',  2.30,   5.50, false, []],
            ['Sprite',                     'Bebidas Não Alcoólicas',  2.30,   5.50, false, []],
            ['Água sem Gás',               'Bebidas Não Alcoólicas',  1.00,   4.00, false, []],
            ['Água com Gás',               'Bebidas Não Alcoólicas',  1.20,   4.50, false, []],
            ['Suco de Laranja Natural',    'Bebidas Não Alcoólicas',  3.00,   9.00, false, []],
            ['Suco de Limão',              'Bebidas Não Alcoólicas',  2.50,   8.00, false, []],
            ['Red Bull',                   'Bebidas Não Alcoólicas',  7.00,  15.00, false, []],
            ['H2OH Limão',                 'Bebidas Não Alcoólicas',  3.50,   8.00, false, []],
            // ── Pratos Principais ───────────────────────────────────────────
            ['Picanha na Chapa (500g)',     'Pratos Principais',      45.00, 110.00, true, ['Ponto da Carne']],
            ['Parmegiana de Carne',        'Pratos Principais',      25.00,  65.00, true, []],
            ['Parmegiana de Frango',       'Pratos Principais',      18.00,  45.00, true, []],
            ['Escondidinho de Carne Seca', 'Pratos Principais',      20.00,  50.00, true, []],
            ['Moqueca de Camarão',         'Pratos Principais',      40.00,  95.00, true, []],
            ['Costela ao Bafo',            'Pratos Principais',      35.00,  80.00, true, ['Ponto da Carne']],
            ['Strogonoff de Carne',        'Pratos Principais',      22.00,  55.00, true, []],
            ['Salmão Grelhado',            'Pratos Principais',      38.00,  85.00, true, []],
            ['Risoto de Frango',           'Pratos Principais',      15.00,  38.00, true, []],
            ['Feijoada Completa (2p)',      'Pratos Principais',      30.00,  75.00, true, []],
            // ── Entradas / Petiscos ─────────────────────────────────────────
            ['Porção de Fritas',           'Entradas / Petiscos',     8.00,  25.00, true, []],
            ['Fritas com Bacon e Cheddar', 'Entradas / Petiscos',    12.00,  35.00, true, []],
            ['Frango a Passarinho',        'Entradas / Petiscos',    14.00,  38.00, true, []],
            ['Calabresa Acebolada',        'Entradas / Petiscos',    15.00,  40.00, true, []],
            ['Isca de Peixe',              'Entradas / Petiscos',    18.00,  48.00, true, []],
            ['Mandioca Frita',             'Entradas / Petiscos',     7.00,  22.00, true, []],
            ['Polenta Frita',              'Entradas / Petiscos',     6.00,  20.00, true, []],
            ['Anéis de Cebola',            'Entradas / Petiscos',     9.00,  28.00, true, []],
            ['Provolone à Milanesa',       'Entradas / Petiscos',    14.00,  35.00, true, []],
            ['Pastel de Carne (10 un)',    'Entradas / Petiscos',    10.00,  30.00, true, []],
            // ── Lanches ────────────────────────────────────────────────────
            ['Hambúrguer Clássico',        'Lanches',                10.00,  28.00, true, ['Ponto da Carne', 'Extras do Lanche', 'Molhos']],
            ['Hambúrguer Duplo Bacon',     'Lanches',                15.00,  38.00, true, ['Ponto da Carne', 'Extras do Lanche', 'Molhos']],
            ['X-Salada',                   'Lanches',                 8.00,  20.00, true, ['Ponto da Carne', 'Molhos']],
            ['X-Bacon',                    'Lanches',                10.00,  24.00, true, ['Ponto da Carne', 'Extras do Lanche', 'Molhos']],
            ['X-Tudo',                     'Lanches',                14.00,  32.00, true, ['Ponto da Carne', 'Extras do Lanche', 'Molhos']],
            ['Beirute de Filé Mignon',     'Lanches',                20.00,  45.00, true, ['Ponto da Carne', 'Molhos']],
            ['Sanduíche Natural de Frango','Lanches',                 6.00,  15.00, true, []],
            ['Misto Quente',               'Lanches',                 4.00,  10.00, true, []],
            ['Cachorro Quente Completo',   'Lanches',                 7.00,  18.00, true, []],
            ['Lanche de Pernil',           'Lanches',                12.00,  26.00, true, []],
            // ── Sobremesas ─────────────────────────────────────────────────
            ['Pudim de Leite',             'Sobremesas',              4.00,  12.00, false, []],
            ['Petit Gateau com Sorvete',   'Sobremesas',              8.00,  22.00, true,  []],
            ['Brownie de Chocolate',       'Sobremesas',              7.00,  18.00, false, []],
            ['Torta de Limão',             'Sobremesas',              5.00,  14.00, false, []],
            ['Mousse de Maracujá',         'Sobremesas',              4.00,  10.00, false, []],
            ['Creme de Papaya',            'Sobremesas',              6.00,  16.00, false, []],
            ['Açaí na Tigela (500ml)',     'Sobremesas',              8.00,  20.00, false, ['Sabores de Açaí']],
            ['Sorvete (2 Bolas)',          'Sobremesas',              5.00,  12.00, false, []],
            ['Churros com Doce de Leite',  'Sobremesas',              6.00,  15.00, true,  []],
            ['Café Expresso',              'Sobremesas',              1.50,   5.00, false, []],
        ];

        $produtos    = [];
        $ids_cozinha = [];
        $ids_addon   = []; // produto_id => [ grupo_id => true ]

        foreach ($catalogo as $index => [$nome, $categoria, $custo, $venda, $requer_cozinha, $grupos_nomes]) {
            $produto = Produto::create([
                'nome_produto'      => $nome,
                'codigo_interno'    => (string) ($index + 1),
                'categoria'         => $categoria,
                'preco_custo_medio' => $custo,
                'preco_venda'       => $venda,
                'estoque_atual'     => rand(5000, 15000),
                'requer_cozinha'    => $requer_cozinha,
                'data_validade'     => Carbon::now()->addDays(rand(60, 730)),
            ]);

            $produtos[] = $produto;

            if ($requer_cozinha) {
                $ids_cozinha[] = $produto->id;
            }

            ProdutoCodigoBarras::create([
                'produto_id'          => $produto->id,
                'codigo_barras'       => '789' . str_pad($index, 9, '0', STR_PAD_LEFT),
                'descricao_variacao'  => null,
            ]);

            ProdutoFornecedor::create([
                'produto_id'            => $produto->id,
                'fornecedor_id'         => $fornecedor->id,
                'codigo_sku_fornecedor' => 'SKU-' . str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'fator_conversao'       => 1,
                'ultimo_preco_compra'   => $custo,
            ]);

            EstoqueEntrada::create([
                'produto_id'           => $produto->id,
                'fornecedor_id'        => $fornecedor->id,
                'usuario_id'           => $dono->id,
                'quantidade_comprada'  => $produto->estoque_atual,
                'custo_unitario_compra'=> $custo,
                'custo_total_entrada'  => $produto->estoque_atual * $custo,
                'created_at'           => $data_inicial,
                'updated_at'           => $data_inicial,
            ]);

            if (!empty($grupos_nomes)) {
                $ids_addon[$produto->id] = [];
                foreach ($grupos_nomes as $grupo_nome) {
                    if (isset($grupos_por_nome[$grupo_nome])) {
                        $grupo_id = $grupos_por_nome[$grupo_nome]->id;
                        DB::table('produto_grupos_adicionais')->insert([
                            'produto_id'         => $produto->id,
                            'grupo_adicional_id' => $grupo_id,
                        ]);
                        $ids_addon[$produto->id][$grupo_id] = true;
                    }
                }
            }
        }

        return [$produtos, $ids_cozinha, $ids_addon];
    }

    // ─────────────────────────────────────────────────────────────
    // MÁQUINA DO TEMPO — 365 DIAS
    // ─────────────────────────────────────────────────────────────

    private function gerar_historico(array $mesas_ids, array $garcons_ids, array $produtos, Carbon $data_inicial): void
    {
        $comandas_lote = [];
        $itens_lote    = [];
        $comanda_id    = 1;

        for ($dia = 0; $dia <= 365; $dia++) {
            $data_atual    = $data_inicial->copy()->addDays($dia);
            $eh_fim_semana = in_array($data_atual->dayOfWeek, [0, 5, 6]); // dom, sex, sab
            $qtd_sessoes   = $eh_fim_semana ? rand(20, 40) : rand(10, 20);

            for ($s = 0; $s < $qtd_sessoes; $s++) {
                $mesa_id   = $mesas_ids[array_rand($mesas_ids)];
                $garcom_id = $garcons_ids[array_rand($garcons_ids)];
                $hora      = rand(1, 10) > 3 ? rand(18, 23) : rand(11, 14); // 70% janta
                $abertura  = $data_atual->copy()->setTime($hora, rand(0, 59), 0);
                $permanencia = rand(30, 180);
                $fechamento  = $abertura->copy()->addMinutes($permanencia);

                $mesa_vazia  = rand(1, 100) <= 5;
                $valor_total = 0;

                // ── Itens da comanda geral ────────────────────────────────
                if (!$mesa_vazia) {
                    $indices = (array) array_rand($produtos, rand(2, 8));
                    foreach ($indices as $idx) {
                        $prod = $produtos[$idx];
                        $qtd  = rand(1, 3);
                        $valor_total += $prod->preco_venda * $qtd;
                        $hora_lanc = $abertura->copy()->addMinutes(rand(5, max(6, $permanencia - 5)));

                        $itens_lote[] = [
                            'comanda_id'           => $comanda_id,
                            'produto_id'           => $prod->id,
                            'quantidade'           => $qtd,
                            'preco_unitario'       => $prod->preco_venda,
                            'data_hora_lancamento' => $hora_lanc,
                            'created_at'           => $hora_lanc,
                            'updated_at'           => $hora_lanc,
                        ];
                    }
                }

                // ── Status da comanda ─────────────────────────────────────
                $status   = 'fechada';
                $motivo   = null;
                $desconto = 0;

                if ($dia === 365 && $abertura->isToday() && $abertura->diffInHours(now()) < 5) {
                    $status     = 'aberta';
                    $fechamento = null;
                } elseif ($mesa_vazia) {
                    $status = rand(1, 2) === 1 ? 'cancelada' : 'fechada';
                    $motivo = $status === 'cancelada' ? 'Cliente desistiu antes de consumir' : null;
                } elseif (rand(1, 100) <= 2) {
                    $status = 'cancelada';
                    $motivo = 'Cliente saiu sem pagar (Calote)';
                } elseif (rand(1, 100) <= 10) {
                    $desconto = rand(5, 20);
                }

                $comandas_lote[] = [
                    'id'                   => $comanda_id,
                    'mesa_id'              => $mesa_id,
                    'usuario_id'           => $garcom_id,
                    'status_comanda'       => $status,
                    'motivo_cancelamento'  => $motivo,
                    'tipo_conta'           => 'geral',
                    'valor_total'          => $valor_total,
                    'desconto'             => $desconto,
                    'data_hora_abertura'   => $abertura,
                    'data_hora_fechamento' => $fechamento,
                    'created_at'           => $fechamento ?? $abertura,
                    'updated_at'           => $fechamento ?? $abertura,
                ];
                $comanda_id++;

                // ── Sub-comandas individuais (30% das sessões) ───────────
                if (!$mesa_vazia && rand(1, 100) <= 30) {
                    $qtd_amigos = rand(1, 3);
                    for ($a = 0; $a < $qtd_amigos; $a++) {
                        $abertura_amigo = $abertura->copy()->addMinutes(rand(10, 60));
                        $valor_amigo    = 0;
                        $indices_amigo  = (array) array_rand($produtos, rand(1, 4));

                        foreach ($indices_amigo as $idx) {
                            $prod = $produtos[$idx];
                            $qtd  = rand(1, 2);
                            $valor_amigo += $prod->preco_venda * $qtd;
                            $hora_lanc = $abertura_amigo->copy()->addMinutes(rand(5, 30));

                            $itens_lote[] = [
                                'comanda_id'           => $comanda_id,
                                'produto_id'           => $prod->id,
                                'quantidade'           => $qtd,
                                'preco_unitario'       => $prod->preco_venda,
                                'data_hora_lancamento' => $hora_lanc,
                                'created_at'           => $hora_lanc,
                                'updated_at'           => $hora_lanc,
                            ];
                        }

                        $comandas_lote[] = [
                            'id'                   => $comanda_id,
                            'mesa_id'              => $mesa_id,
                            'usuario_id'           => $garcom_id,
                            'status_comanda'       => $status,
                            'motivo_cancelamento'  => null,
                            'tipo_conta'           => 'individual',
                            'valor_total'          => $valor_amigo,
                            'desconto'             => 0,
                            'data_hora_abertura'   => $abertura_amigo,
                            'data_hora_fechamento' => $fechamento,
                            'created_at'           => $fechamento ?? $abertura_amigo,
                            'updated_at'           => $fechamento ?? $abertura_amigo,
                        ];
                        $comanda_id++;
                    }
                }
            }

            // Flush a cada ~1500 registros ou no último dia
            if (count($comandas_lote) >= 1500 || $dia === 365) {
                Comanda::insert($comandas_lote);
                ComandaItem::insert($itens_lote);
                $comandas_lote = [];
                $itens_lote    = [];
                $this->command->getOutput()->write('.');
            }
        }

        $this->command->info('');
    }

    // ─────────────────────────────────────────────────────────────
    // PÓS-PROCESSAMENTO: COZINHA
    // ─────────────────────────────────────────────────────────────

    /**
     * Para cada comanda_item de produto que requer cozinha,
     * gera um registro em pedidos_cozinha com tempo realista.
     * Itens históricos: todos finalizados.
     * Itens das últimas 12h: status aleatório.
     */
    private function gerar_pedidos_cozinha(array $ids_cozinha): void
    {
        if (empty($ids_cozinha)) return;

        $agora = Carbon::now();

        ComandaItem::whereIn('produto_id', $ids_cozinha)
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')
            ->select(
                'comanda_itens.id',
                'comanda_itens.comanda_id',
                'comanda_itens.quantidade',
                'comanda_itens.created_at as item_created',
                'comandas.mesa_id',
                'produtos.nome_produto'
            )
            ->orderBy('comanda_itens.id')
            ->chunk(500, function ($itens) use ($agora) {
                $lote = [];

                foreach ($itens as $item) {
                    $criado_em  = Carbon::parse($item->item_created);
                    $eh_recente = $criado_em->diffInHours($agora) < 12;

                    if ($eh_recente) {
                        $status = ['pendente', 'em_preparacao', 'finalizado'][rand(0, 2)];
                    } else {
                        $status = 'finalizado';
                    }

                    $tempo_preparo = rand(8, 25); // minutos no preparo
                    $atualizado_em = ($status === 'finalizado')
                        ? $criado_em->copy()->addMinutes($tempo_preparo)
                        : $criado_em->copy();

                    $lote[] = [
                        'comanda_item_id'  => $item->id,
                        'comanda_id'       => $item->comanda_id,
                        'mesa_id'          => $item->mesa_id,
                        'produto_nome'     => $item->nome_produto,
                        'adicionais_texto' => null,
                        'quantidade'       => $item->quantidade,
                        'status'           => $status,
                        'visto_pelo_garcom'=> $status === 'finalizado',
                        'created_at'       => $criado_em,
                        'updated_at'       => $atualizado_em,
                    ];
                }

                DB::table('pedidos_cozinha')->insert($lote);
            });
    }

    // ─────────────────────────────────────────────────────────────
    // PÓS-PROCESSAMENTO: ADICIONAIS
    // ─────────────────────────────────────────────────────────────

    /**
     * Para 60% dos itens que pertencem a produtos com grupos de adicionais,
     * sorteia 1 item de cada grupo vinculado e cria o registro em comanda_item_adicionais.
     */
    private function gerar_adicionais_itens(array $ids_addon, array $itens_por_grupo_id): void
    {
        if (empty($ids_addon)) return;

        $ids_produtos_addon = array_keys($ids_addon);

        ComandaItem::whereIn('produto_id', $ids_produtos_addon)
            ->select('id', 'produto_id', 'created_at as item_created')
            ->orderBy('id')
            ->chunk(500, function ($itens) use ($ids_addon, $itens_por_grupo_id) {
                $lote = [];

                foreach ($itens as $item) {
                    // 60% dos pedidos adicionam extras
                    if (rand(1, 100) > 60) continue;

                    $grupos_do_produto = $ids_addon[$item->produto_id] ?? [];
                    $criado_em         = Carbon::parse($item->item_created);

                    foreach (array_keys($grupos_do_produto) as $grupo_id) {
                        $itens_grupo = $itens_por_grupo_id[$grupo_id] ?? [];
                        if (empty($itens_grupo)) continue;

                        $adicional = $itens_grupo[array_rand($itens_grupo)];

                        $lote[] = [
                            'comanda_item_id'   => $item->id,
                            'item_adicional_id' => $adicional->id,
                            'quantidade'        => 1,
                            'preco_unitario'    => $adicional->preco,
                            'created_at'        => $criado_em,
                            'updated_at'        => $criado_em,
                        ];
                    }
                }

                if (!empty($lote)) {
                    DB::table('comanda_item_adicionais')->insert($lote);
                }
            });
    }

    // ─────────────────────────────────────────────────────────────
    // PERDAS DE ESTOQUE
    // ─────────────────────────────────────────────────────────────

    private function gerar_perdas_estoque(Carbon $data_inicial, array $garcons_ids): void
    {
        $produtos_custos = Produto::pluck('preco_custo_medio', 'id')->toArray();
        $ids_produtos    = array_keys($produtos_custos);
        $motivos         = ['Quebra / Dano', 'Erro de Cozinha', 'Consumo Interno', 'Vencimento'];
        $lote            = [];

        for ($dia = 0; $dia <= 365; $dia += 5) {
            $data = $data_inicial->copy()->addDays($dia)->setTime(15, 0, 0);

            for ($p = 0; $p < 2; $p++) {
                $prod_id   = $ids_produtos[array_rand($ids_produtos)];
                $garcom_id = $garcons_ids[array_rand($garcons_ids)];
                $qtd       = rand(1, 3);
                $custo     = $produtos_custos[$prod_id] ?? 5;

                $lote[] = [
                    'produto_id'        => $prod_id,
                    'usuario_id'        => $garcom_id,
                    'quantidade'        => $qtd,
                    'motivo'            => $motivos[array_rand($motivos)],
                    'custo_total_perda' => $custo * $qtd,
                    'created_at'        => $data,
                    'updated_at'        => $data,
                ];
            }
        }

        EstoquePerda::insert($lote);
    }
}
