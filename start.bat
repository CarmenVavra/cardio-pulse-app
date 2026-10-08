@echo off
REM ==========================================================================
REM  CardioPulse - Entwicklungsserver starten (http://127.0.0.1:8700)
REM ==========================================================================
setlocal
cd /d "%~dp0"
set PORT=8700

netstat -ano | findstr /R /C:":%PORT% .*LISTENING" >nul
if %errorlevel%==0 (
    echo Port %PORT% ist bereits belegt - laeuft CardioPulse schon? Mit stop.bat beenden.
    goto open
)

if not exist vendor\autoload.php (
    echo Installiere PHP-Abhaengigkeiten ...
    call composer install --no-interaction
)

if not exist .env (
    copy .env.example .env >nul
    php artisan key:generate --force
)

if not exist database\database.sqlite (
    echo Lege Datenbank mit Demo-Daten an ...
    type nul > database\database.sqlite
    php artisan migrate --seed --force
) else (
    php artisan migrate --force
)

if not exist public\build\manifest.json (
    echo Baue Frontend-Assets ...
    if not exist node_modules call npm install
    call npm run build
)

echo Starte CardioPulse auf http://127.0.0.1:%PORT% ...
start "CardioPulse Server" /min php artisan serve --host=127.0.0.1 --port=%PORT%
timeout /t 2 /nobreak >nul

:open
start "" "http://127.0.0.1:%PORT%"
endlocal
