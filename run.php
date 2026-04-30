<?php
/**
 * СБОРЩИК НОВОСТЕЙ — полная версия
 *
 * Через браузер: открой файл напрямую (без ключа)
 * Через cron:     php /путь/к/run.php
 *
 * Cron на Beget (каждые 3 часа):
 *   0 */3 * * * /usr/bin/php /home/u/akkaunt/public_html/run.php
 */

@set_time_limit(300);
@ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== ЗАПУСК СБОРЩИКА НОВОСТЕЙ ===\n\n";

try {
    $cfg = require __DIR__ . '/config.php';
    define('CONFIG_LOADED', true);
    require __DIR__ . '/functions.php';

    initDirectories();

    logMsg('=== НАЧАЛО СБОРА НОВОСТЕЙ ===');
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

    echo "Источников: " . count($cfg['rss_feeds']) . "\n";
    echo "Базовый URL: " . ($baseUrl ?: 'CLI-режим') . "\n\n";

    foreach ($cfg['rss_feeds'] as $feed) {
        $feedUrl  = $feed['url'];
        $feedName = $feed['name'];
        $feedLang = $feed['lang'] ?? 'ru';

        echo "[{$feedName}] Загрузка RSS...\n";
        $xml = httpGet($feedUrl);

        if ($xml === false) {
            echo "  Не удалось загрузить\n";
            logMsg("RSS X: {$feedName}");
            continue;
        }

        $items = parseRSS($xml, $feedName, $feedLang);
        echo "  Записей: " . count($items) . "\n";
        logMsg("RSS: {$feedName} — " . count($items));

        foreach ($items as $item) {
            $hash = linkHash($item['link']);

            if ($cfg['dedup_enabled'] && articleExists($hash)) {
                continue;
            }

            echo "  -> " . mb_substr($item['title'], 0, 60) . "...\n";
            logMsg("  -> " . mb_substr($item['title'], 0, 50));

            $scraped  = scrapeArticle($item['link']);
            $fullText = $scraped['text'];

            if (mb_strlen($fullText) < $cfg['min_text_length']) {
                $fullText = $item['description'];
            }

            if (mb_strlen($fullText) < 100) {
                echo "    Пропущен (короткий текст)\n";
                continue;
            }

            // Загрузка изображения
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

            $slug = generateSlug($item['title']);

            $article = [
                'hash'        => $hash,
                'slug'        => $slug,
                'title'       => $item['title'],
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
                echo "    X Ошибка сохранения\n";
            }

            usleep(500000);
        }

        echo "\n";
    }

    cleanupOldArticles();
    setLastFetchTime();

    echo "=== ГОТОВО ===\n";
    echo "Новых: {$totalAdded}\n";
    echo "Всего в базе: " . getArticleCount() . "\n";
    echo "HTML-страниц: " . getStaticPageCount() . "\n";
    echo "Обновлено: " . getLastFetchTime() . "\n";

} catch (Throwable $e) {
    echo "\nОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
