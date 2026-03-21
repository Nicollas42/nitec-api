@echo off
setlocal enableextensions

set "script_dir=%~dp0"
if "%script_dir:~-1%"=="\" set "script_dir=%script_dir:~0,-1%"

set "env_local_path=%script_dir%\.env"
set "python_path=%script_dir%\.venv\Scripts\python.exe"
set "api_main_path=%script_dir%\main.py"
set "db_status_script=%script_dir%\testar_conexao_banco.py"
set "api_runner_path=%script_dir%\executar_api_local.bat"
set "ssh_runner_path=%script_dir%\executar_tunel_banco.bat"
set "ollama_path=%LocalAppData%\Programs\Ollama\ollama.exe"
set "ollama_window_title=agente_ia_ollama"
set "api_window_title=agente_ia_api_local"
set "ssh_window_title=agente_ia_db_tunnel"
set "ollama_port=11434"
set "api_port=8001"
set "api_health_url=http://127.0.0.1:%api_port%/api/v1/health"

call :carregar_configuracoes_locais

if "%~1"=="" set "modo_interativo=1"

if /i "%~1"=="start" goto :iniciar_agente
if /i "%~1"=="stop" goto :parar_agente
if /i "%~1"=="status" goto :mostrar_status
if /i "%~1"=="restart" goto :reiniciar_agente

:menu_principal
cls
echo ============================================
echo Controle do Agente IA Local - Nitec
echo ============================================
echo [1] Iniciar agente local
echo [2] Parar agente local
echo [3] Ver status
echo [4] Reiniciar agente local
echo [5] Sair
echo.
choice /c 12345 /n /m "Escolha uma opcao: "

if errorlevel 5 goto :fim
if errorlevel 4 goto :reiniciar_agente
if errorlevel 3 goto :mostrar_status
if errorlevel 2 goto :parar_agente
if errorlevel 1 goto :iniciar_agente
goto :menu_principal

rem Descricao: carrega a configuracao local do agente e aplica padroes seguros.
:carregar_configuracoes_locais
if exist "%env_local_path%" (
    for /f "usebackq eol=# tokens=1,* delims==" %%a in ("%env_local_path%") do (
        if not "%%a"=="" set "%%a=%%b"
    )
)

if not defined AGENTE_SSH_TUNNEL_ENABLED set "AGENTE_SSH_TUNNEL_ENABLED=false"
if not defined AGENTE_SSH_HOST set "AGENTE_SSH_HOST=187.77.63.216"
if not defined AGENTE_SSH_PORT set "AGENTE_SSH_PORT=22"
if not defined AGENTE_SSH_USER set "AGENTE_SSH_USER=root"
if not defined AGENTE_LLM_PROVIDER set "AGENTE_LLM_PROVIDER=ollama"
if not defined AGENTE_SSH_LOCAL_BIND_HOST set "AGENTE_SSH_LOCAL_BIND_HOST=127.0.0.1"
if not defined AGENTE_SSH_LOCAL_DB_PORT set "AGENTE_SSH_LOCAL_DB_PORT=3307"
if not defined AGENTE_SSH_REMOTE_DB_HOST set "AGENTE_SSH_REMOTE_DB_HOST=127.0.0.1"
if not defined AGENTE_SSH_REMOTE_DB_PORT set "AGENTE_SSH_REMOTE_DB_PORT=3306"

if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" (
    if not defined AGENTE_DB_HOST set "AGENTE_DB_HOST=%AGENTE_SSH_LOCAL_BIND_HOST%"
    if not defined AGENTE_DB_PORT set "AGENTE_DB_PORT=%AGENTE_SSH_LOCAL_DB_PORT%"
)

if not defined AGENTE_DB_HOST set "AGENTE_DB_HOST=127.0.0.1"
if not defined AGENTE_DB_PORT set "AGENTE_DB_PORT=3306"
exit /b 0

rem Descricao: valida se os arquivos locais necessarios existem.
:validar_dependencias
call :carregar_configuracoes_locais

