#!/bin/bash

echo "========================================================"
echo "  PAPERFLOW LAUNCHER (MACOS): NGROK + SUPABASE CLOUD"
echo "========================================================"
echo ""
echo " [1/3] Memeriksa instalasi PHP dan Node.js..."

if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP tidak ditemukan di PATH sistem macOS ini."
    exit 1
fi

if ! command -v node &> /dev/null; then
    echo "[ERROR] Node.js tidak ditemukan."
    exit 1
fi

if ! command -v ngrok &> /dev/null; then
    echo "[WARNING] ngrok tidak ditemukan di PATH. Menggunakan npx ngrok..."
fi

# Enable pgsql if bootstrap script exists
if [ -f "bootstrap/enable-pgsql.php" ]; then
    php bootstrap/enable-pgsql.php > /dev/null 2>&1
fi

# Switch database connection to Supabase Cloud
echo " [DATABASE] Menghubungkan ke Supabase Cloud (AWS Pooler)..."
php artisan paperflow:switch-supabase cloud > /dev/null 2>&1

# Free port 8000 and stop lingering ngrok
pkill ngrok > /dev/null 2>&1 || true
lsof -ti:8000 | xargs kill -9 > /dev/null 2>&1 || true

echo " PHP      : OK"
echo " Node.js  : OK"
echo " Database : Supabase Cloud"
echo ""

echo " [2/3] Membersihkan cache hot-reload dan kompilasi CSS/JS..."
echo ""

rm -f public/hot
pnpm build || npm run build

if [ $? -ne 0 ]; then
    echo "[ERROR] Gagal melakukan kompilasi aset frontend."
    exit 1
fi

echo ""
echo " [3/3] Menjalankan layanan Paperflow dan Ngrok (Cloud DB):"
echo "  [SERVE] http://127.0.0.1:8000"
echo "  [QUEUE] php artisan queue:work --tries=3"
echo "  [NGROK] https://hormonal-shari-noncommodiously.ngrok-free.dev"
echo ""
echo " Membuka browser ke https://hormonal-shari-noncommodiously.ngrok-free.dev dalam 3 detik..."
echo " Tekan Ctrl + C di jendela ini untuk menghentikan semua service."
echo "========================================================"
echo ""

(sleep 3 && open "https://hormonal-shari-noncommodiously.ngrok-free.dev") &

npx concurrently -k -n "SERVE,QUEUE,NGROK" -c "blue,magenta,cyan" \
    "php artisan serve" \
    "php artisan queue:work --tries=3" \
    "ngrok http --url=hormonal-shari-noncommodiously.ngrok-free.dev 8000"
