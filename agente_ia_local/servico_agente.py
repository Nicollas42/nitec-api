"""Orquestracao principal do agente SQL local."""

from __future__ import annotations

from time import perf_counter
from typing import Any

from cliente_mysql import RepositorioMysqlTenancy
from cliente_ollama import ClienteOllama
from configuracao import ConfiguracaoAplicacao
from esquema_erp import TABELAS_PERMITIDAS, montar_contexto_schema
from modelos_api import RequisicaoConsulta, RespostaConsulta
from seguranca_sql import ValidadorSqlSomenteLeitura


class ServicoAgenteSql:
    """Executa o fluxo completo entre pergunta, SQL e resposta final."""

    def __init__(self, configuracao: ConfiguracaoAplicacao) -> None:
        """Inicializa os componentes internos do agente."""

        self.configuracao = configuracao
        self.repositorio = RepositorioMysqlTenancy(configuracao)
        self.cliente_ollama = ClienteOllama(
            base_url=configuracao.ollama_base_url,
            model=configuracao.ollama_model,
            timeout_seconds=configuracao.ollama_timeout_seconds,
        )
        self.validador_sql = ValidadorSqlSomenteLeitura(
            tabelas_permitidas=TABELAS_PERMITIDAS,
            limite_padrao=configuracao.sql_row_limit,
        )

    def consultar(self, requisicao: RequisicaoConsulta) -> RespostaConsulta:
        """Processa uma pergunta natural e devolve resposta estruturada."""

        inicio = perf_counter()
        tenant = self.repositorio.resolver_tenant(
            tenant_id=requisicao.tenant_id,
            tenant_domain=requisicao.tenant_domain,
        )

        advertencias: list[str] = []

        if not tenant.ativo:
            advertencias.append("O tenant informado esta marcado como inativo na base central.")

        schema_tabelas = self.repositorio.obter_schema_tenant(
            tenant_database=tenant.tenant_database,
            forcar_atualizacao=requisicao.forcar_atualizar_schema,
        )
        contexto_schema = montar_contexto_schema(schema_tabelas)
        plano_sql = self.cliente_ollama.gerar_plano_sql(
            pergunta=requisicao.pergunta,
            contexto_schema=contexto_schema,
        )

        if plano_sql["tipo"] != "sql":
            return RespostaConsulta(
                sucesso=True,
                tipo_resposta=plano_sql["tipo"],
                resposta_texto=str(plano_sql["justificativa"]),
                tenant_id=tenant.tenant_id,
                tenant_domain=tenant.tenant_domain,
                tenant_database=tenant.tenant_database,
                modelo_llm=self.configuracao.ollama_model,
                duracao_ms=self.calcular_duracao_ms(inicio),
                advertencias=advertencias,
            )

        sql_validado = self.validador_sql.validar(str(plano_sql["sql"]))
        linhas, colunas, advertencias_execucao = self.repositorio.executar_consulta_tenant(
            tenant_database=tenant.tenant_database,
            sql=sql_validado,
        )
        advertencias.extend(advertencias_execucao)

        linhas_preview = self.gerar_preview_linhas(linhas, requisicao.limite_linhas)
        resposta_texto = self.cliente_ollama.gerar_resposta_final(
            pergunta=requisicao.pergunta,
            sql_gerado=sql_validado,
            linhas=linhas_preview,
            advertencias=advertencias,
        )

        if not self.configuracao.db_readonly_username:
            advertencias.append("A API esta usando credenciais da aplicacao como fallback. Configure um usuario readonly para producao.")

        return RespostaConsulta(
            sucesso=True,
            tipo_resposta="resposta",
            resposta_texto=resposta_texto,
            tenant_id=tenant.tenant_id,
            tenant_domain=tenant.tenant_domain,
            tenant_database=tenant.tenant_database,
            sql_gerado=sql_validado,
            colunas=colunas,
            linhas=linhas_preview,
            total_linhas=len(linhas),
            advertencias=advertencias,
            modelo_llm=self.configuracao.ollama_model,
            duracao_ms=self.calcular_duracao_ms(inicio),
        )

    def gerar_preview_linhas(
        self,
        linhas: list[dict[str, Any]],
        limite_linhas: int,
    ) -> list[dict[str, Any]]:
        """Recorta as linhas devolvidas para um volume seguro na API."""

        limite_preview = min(limite_linhas, self.configuracao.sql_preview_limit)
        return linhas[:limite_preview]

    def calcular_duracao_ms(self, inicio: float) -> int:
        """Calcula o tempo total da consulta em milissegundos."""

        return int((perf_counter() - inicio) * 1000)
