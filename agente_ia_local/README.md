# Agente IA Local

API local para perguntas em linguagem natural sobre os bancos tenant do ERP.

## O que esta pasta faz

- resolve o tenant pelo `tenant_id` ou pelo dominio
- le o schema real do banco do tenant
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

## Observacoes de seguranca

- O ideal de producao e usar um usuario MySQL somente leitura.
- Se `AGENTE_DB_READONLY_USERNAME` nao estiver configurado, o agente usa as credenciais da aplicacao como fallback.
- Mesmo no fallback, o SQL passa por validacao e a sessao e forzada para leitura.
