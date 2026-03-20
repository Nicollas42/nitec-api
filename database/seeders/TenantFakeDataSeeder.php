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
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\EstoqueEntrada;
use App\Models\EstoquePerda;

class TenantFakeDataSeeder extends Seeder
{
    /**
     * Popula o tenant com dados de demonstração compatíveis com o novo catálogo.
     */
    public function run()
    {
        $this->command->info('🧹 Limpando dados antigos...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        EstoquePerda::truncate();
        EstoqueEntrada::truncate();
        ProdutoFornecedor::truncate();
        ProdutoCodigoBarras::truncate();
        Fornecedor::truncate();
        ComandaItem::truncate();
        Comanda::truncate();
        Produto::truncate();
        Mesa::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ==========================================
        // 1. CRIANDO 10 FUNCIONÁRIOS
        // ==========================================
        $this->command->info('👥 Criando 10 Funcionários...');
        $equipe = [
            User::create(['name' => 'Dono (Admin)', 'email' => 'admin@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'dono', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Carlos (Gerente)', 'email' => 'gerente@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'gerente', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Ana Caixa', 'email' => 'ana@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'caixa', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Marcos Caixa', 'email' => 'marcos@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'caixa', 'tipo_contrato' => 'temporario', 'expiracao_acesso' => Carbon::now()->addDays(30)]),
            User::create(['name' => 'João Garçom', 'email' => 'joao@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Maria Garçom', 'email' => 'maria@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Pedro Garçom', 'email' => 'pedro@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'fixo']),
            User::create(['name' => 'Lucas Garçom', 'email' => 'lucas@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'temporario', 'expiracao_acesso' => Carbon::now()->addHours(8)]),
            User::create(['name' => 'Julia Garçom', 'email' => 'julia@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'temporario', 'expiracao_acesso' => Carbon::now()->addDays(2)]),
            User::create(['name' => 'Roberto Garçom', 'email' => 'roberto@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'fixo'])
        ];
        
        $garcons_ids = array_map(fn($u) => $u->id, array_filter($equipe, fn($u) => $u->tipo_usuario === 'garcom'));

        // ==========================================
        // 2. CRIANDO 20 MESAS
        // ==========================================
        $this->command->info('🪑 Criando 20 Mesas...');
        $mesas_ids = [];
        for ($i = 1; $i <= 20; $i++) {
            $num = str_pad($i, 2, '0', STR_PAD_LEFT);
            $mesas_ids[] = Mesa::create(['nome_mesa' => "Mesa $num", 'status_mesa' => 'livre', 'capacidade_pessoas' => rand(2, 6)])->id;
        }

        // ==========================================
        // 3. CRIANDO 60 PRODUTOS (6 Categorias x 10)
        // ==========================================
        $this->command->info('📦 Criando 60 Produtos...');
        $catalogo_base = [
            // Bebidas Alcoólicas
            ['Heineken 600ml', 'Bebidas Alcoólicas', 7.00, 16.00], ['Stella Artois 330ml', 'Bebidas Alcoólicas', 5.50, 12.00], ['Brahma Chopp 600ml', 'Bebidas Alcoólicas', 6.00, 13.00], ['Skol Beats', 'Bebidas Alcoólicas', 4.50, 10.00], ['Corona Extra', 'Bebidas Alcoólicas', 6.50, 14.00], ['Vinho Tinto Reservado', 'Bebidas Alcoólicas', 35.00, 85.00], ['Caipirinha de Limão', 'Bebidas Alcoólicas', 8.00, 22.00], ['Gin Tônica', 'Bebidas Alcoólicas', 12.00, 30.00], ['Whisky Dose', 'Bebidas Alcoólicas', 15.00, 35.00], ['Chopp Artesanal 500ml', 'Bebidas Alcoólicas', 5.00, 14.00],
            // Bebidas Não Alcoólicas
            ['Coca-Cola Lata', 'Bebidas Não Alcoólicas', 2.50, 6.00], ['Coca-Cola Zero', 'Bebidas Não Alcoólicas', 2.50, 6.00], ['Guaraná Lata', 'Bebidas Não Alcoólicas', 2.30, 5.50], ['Sprite', 'Bebidas Não Alcoólicas', 2.30, 5.50], ['Água sem Gás', 'Bebidas Não Alcoólicas', 1.00, 4.00], ['Água com Gás', 'Bebidas Não Alcoólicas', 1.20, 4.50], ['Suco de Laranja Natural', 'Bebidas Não Alcoólicas', 3.00, 9.00], ['Suco de Limão', 'Bebidas Não Alcoólicas', 2.50, 8.00], ['Red Bull', 'Bebidas Não Alcoólicas', 7.00, 15.00], ['H2OH Limão', 'Bebidas Não Alcoólicas', 3.50, 8.00],
            // Pratos Principais
            ['Picanha na Chapa (500g)', 'Pratos Principais', 45.00, 110.00], ['Parmegiana de Carne', 'Pratos Principais', 25.00, 65.00], ['Parmegiana de Frango', 'Pratos Principais', 18.00, 45.00], ['Escondidinho de Carne Seca', 'Pratos Principais', 20.00, 50.00], ['Moqueca de Camarão', 'Pratos Principais', 40.00, 95.00], ['Costela ao Bafo', 'Pratos Principais', 35.00, 80.00], ['Strogonoff de Carne', 'Pratos Principais', 22.00, 55.00], ['Salmão Grelhado', 'Pratos Principais', 38.00, 85.00], ['Risoto de Frango', 'Pratos Principais', 15.00, 38.00], ['Feijoada Completa (2 pessoas)', 'Pratos Principais', 30.00, 75.00],
            // Entradas e Porções
            ['Porção de Fritas', 'Entradas / Petiscos', 8.00, 25.00], ['Fritas com Bacon e Cheddar', 'Entradas / Petiscos', 12.00, 35.00], ['Frango a Passarinho', 'Entradas / Petiscos', 14.00, 38.00], ['Calabresa Acebolada', 'Entradas / Petiscos', 15.00, 40.00], ['Isca de Peixe', 'Entradas / Petiscos', 18.00, 48.00], ['Mandioca Frita', 'Entradas / Petiscos', 7.00, 22.00], ['Polenta Frita', 'Entradas / Petiscos', 6.00, 20.00], ['Anéis de Cebola (Onion Rings)', 'Entradas / Petiscos', 9.00, 28.00], ['Provolone à Milanesa', 'Entradas / Petiscos', 14.00, 35.00], ['Pastel de Carne (10 un)', 'Entradas / Petiscos', 10.00, 30.00],
            // Lanches
            ['Hambúrguer Clássico', 'Lanches', 10.00, 28.00], ['Hambúrguer Duplo Bacon', 'Lanches', 15.00, 38.00], ['X-Salada', 'Lanches', 8.00, 20.00], ['X-Bacon', 'Lanches', 10.00, 24.00], ['X-Tudo', 'Lanches', 14.00, 32.00], ['Beirute de Filé Mignon', 'Lanches', 20.00, 45.00], ['Sanduíche Natural de Frango', 'Lanches', 6.00, 15.00], ['Misto Quente', 'Lanches', 4.00, 10.00], ['Cachorro Quente Completo', 'Lanches', 7.00, 18.00], ['Lanche de Pernil', 'Lanches', 12.00, 26.00],
            // Sobremesas e Extras
            ['Pudim de Leite', 'Sobremesas', 4.00, 12.00], ['Petit Gateau com Sorvete', 'Sobremesas', 8.00, 22.00], ['Brownie de Chocolate', 'Sobremesas', 7.00, 18.00], ['Torta de Limão', 'Sobremesas', 5.00, 14.00], ['Mousse de Maracujá', 'Sobremesas', 4.00, 10.00], ['Creme de Papaya com Cassis', 'Sobremesas', 6.00, 16.00], ['Açaí na Tigela (500ml)', 'Sobremesas', 8.00, 20.00], ['Sorvete (2 Bolas)', 'Sobremesas', 5.00, 12.00], ['Churros com Doce de Leite', 'Sobremesas', 6.00, 15.00], ['Café Expresso', 'Sobremesas', 1.50, 5.00]
        ];

        $produtos_ids = [];
        $data_inicial = Carbon::now()->subDays(730); // 2 Anos Atrás
        $fornecedor_padrao = Fornecedor::create([
            'nome_fantasia' => 'Atacadista Master',
            'razao_social' => 'Atacadista Master LTDA',
            'cnpj' => '00000000000191',
            'telefone' => '(11) 99999-0000',
            'email' => 'compras@atacadistamaster.com',
            'vendedor' => 'Carlos Henrique',
            'contato_vendedor' => '(11) 98888-7777',
            'status_fornecedor' => 'ativo',
        ]);

        foreach ($catalogo_base as $index => $item) {
            $cod_barras = '789' . str_pad($index, 9, '0', STR_PAD_LEFT);
            $validade = Carbon::now()->addDays(rand(30, 730)); // Validade entre 1 mês e 2 anos no futuro

            $produto = Produto::create([
                'nome_produto' => $item[0],
                'codigo_interno' => (string) ($index + 1),
                'categoria' => $item[1],
                'preco_custo_medio' => $item[2],
                'preco_venda' => $item[3],
                'estoque_atual' => rand(5000, 15000), // Estoque alto para aguentar 2 anos
                'data_validade' => $validade
            ]);
            $produtos_ids[] = $produto;

            ProdutoCodigoBarras::create([
                'produto_id' => $produto->id,
                'codigo_barras' => $cod_barras,
                'descricao_variacao' => null,
            ]);

            ProdutoFornecedor::create([
                'produto_id' => $produto->id,
                'fornecedor_id' => $fornecedor_padrao->id,
                'codigo_sku_fornecedor' => 'SKU-' . str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'fator_conversao' => 1,
                'ultimo_preco_compra' => $item[2],
            ]);

            // Log de entrada inicial
            EstoqueEntrada::create([
                'produto_id' => $produto->id,
                'fornecedor_id' => $fornecedor_padrao->id,
                'usuario_id' => $equipe[0]->id, // Dono
                'quantidade_comprada' => $produto->estoque_atual,
                'custo_unitario_compra' => $produto->preco_custo_medio,
                'custo_total_entrada' => $produto->estoque_atual * $produto->preco_custo_medio,
                'created_at' => $data_inicial,
                'updated_at' => $data_inicial
            ]);
        }

        // ==========================================
        // 4. MÁQUINA DO TEMPO: GERANDO 2 ANOS DE DADOS
        // ==========================================
        $this->command->info('⏳ Viajando no tempo: Gerando 730 dias de operação (Isto pode levar 1 minuto)...');
        
        $comanda_id_counter = 1;
        $comandas_lote = [];
        $itens_lote = [];

        // Loop de 2 Anos (730 dias)
        for ($dia = 0; $dia <= 730; $dia++) {
            $data_atual = $data_inicial->copy()->addDays($dia);
            $dia_semana = $data_atual->dayOfWeek; 
            $eh_fim_de_semana = in_array($dia_semana, [0, 5, 6]);
            
            // Finais de semana têm mais movimento
            $qtd_sessoes_mesas = $eh_fim_de_semana ? rand(20, 40) : rand(10, 20);

            for ($s = 0; $s < $qtd_sessoes_mesas; $s++) {
                $mesa_escolhida = $mesas_ids[array_rand($mesas_ids)];
                $garcom_escolhido = $garcons_ids[array_rand($garcons_ids)];
                
                // Horário da sessão (almoço ou janta)
                $hora_abertura = rand(1, 10) > 3 ? rand(18, 23) : rand(11, 14);
                $abertura = $data_atual->copy()->setTime($hora_abertura, rand(0, 59), 0);
                $permanencia_minutos = rand(30, 180);
                $fechamento = $abertura->copy()->addMinutes($permanencia_minutos);

                // 🟢 CENÁRIO 1: Conta Geral Principal
                $valor_total_geral = 0;
                $itens_geral = [];
                
                // 5% de chance da mesa desistir e a comanda ficar vazia/zerada
                $mesa_vazia = rand(1, 100) <= 5;
                
                if (!$mesa_vazia) {
                    $qtd_itens = rand(2, 8);
                    for ($i = 0; $i < $qtd_itens; $i++) {
                        $prod = $produtos_ids[array_rand($produtos_ids)];
                        $qtd = rand(1, 3);
                        $valor_total_geral += ($prod->preco_venda * $qtd);
                        $hora_lanc = $abertura->copy()->addMinutes(rand(5, $permanencia_minutos - 5));

                        $itens_lote[] = [
                            'comanda_id' => $comanda_id_counter,
                            'produto_id' => $prod->id,
                            'quantidade' => $qtd,
                            'preco_unitario' => $prod->preco_venda,
                            'data_hora_lancamento' => $hora_lanc,
                            'created_at' => $hora_lanc,
                            'updated_at' => $hora_lanc
                        ];
                    }
                }

                // Status da Comanda Principal
                $status = 'fechada';
                $motivo = null;
                $desc = 0;

                // Se for o ÚLTIMO DIA (Hoje) e o horário for recente, deixa aberta!
                if ($dia === 730 && $abertura->isToday() && $abertura->diffInHours(now()) < 5) {
                    $status = 'aberta';
                    $fechamento = null;
                } else if ($mesa_vazia) {
                    $status = rand(1, 2) == 1 ? 'cancelada' : 'fechada'; // Algumas são calote, outras são erro
                    $motivo = $status === 'cancelada' ? 'Cliente desistiu antes de consumir' : null;
                } else if (rand(1, 100) <= 2) {
                    // 2% de calote
                    $status = 'cancelada';
                    $motivo = 'Cliente saiu sem pagar (Calote)';
                } else if (rand(1, 100) <= 10) {
                    // 10% de desconto
                    $desc = rand(5, 20);
                }

                $comandas_lote[] = [
                    'id' => $comanda_id_counter,
                    'mesa_id' => $mesa_escolhida,
                    'usuario_id' => $garcom_escolhido,
                    'status_comanda' => $status,
                    'motivo_cancelamento' => $motivo,
                    'tipo_conta' => 'geral',
                    'valor_total' => $valor_total_geral,
                    'desconto' => $desc,
                    'data_hora_abertura' => $abertura,
                    'data_hora_fechamento' => $fechamento,
                    'created_at' => $fechamento ?? $abertura,
                    'updated_at' => $fechamento ?? $abertura
                ];

                $id_conta_geral = $comanda_id_counter;
                $comanda_id_counter++;

                // 🟢 CENÁRIO 2: Amigos na mesma mesa (Sub-comandas individuais)
                if (!$mesa_vazia && rand(1, 100) <= 30) { // 30% de chance de ter amigos separando a conta
                    $qtd_amigos = rand(1, 3);
                    for ($a = 0; $a < $qtd_amigos; $a++) {
                        // Amigo chega um pouco depois
                        $abertura_amigo = $abertura->copy()->addMinutes(rand(10, 60));
                        $valor_total_amigo = 0;
                        $qtd_itens_amigo = rand(1, 4);

                        for ($i = 0; $i < $qtd_itens_amigo; $i++) {
                            $prod = $produtos_ids[array_rand($produtos_ids)];
                            $qtd = rand(1, 2);
                            $valor_total_amigo += ($prod->preco_venda * $qtd);
                            $hora_lanc = $abertura_amigo->copy()->addMinutes(rand(5, 30));

                            $itens_lote[] = [
                                'comanda_id' => $comanda_id_counter,
                                'produto_id' => $prod->id,
                                'quantidade' => $qtd,
                                'preco_unitario' => $prod->preco_venda,
                                'data_hora_lancamento' => $hora_lanc,
                                'created_at' => $hora_lanc,
                                'updated_at' => $hora_lanc
                            ];
                        }

                        $comandas_lote[] = [
                            'id' => $comanda_id_counter,
                            'mesa_id' => $mesa_escolhida,
                            'usuario_id' => $garcom_escolhido,
                            'status_comanda' => $status, 
                            'motivo_cancelamento' => null, // 🟢 LINHA ADICIONADA AQUI!
                            'tipo_conta' => 'individual',
                            'valor_total' => $valor_total_amigo,
                            'desconto' => 0,
                            'data_hora_abertura' => $abertura_amigo,
                            'data_hora_fechamento' => $fechamento,
                            'created_at' => $fechamento ?? $abertura_amigo,
                            'updated_at' => $fechamento ?? $abertura_amigo
                        ];
                        
                        $comanda_id_counter++;
                    }
                }
            }

            // Gerar 2 perdas aleatórias a cada ~5 dias para popular a Auditoria do BI
            if ($dia % 5 === 0) {
                for ($p = 0; $p < 2; $p++) {
                    $prod_perdido = $produtos_ids[array_rand($produtos_ids)];
                    $qtd_perdida = rand(1, 3);
                    EstoquePerda::create([
                        'produto_id' => $prod_perdido->id,
                        'usuario_id' => $garcons_ids[array_rand($garcons_ids)],
                        'quantidade' => $qtd_perdida,
                        'motivo' => array_rand(array_flip(['Quebra / Dano', 'Erro de Cozinha', 'Consumo Interno', 'Vencimento'])),
                        'custo_total_perda' => $prod_perdido->preco_custo_medio * $qtd_perdida,
                        'created_at' => $data_atual->copy()->setTime(15, 0),
                        'updated_at' => $data_atual->copy()->setTime(15, 0)
                    ]);
                }
            }

            // 🟢 OTIMIZAÇÃO DE MEMÓRIA: Inserir no banco a cada 30 dias de loop e limpar os arrays
            if (count($comandas_lote) >= 1500 || $dia === 730) {
                Comanda::insert($comandas_lote);
                ComandaItem::insert($itens_lote);
                $comandas_lote = [];
                $itens_lote = [];
            }
        }

        // Ativa o Status Ocupado para as mesas que ficaram abertas no último dia
        $mesas_ocupadas = Comanda::where('status_comanda', 'aberta')->pluck('mesa_id')->unique();
        Mesa::whereIn('id', $mesas_ocupadas)->update(['status_mesa' => 'ocupada']);

        $this->command->info('🚀 SUCESSO! Banco populado perfeitamente com 2 Anos de dados e cenários complexos!');
    }
}
