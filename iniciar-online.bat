@echo off
title Plataforma Pinciara - Online
cls
echo ============================================================
echo   PLATAFORMA PINCIARA - INICIALIZADOR ONLINE LEVE
echo ============================================================
echo.

:: Define multi-workers para o PHP atender requisicoes em paralelo
set PHP_CLI_SERVER_WORKERS=4

echo [1/2] Iniciando servidor PHP (ultra-leve)...
start /B php -S 127.0.0.1:8080 -t public > nul 2>&1

:: Pequena pausa para garantir que o PHP subiu
timeout /t 2 /nobreak > nul

echo [2/2] Conectando ao Cloudflare Tunnel...
echo.
echo ============================================================
echo   Copie o link HTTPS terminado em .trycloudflare.com abaixo:
echo ============================================================
echo.

"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://127.0.0.1:8080

:: Quando o usuario fechar com Ctrl+C, encerra o PHP
taskkill /F /IM php.exe > nul 2>&1
