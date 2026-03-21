"""Fabrica simples para escolher o provedor de LLM do agente."""

from __future__ import annotations

from typing import Protocol

from cliente_gemini import ClienteGemini
from cliente_ollama import ClienteOllama
from configuracao import ConfiguracaoAplicacao


class ClienteLlm(Protocol):
    """Define a interface minima esperada do cliente de linguagem."""

    def gerar_plano_sql(
        self,
        pergunta: str,
        contexto_schema: str,
        contexto_conhecimento: str,
    ) -> dict:
        """Gera um plano estruturado de SQL a partir da pergunta do usuario."""

    def gerar_resposta_final(
        self,
        pergunta: str,
        sql_gerado: str,
        linhas: list[dict],
        advertencias: list[str],
    ) -> str:
        """Converte o resultado final em texto amigavel ao usuario."""


def criar_cliente_llm(configuracao: ConfiguracaoAplicacao) -> ClienteLlm:
    """Cria o cliente correto conforme o provedor configurado."""

    if configuracao.llm_provider == "gemini":
        return ClienteGemini(
            api_key=configuracao.gemini_api_key or "",
            model=configuracao.gemini_model,
        )

    return ClienteOllama(
        base_url=configuracao.ollama_base_url,
        model=configuracao.ollama_model,
        timeout_seconds=configuracao.ollama_timeout_seconds,
    )
