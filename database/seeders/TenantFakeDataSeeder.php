<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Mesa;
use App\Models\Produto;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\EstoqueEntrada;
use App\Models\EstoquePerda;

class TenantFakeDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Limpando dados antigos...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        EstoquePerda::truncate();
        EstoqueEntrada::truncate();
        ComandaItem::truncate();
        Comanda::truncate();
        Produto::truncate();
        Mesa::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Criando Equipe...');
        $dono = User::create(['name' => 'Dono (Admin)', 'email' => 'admin@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'dono', 'tipo_contrato' => 'fixo']);
        $garcom1 = User::create(['name' => 'João Silva', 'email' => 'joao@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'fixo']);
        $garcom2 = User::create(['name' => 'Maria Souza', 'email' => 'maria@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'fixo']);
        $garcom3 = User::create(['name' => 'Carlos (Extra)', 'email' => 'carlos@restaurante.com', 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom', 'tipo_contrato' => 'temporario']);
        $garcons = [$garcom1->id, $garcom2->id, $garcom3->id];

        $this->command->info('Criando Mesas...');
        $mesas = [];
        for ($i = 1; $i <= 10; $i++) {
            $mesas[] = Mesa::create(['nome_mesa' => "Mesa 0$i", 'status_mesa' => 'livre', 'capacidade_pessoas' => 4])->id;
        }

        $this->command->info('Criando Produtos e Estoque Inicial...');
        $catalogo = [
            ['nome' => 'Cerveja IPA Artesanal', 'cat' => 'Bebidas (Alcoólicas)', 'custo' => 6.00, 'venda' => 15.00],
            ['nome' => 'Refrigerante Cola Lata', 'cat' => 'Bebidas (Não Alcoólicas)', 'custo' => 2.50, 'venda' => 6.00],
            ['nome' => 'Água Mineral', 'cat' => 'Bebidas (Não Alcoólicas)', 'custo' => 1.00, 'venda' => 4.00],
            ['nome' => 'Picanha na Chapa (500g)', 'cat' => 'Pratos Principais', 'custo' => 45.00, 'venda' => 120.00],
            ['nome' => 'Porção de Fritas com Bacon', 'cat' => 'Entradas / Petiscos', 'custo' => 12.00, 'venda' => 35.00],
            ['nome' => 'Hambúrguer Artesanal', 'cat' => 'Pratos Principais', 'custo' => 14.00, 'venda' => 38.00],
            ['nome' => 'Pudim de Leite', 'cat' => 'Sobremesas', 'custo' => 4.00, 'venda' => 12.00],
            ['nome' => 'Vinho Tinto Reservado', 'cat' => 'Bebidas (Alcoólicas)', 'custo' => 35.00, 'venda' => 90.00],
        ];

        $produtos_ids = [];
        $data_inicial = Carbon::now()->subDays(365); // 1 Ano Atrás

        foreach ($catalogo as $item) {
            $produto = Produto::create([
                'nome_produto' => $item['nome'],
                'categoria' => $item['cat'],
                'preco_custo' => $item['custo'],
                'preco_venda' => $item['venda'],
                'estoque_atual' => 5000 // Estoque gigante para aguentar 1 ano de vendas
            ]);
            $produtos_ids[] = $produto;

            // Gera uma entrada de estoque no início do ano (Log de Auditoria)
            EstoqueEntrada::create([
                'produto_id' => $produto->id,
                'usuario_id' => $dono->id,
                'quantidade_adicionada' => 5000,
                'custo_unitario_compra' => $item['custo'],
                'fornecedor' => 'Fornecedor Padrão',
                'created_at' => $data_inicial,
                'updated_at' => $data_inicial
            ]);
        }

        $this->command->info('Viajando no tempo e gerando 365 dias de vendas (Isso pode levar alguns segundos)...');
        
        $comandas_data = [];
        $itens_data = [];
        $comanda_id_counter = 1;

        // Loop de 365 dias
        for ($dia = 0; $dia <= 365; $dia++) {
            $data_atual = $data_inicial->copy()->addDays($dia);
            $dia_semana = $data_atual->dayOfWeek; // 0 = Domingo, 6 = Sábado
            
            // Finais de semana têm mais movimento
            $eh_fim_de_semana = in_array($dia_semana, [0, 5, 6]);
            $qtd_comandas_dia = $eh_fim_de_semana ? rand(20, 35) : rand(5, 15);

            for ($c = 0; $c < $qtd_comandas_dia; $c++) {
                // Horário de abertura concentrado à noite (18h às 23h)
                $hora_abertura = rand(18, 23);
                $minuto_abertura = rand(0, 59);
                $abertura = $data_atual->copy()->setTime($hora_abertura, $minuto_abertura, 0);
                
                // Cliente fica entre 40 e 150 minutos
                $fechamento = $abertura->copy()->addMinutes(rand(40, 150));

                $valor_total_comanda = 0;
                $qtd_itens = rand(2, 6);

                for ($i = 0; $i < $qtd_itens; $i++) {
                    $prod = $produtos_ids[array_rand($produtos_ids)];
                    $qtd = rand(1, 3);
                    $valor_total_comanda += ($prod->preco_venda * $qtd);

                    // Hora do lançamento do item (distribuído durante a permanência)
                    $hora_lancamento = $abertura->copy()->addMinutes(rand(5, 30));

                    $itens_data[] = [
                        'comanda_id' => $comanda_id_counter,
                        'produto_id' => $prod->id,
                        'quantidade' => $qtd,
                        'preco_unitario' => $prod->preco_venda,
                        'data_hora_lancamento' => $hora_lancamento,
                        'created_at' => $hora_lancamento,
                        'updated_at' => $hora_lancamento
                    ];
                }

                $comandas_data[] = [
                    'id' => $comanda_id_counter,
                    'mesa_id' => $mesas[array_rand($mesas)],
                    'usuario_id' => $garcons[array_rand($garcons)],
                    'status_comanda' => 'fechada',
                    'tipo_conta' => 'geral',
                    'valor_total' => $valor_total_comanda,
                    'data_hora_abertura' => $abertura,
                    'data_hora_fechamento' => $fechamento,
                    'created_at' => $fechamento,
                    'updated_at' => $fechamento
                ];

                $comanda_id_counter++;
            }

            // Gerar 1 perda aleatória a cada ~10 dias para popular o Log
            if (rand(1, 10) === 1) {
                $prod_perdido = $produtos_ids[array_rand($produtos_ids)];
                $qtd_perdida = rand(1, 2);
                EstoquePerda::create([
                    'produto_id' => $prod_perdido->id,
                    'usuario_id' => $garcons[array_rand($garcons)],
                    'quantidade' => $qtd_perdida,
                    'motivo' => array_rand(array_flip(['Quebra / Dano', 'Erro de Cozinha', 'Consumo Interno'])),
                    'custo_total_perda' => $prod_perdido->preco_custo * $qtd_perdida,
                    'created_at' => $data_atual->copy()->setTime(15, 0),
                    'updated_at' => $data_atual->copy()->setTime(15, 0)
                ]);
            }
        }

        $this->command->info("Inserindo " . count($comandas_data) . " comandas no banco...");
        // Inserir em pedaços (chunks) para não estourar a memória RAM do PHP
        foreach (array_chunk($comandas_data, 1000) as $chunk) {
            Comanda::insert($chunk);
        }

        $this->command->info("Inserindo " . count($itens_data) . " itens vendidos no banco...");
        foreach (array_chunk($itens_data, 1000) as $chunk) {
            ComandaItem::insert($chunk);
        }

        $this->command->info('✅ Dados de 1 Ano gerados com sucesso! O seu BI vai ficar lindo!');
    }
}