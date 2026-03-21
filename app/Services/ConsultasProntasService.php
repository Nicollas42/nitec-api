<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ConsultasProntasService
{
    // ─── Opções reutilizáveis ────────────────────────────────────────────────

    private function opcoes_dias(): array
    {
        return [
            ['valor' => '7',   'label' => '7 dias'],
            ['valor' => '14',  'label' => '14 dias'],
            ['valor' => '30',  'label' => '30 dias'],
            ['valor' => '60',  'label' => '60 dias'],
            ['valor' => '90',  'label' => '90 dias'],
            ['valor' => '180', 'label' => '180 dias'],
        ];
    }

    private function opcoes_limite(): array
    {
        return [
            ['valor' => '5',  'label' => 'Top 5'],
            ['valor' => '10', 'label' => 'Top 10'],
            ['valor' => '20', 'label' => 'Top 20'],
            ['valor' => '50', 'label' => 'Top 50'],
        ];
    }

    private function opcoes_usuarios(): array
    {
        $opcoes = [['valor' => '', 'label' => 'Todos os funcionários']];
        try {
            $users = DB::select("SELECT id, name FROM users ORDER BY name");
            foreach ($users as $u) {
                $opcoes[] = ['valor' => (string) $u->id, 'label' => $u->name];
            }
        } catch (\Exception $e) {
            // falha silenciosa
        }
        return $opcoes;
    }

    // ─── Catálogo ────────────────────────────────────────────────────────────

    private function catalogo(): array
    {
        return [
            [
                'id'      => 'vendas',
                'titulo'  => 'Vendas',
                'icone'   => '💰',
                'queries' => [
                    [
                        'slug'      => 'faturamento_hoje',
                        'titulo'    => 'Faturamento de Hoje',
                        'descricao' => 'Total faturado em comandas fechadas hoje.',
                        'sql'       => "SELECT ROUND(COALESCE(SUM(valor_total),0),2) AS faturamento, COUNT(*) AS comandas FROM comandas WHERE status_comanda='fechada' AND DATE(data_hora_fechamento)=CURDATE()",
                    ],
                    [
                        'slug'      => 'faturamento_semana',
                        'titulo'    => 'Faturamento Esta Semana',
                        'descricao' => 'Total faturado em comandas fechadas nesta semana.',
                        'sql'       => "SELECT ROUND(COALESCE(SUM(valor_total),0),2) AS faturamento, COUNT(*) AS comandas FROM comandas WHERE status_comanda='fechada' AND YEARWEEK(data_hora_fechamento,1)=YEARWEEK(CURDATE(),1)",
                    ],
                    [
                        'slug'      => 'faturamento_mes',
                        'titulo'    => 'Faturamento Deste Mês',
                        'descricao' => 'Total faturado em comandas fechadas neste mês.',
                        'sql'       => "SELECT ROUND(COALESCE(SUM(valor_total),0),2) AS faturamento, COUNT(*) AS comandas FROM comandas WHERE status_comanda='fechada' AND YEAR(data_hora_fechamento)=YEAR(CURDATE()) AND MONTH(data_hora_fechamento)=MONTH(CURDATE())",
                    ],
                    [
                        'slug'      => 'ticket_medio_mes',
                        'titulo'    => 'Ticket Médio do Mês',
                        'descricao' => 'Valor médio por comanda fechada neste mês.',
                        'sql'       => "SELECT ROUND(AVG(valor_total),2) AS ticket_medio, COUNT(*) AS total_comandas FROM comandas WHERE status_comanda='fechada' AND YEAR(data_hora_fechamento)=YEAR(CURDATE()) AND MONTH(data_hora_fechamento)=MONTH(CURDATE())",
                    ],
                    [
                        'slug'      => 'descontos_periodo',
                        'titulo'    => 'Descontos por Funcionário',
                        'descricao' => 'Ranking de quem mais descontou no período. Use o filtro para ver detalhes de um funcionário específico.',
                        'sql'       => "SELECT u.name AS funcionario, COUNT(c.id) AS qtd_descontos, ROUND(SUM(c.desconto),2) AS total_descontado, ROUND(AVG(c.desconto),2) AS desconto_medio FROM comandas c JOIN users u ON u.id=c.usuario_id WHERE c.status_comanda='fechada' AND c.desconto>0 {usuario_id} AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY u.id,u.name ORDER BY total_descontado DESC",
                        'parametros' => [
                            ['chave' => 'dias', 'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                            ['chave' => 'usuario_id', 'label' => 'Funcionário', 'tipo' => 'usuario', 'padrao' => ''],
                        ],
                    ],
                    [
                        'slug'      => 'faturamento_balcao_hoje',
                        'titulo'    => 'Vendas de Balcão Hoje',
                        'descricao' => 'Faturamento de vendas de balcão (sem mesa) realizadas hoje.',
                        'sql'       => "SELECT ROUND(COALESCE(SUM(valor_total),0),2) AS faturamento, COUNT(*) AS comandas FROM comandas WHERE status_comanda='fechada' AND DATE(data_hora_fechamento)=CURDATE() AND mesa_id IS NULL",
                    ],
                ],
            ],
            [
                'id'      => 'produtos',
                'titulo'  => 'Produtos',
                'icone'   => '📦',
                'queries' => [
                    [
                        'slug'      => 'top_mais_vendidos',
                        'titulo'    => 'Mais Vendidos por Quantidade',
                        'descricao' => 'Produtos com mais unidades vendidas no período selecionado.',
                        'sql'       => "SELECT p.nome_produto, SUM(ci.quantidade) AS total_vendido, ROUND(SUM(ci.quantidade*ci.preco_unitario),2) AS receita FROM comanda_itens ci JOIN comandas c ON c.id=ci.comanda_id JOIN produtos p ON p.id=ci.produto_id WHERE c.status_comanda='fechada' AND p.deleted_at IS NULL AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY p.id,p.nome_produto ORDER BY total_vendido DESC LIMIT {limite}",
                        'parametros' => [
                            ['chave' => 'dias',   'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias',   'padrao' => '30'],
                            ['chave' => 'limite', 'label' => 'Exibir',  'tipo' => 'select', 'opcoes' => 'limite', 'padrao' => '10'],
                        ],
                    ],
                    [
                        'slug'      => 'mais_rentaveis',
                        'titulo'    => 'Mais Rentáveis por Receita',
                        'descricao' => 'Produtos com maior receita gerada no período selecionado.',
                        'sql'       => "SELECT p.nome_produto, SUM(ci.quantidade) AS total_vendido, ROUND(SUM(ci.quantidade*ci.preco_unitario),2) AS receita FROM comanda_itens ci JOIN comandas c ON c.id=ci.comanda_id JOIN produtos p ON p.id=ci.produto_id WHERE c.status_comanda='fechada' AND p.deleted_at IS NULL AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY p.id,p.nome_produto ORDER BY receita DESC LIMIT {limite}",
                        'parametros' => [
                            ['chave' => 'dias',   'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias',   'padrao' => '30'],
                            ['chave' => 'limite', 'label' => 'Exibir',  'tipo' => 'select', 'opcoes' => 'limite', 'padrao' => '10'],
                        ],
                    ],
                    [
                        'slug'      => 'estoque_baixo',
                        'titulo'    => 'Estoque Abaixo do Mínimo',
                        'descricao' => 'Produtos com estoque positivo, porém abaixo do limite configurado.',
                        'sql'       => "SELECT nome_produto, estoque_atual, preco_venda FROM produtos WHERE deleted_at IS NULL AND estoque_atual > 0 AND estoque_atual < {minimo} ORDER BY estoque_atual ASC",
                        'parametros' => [
                            [
                                'chave'  => 'minimo',
                                'label'  => 'Mínimo',
                                'tipo'   => 'select',
                                'opcoes' => [
                                    ['valor' => '5',  'label' => '< 5 un.'],
                                    ['valor' => '10', 'label' => '< 10 un.'],
                                    ['valor' => '20', 'label' => '< 20 un.'],
                                    ['valor' => '50', 'label' => '< 50 un.'],
                                ],
                                'padrao' => '10',
                            ],
                        ],
                    ],
                    [
                        'slug'      => 'estoque_zerado',
                        'titulo'    => 'Estoque Zerado',
                        'descricao' => 'Produtos ativos sem nenhuma unidade em estoque.',
                        'sql'       => "SELECT nome_produto, estoque_atual, preco_venda FROM produtos WHERE deleted_at IS NULL AND estoque_atual=0 ORDER BY nome_produto ASC",
                    ],
                    [
                        'slug'      => 'vencidos_ou_a_vencer',
                        'titulo'    => 'Vencidos ou a Vencer',
                        'descricao' => 'Produtos com validade vencida ou que vencerão em breve.',
                        'sql'       => "SELECT nome_produto, estoque_atual, data_validade, DATEDIFF(data_validade,CURDATE()) AS dias_para_vencer FROM produtos WHERE deleted_at IS NULL AND estoque_atual>0 AND data_validade IS NOT NULL AND data_validade<=DATE_ADD(CURDATE(),INTERVAL {dias} DAY) ORDER BY data_validade ASC",
                        'parametros' => [
                            [
                                'chave'  => 'dias',
                                'label'  => 'Janela',
                                'tipo'   => 'select',
                                'opcoes' => [
                                    ['valor' => '0',  'label' => 'Apenas vencidos'],
                                    ['valor' => '7',  'label' => 'Próximos 7 dias'],
                                    ['valor' => '15', 'label' => 'Próximos 15 dias'],
                                    ['valor' => '30', 'label' => 'Próximos 30 dias'],
                                    ['valor' => '60', 'label' => 'Próximos 60 dias'],
                                ],
                                'padrao' => '7',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id'      => 'mesas',
                'titulo'  => 'Mesas',
                'icone'   => '🪑',
                'queries' => [
                    [
                        'slug'      => 'mesas_mais_rentaveis',
                        'titulo'    => 'Mesas Mais Rentáveis',
                        'descricao' => 'Ranking de mesas por receita total gerada no período.',
                        'sql'       => "SELECT m.nome_mesa, COUNT(c.id) AS atendimentos, ROUND(SUM(c.valor_total),2) AS receita_total, ROUND(AVG(c.valor_total),2) AS ticket_medio FROM comandas c JOIN mesas m ON m.id=c.mesa_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY m.id,m.nome_mesa ORDER BY receita_total DESC",
                        'parametros' => [
                            ['chave' => 'dias', 'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                        ],
                    ],
                    [
                        'slug'      => 'mesas_hoje',
                        'titulo'    => 'Atendimentos por Mesa Hoje',
                        'descricao' => 'Contagem de atendimentos e receita por mesa no dia de hoje.',
                        'sql'       => "SELECT m.nome_mesa, COUNT(c.id) AS atendimentos, ROUND(SUM(c.valor_total),2) AS receita_total, ROUND(AVG(c.valor_total),2) AS ticket_medio FROM comandas c JOIN mesas m ON m.id=c.mesa_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)=CURDATE() GROUP BY m.id,m.nome_mesa ORDER BY receita_total DESC",
                    ],
                    [
                        'slug'      => 'tempo_medio_atendimento',
                        'titulo'    => 'Tempo Médio de Atendimento',
                        'descricao' => 'Tempo médio em minutos de atendimento por mesa no período.',
                        'sql'       => "SELECT m.nome_mesa, COUNT(c.id) AS atendimentos, ROUND(AVG(TIMESTAMPDIFF(MINUTE,c.data_hora_abertura,c.data_hora_fechamento))) AS minutos_medio FROM comandas c JOIN mesas m ON m.id=c.mesa_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY m.id,m.nome_mesa ORDER BY atendimentos DESC",
                        'parametros' => [
                            ['chave' => 'dias', 'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                        ],
                    ],
                ],
            ],
            [
                'id'      => 'clientes',
                'titulo'  => 'Clientes',
                'icone'   => '👤',
                'queries' => [
                    [
                        'slug'      => 'top_clientes',
                        'titulo'    => 'Clientes que Mais Compraram',
                        'descricao' => 'Ranking de clientes por valor total gasto no período.',
                        'sql'       => "SELECT cl.nome_cliente, COUNT(c.id) AS compras, ROUND(SUM(c.valor_total),2) AS total_gasto FROM comandas c JOIN clientes cl ON cl.id=c.cliente_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY cl.id,cl.nome_cliente ORDER BY total_gasto DESC LIMIT {limite}",
                        'parametros' => [
                            ['chave' => 'dias',   'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias',   'padrao' => '30'],
                            ['chave' => 'limite', 'label' => 'Exibir',  'tipo' => 'select', 'opcoes' => 'limite', 'padrao' => '10'],
                        ],
                    ],
                    [
                        'slug'      => 'clientes_inativos',
                        'titulo'    => 'Clientes Sem Compras',
                        'descricao' => 'Clientes que não realizaram compras há mais do período configurado.',
                        'sql'       => "SELECT cl.nome_cliente, MAX(c.data_hora_fechamento) AS ultima_compra, DATEDIFF(CURDATE(),MAX(c.data_hora_fechamento)) AS dias_sem_compra FROM clientes cl LEFT JOIN comandas c ON c.cliente_id=cl.id AND c.status_comanda='fechada' GROUP BY cl.id,cl.nome_cliente HAVING ultima_compra IS NULL OR dias_sem_compra>{dias} ORDER BY dias_sem_compra DESC LIMIT 20",
                        'parametros' => [
                            [
                                'chave'  => 'dias',
                                'label'  => 'Inativo há',
                                'tipo'   => 'select',
                                'opcoes' => [
                                    ['valor' => '15',  'label' => '+15 dias'],
                                    ['valor' => '30',  'label' => '+30 dias'],
                                    ['valor' => '60',  'label' => '+60 dias'],
                                    ['valor' => '90',  'label' => '+90 dias'],
                                    ['valor' => '180', 'label' => '+180 dias'],
                                ],
                                'padrao' => '30',
                            ],
                        ],
                    ],
                    [
                        'slug'      => 'clientes_identificados_hoje',
                        'titulo'    => 'Atendimentos Identificados Hoje',
                        'descricao' => 'Clientes únicos e faturamento de atendimentos identificados hoje.',
                        'sql'       => "SELECT COUNT(DISTINCT c.cliente_id) AS clientes_unicos, COUNT(c.id) AS comandas_identificadas, ROUND(SUM(c.valor_total),2) AS faturamento FROM comandas c WHERE c.status_comanda='fechada' AND c.cliente_id IS NOT NULL AND DATE(c.data_hora_fechamento)=CURDATE()",
                    ],
                ],
            ],
            [
                'id'      => 'equipe',
                'titulo'  => 'Equipe',
                'icone'   => '👥',
                'queries' => [
                    [
                        'slug'      => 'ranking_vendas_hoje',
                        'titulo'    => 'Ranking de Vendas Hoje',
                        'descricao' => 'Ranking de usuários por comandas fechadas e total vendido hoje.',
                        'sql'       => "SELECT u.name AS usuario, COUNT(c.id) AS comandas_fechadas, ROUND(SUM(c.valor_total),2) AS total_vendido FROM comandas c JOIN users u ON u.id=c.usuario_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)=CURDATE() GROUP BY u.id,u.name ORDER BY total_vendido DESC",
                    ],
                    [
                        'slug'      => 'ranking_vendas_periodo',
                        'titulo'    => 'Ranking de Vendas por Período',
                        'descricao' => 'Ranking de usuários por total vendido no período selecionado.',
                        'sql'       => "SELECT u.name AS usuario, COUNT(c.id) AS comandas_fechadas, ROUND(SUM(c.valor_total),2) AS total_vendido FROM comandas c JOIN users u ON u.id=c.usuario_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY u.id,u.name ORDER BY total_vendido DESC",
                        'parametros' => [
                            ['chave' => 'dias', 'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                        ],
                    ],
                    [
                        'slug'      => 'descontos_por_funcionario',
                        'titulo'    => 'Descontos por Funcionário',
                        'descricao' => 'Quantos e quanto cada funcionário descontou no período.',
                        'sql'       => "SELECT u.name AS usuario, COUNT(c.id) AS qtd_descontos, ROUND(SUM(c.desconto),2) AS total_descontado FROM comandas c JOIN users u ON u.id=c.usuario_id WHERE c.status_comanda='fechada' AND c.desconto>0 {usuario_id} AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY u.id,u.name ORDER BY total_descontado DESC",
                        'parametros' => [
                            ['chave' => 'dias',       'label' => 'Período',     'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                            ['chave' => 'usuario_id', 'label' => 'Funcionário', 'tipo' => 'usuario', 'padrao' => ''],
                        ],
                    ],
                ],
            ],
            [
                'id'      => 'estoque',
                'titulo'  => 'Estoque',
                'icone'   => '📊',
                'queries' => [
                    [
                        'slug'      => 'ultimas_entradas',
                        'titulo'    => 'Últimas Entradas de Estoque',
                        'descricao' => 'Entradas de estoque mais recentes registradas.',
                        'sql'       => "SELECT p.nome_produto, ee.quantidade_comprada, ee.custo_unitario_compra, ROUND(ee.quantidade_comprada*ee.custo_unitario_compra,2) AS custo_total, DATE(ee.created_at) AS data_entrada FROM estoque_entradas ee JOIN produtos p ON p.id=ee.produto_id ORDER BY ee.created_at DESC LIMIT {limite}",
                        'parametros' => [
                            ['chave' => 'limite', 'label' => 'Exibir', 'tipo' => 'select', 'opcoes' => 'limite', 'padrao' => '15'],
                        ],
                    ],
                    [
                        'slug'      => 'perdas_periodo',
                        'titulo'    => 'Perdas de Estoque',
                        'descricao' => 'Perdas de estoque registradas no período, agrupadas por produto.',
                        'sql'       => "SELECT p.nome_produto, SUM(ep.quantidade) AS total_perdido, ep.motivo FROM estoque_perdas ep JOIN produtos p ON p.id=ep.produto_id WHERE DATE(ep.created_at)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY) GROUP BY p.id,p.nome_produto,ep.motivo ORDER BY total_perdido DESC",
                        'parametros' => [
                            ['chave' => 'dias', 'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                        ],
                    ],
                    [
                        'slug'      => 'encalhados',
                        'titulo'    => 'Sem Venda no Período',
                        'descricao' => 'Produtos com estoque disponível que não tiveram vendas no período.',
                        'sql'       => "SELECT p.nome_produto, p.estoque_atual, p.preco_venda FROM produtos p WHERE p.deleted_at IS NULL AND p.estoque_atual>0 AND p.id NOT IN (SELECT DISTINCT ci.produto_id FROM comanda_itens ci JOIN comandas c ON c.id=ci.comanda_id WHERE c.status_comanda='fechada' AND DATE(c.data_hora_fechamento)>=DATE_SUB(CURDATE(),INTERVAL {dias} DAY)) ORDER BY p.estoque_atual DESC",
                        'parametros' => [
                            ['chave' => 'dias', 'label' => 'Período', 'tipo' => 'select', 'opcoes' => 'dias', 'padrao' => '30'],
                        ],
                    ],
                ],
            ],
            [
                'id'      => 'fornecedores',
                'titulo'  => 'Fornecedores',
                'icone'   => '🚚',
                'queries' => [
                    [
                        'slug'      => 'fornecedores_ativos',
                        'titulo'    => 'Fornecedores Cadastrados',
                        'descricao' => 'Todos os fornecedores com a quantidade de produtos vinculados.',
                        'sql'       => "SELECT f.nome_fantasia, f.nome_razao_social, COUNT(DISTINCT pf.produto_id) AS produtos_vinculados FROM fornecedores f LEFT JOIN produto_fornecedor pf ON pf.fornecedor_id=f.id GROUP BY f.id,f.nome_fantasia,f.nome_razao_social ORDER BY produtos_vinculados DESC",
                    ],
                    [
                        'slug'      => 'ultimas_compras',
                        'titulo'    => 'Últimas Compras por Fornecedor',
                        'descricao' => 'Compras de estoque mais recentes com informação do fornecedor.',
                        'sql'       => "SELECT f.nome_fantasia, p.nome_produto, ee.quantidade_comprada, ee.custo_unitario_compra, DATE(ee.created_at) AS data FROM estoque_entradas ee JOIN produtos p ON p.id=ee.produto_id LEFT JOIN fornecedores f ON f.id=ee.fornecedor_id ORDER BY ee.created_at DESC LIMIT {limite}",
                        'parametros' => [
                            ['chave' => 'limite', 'label' => 'Exibir', 'tipo' => 'select', 'opcoes' => 'limite', 'padrao' => '15'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ─── API pública ─────────────────────────────────────────────────────────

    public function listar(): array
    {
        $opcoes_usuarios = null;

        return array_map(function (array $categoria) use (&$opcoes_usuarios) {
            return [
                'id'      => $categoria['id'],
                'titulo'  => $categoria['titulo'],
                'icone'   => $categoria['icone'],
                'queries' => array_map(function (array $q) use (&$opcoes_usuarios) {
                    $parametros = [];
                    foreach ($q['parametros'] ?? [] as $param) {
                        $parametros[] = $this->resolver_param($param, $opcoes_usuarios);
                    }
                    return [
                        'slug'       => $q['slug'],
                        'titulo'     => $q['titulo'],
                        'descricao'  => $q['descricao'],
                        'parametros' => $parametros,
                    ];
                }, $categoria['queries']),
            ];
        }, $this->catalogo());
    }

    public function executar(string $slug, array $valores = []): array
    {
        $encontrada = null;
        foreach ($this->catalogo() as $categoria) {
            foreach ($categoria['queries'] as $q) {
                if ($q['slug'] === $slug) {
                    $encontrada = $q;
                    break 2;
                }
            }
        }

        if ($encontrada === null) {
            throw new \InvalidArgumentException("Consulta '{$slug}' não encontrada.");
        }

        $sql = $this->aplicar_parametros(
            $encontrada['sql'],
            $encontrada['parametros'] ?? [],
            $valores,
        );

        $resultado = DB::select($sql);
        $linhas    = array_map(fn ($row) => (array) $row, $resultado);
        $colunas   = count($linhas) > 0 ? array_keys($linhas[0]) : [];

        return [
            'titulo'      => $encontrada['titulo'],
            'descricao'   => $encontrada['descricao'],
            'colunas'     => $colunas,
            'linhas'      => $linhas,
            'total_linhas' => count($linhas),
        ];
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    private function resolver_param(array $param, ?array &$cache_usuarios): array
    {
        if ($param['tipo'] === 'usuario') {
            if ($cache_usuarios === null) {
                $cache_usuarios = $this->opcoes_usuarios();
            }
            return array_merge($param, ['opcoes' => $cache_usuarios]);
        }

        // Resolve atalhos de opcoes compartilhadas
        if (isset($param['opcoes']) && is_string($param['opcoes'])) {
            $param['opcoes'] = match ($param['opcoes']) {
                'dias'   => $this->opcoes_dias(),
                'limite' => $this->opcoes_limite(),
                default  => [],
            };
        }

        return $param;
    }

    private function aplicar_parametros(string $sql, array $definicoes, array $valores): string
    {
        foreach ($definicoes as $param) {
            $chave = $param['chave'];
            $valor = $valores[$chave] ?? $param['padrao'];

            if ($param['tipo'] === 'usuario') {
                $id_seguro = (int) $valor;
                $fragmento = ($id_seguro > 0) ? "AND c.usuario_id = {$id_seguro}" : '';
                $sql = str_replace('{usuario_id}', $fragmento, $sql);
                continue;
            }

            // tipo 'select': valida valor contra lista de opcoes
            $opcoes_raw = is_string($param['opcoes'])
                ? match ($param['opcoes']) {
                    'dias'   => $this->opcoes_dias(),
                    'limite' => $this->opcoes_limite(),
                    default  => [],
                }
                : $param['opcoes'];

            $valores_validos = array_column($opcoes_raw, 'valor');
            if (!in_array((string) $valor, $valores_validos, true)) {
                $valor = $param['padrao'];
            }

            $sql = str_replace("{{$chave}}", (string) $valor, $sql);
        }

        return $sql;
    }
}
