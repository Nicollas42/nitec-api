# Agente IA Local

API local para perguntas em linguagem natural sobre os bancos tenant do ERP.

## O que esta pasta faz

- resolve o tenant pelo `tenant_id` ou pelo dominio
- le o schema real do banco do tenant
- injeta apenas o recorte relevante do dicionario do ERP
- tenta usar consultas prontas para perguntas frequentes antes de acionar o modelo
- pede ao Ollama ou Gemini para gerar SQL MySQL
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

## Providers de LLM

- `AGENTE_LLM_PROVIDER=ollama`: usa o modelo local configurado em `AGENTE_OLLAMA_MODEL`.
- `AGENTE_LLM_PROVIDER=gemini`: usa o modelo hospedado configurado em `AGENTE_GEMINI_MODEL`.
- Para Gemini, configure `AGENTE_GEMINI_API_KEY` ou `GEMINI_API_KEY`.
- O modelo sugerido para free tier e testes de baixo custo e `gemini-2.5-flash-lite`.

## Como usar o banco da VPS

1. Copie `.env.example` para `.env`.
2. Preencha `AGENTE_DB_DATABASE`, `AGENTE_DB_USERNAME` e `AGENTE_DB_PASSWORD` com um usuario valido do MySQL da VPS.
3. Preferencialmente, configure `AGENTE_DB_READONLY_USERNAME` e `AGENTE_DB_READONLY_PASSWORD` para um usuario somente leitura.
4. Mantenha `AGENTE_SSH_TUNNEL_ENABLED=true` para que o script abra um tunnel local em `127.0.0.1:3307`.
5. Inicie o agente com `controlar_agente_ia_local.bat start`.

Com isso, o fluxo fica:

- sua API local conecta em `127.0.0.1:3307`
- o tunnel SSH encaminha para `127.0.0.1:3306` da VPS
- o MySQL da VPS continua fechado para a internet publica

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
- O script `controlar_agente_ia_local.bat` agora pode subir tambem o tunnel SSH do banco quando `AGENTE_SSH_TUNNEL_ENABLED=true`.
- O script `testar_conexao_banco.py` ajuda a confirmar se as credenciais atuais conseguem abrir a base central antes de testar pelo site.
- No free tier do Gemini, a propria tabela de pricing indica que os dados podem ser usados para melhorar os produtos. Use com cautela em dados sensiveis.