if /i "%AGENTE_LLM_PROVIDER%"=="ollama" (
    if not exist "%ollama_path%" (
        echo [ERRO] Ollama nao encontrado em:
        echo %ollama_path%
        exit /b 1
    )
)

if not exist "%python_path%" (
    echo [ERRO] Python da virtualenv nao encontrado em:
    echo %python_path%
    exit /b 1
)

if not exist "%api_main_path%" (
    echo [ERRO] Arquivo main.py nao encontrado em:
    echo %api_main_path%
    exit /b 1
)

if not exist "%db_status_script%" (
    echo [ERRO] Script de teste do banco nao encontrado em:
    echo %db_status_script%
    exit /b 1
)

if not exist "%api_runner_path%" (
    echo [ERRO] Script auxiliar da API nao encontrado em:
    echo %api_runner_path%
    exit /b 1
)

if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" if not exist "%ssh_runner_path%" (
    echo [ERRO] Script auxiliar do tunnel nao encontrado em:
    echo %ssh_runner_path%
    exit /b 1
)

if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" (
    where ssh >nul 2>&1
    if errorlevel 1 (
        echo [ERRO] Cliente SSH nao encontrado no PATH do Windows.
        exit /b 1
    )
)

exit /b 0

rem Descricao: verifica se uma porta TCP esta em modo LISTENING.
:porta_ativa
netstat -ano | findstr /c:":%~1" | findstr "LISTENING" >nul 2>&1
if errorlevel 1 exit /b 1
exit /b 0

rem Descricao: encerra a janela CMD criada por este script.
:encerrar_janela_cmd
taskkill /fi "WINDOWTITLE eq %~1" /fi "IMAGENAME eq cmd.exe" /t /f >nul 2>&1
if errorlevel 1 (
    echo [INFO] Janela "%~1" nao estava aberta por este script.
    exit /b 1
)

echo [OK] Janela "%~1" encerrada.
exit /b 0

rem Descricao: obtem o PID que esta escutando em uma porta local.
:obter_pid_por_porta
set "%~2="
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /c:":%~1" ^| findstr "LISTENING"') do (
    if not defined %~2 set "%~2=%%p"
)

if not defined %~2 exit /b 1
exit /b 0

rem Descricao: encerra o processo que estiver escutando em uma porta especifica.
:encerrar_processo_por_porta
set "pid_por_porta="
call :obter_pid_por_porta %~1 pid_por_porta
if errorlevel 1 (
    echo [INFO] Nenhum processo encontrado na porta %~1.
    exit /b 1
)

taskkill /pid %pid_por_porta% /t /f >nul 2>&1
if errorlevel 1 (
    echo [INFO] Nao foi possivel encerrar o processo da porta %~1.
    exit /b 1
)

echo [OK] Processo da porta %~1 encerrado.
exit /b 0

rem Descricao: mostra avisos importantes sobre a configuracao atual do banco.
:mostrar_avisos_configuracao
if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" (
    if not exist "%env_local_path%" (
        echo [AVISO] O arquivo .env local do agente ainda nao existe.
        echo [AVISO] Para usar o banco da VPS, copie .env.example para .env e preencha as credenciais do MySQL remoto.
    )
)
exit /b 0

rem Descricao: inicia o Ollama e a API local do agente.
:iniciar_agente
call :validar_dependencias
if errorlevel 1 goto :voltar_ou_sair

call :mostrar_avisos_configuracao
if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" (
    call :porta_ativa %AGENTE_SSH_LOCAL_DB_PORT%
    if errorlevel 1 (
        if "%AGENTE_SSH_HOST%"=="" (
            echo [ERRO] AGENTE_SSH_HOST nao configurado.
            goto :voltar_ou_sair
        )

        set "ssh_destino=%AGENTE_SSH_HOST%"
        if not "%AGENTE_SSH_USER%"=="" set "ssh_destino=%AGENTE_SSH_USER%@%AGENTE_SSH_HOST%"

        echo [INFO] Iniciando tunnel SSH do banco em %AGENTE_SSH_LOCAL_BIND_HOST%:%AGENTE_SSH_LOCAL_DB_PORT%...
        start "%ssh_window_title%" cmd /k ""%ssh_runner_path%""
        timeout /t 4 /nobreak >nul
    ) else (
        echo [OK] Tunnel SSH do banco ja esta ativo em %AGENTE_SSH_LOCAL_BIND_HOST%:%AGENTE_SSH_LOCAL_DB_PORT%.
    )
)

