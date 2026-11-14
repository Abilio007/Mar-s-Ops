@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "BACKEND_DIR=%~dp0backend"
set "FRONTEND_DIR=%~dp0frontend"

where php >nul 2>&1
if errorlevel 1 (
    echo ERRO: PHP nao foi encontrado no PATH.
    goto :failed
)
where npm >nul 2>&1
if errorlevel 1 (
    echo ERRO: npm nao foi encontrado no PATH.
    goto :failed
)
where curl.exe >nul 2>&1
if errorlevel 1 (
    echo ERRO: curl.exe nao foi encontrado no Windows.
    goto :failed
)
if not exist "%BACKEND_DIR%\.env" (
    echo ERRO: backend\.env nao existe. Copie backend\.env.example para backend\.env e configure-o.
    goto :failed
)
if not exist "%BACKEND_DIR%\vendor\autoload.php" (
    echo ERRO: dependencias Laravel ausentes. Execute composer install em backend.
    goto :failed
)
if not exist "%FRONTEND_DIR%\.env" (
    echo ERRO: frontend\.env nao existe. Copie frontend\.env.example e configure VITE_REVERB_APP_KEY.
    goto :failed
)
if not exist "%FRONTEND_DIR%\node_modules\.bin\vite.cmd" (
    echo ERRO: dependencias do frontend ausentes. Execute npm install em frontend.
    goto :failed
)

pushd "%BACKEND_DIR%"
php artisan config:clear
if errorlevel 1 (
    popd
    echo ERRO: nao foi possivel limpar o cache de configuracao do Laravel.
    goto :failed
)
php artisan migrate --seed --force
if errorlevel 1 (
    popd
    echo ERRO: migrations ou seed falharam. Consulte a mensagem acima.
    goto :failed
)
popd

curl.exe --silent --fail --max-time 2 "http://127.0.0.1:8000/api/data/regions" -o nul >nul 2>&1
if errorlevel 1 (
    start "MARTE - Laravel API" /D "%BACKEND_DIR%" cmd /k "php artisan serve --host=0.0.0.0 --port=8000"
) else (
    echo A API ja esta ativa na porta 8000; sera reutilizada.
)

netstat -ano -p tcp | findstr /R /C:":8080 .*LISTENING" >nul
if errorlevel 1 (
    start "MARTE - Reverb" /D "%BACKEND_DIR%" cmd /k "php artisan reverb:start"
    call :wait_for_port 8080
    if errorlevel 1 (
        echo ERRO: o Reverb nao respondeu na porta 8080.
        goto :failed
    )
) else (
    echo Reverb ja esta ativo na porta 8080; sera reutilizado.
)

set "VITE_API_BASE_URL=http://127.0.0.1:8000/api"
set "VITE_REVERB_HOST=127.0.0.1"
curl.exe --silent --fail --max-time 2 "http://127.0.0.1:3000/" -o nul >nul 2>&1
if errorlevel 1 (
    start "MARTE - Frontend" /D "%FRONTEND_DIR%" cmd /k "npm run dev -- --strictPort"
) else (
    echo O frontend ja esta ativo na porta 3000; sera reutilizado.
)

call :wait_for_url "http://127.0.0.1:8000/api/data/regions"
if errorlevel 1 (
    echo ERRO: a API nao respondeu em http://127.0.0.1:8000.
    goto :failed
)
call :wait_for_url "http://127.0.0.1:3000/"
if errorlevel 1 (
    echo ERRO: o frontend nao respondeu em http://127.0.0.1:3000.
    goto :failed
)

echo.
echo MARTE esta pronto: http://127.0.0.1:3000
start "" "http://127.0.0.1:3000"
exit /b 0

:wait_for_url
set "WAIT_COUNT=0"
:wait_loop
curl.exe --silent --fail --max-time 2 "%~1" -o nul >nul 2>&1
if not errorlevel 1 exit /b 0
set /a WAIT_COUNT+=1
if %WAIT_COUNT% GEQ 30 exit /b 1
timeout /t 1 /nobreak >nul
goto :wait_loop

:wait_for_port
set "WAIT_COUNT=0"
:port_loop
netstat -ano -p tcp | findstr /R /C:":%~1 .*LISTENING" >nul
if not errorlevel 1 exit /b 0
set /a WAIT_COUNT+=1
if %WAIT_COUNT% GEQ 30 exit /b 1
timeout /t 1 /nobreak >nul
goto :port_loop

:failed
echo.
echo A inicializacao foi interrompida. Corrija o erro e execute este arquivo novamente.
pause
exit /b 1
