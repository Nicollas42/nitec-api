"""Carregamento da base de conhecimento textual do agente SQL."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import unicodedata


@dataclass(slots=True)
class ResultadoConhecimento:
    """Agrupa o contexto textual pronto para o prompt e as advertencias de carga."""

    contexto_conhecimento: str
    advertencias: list[str]


class BaseConhecimentoAgente:
    """Carrega e recorta o dicionario de dados usado como contexto do agente."""

    def __init__(self, caminho_arquivo: Path, limite_caracteres: int) -> None:
        """Inicializa a base de conhecimento com o arquivo e o limite de carga."""

        self.caminho_arquivo = caminho_arquivo
        self.limite_caracteres = max(2000, limite_caracteres)
        self.modificado_em_cache: float | None = None
        self.conteudo_cache: str | None = None

    def carregar_contexto(self) -> ResultadoConhecimento:
        """Le o arquivo configurado e devolve o contexto completo pronto para o prompt."""

        conteudo_normalizado, advertencias = self.carregar_documento()

        if not conteudo_normalizado:
            return ResultadoConhecimento("", advertencias)

        conteudo_limitado, advertencias_limite = self.aplicar_limite(conteudo_normalizado)
        return ResultadoConhecimento(
            contexto_conhecimento=conteudo_limitado,
            advertencias=[*advertencias, *advertencias_limite],
        )

    def carregar_contexto_relevante(
        self,
        pergunta: str,
        tabelas_relevantes: tuple[str, ...],
    ) -> ResultadoConhecimento:
        """Seleciona apenas os blocos do dicionario mais relevantes para a pergunta."""

        conteudo_normalizado, advertencias = self.carregar_documento()

        if not conteudo_normalizado:
            return ResultadoConhecimento("", advertencias)

        secoes = self.mapear_secoes_markdown(conteudo_normalizado)
        titulos_relevantes = self.selecionar_titulos_relevantes(
            pergunta,
            tabelas_relevantes,
            secoes,
        )
        blocos_selecionados = [
            secoes[titulo]
            for titulo in titulos_relevantes
            if titulo in secoes
        ]

        if not blocos_selecionados:
            blocos_selecionados = [conteudo_normalizado]

        contexto_relevante = "\n\n".join(blocos_selecionados).strip()
        contexto_limitado, advertencias_limite = self.aplicar_limite(contexto_relevante)
        return ResultadoConhecimento(
            contexto_conhecimento=contexto_limitado,
            advertencias=[*advertencias, *advertencias_limite],
        )

    def carregar_documento(self) -> tuple[str, list[str]]:
        """Carrega e cacheia o documento bruto normalizado para reutilizacao."""

        if not self.caminho_arquivo.exists():
            return (
                "",
                [
                    f"O dicionario de dados configurado nao foi encontrado em: {self.caminho_arquivo}"
                ],
            )

        modificado_em = self.caminho_arquivo.stat().st_mtime

        if self.conteudo_cache is not None and self.modificado_em_cache == modificado_em:
            return self.conteudo_cache, []

        conteudo_bruto = self.caminho_arquivo.read_text(
            encoding="utf-8",
            errors="replace",
        )
        conteudo_normalizado = self.normalizar_conteudo(conteudo_bruto)

        if not conteudo_normalizado:
            return (
                "",
                [
                    f"O dicionario de dados configurado esta vazio: {self.caminho_arquivo}"
                ],
            )

        self.modificado_em_cache = modificado_em
        self.conteudo_cache = conteudo_normalizado
        return conteudo_normalizado, []

    def normalizar_conteudo(self, conteudo_bruto: str) -> str:
        """Uniformiza as quebras de linha e remove espacos sobrando no fim das linhas."""

        conteudo_normalizado = conteudo_bruto.replace("\r\n", "\n").replace("\r", "\n").strip()
        linhas = [linha.rstrip() for linha in conteudo_normalizado.split("\n")]
        conteudo_normalizado = "\n".join(linhas)

        while "\n\n\n" in conteudo_normalizado:
            conteudo_normalizado = conteudo_normalizado.replace("\n\n\n", "\n\n")

        return conteudo_normalizado.strip()

    def mapear_secoes_markdown(self, conteudo_normalizado: str) -> dict[str, str]:
        """Mapeia secoes do markdown pelo titulo para selecao posterior."""

        secoes: dict[str, list[str]] = {}
        titulo_atual = "__inicio__"
        secoes[titulo_atual] = []

        for linha in conteudo_normalizado.split("\n"):
            if linha.startswith("## ") or linha.startswith("### "):
                titulo_atual = linha.strip()
                secoes.setdefault(titulo_atual, [])

            secoes[titulo_atual].append(linha)

        return {
            titulo: "\n".join(linhas).strip()
            for titulo, linhas in secoes.items()
            if any(linha.strip() for linha in linhas)
        }

    def selecionar_titulos_relevantes(
        self,
        pergunta: str,
        tabelas_relevantes: tuple[str, ...],
        secoes: dict[str, str],
    ) -> list[str]:
        """Escolhe os titulos do dicionario que mais ajudam na pergunta atual."""

        pergunta_normalizada = self.normalizar_texto_busca(pergunta)
        titulos_relevantes: list[str] = []

        titulos_fixos = (
            "__inicio__",
            "## Escopo do agente",
            "## Visao geral do dominio",
            "## Regras obrigatorias de interpretacao",
            "## Glossario de linguagem natural",
        )

        for titulo in titulos_fixos:
            if titulo in secoes and titulo not in titulos_relevantes:
                titulos_relevantes.append(titulo)

        for tabela in tabelas_relevantes:
            titulo_tabela = f"### `{tabela}`"
            if titulo_tabela in secoes and titulo_tabela not in titulos_relevantes:
                titulos_relevantes.append(titulo_tabela)

        if "## Formulas e KPIs usados no ERP" in secoes:
            titulos_relevantes.append("## Formulas e KPIs usados no ERP")

        palavras_gatilho_exemplos = (
            "mais vendido",
            "ranking",
            "faturamento",
            "receita",
            "lucro",
            "desconto",
            "estoque",
            "fornecedor",
            "encalhado",
        )
        titulo_exemplos = "## Perguntas frequentes e interpretacao esperada"
        if (
            any(palavra in pergunta_normalizada for palavra in palavras_gatilho_exemplos)
            and titulo_exemplos in secoes
            and titulo_exemplos not in titulos_relevantes
        ):
            titulos_relevantes.append(titulo_exemplos)

        return titulos_relevantes

    def normalizar_texto_busca(self, texto: str) -> str:
        """Normaliza o texto para comparacoes simples de palavras-chave."""

        texto_normalizado = unicodedata.normalize("NFKD", texto)
        texto_sem_acento = "".join(
            caractere for caractere in texto_normalizado if not unicodedata.combining(caractere)
        )
        return " ".join(texto_sem_acento.lower().split())

    def aplicar_limite(self, conteudo_normalizado: str) -> tuple[str, list[str]]:
        """Recorta o conteudo para um tamanho seguro sem quebrar o agente."""

        advertencias: list[str] = []

        if len(conteudo_normalizado) <= self.limite_caracteres:
            return conteudo_normalizado, advertencias

        posicao_corte = conteudo_normalizado.rfind("\n", 0, self.limite_caracteres)

        if posicao_corte < int(self.limite_caracteres * 0.6):
            posicao_corte = self.limite_caracteres

        conteudo_limitado = conteudo_normalizado[:posicao_corte].rstrip()
        advertencias.append(
            "O dicionario de dados foi resumido para caber no prompt do modelo."
        )

        return conteudo_limitado, advertencias
