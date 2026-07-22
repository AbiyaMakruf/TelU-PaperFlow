@echo off
title Paperflow Ngrok Server (Mobile dan HP Compatible)
cls

:: Tambahkan path PHP dan Node.js Laragon ke PATH Windows
set "PATH=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\nodejs\node-v22;%PATH%"

echo ========================================================
echo        PAPERFLOW NGROK LAUNCHER (UNTUK HP DAN LAPTOP LAIN)
echo ========================================================
echo.
echo  [1/2] Membersihkan cache hot-reload dan kompilasi CSS/JS...
echo.

if exist "public\hot" del "public\hot"
call npm run build

echo.
echo  [2/2] Menjalankan layanan Paperflow dan Ngrok:
echo   [SERVE] http://127.0.0.1:8000
echo   [QUEUE] php artisan queue:work --tries=3
echo   [NGROK] https://hormonal-shari-noncommodiously.ngrok-free.dev
echo.
echo  Membuka browser ke https://hormonal-shari-noncommodiously.ngrok-free.dev dalam 3 detik...
echo  Akses dari HP dan laptop lain kini 100%% memuat CSS tanpa popup!
echo  Tekan Ctrl + C di jendela ini untuk menghentikan semua service.
echo ========================================================
echo.

start "" powershell -Command "Start-Sleep -Seconds 3; Start-Process 'https://hormonal-shari-noncommodiously.ngrok-free.dev'"

call npm run dev:ngrok

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Terjadi kesalahan saat menjalankan service.
    pause
)
