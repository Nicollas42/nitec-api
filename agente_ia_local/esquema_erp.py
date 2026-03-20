"""Descricoes de dominio, roteamento de contexto e formatacao de schema para o agente."""

from __future__ import annotations

import unicodedata


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
    "comandas": "Cabecalho das vendas e atendimentos. valor_total guarda o total final apos desconto. Balcao usa mesa_id nulo.",
    "comanda_itens": "Itens vendidos em cada comanda. A quantidade vendida normalmente vem daqui.",
    "produtos": "Cadastro mestre de produtos, com nome_produto, categoria, estoque_atual, preco_venda e preco_custo_medio.",
    "clientes": "Clientes associados a comandas, quando existir identificacao do consumidor.",
    "users": "Usuarios do tenant, incluindo dono, gerente, caixa e garcom.",
    "mesas": "Mesas e salao, quando a operacao usa atendimento local.",
    "fornecedores": "Cadastro de fornecedores vinculados ao estoque e as compras.",
    "estoque_entradas": "Entradas de estoque e compras recebidas. quantidade_comprada representa a quantidade comprada na unidade do fornecedor.",
    "estoque_perdas": "Perdas e baixas de estoque sem faturamento.",
    "produto_fornecedor": "Relaciona produto e fornecedor com SKU, unidade_embalagem, fator_conversao e ultimo_preco_compra.",
    "produto_estoque_lotes": "Lotes FIFO atuais e historicos por produto, com saldo e custo por unidade.",
    "produto_estoque_consumos": "Consumos FIFO por item, perda ou ajuste, ligados a uma referencia operacional.",
}


REGRAS_NEGOCIO = (
    "Quando a pergunta falar de vendas concluidas, prefira comandas.status_comanda = 'fechada'.",
    "Venda de balcao ou avulsa significa comandas.mesa_id IS NULL.",
    "comandas.valor_total guarda o total final apos desconto; para valor antes do desconto use valor_total + desconto.",
    "Produtos apagados logicamente podem ter deleted_at; use produtos.deleted_at IS NULL quando fizer sentido falar de catalogo atual.",
    "Para produto mais vendido, use SUM(comanda_itens.quantidade).",
    "Para receita por produto, use SUM(comanda_itens.quantidade * comanda_itens.preco_unitario).",
    "Para lucro estimado, use preco_unitario - COALESCE(produtos.preco_custo_medio, 0).",
    "estoque_entradas.quantidade_comprada representa embalagens compradas; produto_fornecedor.fator_conversao converte para unidades reais.",
    "produto_estoque_lotes e produto_estoque_consumos implementam o rastreio FIFO do estoque.",
    "Produto encalhado costuma ser produto com estoque_atual > 0 e sem venda no periodo consultado.",
)


MAPA_ASSUNTOS_TABELAS = (
    {
        "palavras_chave": (
            "venda",
            "vendas",
            "faturamento",
            "receita",
            "ticket",
            "desconto",
            "balcao",
            "mesa",
            "comanda",
            "produto mais vendido",
            "item mais vendido",
        ),
        "tabelas": ("comandas", "comanda_itens", "produtos", "users", "mesas", "clientes"),
    },
    {
        "palavras_chave": (
            "estoque",
            "lote",
            "fifo",
            "saldo",
            "validade",
            "perda",
            "encalhado",
            "consumo interno",
            "quebra",
            "vencimento",
        ),
        "tabelas": (
            "produtos",
            "produto_estoque_lotes",
            "produto_estoque_consumos",
            "estoque_entradas",
            "estoque_perdas",
            "produto_fornecedor",
            "fornecedores",
        ),
    },
    {
        "palavras_chave": (
            "fornecedor",
            "fornecedores",
            "compra",
            "compras",
            "nf",
            "nota fiscal",
            "entrada",
            "entradas",
        ),
        "tabelas": ("fornecedores", "produto_fornecedor", "estoque_entradas", "produtos"),
    },
    {
        "palavras_chave": (
            "garcom",
            "caixa",
            "gerente",
            "dono",
            "equipe",
            "funcionario",
            "usuario",
            "quem vendeu",
        ),
        "tabelas": ("users", "comandas", "comanda_itens", "produtos"),
    },
    {
        "palavras_chave": ("cliente", "clientes", "consumidor"),
        "tabelas": ("clientes", "comandas", "comanda_itens", "produtos"),
    },
)


