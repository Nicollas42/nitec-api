"""Descricoes de dominio e formatacao de schema para o agente."""

from __future__ import annotations


TABELAS_PERMITIDAS = (
    "comandas",
    "comanda_itens",
    "produtos",
    "clientes",
    "users",
    "mesas",
    "fornecedores",
    "estoque_entradas",
    "estoque_perdas",
    "produto_fornecedor",
    "produto_estoque_lotes",
    "produto_estoque_consumos",
)


DESCRICOES_TABELAS = {
    "comandas": "Cabecalho das vendas. Para vendas efetivas, normalmente use status_comanda = 'fechada'.",
    "comanda_itens": "Itens vendidos em cada comanda. A quantidade vendida normalmente vem daqui.",
    "produtos": "Cadastro mestre de produtos, com nome_produto, categoria, estoque_atual, preco_venda e preco_custo_medio.",
    "clientes": "Clientes associados a comandas, quando existir identificacao do consumidor.",
    "users": "Usuarios do tenant, incluindo dono, atendente, caixa e equipe.",
    "mesas": "Mesas e salao, quando a operacao usa atendimento local.",
    "fornecedores": "Cadastro de fornecedores vinculados ao estoque.",
    "estoque_entradas": "Entradas de estoque e compras recebidas.",
    "estoque_perdas": "Perdas e baixas de estoque.",
    "produto_fornecedor": "Relaciona produto e fornecedor com preco, fator de conversao e SKU do fornecedor.",
    "produto_estoque_lotes": "Lotes FIFO atuais e historicos por produto.",
    "produto_estoque_consumos": "Consumos FIFO por item, perda ou ajuste.",
}


REGRAS_NEGOCIO = (
    "Quando a pergunta falar de vendas, prefira status_comanda = 'fechada'.",
    "Considere desconto em comandas quando a pergunta envolver faturamento liquido.",
    "Produtos apagados logicamente podem ter deleted_at; se a coluna existir, prefira deleted_at IS NULL quando fizer sentido.",
    "Para produto mais vendido, use SUM(comanda_itens.quantidade).",
    "Para faturamento, voce pode usar comandas.valor_total ou a soma dos itens, conforme a pergunta pedir.",
)


def montar_contexto_schema(schema_tabelas: dict[str, list[dict[str, str]]]) -> str:
    """Transforma o schema introspectado em texto para o prompt do modelo."""

    linhas: list[str] = []
    linhas.append("Tabelas permitidas:")

    for tabela in TABELAS_PERMITIDAS:
        if tabela not in schema_tabelas:
            continue

        linhas.append(f"- {tabela}: {DESCRICOES_TABELAS.get(tabela, 'Sem descricao.')}")

        for coluna in schema_tabelas[tabela]:
            linhas.append(
                "  - "
                f"{coluna['column_name']} "
                f"({coluna['column_type']}, null={coluna['is_nullable']}, key={coluna['column_key']})"
            )

    linhas.append("")
    linhas.append("Regras de negocio:")

    for regra in REGRAS_NEGOCIO:
        linhas.append(f"- {regra}")

    return "\n".join(linhas)
