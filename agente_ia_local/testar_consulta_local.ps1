$script_dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$tenant_id = Read-Host 'Informe o tenant_id [teste]'
$pergunta = Read-Host 'Informe a pergunta [Qual o produto mais vendido?]'

if ([string]::IsNullOrWhiteSpace($tenant_id)) {
    $tenant_id = 'teste'
}

if ([string]::IsNullOrWhiteSpace($pergunta)) {
    $pergunta = 'Qual o produto mais vendido?'
}

$body = @{
    tenant_id = $tenant_id
    pergunta = $pergunta
    limite_linhas = 10
} | ConvertTo-Json

Invoke-RestMethod `
    -Method Post `
    -Uri 'http://127.0.0.1:8001/api/v1/consultar-agente' `
    -ContentType 'application/json' `
    -Body $body | ConvertTo-Json -Depth 8
