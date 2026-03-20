"""Orquestracao principal do agente SQL local."""

from __future__ import annotations

from time import perf_counter
from typing import Any

from base_conhecimento import BaseConhecimentoAgente
from cliente_mysql import RepositorioMysqlTenancy
from cliente_ollama import ClienteOllama
from configuracao import ConfiguracaoAplicacao
from consultas_prontas import ResolvedorConsultasProntas
from esquema_erp import TABELAS_PERMITIDAS, montar_contexto_schema, selecionar_tabelas_relevantes
from formatador_resposta import FormatadorResposta
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
        self.base_conhecimento = BaseConhecimentoAgente(
            caminho_arquivo=configuracao.dicionario_dados_path,
            limite_caracteres=configuracao.conhecimento_max_chars,
        )
        self.resolvedor_consultas_prontas = ResolvedorConsultasProntas()
        self.formatador_resposta = FormatadorResposta()
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

        consulta_pronta = self.resolvedor_consultas_prontas.resolver(
            requisicao.pergunta,
            requisicao.limite_linhas,
        )
        if consulta_pronta:
            return self.executar_consulta_sql(
                tenant=tenant,
                pergunta=requisicao.pergunta,
                sql_gerado=consulta_pronta.sql,
                advertencias=advertencias,
                inicio=inicio,
                limite_linhas=requisicao.limite_linhas,
                tipo_consulta=consulta_pronta.tipo_consulta,
            )

        schema_tabelas = self.repositorio.obter_schema_tenant(
            tenant_database=tenant.tenant_database,
            forcar_atualizacao=requisicao.forcar_atualizar_schema,
        )
        tabelas_relevantes = selecionar_tabelas_relevantes(
            requisicao.pergunta,
            schema_tabelas,
            self.configuracao.max_tabelas_contexto,
        )
        contexto_schema = montar_contexto_schema(schema_tabelas, tabelas_relevantes)
        contexto_conhecimento, advertencias_conhecimento = self.obter_contexto_conhecimento(
            requisicao.pergunta,
            tabelas_relevantes,
        )
        advertencias.extend(advertencias_conhecimento)
        plano_sql = self.cliente_ollama.gerar_plano_sql(
            pergunta=requisicao.pergunta,
            contexto_schema=contexto_schema,
            contexto_conhecimento=contexto_conhecimento,
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

        return self.executar_consulta_sql(
            tenant=tenant,
            pergunta=requisicao.pergunta,
            sql_gerado=str(plano_sql["sql"]),
            advertencias=advertencias,
            inicio=inicio,
            limite_linhas=requisicao.limite_linhas,
        )

    def executar_consulta_sql(
        self,
        tenant: Any,
        pergunta: str,
        sql_gerado: str,
        advertencias: list[str],
        inicio: float,
        limite_linhas: int,
        tipo_consulta: str | None = None,
    ) -> RespostaConsulta:
        """Valida, executa e serializa uma consulta SQL pronta para resposta."""

        sql_validado = self.validador_sql.validar(sql_gerado)
        linhas, colunas, advertencias_execucao = self.repositorio.executar_consulta_tenant(
            tenant_database=tenant.tenant_database,
            sql=sql_validado,
        )
        advertencias.extend(advertencias_execucao)

        linhas_preview = self.gerar_preview_linhas(linhas, limite_linhas)
        resposta_texto = self.montar_resposta_final(
            pergunta=pergunta,
            sql_gerado=sql_validado,
            linhas=linhas_preview,
            total_linhas=len(linhas),
            advertencias=advertencias,
            tipo_consulta=tipo_consulta,
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

    def montar_resposta_final(
        self,
        pergunta: str,
        sql_gerado: str,
        linhas: list[dict[str, Any]],
        total_linhas: int,
        advertencias: list[str],
        tipo_consulta: str | None = None,
    ) -> str:
        """Escolhe entre resposta deterministica rapida ou segunda chamada ao LLM."""

        if self.configuracao.usar_llm_resposta_final:
            return self.cliente_ollama.gerar_resposta_final(
                pergunta=pergunta,
                sql_gerado=sql_gerado,
                linhas=linhas,
                advertencias=advertencias,
            )

        return self.formatador_resposta.formatar_resposta(
            pergunta=pergunta,
            linhas=linhas,
            total_linhas=total_linhas,
            tipo_consulta=tipo_consulta,
        )

    def gerar_preview_linhas(
        self,
        linhas: list[dict[str, Any]],
        limite_linhas: int,
    ) -> list[dict[str, Any]]:
        """Recorta as linhas devolvidas para um volume seguro na API."""

        limite_preview = min(limite_linhas, self.configuracao.sql_preview_limit)
        return linhas[:limite_preview]

    def obter_contexto_conhecimento(
        self,
        pergunta: str,
        tabelas_relevantes: tuple[str, ...],
    ) -> tuple[str, list[str]]:
        """Carrega apenas o recorte relevante do dicionario de dados."""

        resultado = self.base_conhecimento.carregar_contexto_relevante(
            pergunta=pergunta,
            tabelas_relevantes=tabelas_relevantes,
        )
        return resultado.contexto_conhecimento, list(resultado.advertencias)

    def calcular_duracao_ms(self, inicio: float) -> int:
        """Calcula o tempo total da consulta em milissegundos."""

        return int((perf_counter() - inicio) * 1000)
