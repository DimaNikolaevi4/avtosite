<?php
/**
 * ============================================================
 *  Самонаполняющийся новостной сайт — Сборщик новостей
 * ============================================================
 *
 *  Запуск:
 *    - Через cron:    php /путь/к/update.php
 *    - Через браузер: https://ваш-сайт.ru/update.php?key=update2025
 *
 *  Cron на Beget (каждые 3 часа):
 *    0 */3 * * * /usr/bin/php /home/u/аккаунт/public_html/update.php
 */

$isCli    = (php_sapi_name() === 'cli');
$hasKey   = (isset($_GET['key']) && $_GET['key'] === 'update2025');

if (!$isCli && !$hasKey) {
    http_response_code(403);
    die('Доступ запрещён');
}

@set_time_limit(300);

if (!$isCli) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}

try {
    $cfg = require __DIR__ . '/config.php';
    define('CONFIG_LOADED', true);
    require __DIR__ . '/functions.php';

    initDirectories();

    logMsg('=== НАЧАЛО СБОРА НОВОСТЕЙ ===');
    $totalAdded = 0;

    $baseUrl = '';
    if (!$isCli) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $baseUrl = $scheme . '://' . $host . $dir;
    }
    if (empty($baseUrl) && !empty($cfg['site_url'])) {
        $baseUrl = $cfg['site_url'];
    }

    foreach ($cfg['rss_feeds'] as $feed) {
        $feedUrl  = $feed['url'];
        $feedName = $feed['name'];
        $feedLang = $feed['lang'] ?? 'ru';

        logMsg("RSS: {$feedName}");
        $xml = httpGet($feedUrl);

        if ($xml === false) {
            logMsg("  X Не удалось загрузить: {$feedName}");
            continue;
        }

        $items = parseRSS($xml, $feedName, $feedLang);
        logMsg("  Записей: " . count($items));

        foreach ($items as $item) {
            $hash = linkHash($item['link']);

            if ($cfg['dedup_enabled'] && articleExists($hash)) {
                continue;
            }

            logMsg("  -> " . mb_substr($item['title'], 0, 50));

            $scraped  = scrapeArticle($item['link']);
            $fullText = $scraped['text'];

            if (mb_strlen($fullText) < $cfg['min_text_length']) {
                $fullText = $item['description'];
            }

            if (mb_strlen($fullText) < 100) {
                logMsg("    X Текст слишком короткий");
                continue;
            }

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
                    logMsg("    + Фото: " . $localImage);
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
                logMsg("    + Сохранено: " . $slug);
            } else {
                logMsg("    X Ошибка сохранения");
            }

            usleep(500000);
        }
    }

    cleanupOldArticles();
    setLastFetchTime();

    logMsg("=== ГОТОВО. Новых: {$totalAdded} | Всего: " . getArticleCount() . " | HTML: " . getStaticPageCount() . " ===");

    if (!$isCli) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Сбор завершён.\n";
        echo "Новых: {$totalAdded}\n";
        echo "В базе: " . getArticleCount() . "\n";
        echo "Страниц: " . getStaticPageCount() . "\n";
        echo "Обновлено: " . getLastFetchTime() . "\n";
    }

} catch (Throwable $e) {
    if (!$isCli) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
    }
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
