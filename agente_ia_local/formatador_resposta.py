"""Formatacao deterministica da resposta final sem segunda chamada ao LLM."""

from __future__ import annotations

from decimal import Decimal, InvalidOperation
from typing import Any


class FormatadorResposta:
    """Converte linhas SQL em respostas legiveis com baixa latencia."""

    def formatar_resposta(
        self,
        pergunta: str,
        linhas: list[dict[str, Any]],
        total_linhas: int,
        tipo_consulta: str | None = None,
    ) -> str:
        """Monta a resposta textual principal sem depender de outra inferencia."""

        if not linhas:
            return "Nao encontrei registros para responder essa pergunta com os filtros atuais."

        if tipo_consulta == "produto_mais_vendido":
            return self.formatar_produto_mais_vendido(linhas[0])

        if tipo_consulta == "ranking_produtos_vendidos":
            return self.formatar_ranking_produtos(linhas, total_linhas)

        if tipo_consulta in {"faturamento_total", "faturamento_bruto"}:
            return self.formatar_faturamento(linhas[0], tipo_consulta)

        if tipo_consulta == "total_descontos":
            return self.formatar_total_descontos(linhas[0])

        return self.formatar_resposta_generica(pergunta, linhas, total_linhas)

    def formatar_produto_mais_vendido(self, linha: dict[str, Any]) -> str:
        """Formata a resposta para consultas de produto mais vendido."""

        nome_produto = str(linha.get("nome_produto", "Produto nao identificado"))
        total_vendido = self.formatar_valor(
            linha.get("total_vendido", linha.get("quantidade_vendida", 0))
        )
        return f'O produto mais vendido e "{nome_produto}", com {total_vendido} unidades vendidas.'

    def formatar_ranking_produtos(
        self,
        linhas: list[dict[str, Any]],
        total_linhas: int,
    ) -> str:
        """Formata uma resposta curta para ranking de produtos vendidos."""

        primeiros_itens: list[str] = []

        for linha in linhas[:3]:
            nome_produto = str(linha.get("nome_produto", "Produto"))
            total_vendido = self.formatar_valor(
                linha.get("total_vendido", linha.get("quantidade_vendida", 0))
            )
            primeiros_itens.append(f"{nome_produto} ({total_vendido})")

        resumo_topo = ", ".join(primeiros_itens)
        return f"Encontrei um ranking com {total_linhas} produto(s). Os primeiros sao: {resumo_topo}."

    def formatar_faturamento(
        self,
        linha: dict[str, Any],
        tipo_consulta: str,
    ) -> str:
        """Formata a resposta para consultas de faturamento."""

        chave_total = "faturamento_bruto" if tipo_consulta == "faturamento_bruto" else "faturamento_total"
        valor_total = self.formatar_moeda(linha.get(chave_total, 0))

        if tipo_consulta == "faturamento_bruto":
            return f"O faturamento bruto calculado para a consulta atual foi de {valor_total}."

        return f"O faturamento calculado para a consulta atual foi de {valor_total}."

    def formatar_total_descontos(self, linha: dict[str, Any]) -> str:
        """Formata a resposta para consultas de total de descontos."""

        valor_total = self.formatar_moeda(linha.get("total_descontos", 0))
        return f"O total de descontos concedidos na consulta atual foi de {valor_total}."

    def formatar_resposta_generica(
        self,
        pergunta: str,
        linhas: list[dict[str, Any]],
        total_linhas: int,
    ) -> str:
        """Formata uma resposta generica para consultas que nao tem modelo pronto."""

        primeira_linha = linhas[0]
        pares_formatados: list[str] = []

        for chave, valor in list(primeira_linha.items())[:3]:
            pares_formatados.append(f"{chave}: {self.formatar_valor(valor)}")

        resumo_primeira_linha = ", ".join(pares_formatados)

        if total_linhas == 1:
            return f"Encontrei 1 resultado para a pergunta \"{pergunta}\". Resumo: {resumo_primeira_linha}."

        return f"Encontrei {total_linhas} resultados para a pergunta \"{pergunta}\". Primeiro registro: {resumo_primeira_linha}."

    def formatar_valor(self, valor: Any) -> str:
        """Converte numeros e textos em uma representacao curta e legivel."""

        if valor is None:
            return "-"

        valor_decimal = self.converter_decimal(valor)
        if valor_decimal is None:
            return str(valor)

        if valor_decimal == valor_decimal.to_integral():
            return f"{int(valor_decimal):,}".replace(",", ".")

        return str(round(float(valor_decimal), 2)).replace(".", ",")

    def formatar_moeda(self, valor: Any) -> str:
        """Converte um valor numerico em moeda brasileira simplificada."""

        valor_decimal = self.converter_decimal(valor)
        if valor_decimal is None:
            return f"R$ {valor}"

        valor_float = float(valor_decimal)
        valor_formatado = f"{valor_float:,.2f}"
        valor_formatado = valor_formatado.replace(",", "_").replace(".", ",").replace("_", ".")
        return f"R$ {valor_formatado}"

    def converter_decimal(self, valor: Any) -> Decimal | None:
        """Tenta converter um valor arbitrario para Decimal."""

        try:
            return Decimal(str(valor))
        except (InvalidOperation, ValueError, TypeError):
            return None
