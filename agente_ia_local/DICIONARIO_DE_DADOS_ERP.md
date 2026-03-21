# Dicionario de Dados - NitecSystem

Ultima revisao: 2026-03-20

Este documento descreve o schema tenant atual do ERP e serve como base de conhecimento para o agente local de consultas em linguagem natural.

## Escopo do agente

- O agente gera apenas SQL `SELECT`.
- O agente consulta somente tabelas do banco tenant.
- O agente usa a base central apenas para resolver qual tenant deve ser consultado.
- O agente atual trabalha com estas tabelas tenant:
  - `users`
  - `mesas`
  - `clientes`
  - `comandas`
  - `comanda_itens`
  - `produtos`
  - `fornecedores`
  - `produto_fornecedor`
  - `estoque_entradas`
  - `produto_estoque_lotes`
  - `produto_estoque_consumos`
  - `estoque_perdas`

## Visao geral do dominio

- Uma `comanda` representa uma venda ou atendimento.
- Uma `comanda` pode estar ligada a uma `mesa`, a um `cliente` e a um `user`.
- Uma `comanda` possui varios `comanda_itens`.
- Cada `comanda_item` congela o `preco_unitario` praticado no momento da venda.
- O cadastro de `produtos` guarda o saldo consolidado e o custo medio atual.
- O estoque operacional usa lotes FIFO em `produto_estoque_lotes`.
- Cada consumo FIFO fica rastreado em `produto_estoque_consumos`.
- Entradas de estoque ficam em `estoque_entradas`.
- Perdas, quebras, vencimentos e baixas nao financeiras ficam em `estoque_perdas`.
- O relacionamento entre `produtos` e `fornecedores` fica em `produto_fornecedor`.

## Regras obrigatorias de interpretacao

- Quando a pergunta falar de venda concluida, faturamento, receita, ranking de produtos vendidos ou desempenho comercial, usar `comandas.status_comanda = 'fechada'`, salvo pedido explicito em contrario.
- Comanda de balcao, venda avulsa, atendimento sem mesa ou venda sem mesa significa `comandas.mesa_id IS NULL`.
- Comandas canceladas nao entram em faturamento nem em quantidade vendida, salvo pedido explicito do usuario.
- `comandas.valor_total` guarda o total final da comanda apos desconto.
- `comandas.desconto` guarda o desconto separado.
- Se a pergunta for sobre valor antes do desconto, usar `comandas.valor_total + comandas.desconto`.
- Produto mais vendido normalmente significa `SUM(comanda_itens.quantidade)`.
- Receita por produto normalmente significa `SUM(comanda_itens.quantidade * comanda_itens.preco_unitario)`.
- Lucro estimado por produto ou por vendedor normalmente significa `SUM(comanda_itens.quantidade * (comanda_itens.preco_unitario - COALESCE(produtos.preco_custo_medio, 0)))`.
- `estoque_entradas.quantidade_comprada` representa quantidade comprada em embalagem, caixa ou unidade de compra.
- `produto_fornecedor.fator_conversao` converte a quantidade comprada em unidades reais de estoque.
- `produto_estoque_lotes.quantidade_atual` representa o saldo atual do lote.
- `produto_estoque_consumos` registra de qual lote FIFO saiu cada baixa.
- Produto encalhado significa produto com `produtos.estoque_atual > 0` e sem venda no periodo analisado.
- Produtos excluidos logicamente ainda podem existir com `deleted_at` preenchido.
- Quando a pergunta for sobre catalogo atual ou produtos ativos, considerar `produtos.deleted_at IS NULL`.

## Glossario de linguagem natural

- "venda", "faturamento", "receita", "comanda fechada" -> `comandas`
- "item vendido", "quantidade vendida", "produto campeao" -> `comanda_itens`
- "garcom", "caixa", "gerente", "dono", "equipe", "vendedor" -> `users`
- "mesa", "salao", "atendimento local" -> `mesas`
- "cliente" -> `clientes`
- "fornecedor", "compra", "entrada", "nota fiscal", "NF" -> `fornecedores` + `estoque_entradas`
- "estoque", "lote", "FIFO", "saldo", "validade" -> `produtos` + `produto_estoque_lotes`
- "baixa", "quebra", "vencimento", "perda", "consumo interno" -> `estoque_perdas`
- "sku do fornecedor", "embalagem", "fator de conversao" -> `produto_fornecedor`
- "balcao", "avulso" -> `comandas.mesa_id IS NULL`