TABELAS_PADRAO_FALLBACK = (
    "comandas",
    "comanda_itens",
    "produtos",
    "users",
    "mesas",
    "clientes",
)


def normalizar_texto(texto: str) -> str:
    """Normaliza um texto para comparacoes simples por palavra-chave."""

    texto_normalizado = unicodedata.normalize("NFKD", texto)
    texto_sem_acento = "".join(
        caractere for caractere in texto_normalizado if not unicodedata.combining(caractere)
    )
    return " ".join(texto_sem_acento.lower().split())


def selecionar_tabelas_relevantes(
    pergunta: str,
    schema_tabelas: dict[str, list[dict[str, str]]],
    limite_tabelas: int = 6,
) -> tuple[str, ...]:
    """Seleciona apenas as tabelas mais provaveis para a pergunta atual."""

    pergunta_normalizada = normalizar_texto(pergunta)
    tabelas_disponiveis = set(schema_tabelas)
    tabelas_relevantes: list[str] = []

    for mapeamento in MAPA_ASSUNTOS_TABELAS:
        if any(palavra in pergunta_normalizada for palavra in mapeamento["palavras_chave"]):
            for tabela in mapeamento["tabelas"]:
                if tabela in tabelas_disponiveis and tabela not in tabelas_relevantes:
                    tabelas_relevantes.append(tabela)

    if not tabelas_relevantes:
        for tabela in TABELAS_PADRAO_FALLBACK:
            if tabela in tabelas_disponiveis and tabela not in tabelas_relevantes:
                tabelas_relevantes.append(tabela)

    if not tabelas_relevantes:
        for tabela in TABELAS_PERMITIDAS:
            if tabela in tabelas_disponiveis:
                tabelas_relevantes.append(tabela)

    return tuple(tabelas_relevantes[: max(1, limite_tabelas)])


def formatar_coluna_contexto(coluna: dict[str, str]) -> str:
    """Compacta a descricao de uma coluna para reduzir tokens no prompt."""

    partes = [coluna["column_name"], f"({coluna['column_type']}"]

    if coluna["column_key"] == "PRI":
        partes.append(", PK")
    elif coluna["column_key"]:
        partes.append(f", key={coluna['column_key']}")

    if coluna["is_nullable"] == "NO":
        partes.append(", NOT NULL")

    partes.append(")")
    return "".join(partes)


def montar_contexto_schema(
    schema_tabelas: dict[str, list[dict[str, str]]],
    tabelas_relevantes: tuple[str, ...] | None = None,
) -> str:
    """Transforma o schema introspectado em um contexto compacto para o prompt."""

    linhas: list[str] = []
    linhas.append("Tabelas permitidas:")
    tabelas_contexto = tabelas_relevantes or tuple(
        tabela for tabela in TABELAS_PERMITIDAS if tabela in schema_tabelas
    )

    for tabela in tabelas_contexto:
        if tabela not in schema_tabelas:
            continue

        linhas.append(f"- {tabela}: {DESCRICOES_TABELAS.get(tabela, 'Sem descricao.')}")
        colunas_formatadas = ", ".join(
            formatar_coluna_contexto(coluna) for coluna in schema_tabelas[tabela]
        )
        linhas.append(f"  colunas: {colunas_formatadas}")

    linhas.append("")
    linhas.append("Regras de negocio essenciais:")

    for regra in REGRAS_NEGOCIO:
        linhas.append(f"- {regra}")

    return "\n".join(linhas)
