@echo off
if "%~1"=="RUNNING_IN_PERSISTENT_CMD" goto :MAIN
cmd /k ""%~f0" RUNNING_IN_PERSISTENT_CMD"
exit /b

:MAIN
title Paperflow Development Server (Local)
cls

echo ========================================================
echo             PAPERFLOW DEVELOPMENT LAUNCHER (LOCAL)
echo ========================================================
echo.
echo  [1/2] Memeriksa instalasi PHP dan Node.js...

:: 1. Auto-detect PHP (Drives C, D, E, F)
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\laragon\bin\php" for /d %%F in ("C:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PATH=%%F;%PATH%"
    if exist "D:\laragon\bin\php" for /d %%F in ("D:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PATH=%%F;%PATH%"
    if exist "E:\laragon\bin\php" for /d %%F in ("E:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PATH=%%F;%PATH%"
    if exist "F:\laragon\bin\php" for /d %%F in ("F:\laragon\bin\php\*") do if exist "%%F\php.exe" set "PATH=%%F;%PATH%"
    if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;%PATH%"
    if exist "D:\xampp\php\php.exe" set "PATH=D:\xampp\php;%PATH%"
    if exist "E:\xampp\php\php.exe" set "PATH=E:\xampp\php;%PATH%"
    if exist "F:\xampp\php\php.exe" set "PATH=F:\xampp\php;%PATH%"
    if exist "C:\php\php.exe" set "PATH=C:\php;%PATH%"
    if exist "D:\php\php.exe" set "PATH=D:\php;%PATH%"
)

:: 2. Auto-detect Node.js (Drives C, D, E, F)
where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\laragon\bin\nodejs" for /d %%F in ("C:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "PATH=%%F;%PATH%"
    if exist "D:\laragon\bin\nodejs" for /d %%F in ("D:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "PATH=%%F;%PATH%"
    if exist "E:\laragon\bin\nodejs" for /d %%F in ("E:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "PATH=%%F;%PATH%"
    if exist "F:\laragon\bin\nodejs" for /d %%F in ("F:\laragon\bin\nodejs\*") do if exist "%%F\node.exe" set "PATH=%%F;%PATH%"
    if exist "C:\Program Files\nodejs\node.exe" set "PATH=C:\Program Files\nodejs;%PATH%"
    if exist "D:\Program Files\nodejs\node.exe" set "PATH=D:\Program Files\nodejs;%PATH%"
)

:: Verification
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo [ERROR] PHP tidak ditemukan di Laragon / XAMPP / PATH.
    echo ========================================================
    echo Mohon pastikan Laragon / XAMPP sudah terinstall.
    echo.
    pause
    exit /b 1
)

where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ========================================================
    echo [ERROR] Node.js tidak ditemukan di komputer ini.
    echo ========================================================
    echo Mohon install Node.js dari https://nodejs.org/
    echo.
    pause
    exit /b 1
)

echo  PHP    : OK
echo  Node.js: OK
echo.

echo  [2/2] Menjalankan layanan Paperflow lokal:
echo   [SERVE] http://127.0.0.1:8000
echo   [QUEUE] php artisan queue:work --tries=3
echo   [VITE]  Vite Dev Server
echo.
echo  Membuka browser ke http://127.0.0.1:8000 dalam 3 detik...
echo  Tekan Ctrl + C di jendela ini untuk menghentikan semua service.
echo ========================================================
echo.

start "" powershell -Command "Start-Sleep -Seconds 3; Start-Process 'http://127.0.0.1:8000'"

call npm run dev

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Terjadi kesalahan saat menjalankan service.
)

pause
exit /b 0
