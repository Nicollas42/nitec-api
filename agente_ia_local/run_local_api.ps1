$script_dir = Split-Path -Parent $MyInvocation.MyCommand.Path
$python_path = Join-Path $script_dir '.venv\Scripts\python.exe'

if (-not (Test-Path $python_path)) {
    Write-Host 'Virtualenv nao encontrada. Execute a instalacao primeiro.' -ForegroundColor Yellow
    exit 1
}

Set-Location $script_dir
& $python_path (Join-Path $script_dir 'main.py')
