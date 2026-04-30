<?php
/**
 * Диагностика сервера — загружи в корень сайта и открой в браузере
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== ДИАГНОСТИКА СЕРВЕРА ===\n\n";

// PHP
echo "PHP версия: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "ОС: " . PHP_OS . "\n\n";

// Расширения
$exts = ['curl', 'dom', 'json', 'fileinfo', 'mbstring', 'simplexml', 'openssl'];
echo "Расширения:\n";
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext) ? '✓ ОК' : '✗ НЕТ';
    echo "  {$ext}: {$loaded}\n";
}
echo "\n";

// Папки
$dirs = ['data', 'images', 'cache', 'news'];
$root = __DIR__;
echo "Папки (root: {$root}):\n";
foreach ($dirs as $dir) {
    $path = $root . '/' . $dir;
    $exists = is_dir($path) ? 'существует' : 'НЕТ';
    $writable = is_writable($root) ? 'запись ОК' : 'НЕТ записи в root';
    echo "  {$dir}/: {$exists} | {$writable}\n";
}
echo "\n";

// Проверяем права на запись
$testFile = $root . '/data/test_write.txt';
if (is_dir($root . '/data')) {
    $written = @file_put_contents($testFile, 'ok');
    if ($written !== false) {
        echo "Запись в data/: ✓ ОК\n";
        @unlink($testFile);
    } else {
        echo "Запись в data/: ✗ ОШИБКА\n";
    }
} else {
    echo "Папка data/ не существует\n";
}
echo "\n";

// allow_url_fopen
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . "\n";
echo "allow_url_include: " . ini_get('allow_url_include') . "\n";
echo "disable_functions: " . ini_get('disable_functions') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n\n";

// Пробуем curl
if (function_exists('curl_init')) {
    echo "cURL тест: ";
    $ch = curl_init('https://www.google.com');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "code={$code} error={$err}\n";
} else {
    echo "cURL: недоступен\n";
}

echo "\n=== ГОТОВО ===\n";