## Tabelas e colunas principais

### `users`

Finalidade:
- Equipe operacional e administrativa do tenant.

Colunas importantes:
- `id`
- `name`
- `email`
- `telefone`
- `tipo_usuario`
- `status_conta`
- `tipo_contrato`
- `expiracao_acesso`
- `created_at`
- `updated_at`

Regras de negocio:
- `tipo_usuario` costuma assumir `dono`, `gerente`, `caixa` ou `garcom`.
- `status_conta` costuma assumir `ativo`, `inativo` ou `demitido`.
- `tipo_contrato` costuma assumir `fixo` ou `temporario`.
- Perguntas sobre desempenho por funcionario normalmente cruzam `users` com `comandas`.

### `mesas`

Finalidade:
- Infraestrutura do salao.

Colunas importantes:
- `id`
- `nome_mesa`
- `status_mesa`
- `capacidade_pessoas`
- `created_at`
- `updated_at`

Regras de negocio:
- `status_mesa` costuma ser `livre` ou `ocupada`.
- Ranking de mesas usa `comandas` fechadas com `mesa_id` preenchido.

### `clientes`

Finalidade:
- Cadastro basico de clientes identificados.

Colunas importantes:
- `id`
- `nome_cliente`
- `telefone`
- `created_at`
- `updated_at`

Regras de negocio:
- Nem toda comanda tem cliente identificado.
- Em vendas de balcao o cliente pode ser nulo.

### `comandas`

Finalidade:
- Cabecalho das vendas e dos atendimentos.

Colunas importantes:
- `id`
- `mesa_id`
- `cliente_id`
- `usuario_id`
- `status_comanda`
- `motivo_cancelamento`
- `data_hora_abertura`
- `data_hora_fechamento`
- `tipo_conta`
- `valor_total`
- `desconto`
- `created_at`
- `updated_at`
- `deleted_at`

Regras de negocio:
- `status_comanda` aparece pelo menos como `aberta`, `fechada` e `cancelada`.
- `valor_total` representa o total final apos desconto.
- `desconto` fica separado e pode ser somado para reconstruir o valor antes do desconto.
- `tipo_conta` tem padrao `geral`.
- `mesa_id` nulo significa venda de balcao ou avulsa.
- Tempo de permanencia pode ser calculado por `TIMESTAMPDIFF(MINUTE, data_hora_abertura, data_hora_fechamento)`.

### `comanda_itens`

Finalidade:
- Itens lancados dentro de cada comanda.

Colunas importantes:
- `id`
- `comanda_id`
- `produto_id`
- `quantidade`
- `preco_unitario`
- `data_hora_lancamento`
- `created_at`
- `updated_at`

Regras de negocio:
- Esta tabela e a fonte principal para quantidade vendida.
- `preco_unitario` e congelado na hora da venda.
- Receita por item usa `quantidade * preco_unitario`.

### `produtos`

Finalidade:
- Catalogo principal do ERP com preco, estoque e custo medio.

Colunas importantes:
- `id`
- `nome_produto`
- `codigo_interno`
- `categoria`
- `unidade_medida`
- `preco_venda`
- `preco_custo_medio`
- `margem_lucro_percentual`
- `estoque_atual`
- `data_validade`
- `created_at`
- `updated_at`
- `deleted_at`

Regras de negocio:
- `estoque_atual` e o saldo consolidado atual do produto.
- `preco_custo_medio` e recalculado a partir dos lotes ativos.
- `margem_lucro_percentual` e informativa e nao substitui o calculo real de lucro.
- `deleted_at` indica exclusao logica.

### `fornecedores`

Finalidade:
- Cadastro de fornecedores do tenant.

Colunas importantes:
- `id`
- `nome_fantasia`
- `razao_social`
- `cnpj`
- `telefone`
- `email`
- `vendedor`
- `contato_vendedor`
- `status_fornecedor`
- `created_at`
- `updated_at`

