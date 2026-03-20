"""Modelos de entrada e saida da API local do agente."""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field, model_validator


class RequisicaoConsulta(BaseModel):
    """Representa a carga util recebida pela API do agente."""

    tenant_id: str | None = None
    tenant_domain: str | None = None
    pergunta: str = Field(min_length=3, max_length=1500)
    limite_linhas: int = Field(default=50, ge=1, le=200)
    forcar_atualizar_schema: bool = False

    @model_validator(mode="after")
    def validar_tenant(self) -> "RequisicaoConsulta":
        """Garante que pelo menos um identificador de tenant foi informado."""

        if not self.tenant_id and not self.tenant_domain:
            raise ValueError("Informe tenant_id ou tenant_domain.")

        return self


class RespostaConsulta(BaseModel):
    """Representa a resposta serializada da consulta do agente."""

    sucesso: bool
    tipo_resposta: str
    resposta_texto: str
    tenant_id: str | None = None
    tenant_domain: str | None = None
    tenant_database: str | None = None
    sql_gerado: str | None = None
    colunas: list[str] = Field(default_factory=list)
    linhas: list[dict[str, Any]] = Field(default_factory=list)
    total_linhas: int = 0
    advertencias: list[str] = Field(default_factory=list)
    modelo_llm: str
    duracao_ms: int
