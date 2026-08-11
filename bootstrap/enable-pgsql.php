<?php

// Auto-enable required extensions in php.ini if commented out (Windows only)
if (PHP_OS_FAMILY !== 'Windows') {
    return;
}

$ini = php_ini_loaded_file();
if ($ini && file_exists($ini)) {
    $content = file_get_contents($ini);
    $modified = false;

    $extensions = ['pdo_pgsql', 'pgsql', 'mbstring', 'fileinfo', 'zip', 'openssl', 'pdo_sqlite'];

    foreach ($extensions as $ext) {
        if (str_contains($content, ';extension='.$ext)) {
            $content = str_replace(';extension='.$ext, 'extension='.$ext, $content);
            $modified = true;
        }
    }

    if ($modified) {
        @file_put_contents($ini, $content);
        echo "   [FIX] Ekstensi PHP telah diaktifkan di {$ini}\n";
    }
}
