# Agente IA Local

API local para perguntas em linguagem natural sobre os bancos tenant do ERP.

## O que esta pasta faz

- resolve o tenant pelo `tenant_id` ou pelo dominio
- le o schema real do banco do tenant
- injeta apenas o recorte relevante do dicionario do ERP
- tenta usar consultas prontas para perguntas frequentes antes de acionar o modelo
- pede ao Ollama para gerar SQL MySQL
- valida que o SQL e somente leitura
- executa a consulta e devolve uma resposta em portugues

## Endpoint principal

- `POST /api/v1/consultar-agente`

## Como subir localmente

```powershell
cd C:\PDP\nitec_api\agente_ia_local
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe main.py
```

## Como testar

```powershell
$body = @{
  tenant_id = 'SEU_TENANT_ID'
  pergunta = 'Qual o produto mais vendido?'
} | ConvertTo-Json

Invoke-RestMethod `
  -Method Post `
  -Uri 'http://127.0.0.1:8001/api/v1/consultar-agente' `
  -ContentType 'application/json' `
  -Body $body
```

## Base de conhecimento

- Por padrao, o agente carrega `C:\PDP\nitec_app\DICIONARIO_DE_DADOS.md`.
- O caminho pode ser alterado com `AGENTE_DICIONARIO_DADOS_PATH`.
- O tamanho maximo enviado ao modelo pode ser ajustado com `AGENTE_CONHECIMENTO_MAX_CHARS`.
- O limite de tabelas no contexto do schema pode ser ajustado com `AGENTE_MAX_TABELAS_CONTEXTO`.
- A resposta final pode continuar no LLM se `AGENTE_USAR_LLM_RESPOSTA_FINAL=true`, mas o modo padrao e a resposta deterministica mais rapida.
- Sempre que o arquivo muda em disco, o agente recarrega o contexto automaticamente.

## Observacoes de seguranca

- O ideal de producao e usar um usuario MySQL somente leitura.
- Se `AGENTE_DB_READONLY_USERNAME` nao estiver configurado, o agente usa as credenciais da aplicacao como fallback.
- Mesmo no fallback, o SQL passa por validacao e a sessao e forzada para leitura.
