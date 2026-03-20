"""Consultas prontas para responder perguntas frequentes sem passar pelo LLM."""

from __future__ import annotations

import re
from dataclasses import dataclass

from esquema_erp import normalizar_texto


@dataclass(slots=True)
class ConsultaProntaResolvida:
    """Representa uma consulta pronta identificada para a pergunta recebida."""

    justificativa: str
    sql: str
    tipo_consulta: str


class ResolvedorConsultasProntas:
    """Identifica perguntas frequentes e devolve SQL pronto de baixa latencia."""

    def resolver(
        self,
        pergunta: str,
        limite_linhas: int,
    ) -> ConsultaProntaResolvida | None:
        """Tenta resolver a pergunta com uma consulta pronta antes de usar o modelo."""

        pergunta_normalizada = normalizar_texto(pergunta)

        consulta_pronta = self.resolver_consulta_produto_mais_vendido(
            pergunta_normalizada,
            limite_linhas,
        )
        if consulta_pronta:
            return consulta_pronta

        consulta_pronta = self.resolver_consulta_faturamento(pergunta_normalizada)
        if consulta_pronta:
            return consulta_pronta

        return self.resolver_consulta_descontos(pergunta_normalizada)

    def resolver_consulta_produto_mais_vendido(
        self,
        pergunta_normalizada: str,
        limite_linhas: int,
    ) -> ConsultaProntaResolvida | None:
        """Resolve perguntas sobre produto mais vendido ou ranking de produtos vendidos."""

        frases_singulares = (
            "produto mais vendido",
            "produto que mais vendeu",
            "item mais vendido",
            "item que mais vendeu",
            "produto mais saiu",
            "item mais saiu",
        )
        frases_ranking = (
            "produtos mais vendidos",
            "itens mais vendidos",
            "ranking de produtos",
            "top produtos",
            "top 10 produtos",
            "top 5 produtos",
        )

        if any(frase in pergunta_normalizada for frase in frases_singulares):
            filtro_periodo, justificativa_periodo = self.resolver_filtro_periodo(
                pergunta_normalizada,
                "c",
            )
            sql = f"""
                SELECT
                    p.id AS produto_id,
                    p.nome_produto,
                    SUM(ci.quantidade) AS total_vendido
                FROM comandas c
                INNER JOIN comanda_itens ci ON ci.comanda_id = c.id
                INNER JOIN produtos p ON p.id = ci.produto_id
                WHERE c.status_comanda = 'fechada'
                  AND p.deleted_at IS NULL
                  {filtro_periodo}
                GROUP BY p.id, p.nome_produto
                ORDER BY total_vendido DESC, p.nome_produto ASC
                LIMIT 1
            """.strip()
            return ConsultaProntaResolvida(
                justificativa=(
                    "Consulta pronta para encontrar o produto mais vendido "
                    f"{justificativa_periodo}."
                ),
                sql=sql,
                tipo_consulta="produto_mais_vendido",
            )

        if any(frase in pergunta_normalizada for frase in frases_ranking):
            filtro_periodo, justificativa_periodo = self.resolver_filtro_periodo(
                pergunta_normalizada,
                "c",
            )
            limite_resultados = self.extrair_limite_ranking(
                pergunta_normalizada,
                limite_linhas,
            )
            sql = f"""
                SELECT
                    p.id AS produto_id,
                    p.nome_produto,
                    SUM(ci.quantidade) AS total_vendido
                FROM comandas c
                INNER JOIN comanda_itens ci ON ci.comanda_id = c.id
                INNER JOIN produtos p ON p.id = ci.produto_id
                WHERE c.status_comanda = 'fechada'
                  AND p.deleted_at IS NULL
                  {filtro_periodo}
                GROUP BY p.id, p.nome_produto
                ORDER BY total_vendido DESC, p.nome_produto ASC
                LIMIT {limite_resultados}
            """.strip()
            return ConsultaProntaResolvida(
                justificativa=(
                    "Consulta pronta para montar o ranking de produtos mais vendidos "
                    f"{justificativa_periodo}."
                ),
                sql=sql,
                tipo_consulta="ranking_produtos_vendidos",
            )

        return None

    def resolver_consulta_faturamento(
        self,
        pergunta_normalizada: str,
    ) -> ConsultaProntaResolvida | None:
        """Resolve perguntas simples sobre faturamento total ou faturamento de balcao."""

        if "faturamento" not in pergunta_normalizada and "receita" not in pergunta_normalizada:
            return None

        filtro_periodo, justificativa_periodo = self.resolver_filtro_periodo(
            pergunta_normalizada,
            "c",
        )
        filtro_balcao = "AND c.mesa_id IS NULL" if "balcao" in pergunta_normalizada or "avulso" in pergunta_normalizada else ""
        if "bruto" in pergunta_normalizada and "desconto" not in pergunta_normalizada:
            expressao_total = "ROUND(COALESCE(SUM(c.valor_total + c.desconto), 0), 2)"
            alias_total = "faturamento_bruto"
            justificativa_total = "antes dos descontos"
        else:
            expressao_total = "ROUND(COALESCE(SUM(c.valor_total), 0), 2)"
            alias_total = "faturamento_total"
            justificativa_total = "final"

        sql = f"""
            SELECT {expressao_total} AS {alias_total}
            FROM comandas c
            WHERE c.status_comanda = 'fechada'
              {filtro_balcao}
              {filtro_periodo}
        """.strip()

        return ConsultaProntaResolvida(
            justificativa=(
                f"Consulta pronta para calcular o faturamento {justificativa_total} "
                f"{justificativa_periodo}."
            ),
            sql=sql,
            tipo_consulta=alias_total,
        )

    def resolver_consulta_descontos(
        self,
        pergunta_normalizada: str,
    ) -> ConsultaProntaResolvida | None:
        """Resolve perguntas simples sobre total de descontos concedidos."""

        if "desconto" not in pergunta_normalizada:
            return None

        if "quanto" not in pergunta_normalizada and "total" not in pergunta_normalizada and "soma" not in pergunta_normalizada:
            return None

        filtro_periodo, justificativa_periodo = self.resolver_filtro_periodo(
            pergunta_normalizada,
            "c",
        )
        sql = f"""
            SELECT ROUND(COALESCE(SUM(c.desconto), 0), 2) AS total_descontos
            FROM comandas c
            WHERE c.status_comanda = 'fechada'
              AND c.desconto > 0
              {filtro_periodo}
        """.strip()

        return ConsultaProntaResolvida(
            justificativa=(
                "Consulta pronta para somar os descontos concedidos "
                f"{justificativa_periodo}."
            ),
            sql=sql,
            tipo_consulta="total_descontos",
        )

    def resolver_filtro_periodo(
        self,
        pergunta_normalizada: str,
        alias_tabela: str,
    ) -> tuple[str, str]:
        """Traduz periodos simples da linguagem natural para filtros SQL."""

        prefixo_coluna = f"{alias_tabela}.data_hora_fechamento"

        if "hoje" in pergunta_normalizada:
            return f"AND DATE({prefixo_coluna}) = CURDATE()", "de hoje"

        if "ontem" in pergunta_normalizada:
            return (
                f"AND DATE({prefixo_coluna}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                "de ontem",
            )

        if "esta semana" in pergunta_normalizada or "nessa semana" in pergunta_normalizada:
            return (
                f"AND YEARWEEK({prefixo_coluna}, 1) = YEARWEEK(CURDATE(), 1)",
                "desta semana",
            )

        if "este mes" in pergunta_normalizada or "nesse mes" in pergunta_normalizada:
            return (
                "AND YEAR({0}) = YEAR(CURDATE()) "
                "AND MONTH({0}) = MONTH(CURDATE())".format(prefixo_coluna),
                "deste mes",
            )

        if "mes passado" in pergunta_normalizada or "ultimo mes" in pergunta_normalizada:
            return (
                "AND YEAR({0}) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) "
                "AND MONTH({0}) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))".format(prefixo_coluna),
                "do mes passado",
            )

        correspondencia_dias = re.search(r"ultimos?\s+(\d+)\s+dias", pergunta_normalizada)
        if correspondencia_dias:
            total_dias = max(1, int(correspondencia_dias.group(1)))
            return (
                f"AND DATE({prefixo_coluna}) >= DATE_SUB(CURDATE(), INTERVAL {total_dias} DAY)",
                f"dos ultimos {total_dias} dias",
            )

        return "", "sem filtro de periodo explicito"

    def extrair_limite_ranking(
        self,
        pergunta_normalizada: str,
        limite_linhas: int,
    ) -> int:
        """Resolve quantas linhas um ranking pronto deve devolver."""

        correspondencia_top = re.search(r"top\s+(\d+)", pergunta_normalizada)
        if correspondencia_top:
            return max(1, min(int(correspondencia_top.group(1)), 20))

        return max(1, min(limite_linhas, 20))
