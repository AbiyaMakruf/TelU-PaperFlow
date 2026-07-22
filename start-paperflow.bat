@echo off
title Paperflow Development Server (Local)
cls

:: Auto-detect PHP dan Node.js di Windows (Laragon, XAMPP, atau System PATH)
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    for /d %%D in ("C:\laragon\bin\php\php-*") do (
        if exist "%%D\php.exe" set "PATH=%%D;%PATH%"
    )
)
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" set "PATH=C:\xampp\php;%PATH%"
    if exist "C:\php\php.exe" set "PATH=C:\php;%PATH%"
)

where node >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    for /d %%D in ("C:\laragon\bin\nodejs\node-*") do (
        if exist "%%D\node.exe" set "PATH=%%D;%PATH%"
    )
    if exist "C:\Program Files\nodejs\node.exe" set "PATH=C:\Program Files\nodejs;%PATH%"
)

where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PHP (php.exe) tidak ditemukan di PATH sistem, Laragon, atau XAMPP.
    echo Mohon pastikan PHP sudah terinstall atau tambahkan folder PHP ke Environment Variables.
    pause
    exit /b 1
)

echo ========================================================
echo             PAPERFLOW DEVELOPMENT LAUNCHER (LOCAL)
echo ========================================================
echo.
echo  Menjalankan layanan Paperflow lokal:
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
    pause
)
