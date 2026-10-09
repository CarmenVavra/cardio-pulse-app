@echo off
setlocal
cd /d "%~dp0"
title CardioPulse - Assets hochladen

rem Baut CSS/JS lokal und laedt sie auf den Server - fuer Hosting ohne Node.js
rem (z. B. netcup-Webhosting). Danach auf dem Server ./deploy.sh ausfuehren.
rem Der SSH-Zugang wird in .deploy-target gemerkt (nicht in Git).

if exist .deploy-target set /p TARGET=<.deploy-target
if not defined TARGET set /p TARGET=SSH-Zugang (z. B. hosting123456@123.45.67.89):
if not defined TARGET goto failed

rem Die Assets muessen zum Stand auf GitHub passen, den deploy.sh auf dem Server holt.
git fetch -q origin
for /f %%i in ('git rev-parse HEAD') do set LOCAL=%%i
for /f %%i in ('git rev-parse origin/main') do set REMOTE=%%i
if not "%LOCAL%"=="%REMOTE%" echo WARNUNG: Lokaler Stand weicht von GitHub ab - vorher git pull bzw. git push ausfuehren.
git diff --quiet HEAD -- resources package.json package-lock.json vite.config.js || echo WARNUNG: Ungespeicherte Aenderungen an den Assets werden mit hochgeladen.

echo ==^> Pakete abgleichen
call npm install --no-audit --no-fund
if errorlevel 1 goto failed

echo ==^> Assets bauen
call npm run build
if errorlevel 1 goto failed

rem Versionsangabe fuer deploy.sh: letzter Commit, der CSS/JS geaendert hat
rem ("-dirty" bei ungespeicherten Aenderungen). deploy.sh warnt, wenn sie nicht passt.
set ASSETS=
for /f %%i in ('git log -1 --format^=%%H -- resources/css resources/js package.json package-lock.json vite.config.js') do set ASSETS=%%i
git diff --quiet HEAD -- resources/css resources/js package.json package-lock.json vite.config.js || set ASSETS=%ASSETS%-dirty
> public\build\.source-commit echo %ASSETS%

echo ==^> Hochladen nach %TARGET%:cardio-pulse/public/build
tar --format ustar -czf - -C public build | ssh %TARGET% "rm -rf cardio-pulse/public/build && tar -xzf - -C cardio-pulse/public"
if errorlevel 1 goto failed

> .deploy-target echo %TARGET%
echo.
echo Fertig. Jetzt auf dem Server: cd cardio-pulse ^&^& ./deploy.sh
pause
exit /b 0

:failed
echo.
echo FEHLER - Hochladen abgebrochen.
pause
exit /b 1
