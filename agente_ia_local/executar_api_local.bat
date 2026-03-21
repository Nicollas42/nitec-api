@echo off
setlocal enableextensions

set "script_dir=%~dp0"
if "%script_dir:~-1%"=="\" set "script_dir=%script_dir:~0,-1%"
title agente_ia_api_local
cd /d "%script_dir%"
"%script_dir%\.venv\Scripts\python.exe" "%script_dir%\main.py"

endlocal
