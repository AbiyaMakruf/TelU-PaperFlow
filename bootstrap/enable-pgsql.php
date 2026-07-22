<?php

// Auto-enable pdo_pgsql and pgsql extensions in php.ini if commented out
$ini = php_ini_loaded_file();
if ($ini && file_exists($ini)) {
    $content = file_get_contents($ini);
    $modified = false;

    if (str_contains($content, ';extension=pdo_pgsql')) {
        $content = str_replace(';extension=pdo_pgsql', 'extension=pdo_pgsql', $content);
        $modified = true;
    }

    if (str_contains($content, ';extension=pgsql')) {
        $content = str_replace(';extension=pgsql', 'extension=pgsql', $content);
        $modified = true;
    }

    if ($modified) {
        @file_put_contents($ini, $content);
        echo "   [FIX] Extension pdo_pgsql dan pgsql telah diaktifkan di {$ini}\n";
    }
}
