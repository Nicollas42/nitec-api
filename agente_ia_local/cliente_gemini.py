"""Cliente HTTP/SDK para conversar com o Gemini API."""

from __future__ import annotations

from typing import Any, Literal

from google import genai
from google.genai import types
from pydantic import BaseModel


class PlanoSqlLlm(BaseModel):
    """Representa o formato estruturado esperado ao pedir um plano SQL."""

    tipo: Literal["sql", "clarificacao", "recusa"]
    justificativa: str
    sql: str


class ClienteGemini:
    """Encapsula as chamadas ao Gemini para gerar SQL e resposta final."""

    def __init__(self, api_key: str, model: str) -> None:
        """Inicializa o cliente oficial do Gemini API."""

        self.client = genai.Client(api_key=api_key)
        self.model = model

    def gerar_plano_sql(
        self,
        pergunta: str,
        contexto_schema: str,
        contexto_conhecimento: str,
    ) -> dict[str, Any]:
        """Pede ao Gemini um JSON contendo o SQL ou um pedido de clarificacao."""

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

        resposta = self.client.models.generate_content(
            model=self.model,
            contents=user_prompt,
            config=types.GenerateContentConfig(
                system_instruction=system_prompt,
                temperature=0.1,
                response_mime_type="application/json",
                response_json_schema=PlanoSqlLlm.model_json_schema(),
            ),
        )
        texto = (resposta.text or "").strip()

        if not texto:
            raise ValueError("O Gemini nao retornou conteudo ao gerar o plano SQL.")

        return PlanoSqlLlm.model_validate_json(texto).model_dump()

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
            f"Advertencias:\n{advertencias}\n\n"
            f"Linhas retornadas:\n{linhas}\n"
        )

        resposta = self.client.models.generate_content(
            model=self.model,
            contents=user_prompt,
            config=types.GenerateContentConfig(
                system_instruction=system_prompt,
                temperature=0.1,
            ),
        )
        texto = (resposta.text or "").strip()

        if not texto:
            raise ValueError("O Gemini nao retornou conteudo ao gerar a resposta final.")

        return texto
