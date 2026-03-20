"""Carregamento de configuracao da API local do agente."""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv


def ler_booleano(nome_env: str, padrao: bool) -> bool:
    """Converte uma variavel de ambiente em booleano."""

    valor = os.getenv(nome_env)

    if valor is None:
        return padrao

    return valor.strip().lower() in {"1", "true", "yes", "on", "sim"}


def ler_inteiro(nome_env: str, padrao: int) -> int:
    """Converte uma variavel de ambiente em inteiro."""

    valor = os.getenv(nome_env)

    if valor is None or valor.strip() == "":
        return padrao

    return int(valor)


def ler_string_obrigatoria(nome_env: str) -> str:
    """Le uma variavel de ambiente obrigatoria."""

    valor = os.getenv(nome_env, "").strip()

    if not valor:
        raise ValueError(f"Variavel obrigatoria ausente: {nome_env}")

    return valor


def ler_string_com_padrao(nome_env: str, padrao: str) -> str:
    """Le uma variavel de ambiente textual com valor padrao."""

    return os.getenv(nome_env, padrao).strip()


def carregar_arquivos_env(base_dir: Path) -> Path:
    """Carrega primeiro o .env do Laravel e depois o .env local do agente."""

    laravel_env_relativo = os.getenv("AGENTE_LARAVEL_ENV_PATH", "../.env")
    laravel_env_path = (base_dir / laravel_env_relativo).resolve()

    if laravel_env_path.exists():
        load_dotenv(laravel_env_path, override=False)

    env_local_path = base_dir / ".env"

    if env_local_path.exists():
        load_dotenv(env_local_path, override=True)

    return laravel_env_path


@dataclass(slots=True)
class ConfiguracaoAplicacao:
    """Agrupa toda a configuracao necessaria da API local."""

    api_host: str
    api_port: int
    api_log_level: str
    ollama_base_url: str
    ollama_model: str
    ollama_timeout_seconds: int
    schema_cache_seconds: int
    sql_row_limit: int
    sql_preview_limit: int
    permitir_credenciais_app: bool
    db_host: str
    db_port: int
    db_database: str
    db_username: str
    db_password: str
    db_readonly_username: str | None
    db_readonly_password: str | None
    db_tenant_prefix: str
    db_tenant_suffix: str
    laravel_env_path: Path

    @classmethod
    def carregar(cls, base_dir: Path) -> "ConfiguracaoAplicacao":
        """Carrega a configuracao consolidada da API local."""

        laravel_env_path = carregar_arquivos_env(base_dir)

        return cls(
            api_host=os.getenv("AGENTE_API_HOST", "127.0.0.1").strip(),
            api_port=ler_inteiro("AGENTE_API_PORT", 8001),
            api_log_level=os.getenv("AGENTE_API_LOG_LEVEL", "info").strip(),
            ollama_base_url=os.getenv("AGENTE_OLLAMA_BASE_URL", "http://127.0.0.1:11434").strip(),
            ollama_model=os.getenv("AGENTE_OLLAMA_MODEL", "qwen2.5:7b").strip(),
            ollama_timeout_seconds=ler_inteiro("AGENTE_OLLAMA_TIMEOUT_SECONDS", 180),
            schema_cache_seconds=ler_inteiro("AGENTE_SCHEMA_CACHE_SECONDS", 300),
            sql_row_limit=ler_inteiro("AGENTE_SQL_ROW_LIMIT", 200),
            sql_preview_limit=ler_inteiro("AGENTE_SQL_PREVIEW_LIMIT", 20),
            permitir_credenciais_app=ler_booleano("AGENTE_PERMITIR_CREDENCIAIS_APP", True),
            db_host=ler_string_com_padrao("AGENTE_DB_HOST", ler_string_com_padrao("DB_HOST", "127.0.0.1")),
            db_port=ler_inteiro("AGENTE_DB_PORT", ler_inteiro("DB_PORT", 3306)),
            db_database=ler_string_obrigatoria("AGENTE_DB_DATABASE") if os.getenv("AGENTE_DB_DATABASE") else ler_string_obrigatoria("DB_DATABASE"),
            db_username=ler_string_com_padrao("AGENTE_DB_USERNAME", ler_string_com_padrao("DB_USERNAME", "root")),
            db_password=os.getenv("AGENTE_DB_PASSWORD", os.getenv("DB_PASSWORD", "")).strip(),
            db_readonly_username=os.getenv("AGENTE_DB_READONLY_USERNAME", "").strip() or None,
            db_readonly_password=os.getenv("AGENTE_DB_READONLY_PASSWORD", "").strip() or None,
            db_tenant_prefix=os.getenv("AGENTE_DB_TENANT_PREFIX", "tenant_").strip(),
            db_tenant_suffix=os.getenv("AGENTE_DB_TENANT_SUFFIX", "").strip(),
            laravel_env_path=laravel_env_path,
        )