if /i "%AGENTE_LLM_PROVIDER%"=="ollama" (
    call :porta_ativa %ollama_port%
    if errorlevel 1 (
        echo [INFO] Iniciando Ollama em 127.0.0.1:%ollama_port%...
        start "%ollama_window_title%" cmd /k "title %ollama_window_title% && set OLLAMA_HOST=127.0.0.1:%ollama_port% && set OLLAMA_CONTEXT_LENGTH=4096 && set OLLAMA_KEEP_ALIVE=30m && set OLLAMA_NO_CLOUD=1 && set OLLAMA_VULKAN=1 && ""%ollama_path%"" serve"
        timeout /t 4 /nobreak >nul
    ) else (
        echo [OK] Ollama ja esta ativo em 127.0.0.1:%ollama_port%.
    )
)

call :porta_ativa %api_port%
if errorlevel 1 (
    echo [INFO] Iniciando API local do agente em 127.0.0.1:%api_port%...
    start "%api_window_title%" "%api_runner_path%"
    timeout /t 4 /nobreak >nul
) else (
    echo [OK] API local ja esta ativa em 127.0.0.1:%api_port%.
)

echo.
call :mostrar_status_sem_menu
goto :voltar_ou_sair

rem Descricao: para o Ollama e a API local abertos por este script.
:parar_agente
echo [INFO] Encerrando componentes locais do agente...
call :encerrar_janela_cmd "%api_window_title%"
if /i "%AGENTE_LLM_PROVIDER%"=="ollama" call :encerrar_janela_cmd "%ollama_window_title%"
call :encerrar_janela_cmd "%ssh_window_title%"
call :encerrar_processo_por_porta %api_port%
if /i "%AGENTE_LLM_PROVIDER%"=="ollama" call :encerrar_processo_por_porta %ollama_port%
if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" call :encerrar_processo_por_porta %AGENTE_SSH_LOCAL_DB_PORT%

echo.
call :mostrar_status_sem_menu
goto :voltar_ou_sair

rem Descricao: reinicia o Ollama e a API local.
:reiniciar_agente
call :parar_agente_sem_retorno
call :iniciar_agente_sem_retorno
echo.
call :mostrar_status_sem_menu
goto :voltar_ou_sair

rem Descricao: mostra o estado atual do agente local.
:mostrar_status
call :mostrar_status_sem_menu
goto :voltar_ou_sair

rem Descricao: implementa a parte de inicio sem retorno ao menu.
:iniciar_agente_sem_retorno
call :validar_dependencias
if errorlevel 1 exit /b 1

call :mostrar_avisos_configuracao
if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" (
    call :porta_ativa %AGENTE_SSH_LOCAL_DB_PORT%
    if errorlevel 1 (
        if "%AGENTE_SSH_HOST%"=="" (
            echo [ERRO] AGENTE_SSH_HOST nao configurado.
            exit /b 1
        )

        set "ssh_destino=%AGENTE_SSH_HOST%"
        if not "%AGENTE_SSH_USER%"=="" set "ssh_destino=%AGENTE_SSH_USER%@%AGENTE_SSH_HOST%"

        echo [INFO] Iniciando tunnel SSH do banco em %AGENTE_SSH_LOCAL_BIND_HOST%:%AGENTE_SSH_LOCAL_DB_PORT%...
        start "%ssh_window_title%" cmd /k ""%ssh_runner_path%""
        timeout /t 4 /nobreak >nul
    ) else (
        echo [OK] Tunnel SSH do banco ja esta ativo em %AGENTE_SSH_LOCAL_BIND_HOST%:%AGENTE_SSH_LOCAL_DB_PORT%.
    )
)

