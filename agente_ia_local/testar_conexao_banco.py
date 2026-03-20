"""Teste rapido da conectividade com o banco configurado para o agente."""

from __future__ import annotations

from contextlib import closing
from pathlib import Path

import mysql.connector

from configuracao import ConfiguracaoAplicacao


def obter_usuario_conexao(configuracao: ConfiguracaoAplicacao) -> tuple[str, str]:
    """Resolve o usuario efetivo usado pelo agente para se conectar ao MySQL."""

    if configuracao.db_readonly_username and configuracao.db_readonly_password:
        return configuracao.db_readonly_username, configuracao.db_readonly_password

    return configuracao.db_username, configuracao.db_password


def testar_conexao(configuracao: ConfiguracaoAplicacao) -> None:
    """Valida a conectividade ao banco central e imprime um resumo legivel."""

    usuario, senha = obter_usuario_conexao(configuracao)

    with closing(
        mysql.connector.connect(
            host=configuracao.db_host,
            port=configuracao.db_port,
            user=usuario,
            password=senha,
            database=configuracao.db_database,
            autocommit=True,
        )
    ) as conexao:
        cursor = conexao.cursor(dictionary=True)
        cursor.execute(
            """
            SELECT
                DATABASE() AS banco_atual,
                CURRENT_USER() AS usuario_atual,
                @@hostname AS servidor_mysql,
                @@port AS porta_mysql
            """
        )
        registro = cursor.fetchone() or {}

    banco_atual = str(registro.get("banco_atual") or configuracao.db_database)
    usuario_atual = str(registro.get("usuario_atual") or usuario)
    servidor_mysql = str(registro.get("servidor_mysql") or "desconhecido")
    porta_mysql = str(registro.get("porta_mysql") or configuracao.db_port)

    print(
        "conectado: "
        f"banco={banco_atual} "
        f"usuario={usuario_atual} "
        f"servidor={servidor_mysql} "
        f"porta={porta_mysql}"
    )


def main() -> int:
    """Executa o teste de conectividade usando a configuracao local consolidada."""

    try:
        base_dir = Path(__file__).resolve().parent
        configuracao = ConfiguracaoAplicacao.carregar(base_dir)
        testar_conexao(configuracao)
        return 0
    except Exception as exc:
        print(f"indisponivel: {exc}")
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
