<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Mesa;
use App\Models\Produto;
use App\Models\Comanda;
use App\Models\ComandaItem;

class TenantFakeDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Iniciando a geração de dados fictícios de BI...');

        // 1. CRIAR GARÇONS
        $garcons = [];
        $nomes = ['Carlos Silva', 'Ana Souza', 'Marcos Pedroso'];
        foreach ($nomes as $index => $nome) {
            $garcons[] = User::firstOrCreate(
                ['email' => "garcom{$index}@teste.com"],
                ['name' => $nome, 'password' => Hash::make('123456'), 'tipo_usuario' => 'garcom']
            );
        }
        $dono = User::first(); // Pega o seu user atual para também ter vendas

        // 2. CRIAR MESAS (Garante que há pelo menos 10)
        $mesas = [];
        for ($i = 1; $i <= 10; $i++) {
            $mesas[] = Mesa::firstOrCreate(['nome_mesa' => "Mesa VIP {$i}"], ['capacidade_pessoas' => 4]);
        }

        // 3. CRIAR PRODUTOS ESTRATÉGICOS (Curvas A, B e Encalhado)
        $produtos = [];
        $produtos[] = Produto::firstOrCreate(['nome_produto' => 'Cerveja IPA Artesanal'], ['categoria' => 'Bebidas (Alcoólicas)', 'preco_venda' => 18.00, 'preco_custo' => 7.00, 'estoque_atual' => 500]);
        $produtos[] = Produto::firstOrCreate(['nome_produto' => 'Porção de Fritas com Bacon'], ['categoria' => 'Entradas / Petiscos', 'preco_venda' => 45.00, 'preco_custo' => 15.00, 'estoque_atual' => 100]);
        $produtos[] = Produto::firstOrCreate(['nome_produto' => 'Picanha na Tábua (2 Pessoas)'], ['categoria' => 'Pratos Principais', 'preco_venda' => 120.00, 'preco_custo' => 55.00, 'estoque_atual' => 50]);
        $produtos[] = Produto::firstOrCreate(['nome_produto' => 'Refrigerante Cola Lata'], ['categoria' => 'Bebidas (Não Alcoólicas)', 'preco_venda' => 7.00, 'preco_custo' => 3.00, 'estoque_atual' => 300]);
        
        // O Encalhado (Não vamos adicionar vendas para este)
        Produto::firstOrCreate(['nome_produto' => 'Vinho Tinto Francês Safra 2010'], ['categoria' => 'Bebidas (Alcoólicas)', 'preco_venda' => 350.00, 'preco_custo' => 180.00, 'estoque_atual' => 12]);

        // 4. MÁQUINA DO TEMPO: GERAR 30 DIAS DE VENDAS
        $hoje = Carbon::now();
        $total_comandas = 0;

        // Vamos viajar de 30 dias atrás até ao dia de hoje
        for ($diasAtras = 30; $diasAtras >= 0; $diasAtras--) {
            $dataReferencia = clone $hoje;
            $dataReferencia->subDays($diasAtras);

            // Inteligência: Sextas (5) e Sábados (6) têm o dobro do movimento
            $diaDaSemana = $dataReferencia->dayOfWeek;
            $isFimDeSemana = in_array($diaDaSemana, [5, 6]);
            $qtdComandasNoDia = $isFimDeSemana ? rand(15, 25) : rand(5, 12);

            for ($c = 0; $c < $qtdComandasNoDia; $c++) {
                
                // Horário de abertura da mesa (Picos entre 18h e 22h)
                $horaAbertura = clone $dataReferencia;
                $horaAbertura->setTime(rand(18, 22), rand(0, 59), 0);

                // Tempo que o cliente ficou (entre 45 min e 3 horas)
                $minutosPermanencia = rand(45, 180);
                $horaFechamento = clone $horaAbertura;
                $horaFechamento->addMinutes($minutosPermanencia);

                // Escolhe um garçom aleatório (Dono ou Garçons)
                $todosUsers = array_merge($garcons, [$dono]);
                $usuarioResponsavel = $todosUsers[array_rand($todosUsers)];

                $comanda = Comanda::create([
                    'mesa_id' => $mesas[array_rand($mesas)]->id,
                    'usuario_id' => $usuarioResponsavel->id,
                    'status_comanda' => 'fechada',
                    'tipo_conta' => 'geral',
                    'data_hora_abertura' => $horaAbertura,
                    'data_hora_fechamento' => $horaFechamento,
                    'created_at' => $horaAbertura,
                    'updated_at' => $horaFechamento,
                    'valor_total' => 0 // Será calculado abaixo
                ]);

                $valorTotalComanda = 0;
                $qtdItensDiferentes = rand(2, 4);

                for ($i = 0; $i < $qtdItensDiferentes; $i++) {
                    $produto = $produtos[array_rand($produtos)];
                    
                    // Se for Cerveja, compram mais quantidade. Se for Prato, compram 1 ou 2.
                    $quantidade = str_contains($produto->nome_produto, 'Cerveja') ? rand(3, 10) : rand(1, 2);

                    // Hora exata em que o garçom clicou no botão de lançar (Espalhado durante a permanência)
                    $horaLancamento = clone $horaAbertura;
                    $horaLancamento->addMinutes(rand(5, $minutosPermanencia - 10));

                    ComandaItem::create([
                        'comanda_id' => $comanda->id,
                        'produto_id' => $produto->id,
                        'quantidade' => $quantidade,
                        'preco_unitario' => $produto->preco_venda,
                        'data_hora_lancamento' => $horaLancamento,
                        'created_at' => $horaLancamento,
                        'updated_at' => $horaLancamento
                    ]);

                    $valorTotalComanda += ($quantidade * $produto->preco_venda);
                }

                // Atualiza o valor correto da comanda após lançar os itens
                $comanda->update(['valor_total' => $valorTotalComanda]);
                $total_comandas++;
            }
        }

        $this->command->info("Sucesso! Foram geradas {$total_comandas} comandas históricas com perfis de consumo realistas.");
    }
}