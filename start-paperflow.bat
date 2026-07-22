@echo off
title Paperflow Development Server
cls

:: Tambahkan path PHP dan Node.js Laragon ke PATH Windows
set "PATH=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\nodejs\node-v22;%PATH%"

echo ========================================================
echo             PAPERFLOW DEVELOPMENT LAUNCHER
echo ========================================================
echo.
echo  Menjalankan layanan Paperflow:
echo   [SERVE] http://127.0.0.1:8000
echo   [QUEUE] php artisan queue:work --tries=3
echo   [VITE]  Vite Dev Server
echo.
echo  Membuka browser dalam 3 detik...
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
