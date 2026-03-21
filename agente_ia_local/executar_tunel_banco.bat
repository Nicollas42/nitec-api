@echo off
setlocal enableextensions

title agente_ia_db_tunnel

if "%AGENTE_SSH_HOST%"=="" (
    echo [ERRO] AGENTE_SSH_HOST nao configurado.
    exit /b 1
)

set "ssh_destino=%AGENTE_SSH_HOST%"
if not "%AGENTE_SSH_USER%"=="" set "ssh_destino=%AGENTE_SSH_USER%@%AGENTE_SSH_HOST%"

ssh -o ServerAliveInterval=30 -o ExitOnForwardFailure=yes -N -L %AGENTE_SSH_LOCAL_BIND_HOST%:%AGENTE_SSH_LOCAL_DB_PORT%:%AGENTE_SSH_REMOTE_DB_HOST%:%AGENTE_SSH_REMOTE_DB_PORT% -p %AGENTE_SSH_PORT% %ssh_destino%

endlocal
