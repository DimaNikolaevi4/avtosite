<?php
/**
 * СБОРЩИК НОВОСТЕЙ — полная версия
 *
 * Через браузер: открой файл напрямую
 * Через cron:     php /путь/к/test_fetch.php
 */

@set_time_limit(300);

// Показываем ошибки
if (php_sapi_name() !== 'cli') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

header('Content-Type: text/plain; charset=utf-8');

try {

    $cfg = require __DIR__ . '/config.php';
    define('CONFIG_LOADED', true);
    require __DIR__ . '/functions.php';

    initDirectories();

    echo "=== СБОРЩИК НОВОСТЕЙ ===\n\n";

    $totalAdded = 0;

    $baseUrl = '';
    if (php_sapi_name() !== 'cli') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $baseUrl = $scheme . '://' . $host . $dir;
    }
    if (empty($baseUrl) && !empty($cfg['site_url'])) {
        $baseUrl = $cfg['site_url'];
    }

    echo "Базовый URL: " . ($baseUrl ?: 'CLI') . "\n";
    echo "Источников: " . count($cfg['rss_feeds']) . "\n\n";

    foreach ($cfg['rss_feeds'] as $feed) {
        $feedUrl  = $feed['url'];
        $feedName = $feed['name'];
        $feedLang = $feed['lang'] ?? 'ru';

        echo "[{$feedName}] Загрузка...\n";
        $xml = httpGet($feedUrl);

        if ($xml === false) {
            echo "  Не удалось загрузить\n";
            logMsg("X: {$feedName}");
            continue;
        }

        $items = parseRSS($xml, $feedName, $feedLang);
        echo "  Записей: " . count($items) . "\n";
        logMsg("RSS: {$feedName} - " . count($items));

        foreach ($items as $item) {
            $hash = linkHash($item['link']);

            if ($cfg['dedup_enabled'] && articleExists($hash)) {
                continue;
            }

            $title = $item['title'];
            echo "  " . mb_substr($title, 0, 55) . "...\n";
            logMsg("-> " . mb_substr($title, 0, 50));

            // Скрапинг
            $scraped  = scrapeArticle($item['link']);
            $fullText = $scraped['text'];

            if (mb_strlen($fullText) < $cfg['min_text_length']) {
                $fullText = $item['description'];
            }

            if (mb_strlen($fullText) < 100) {
                echo "    Пропущен (мало текста)\n";
                continue;
            }

            // Загрузка фото
            $localImage = '';
            $imageUrl   = $scraped['image'];

            if (!empty($imageUrl)) {
                $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
                if (empty($ext)) $ext = 'jpg';
                $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
                $imgFilename = $hash . '.' . $ext;
                $imgPath     = $cfg['images_dir'] . '/' . $imgFilename;

                if (downloadImage($imageUrl, $imgPath)) {
                    $actualFiles = glob($cfg['images_dir'] . '/' . $hash . '.*');
                    $localImage = !empty($actualFiles) ? basename($actualFiles[0]) : $imgFilename;
                    echo "    Фото: {$localImage}\n";
                }
            }

            $slug = generateSlug($title);

            $article = [
                'hash'        => $hash,
                'slug'        => $slug,
                'title'       => $title,
                'description' => truncateText($fullText, 200),
                'text'        => $fullText,
                'link'        => $item['link'],
                'source'      => $feedName,
                'lang'        => $feedLang,
                'pub_date'    => $item['pub_date'],
                'image_url'   => $imageUrl,
                'local_image' => $localImage,
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            if (saveArticle($article)) {
                $totalAdded++;
                if (!empty($baseUrl)) {
                    generateStaticHTML($article, $baseUrl);
                }
                echo "    + Сохранено\n";
            } else {
                echo "    X Ошибка записи\n";
            }

            usleep(500000);
        }

        echo "\n";
    }

    cleanupOldArticles();
    setLastFetchTime();

    echo "=== ГОТОВО ===\n";
    echo "Добавлено: {$totalAdded}\n";
    echo "В базе: " . getArticleCount() . "\n";
    echo "Страниц: " . getStaticPageCount() . "\n";
    echo "Обновлено: " . getLastFetchTime() . "\n";

} catch (Throwable $e) {
    echo "\nОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
