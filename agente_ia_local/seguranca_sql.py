"""Validacao de SQL para garantir execucao somente leitura."""

from __future__ import annotations

import re

import sqlglot
from sqlglot import expressions as exp


class ValidadorSqlSomenteLeitura:
    """Valida e normaliza SQL gerado pelo modelo antes da execucao."""

    def __init__(self, tabelas_permitidas: tuple[str, ...], limite_padrao: int) -> None:
        """Inicializa o validador com a allowlist de tabelas."""

        self.tabelas_permitidas = set(tabelas_permitidas)
        self.limite_padrao = limite_padrao
        self.padrao_bloqueado = re.compile(
            r"\b(insert|update|delete|drop|alter|truncate|create|grant|revoke|replace|rename|call|use|show|describe|explain|handler|load|outfile|dumpfile|sleep|benchmark)\b",
            re.IGNORECASE,
        )

    def validar(self, sql: str) -> str:
        """Valida o SQL e devolve uma versao pronta para execucao."""

        sql_normalizado = self.normalizar(sql)
        self.validar_palavras_proibidas(sql_normalizado)
        expressao = self.validar_arvore_sql(sql_normalizado)
        self.validar_tabelas(expressao)

        if re.search(r"\blimit\b", sql_normalizado, flags=re.IGNORECASE) is None:
            sql_normalizado = f"{sql_normalizado} LIMIT {self.limite_padrao}"

        return sql_normalizado

    def normalizar(self, sql: str) -> str:
        """Remove espacos excedentes e ponto e virgula final."""

        sql_limpo = sql.strip()

        while sql_limpo.endswith(";"):
            sql_limpo = sql_limpo[:-1].strip()

        return sql_limpo

    def validar_palavras_proibidas(self, sql: str) -> None:
        """Bloqueia instrucoes de escrita e comandos administrativos."""

        if self.padrao_bloqueado.search(sql):
            raise ValueError("SQL bloqueado por conter instrucao proibida.")

        if "--" in sql or "/*" in sql or "*/" in sql:
            raise ValueError("Comentarios SQL nao sao permitidos.")

    def validar_arvore_sql(self, sql: str) -> exp.Expression:
        """Garante que existe apenas uma consulta e que ela e do tipo leitura."""

        expressoes = sqlglot.parse(sql, read="mysql")

        if len(expressoes) != 1:
            raise ValueError("Apenas uma consulta por vez e permitida.")

        expressao = expressoes[0]

        if not isinstance(expressao, exp.Query):
            raise ValueError("Somente consultas SELECT sao permitidas.")

        return expressao

    def validar_tabelas(self, expressao: exp.Expression) -> None:
        """Garante que o SQL utiliza apenas tabelas da allowlist."""

        tabelas_encontradas = {
            tabela.name
            for tabela in expressao.find_all(exp.Table)
            if tabela.name
        }

        tabelas_invalidas = sorted(tabelas_encontradas - self.tabelas_permitidas)

        if tabelas_invalidas:
            raise ValueError(
                "SQL tentou acessar tabelas nao permitidas: "
                + ", ".join(tabelas_invalidas)
            )
