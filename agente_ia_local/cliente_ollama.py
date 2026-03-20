"""Cliente HTTP para conversar com o Ollama local."""

from __future__ import annotations

import json
from typing import Any

import requests


class ClienteOllama:
    """Encapsula as chamadas ao Ollama para gerar SQL e resposta final."""

    def __init__(self, base_url: str, model: str, timeout_seconds: int) -> None:
        """Inicializa o cliente HTTP do Ollama."""

        self.base_url = base_url.rstrip("/")
        self.model = model
        self.timeout_seconds = timeout_seconds

    def gerar_plano_sql(
        self,
        pergunta: str,
        contexto_schema: str,
        contexto_conhecimento: str,
    ) -> dict[str, Any]:
        """Pede ao modelo um JSON contendo o SQL ou um pedido de clarificacao."""

        system_prompt = (
            "Voce e um especialista em MySQL para um ERP SaaS multi-tenant. "
            "Responda exclusivamente em JSON valido sem markdown. "
            "Use apenas tabelas e colunas do schema informado. "
            "Use o conhecimento de negocio validado para interpretar a pergunta do usuario. "
            "Quando houver conflito, use o schema para nomes tecnicos e o conhecimento para semantica de negocio. "
            "Gere somente uma consulta SELECT unica. "
            "Se a pergunta estiver ambigua, devolva tipo=clarificacao. "
            "Formato obrigatorio: "
            '{"tipo":"sql|clarificacao|recusa","justificativa":"texto","sql":"texto ou vazio"}.'
        )

        conhecimento_prompt = contexto_conhecimento or "Nenhum conhecimento adicional foi carregado."

        user_prompt = (
            f"Pergunta do usuario:\n{pergunta}\n\n"
            f"Schema disponivel:\n{contexto_schema}\n\n"
            f"Conhecimento de negocio validado:\n{conhecimento_prompt}\n\n"
            "Instrucoes finais:\n"
            "- Use aliases simples e legiveis.\n"
            "- Evite SELECT * quando houver alternativa melhor.\n"
            "- Se listar linhas, limite a consulta.\n"
            "- Nunca use tabelas ou colunas fora do schema informado.\n"
        )

        conteudo = self.executar_chat(
            system_prompt,
            user_prompt,
            formato_json=True,
        )
        plano = self.extrair_json(conteudo)

        if "tipo" not in plano or "justificativa" not in plano or "sql" not in plano:
            raise ValueError("O modelo nao retornou o JSON esperado para o plano SQL.")

        return plano

    def gerar_resposta_final(
        self,
        pergunta: str,
        sql_gerado: str,
        linhas: list[dict[str, Any]],
        advertencias: list[str],
    ) -> str:
        """Converte o resultado SQL em uma resposta objetiva em portugues."""

        if not linhas:
            return "Nao encontrei registros para responder essa pergunta com os filtros atuais."

        system_prompt = (
            "Voce resume resultados de BI em portugues. "
            "Seja objetivo, fiel aos dados e nao invente nada. "
            "Nao cite colunas tecnicas desnecessarias. "
            "Se houver ranking, destaque os primeiros itens."
        )

        user_prompt = (
            f"Pergunta original:\n{pergunta}\n\n"
            f"SQL executado:\n{sql_gerado}\n\n"
            f"Advertencias:\n{json.dumps(advertencias, ensure_ascii=True)}\n\n"
            f"Linhas retornadas:\n{json.dumps(linhas, ensure_ascii=True, default=str)}\n"
        )

        return self.executar_chat(system_prompt, user_prompt).strip()

    def executar_chat(
        self,
        system_prompt: str,
        user_prompt: str,
        formato_json: bool = False,
    ) -> str:
        """Executa uma chamada de chat no Ollama e devolve o conteudo textual."""

        payload = {
            "model": self.model,
            "stream": False,
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            "options": {
                "temperature": 0.1,
            },
        }

        if formato_json:
            payload["format"] = "json"

        resposta = requests.post(
            f"{self.base_url}/api/chat",
            json=payload,
            timeout=self.timeout_seconds,
        )
        resposta.raise_for_status()
        dados = resposta.json()

        try:
            return dados["message"]["content"]
        except KeyError as exc:
            raise ValueError("Resposta invalida do Ollama.") from exc

    def extrair_json(self, conteudo: str) -> dict[str, Any]:
        """Extrai o primeiro objeto JSON valido retornado pelo modelo."""

        conteudo_limpo = conteudo.strip()

        if conteudo_limpo.startswith("```"):
            partes = [parte for parte in conteudo_limpo.split("```") if parte.strip()]
            conteudo_limpo = partes[0]

        inicio = conteudo_limpo.find("{")
        fim = conteudo_limpo.rfind("}")

        if inicio == -1 or fim == -1 or fim <= inicio:
            raise ValueError("Nao foi possivel localizar um objeto JSON na resposta do modelo.")

        return json.loads(conteudo_limpo[inicio : fim + 1])