Regras de negocio:
- Perguntas sobre compras por fornecedor normalmente cruzam esta tabela com `estoque_entradas`.
- Perguntas sobre mix de fornecedores por produto normalmente cruzam com `produto_fornecedor`.

### `produto_fornecedor`

Finalidade:
- Relacao editavel entre produto e fornecedor.

Colunas importantes:
- `id`
- `produto_id`
- `fornecedor_id`
- `codigo_sku_fornecedor`
- `unidade_embalagem`
- `fator_conversao`
- `ultimo_preco_compra`

Regras de negocio:
- Uma linha representa como um fornecedor entrega um produto especifico.
- `unidade_embalagem` costuma guardar algo como `CX`, `PC` ou outra sigla operacional.
- `fator_conversao` informa quantas unidades reais entram no estoque a cada embalagem comprada.
- `ultimo_preco_compra` e o preco da ultima compra naquela embalagem do fornecedor.

### `estoque_entradas`

Finalidade:
- Historico de entradas de estoque.

Colunas importantes:
- `id`
- `produto_id`
- `fornecedor_id`
- `numero_nf`
- `data_emissao_nf`
- `chave_nfe`
- `usuario_id`
- `quantidade_comprada`
- `custo_unitario_compra`
- `custo_total_entrada`
- `created_at`
- `updated_at`

Regras de negocio:
- `quantidade_comprada` representa a quantidade comprada na unidade do fornecedor, nao a quantidade final de unidades em estoque.
- `custo_total_entrada` costuma ser `quantidade_comprada * custo_unitario_compra`.
- Para estimar unidades fisicas recebidas, cruzar com `produto_fornecedor.fator_conversao`.
- `numero_nf`, `data_emissao_nf` e `chave_nfe` ajudam em auditoria fiscal e rastreabilidade.

### `produto_estoque_lotes`

Finalidade:
- Saldo por lote com politica FIFO.

Colunas importantes:
- `id`
- `produto_id`
- `fornecedor_id`
- `estoque_entrada_id`
- `modo_origem`
- `data_validade`
- `quantidade_inicial`
- `quantidade_atual`
- `custo_unitario_medio`
- `created_at`
- `updated_at`

Regras de negocio:
- Cada linha representa um lote de estoque.
- `quantidade_atual` mostra o saldo restante do lote.
- FIFO consome primeiro os lotes mais antigos por `created_at` e `id`.
- `modo_origem` pode aparecer como `saldo_legado`, `ajuste_cadastro` ou modos de entrada operacionais.
- `custo_unitario_medio` e o custo por unidade real de estoque, nao por embalagem comprada.

### `produto_estoque_consumos`

Finalidade:
- Rastreio das baixas FIFO por referencia operacional.

Colunas importantes:
- `id`
- `produto_id`
- `fornecedor_id`
- `produto_estoque_lote_id`
- `referencia_tipo`
- `referencia_id`
- `quantidade`
- `custo_unitario_medio`
- `created_at`
- `updated_at`

Regras de negocio:
- Cada linha registra uma faixa consumida de um lote.
- `referencia_tipo` pode apontar para algo como `comanda_item`, `estoque_perda` ou `ajuste_cadastro`.
- Esta tabela e util para auditoria de custo e rastreabilidade FIFO.

### `estoque_perdas`

Finalidade:
- Baixas nao financeiras do estoque.

Colunas importantes:
- `id`
- `produto_id`
- `usuario_id`
- `quantidade`
- `motivo`
- `custo_total_perda`
- `created_at`
- `updated_at`

Regras de negocio:
- Registra perdas, quebras, vencimentos, consumo interno e baixas similares.
- `custo_total_perda` representa o impacto financeiro da perda.
- Pode ser usada para ranking de desperdicio por produto, por usuario ou por motivo.

## Relacionamentos principais