if /i "%AGENTE_LLM_PROVIDER%"=="ollama" (
    call :porta_ativa %ollama_port%
    if errorlevel 1 (
        echo [INFO] Iniciando Ollama em 127.0.0.1:%ollama_port%...
        start "%ollama_window_title%" cmd /k "title %ollama_window_title% && set OLLAMA_HOST=127.0.0.1:%ollama_port% && set OLLAMA_CONTEXT_LENGTH=4096 && set OLLAMA_KEEP_ALIVE=30m && set OLLAMA_NO_CLOUD=1 && set OLLAMA_VULKAN=1 && ""%ollama_path%"" serve"
        timeout /t 4 /nobreak >nul
    ) else (
        echo [OK] Ollama ja esta ativo em 127.0.0.1:%ollama_port%.
    )
)

call :porta_ativa %api_port%
if errorlevel 1 (
    echo [INFO] Iniciando API local do agente em 127.0.0.1:%api_port%...
    start "%api_window_title%" "%api_runner_path%"
    timeout /t 4 /nobreak >nul
) else (
    echo [OK] API local ja esta ativa em 127.0.0.1:%api_port%.
)

exit /b 0

rem Descricao: implementa a parte de parada sem retorno ao menu.
:parar_agente_sem_retorno
echo [INFO] Encerrando componentes locais do agente...
call :encerrar_janela_cmd "%api_window_title%"
if /i "%AGENTE_LLM_PROVIDER%"=="ollama" call :encerrar_janela_cmd "%ollama_window_title%"
call :encerrar_janela_cmd "%ssh_window_title%"
call :encerrar_processo_por_porta %api_port%
if /i "%AGENTE_LLM_PROVIDER%"=="ollama" call :encerrar_processo_por_porta %ollama_port%
if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" call :encerrar_processo_por_porta %AGENTE_SSH_LOCAL_DB_PORT%
exit /b 0

rem Descricao: imprime o status dos componentes e do tunnel.
:mostrar_status_sem_menu
call :carregar_configuracoes_locais

echo ============================================
echo Status do Agente IA Local
echo ============================================

if /i "%AGENTE_LLM_PROVIDER%"=="ollama" (
    call :porta_ativa %ollama_port%
    if errorlevel 1 (
        echo Ollama: parado
    ) else (
        echo Ollama: ativo em 127.0.0.1:%ollama_port%
    )
) else (
    echo Ollama: desabilitado para provider %AGENTE_LLM_PROVIDER%
)

call :porta_ativa %api_port%
if errorlevel 1 (
    echo API local: parada
) else (
    echo API local: ativa em 127.0.0.1:%api_port%
)

if /i "%AGENTE_SSH_TUNNEL_ENABLED%"=="true" (
    call :porta_ativa %AGENTE_SSH_LOCAL_DB_PORT%
    if errorlevel 1 (
        echo Tunnel banco VPS: parado
    ) else (
        echo Tunnel banco VPS: ativo em %AGENTE_SSH_LOCAL_BIND_HOST%:%AGENTE_SSH_LOCAL_DB_PORT%
    )
    echo Banco configurado: %AGENTE_DB_HOST%:%AGENTE_DB_PORT%
) else (
    echo Tunnel banco VPS: desabilitado
    echo Banco configurado: %AGENTE_DB_HOST%:%AGENTE_DB_PORT%
)

sc query Cloudflared | findstr /c:"RUNNING" >nul 2>&1
if errorlevel 1 (
    echo Cloudflared: nao esta em execucao
    echo Observacao: sem o tunnel, a VPS nao consegue chegar ao seu PC.
) else (
    echo Cloudflared: servico em execucao
)

echo.
echo Health da API local:
powershell -NoProfile -Command "try { (Invoke-WebRequest -UseBasicParsing '%api_health_url%').Content } catch { 'indisponivel' }"

echo.
echo Conectividade do banco:
"%python_path%" "%db_status_script%"
exit /b 0

rem Descricao: controla se volta ao menu ou encerra o script.
:voltar_ou_sair
if defined modo_interativo (
    echo.
    pause
    goto :menu_principal
)

:fim
endlocal
exit /b 0
