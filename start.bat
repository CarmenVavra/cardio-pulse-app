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

REM Abhaengigkeiten bei jedem Start mit composer.lock / package-lock.json abgleichen,
REM damit nach einem git pull neue oder aktualisierte Pakete installiert werden.
REM Ohne Aenderungen dauert das nur wenige Sekunden.
echo Pruefe PHP-Abhaengigkeiten ...
call composer install --no-interaction --no-progress
if errorlevel 1 goto failed

echo Pruefe JavaScript-Abhaengigkeiten ...
call npm install --no-audit --no-fund
if errorlevel 1 goto failed

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
if errorlevel 1 goto failed

REM Assets bei jedem Start bauen, damit nach einem git pull kein veraltetes CSS/JS ausgeliefert wird.
echo Baue Frontend-Assets ...
call npm run build
if errorlevel 1 goto failed

echo Starte CardioPulse auf http://127.0.0.1:%PORT% ...
start "CardioPulse Server" /min php artisan serve --host=127.0.0.1 --port=%PORT%
timeout /t 2 /nobreak >nul

:open
start "" "http://127.0.0.1:%PORT%"
endlocal
exit /b 0

:failed
echo.
echo Start abgebrochen - bitte die Fehlermeldung oben pruefen.
pause
endlocal
exit /b 1