- `comandas.usuario_id -> users.id`
- `comandas.mesa_id -> mesas.id`
- `comandas.cliente_id -> clientes.id`
- `comanda_itens.comanda_id -> comandas.id`
- `comanda_itens.produto_id -> produtos.id`
- `estoque_entradas.produto_id -> produtos.id`
- `estoque_entradas.fornecedor_id -> fornecedores.id`
- `estoque_entradas.usuario_id -> users.id`
- `produto_fornecedor.produto_id -> produtos.id`
- `produto_fornecedor.fornecedor_id -> fornecedores.id`
- `produto_estoque_lotes.produto_id -> produtos.id`
- `produto_estoque_lotes.fornecedor_id -> fornecedores.id`
- `produto_estoque_lotes.estoque_entrada_id -> estoque_entradas.id`
- `produto_estoque_consumos.produto_id -> produtos.id`
- `produto_estoque_consumos.fornecedor_id -> fornecedores.id`
- `produto_estoque_consumos.produto_estoque_lote_id -> produto_estoque_lotes.id`
- `estoque_perdas.produto_id -> produtos.id`
- `estoque_perdas.usuario_id -> users.id`

## Formulas e KPIs usados no ERP

- Produto mais vendido:
  - `SUM(comanda_itens.quantidade)` por produto em comandas fechadas.
- Receita por produto:
  - `SUM(comanda_itens.quantidade * comanda_itens.preco_unitario)`.
- Lucro estimado por produto:
  - `SUM(comanda_itens.quantidade * (comanda_itens.preco_unitario - COALESCE(produtos.preco_custo_medio, 0)))`.
- Faturamento final do periodo:
  - `SUM(comandas.valor_total)` em comandas fechadas.
- Total de descontos do periodo:
  - `SUM(comandas.desconto)` em comandas fechadas.
- Valor antes dos descontos:
  - `SUM(comandas.valor_total + comandas.desconto)` em comandas fechadas.
- Ticket medio:
  - `SUM(comandas.valor_total) / COUNT(comandas.id)` em comandas fechadas.
- Faturamento de balcao:
  - `SUM(comandas.valor_total)` em comandas fechadas com `mesa_id IS NULL`.
- Tempo medio de atendimento:
  - `AVG(TIMESTAMPDIFF(MINUTE, comandas.data_hora_abertura, comandas.data_hora_fechamento))`.
- Valor de estoque atual:
  - `SUM(produto_estoque_lotes.quantidade_atual * produto_estoque_lotes.custo_unitario_medio)` considerando lotes com saldo.
- Prejuizo por perdas:
  - `SUM(estoque_perdas.custo_total_perda)`.

## Perguntas frequentes e interpretacao esperada

- "Qual o produto mais vendido?"
  - Agrupar `comanda_itens` por produto, juntar com `comandas` e `produtos`, filtrar `status_comanda = 'fechada'` e ordenar por `SUM(quantidade)` desc.

- "Quanto vendi no balcao hoje?"
  - Somar `comandas.valor_total` com `status_comanda = 'fechada'`, `mesa_id IS NULL` e filtro de data em `data_hora_fechamento`.

- "Quais produtos estao encalhados?"
  - Listar produtos com `estoque_atual > 0` e sem venda no periodo analisado.

- "Qual garcom vendeu mais?"
  - Somar `comandas.valor_total` por `usuario_id` em comandas fechadas, juntando com `users`.

- "Quanto perdi de estoque neste mes?"
  - Somar `estoque_perdas.custo_total_perda` no intervalo informado.

- "Quanto entrou de estoque por fornecedor?"
  - Usar `estoque_entradas` agrupado por `fornecedor_id`.
  - Se o usuario perguntar em unidades reais, considerar `quantidade_comprada * fator_conversao`.

- "Qual o custo medio atual de cada produto?"
  - Ler diretamente `produtos.preco_custo_medio`.
  - Se precisar auditar a composicao, cruzar com `produto_estoque_lotes`.

## Observacoes para o agente

- Preferir aliases simples e legiveis.
- Evitar `SELECT *` quando nao for necessario.
- Se a pergunta pedir ranking, devolver ordenacao coerente com a metrica principal.
- Se a pergunta nao trouxer periodo, o agente pode responder sem filtro temporal, mas deve explicar isso na justificativa.
- Se a pergunta estiver ambigua, o agente deve pedir clarificacao em vez de adivinhar.
