@echo off
setlocal
cd /d "%~dp0"
title CardioPulse - Sicherung vom Server holen

rem Legt auf dem Server einen Schnappschuss der Datenbank an und laedt ihn
rem zusammen mit der .env (enthaelt APP_KEY) nach backups\ herunter.
rem Der SSH-Zugang wird in .deploy-target gemerkt (nicht in Git).

if exist .deploy-target set /p TARGET=<.deploy-target
if not defined TARGET set /p TARGET=SSH-Zugang (z. B. hosting123456@123.45.67.89):
if not defined TARGET goto failed

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set STAMP=%%i
if not exist backups mkdir backups
set FILE=backups\cardiopulse-%STAMP%.tar.gz

echo ==^> Sicherung auf dem Server anlegen und herunterladen
ssh %TARGET% "cd cardio-pulse && f=$(php artisan cardiopulse:backup --no-ansi) && tar -czf - -C $(dirname $f) $(basename $f) -C $PWD .env" > "%FILE%"
if errorlevel 1 goto failed
for %%A in ("%FILE%") do if %%~zA LSS 1024 goto failed

> .deploy-target echo %TARGET%
echo.
echo Fertig: %FILE%
echo Enthaelt Gesundheitsdaten und den App-Schluessel - sicher aufbewahren
echo (z. B. auf einem verschluesselten USB-Stick), nicht per E-Mail versenden.
pause
exit /b 0

:failed
if defined FILE if exist "%FILE%" del "%FILE%"
echo.
echo FEHLER - Sicherung abgebrochen.
pause
exit /b 1
