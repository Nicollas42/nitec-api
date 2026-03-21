"""API local do agente SQL para o ERP multi-tenant."""

from __future__ import annotations

from pathlib import Path

import uvicorn
from fastapi import FastAPI, HTTPException

from configuracao import ConfiguracaoAplicacao
from modelos_api import RequisicaoConsulta, RespostaConsulta
from servico_agente import ServicoAgenteSql


BASE_DIR = Path(__file__).resolve().parent
CONFIGURACAO = ConfiguracaoAplicacao.carregar(BASE_DIR)
SERVICO_AGENTE = ServicoAgenteSql(CONFIGURACAO)
APP = FastAPI(
    title="Agente IA Local Nitec",
    version="0.1.0",
)


@APP.get("/")
def raiz() -> dict[str, str]:
    """Retorna um resumo simples da API local."""

    return {
        "status": "ok",
        "servico": "agente_ia_local",
        "provedor_llm": CONFIGURACAO.llm_provider,
        "modelo_llm": CONFIGURACAO.obter_modelo_llm_ativo(),
    }


@APP.get("/api/v1/health")
def health() -> dict[str, str]:
    """Retorna um healthcheck simples para observabilidade local."""

    return {
        "status": "ok",
        "provedor_llm": CONFIGURACAO.llm_provider,
        "modelo_llm": CONFIGURACAO.obter_modelo_llm_ativo(),
        "ollama_base_url": CONFIGURACAO.ollama_base_url,
    }


@APP.post("/api/v1/consultar-agente", response_model=RespostaConsulta)
def consultar_agente(requisicao: RequisicaoConsulta) -> RespostaConsulta:
    """Executa uma consulta em linguagem natural no banco do tenant."""

    try:
        return SERVICO_AGENTE.consultar(requisicao)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc


def main() -> None:
    """Inicia o servidor Uvicorn para a API local."""

    uvicorn.run(
        "main:APP",
        host=CONFIGURACAO.api_host,
        port=CONFIGURACAO.api_port,
        reload=False,
        log_level=CONFIGURACAO.api_log_level,
    )


if __name__ == "__main__":
    main()
