$tunnel_id = '04e743c0-091e-47f6-93e0-f26cf61d05e4'
$hostname_publico = 'agente-ia.nitec.dev.br'
$servico_local = 'http://127.0.0.1:8001'

$usuario_cloudflared_dir = 'C:\Users\Ncls4.2\.cloudflared'
$sistema_cloudflared_dir = 'C:\Windows\System32\config\systemprofile\.cloudflared'

$usuario_config_path = Join-Path $usuario_cloudflared_dir 'config.yml'
$sistema_config_path = Join-Path $sistema_cloudflared_dir 'config.yml'
$credencial_nome = "$tunnel_id.json"
$usuario_credencial_path = Join-Path $usuario_cloudflared_dir $credencial_nome
$sistema_credencial_path = Join-Path $sistema_cloudflared_dir $credencial_nome
$usuario_cert_path = Join-Path $usuario_cloudflared_dir 'cert.pem'
$sistema_cert_path = Join-Path $sistema_cloudflared_dir 'cert.pem'

if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] 'Administrator')) {
    Write-Host 'Execute este script como administrador.' -ForegroundColor Yellow
    exit 1
}

if (-not (Test-Path $usuario_credencial_path)) {
    Write-Host "Credencial do tunnel nao encontrada em $usuario_credencial_path" -ForegroundColor Red
    exit 1
}

$config_yaml = @"
tunnel: $tunnel_id
credentials-file: C:/Windows/System32/config/systemprofile/.cloudflared/$credencial_nome

ingress:
  - hostname: $hostname_publico
    service: $servico_local
  - service: http_status:404
"@

New-Item -ItemType Directory -Path $sistema_cloudflared_dir -Force | Out-Null
Copy-Item -Path $usuario_credencial_path -Destination $sistema_credencial_path -Force

if (Test-Path $usuario_cert_path) {
    Copy-Item -Path $usuario_cert_path -Destination $sistema_cert_path -Force
}

Set-Content -Path $usuario_config_path -Value $config_yaml -Encoding ascii
Set-Content -Path $sistema_config_path -Value $config_yaml -Encoding ascii

Write-Host 'Parando servico Cloudflared...' -ForegroundColor Cyan
sc.exe stop Cloudflared | Out-Host
Start-Sleep -Seconds 3

Write-Host 'Iniciando servico Cloudflared...' -ForegroundColor Cyan
sc.exe start Cloudflared | Out-Host
Start-Sleep -Seconds 5

Write-Host 'Status do servico:' -ForegroundColor Cyan
Get-Service Cloudflared | Format-Table Name, Status, StartType -AutoSize | Out-Host

Write-Host 'Health local da API:' -ForegroundColor Cyan
Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8001/api/v1/health' | Select-Object -ExpandProperty Content | Out-Host

Write-Host ''
Write-Host 'Tunnel publicado para a API local.' -ForegroundColor Green
Write-Host "Agora teste com seu Service Token em https://$hostname_publico/api/v1/health" -ForegroundColor Green
