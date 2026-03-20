"""Repositorio MySQL para central e tenant do ERP."""

from __future__ import annotations

import time
from contextlib import closing
from dataclasses import dataclass
from typing import Any

import mysql.connector

from configuracao import ConfiguracaoAplicacao
from esquema_erp import TABELAS_PERMITIDAS


@dataclass(slots=True)
class TenantResolvido:
    """Representa o tenant alvo da consulta."""

    tenant_id: str
    tenant_domain: str | None
    tenant_database: str
    ativo: bool


@dataclass(slots=True)
class CacheSchema:
    """Mantem o schema em memoria por um tempo limitado."""

    expira_em: float
    schema_tabelas: dict[str, list[dict[str, str]]]


class RepositorioMysqlTenancy:
    """Centraliza a resolucao de tenant, schema e execucao SQL."""

    def __init__(self, configuracao: ConfiguracaoAplicacao) -> None:
        """Inicializa o repositorio MySQL."""

        self.configuracao = configuracao
        self.cache_schema: dict[str, CacheSchema] = {}

    def resolver_tenant(
        self,
        tenant_id: str | None,
        tenant_domain: str | None,
    ) -> TenantResolvido:
        """Resolve o tenant pelo id ou pelo dominio na base central."""

        with closing(self.abrir_conexao_central()) as conexao:
            cursor = conexao.cursor(dictionary=True)

            if tenant_id:
                cursor.execute(
                    """
                    SELECT tenants.id, tenants.ativo, domains.domain
                    FROM tenants
                    LEFT JOIN domains ON domains.tenant_id = tenants.id
                    WHERE tenants.id = %s
                    ORDER BY domains.id
                    LIMIT 1
                    """,
                    (tenant_id,),
                )
            else:
                cursor.execute(
                    """
                    SELECT tenants.id, tenants.ativo, domains.domain
                    FROM domains
                    INNER JOIN tenants ON tenants.id = domains.tenant_id
                    WHERE domains.domain = %s
                    LIMIT 1
                    """,
                    (tenant_domain,),
                )

            registro = cursor.fetchone()

        if not registro:
            raise ValueError("Tenant nao encontrado na base central.")

        tenant_id_resolvido = str(registro["id"])
        tenant_database = (
            f"{self.configuracao.db_tenant_prefix}{tenant_id_resolvido}"
            f"{self.configuracao.db_tenant_suffix}"
        )

        return TenantResolvido(
            tenant_id=tenant_id_resolvido,
            tenant_domain=registro.get("domain"),
            tenant_database=tenant_database,
            ativo=bool(registro.get("ativo")),
        )

    def obter_schema_tenant(
        self,
        tenant_database: str,
        forcar_atualizacao: bool = False,
    ) -> dict[str, list[dict[str, str]]]:
        """Retorna o schema introspectado do tenant com cache em memoria."""

        cache = self.cache_schema.get(tenant_database)
        agora = time.time()

        if not forcar_atualizacao and cache and cache.expira_em > agora:
            return cache.schema_tabelas

        placeholders = ", ".join(["%s"] * len(TABELAS_PERMITIDAS))
        sql = f"""
            SELECT
                table_name,
                column_name,
                column_type,
                is_nullable,
                column_key
            FROM information_schema.columns
            WHERE table_schema = %s
              AND table_name IN ({placeholders})
            ORDER BY table_name, ordinal_position
        """

        parametros: tuple[Any, ...] = (tenant_database, *TABELAS_PERMITIDAS)

        with closing(self.abrir_conexao_central()) as conexao:
            cursor = conexao.cursor(dictionary=True)
            cursor.execute(sql, parametros)
            registros = cursor.fetchall()

        schema_tabelas: dict[str, list[dict[str, str]]] = {}

        for registro in registros:
            tabela = str(registro["table_name"])
            schema_tabelas.setdefault(tabela, []).append(
                {
                    "column_name": str(registro["column_name"]),
                    "column_type": str(registro["column_type"]),
                    "is_nullable": str(registro["is_nullable"]),
                    "column_key": str(registro["column_key"] or ""),
                }
            )

        self.cache_schema[tenant_database] = CacheSchema(
            expira_em=agora + self.configuracao.schema_cache_seconds,
            schema_tabelas=schema_tabelas,
        )

        return schema_tabelas

    def executar_consulta_tenant(
        self,
        tenant_database: str,
        sql: str,
    ) -> tuple[list[dict[str, Any]], list[str], list[str]]:
        """Executa uma consulta somente leitura no banco tenant."""

        advertencias: list[str] = []

        with closing(self.abrir_conexao_tenant(tenant_database)) as conexao:
            cursor = conexao.cursor(dictionary=True)

            try:
                cursor.execute("SET SESSION TRANSACTION READ ONLY")
                conexao.start_transaction()
            except Exception:
                advertencias.append("Nao foi possivel forcar transacao somente leitura na conexao.")

            cursor.execute(sql)
            linhas = cursor.fetchall()
            colunas = list(cursor.column_names or [])

            try:
                conexao.rollback()
            except Exception:
                pass

        return linhas, colunas, advertencias

    def abrir_conexao_central(self):
        """Abre uma conexao com a base central do ERP."""

        return mysql.connector.connect(
            host=self.configuracao.db_host,
            port=self.configuracao.db_port,
            user=self.configuracao.db_username,
            password=self.configuracao.db_password,
            database=self.configuracao.db_database,
            autocommit=False,
        )

    def abrir_conexao_tenant(self, tenant_database: str):
        """Abre uma conexao com o banco do tenant alvo."""

        usuario = self.configuracao.db_readonly_username
        senha = self.configuracao.db_readonly_password

        if not usuario or not senha:
            if not self.configuracao.permitir_credenciais_app:
                raise ValueError("Credenciais readonly nao configuradas para o banco tenant.")

            usuario = self.configuracao.db_username
            senha = self.configuracao.db_password

        return mysql.connector.connect(
            host=self.configuracao.db_host,
            port=self.configuracao.db_port,
            user=usuario,
            password=senha,
            database=tenant_database,
            autocommit=False,
        )
