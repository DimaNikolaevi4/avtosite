<?php
/**
 * ============================================================
 *  Самонаполняющийся новостной сайт — Ядро функций
 * ============================================================
 */

if (!defined('CONFIG_LOADED')) {
    die('Прямой вызов запрещён');
}

// ============================================================
//  Инициализация директорий
// ============================================================
function initDirectories(): void
{
    global $cfg;
    foreach ([$cfg['data_dir'], $cfg['images_dir'], $cfg['cache_dir'], $cfg['news_dir']] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// ============================================================
//  Логирование
// ============================================================
function logMsg(string $message): void
{
    global $cfg;
    if (!$cfg['log_enabled']) return;

    $line = date('Y-m-d H:i:s') . '  ' . $message . PHP_EOL;
    @file_put_contents($cfg['log_file'], $line, FILE_APPEND | LOCK_EX);
}

// ============================================================
//  HTTP-запрос с cURL
// ============================================================
function httpGet(string $url, int $timeout = 0): string|false
{
    global $cfg;
    $timeout = $timeout ?: $cfg['request_timeout'];

    if (!function_exists('curl_init')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout'   => $timeout,
                'header'    => "User-Agent: {$cfg['user_agent']}\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        return $data !== false ? $data : false;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => $cfg['user_agent'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_HEADER         => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 400) {
        return $response;
    }
    return false;
}

// ============================================================
//  Загрузка изображения на диск
// ============================================================
function downloadImage(string $url, string $savePath): bool
{
    $data = httpGet($url, 20);
    if ($data === false) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->buffer($data);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    if (!in_array($mime, $allowed)) {
        return false;
    }

    $extMap = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $ext     = $extMap[$mime] ?? 'jpg';
    $savePath = preg_replace('/\.[^.]+$/', '.' . $ext, $savePath);

    return (bool) file_put_contents($savePath, $data);
}

// ============================================================
//  Парсинг RSS-ленты
// ============================================================
function parseRSS(string $xml, string $sourceName, string $lang = 'ru'): array
{
    if (empty($xml)) return [];

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadXML($xml);
    libxml_clear_errors();

    $articles = [];
    $items = $doc->getElementsByTagName('item');
    if ($items->length === 0) {
        $items = $doc->getElementsByTagName('entry');
    }

    foreach ($items as $item) {
        $node    = $item instanceof DOMElement ? $item : $item;
        $title   = getNodeValue($node, 'title') ?: getNodeValue($node, 'name');
        $link    = getNodeAttr($node, 'link', 'href') ?: getNodeValue($node, 'link');
        $desc    = getNodeValue($node, 'description') ?: getNodeValue($node, 'summary') ?: getNodeValue($node, 'content');
        $pubDate = getNodeValue($node, 'pubDate') ?: getNodeValue($node, 'published') ?: getNodeValue($node, 'updated');

        if (empty($title) || empty($link)) continue;

        $articles[] = [
            'title'      => trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'link'       => trim($link),
            'description'=> cleanHTML($desc),
            'pub_date'   => $pubDate,
            'source'     => $sourceName,
            'lang'       => $lang,
        ];
    }

    return $articles;
}

function getNodeValue(DOMElement $parent, string $tagName): string
{
    $list = $parent->getElementsByTagName($tagName);
    return $list->length > 0 ? $list->item(0)->textContent : '';
}

function getNodeAttr(DOMElement $parent, string $tagName, string $attr): string
{
    $list = $parent->getElementsByTagName($tagName);
    if ($list->length > 0) {
        $el = $list->item(0);
        return $el instanceof DOMElement ? $el->getAttribute($attr) : '';
    }
    return '';
}

// ============================================================
//  Очистка HTML от тегов и лишних пробелов
// ============================================================
function cleanHTML(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// ============================================================
//  Скрапинг полного текста статьи по URL
// ============================================================
function scrapeArticle(string $url): array
{
    global $cfg;
    $html = httpGet($url);
    if ($html === false) return ['text' => '', 'image' => ''];

    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    @$doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    $imageUrl = '';
    $ogImages = $xpath->query('//meta[@property="og:image"]/@content');
    if ($ogImages->length > 0) {
        $imageUrl = trim($ogImages->item(0)->textContent);
    }
    if (empty($imageUrl)) {
        $twitterImages = $xpath->query('//meta[@name="twitter:image"]/@content');
        if ($twitterImages->length > 0) {
            $imageUrl = trim($twitterImages->item(0)->textContent);
        }
    }
    if (empty($imageUrl)) {
        $twitterImages = $xpath->query('//meta[@name="twitter:image:src"]/@content');
        if ($twitterImages->length > 0) {
            $imageUrl = trim($twitterImages->item(0)->textContent);
        }
    }

    $text = '';
    $contentSelectors = [
        '//article',
        '//div[contains(@class,"article")]',
        '//div[contains(@class,"content")]',
        '//div[contains(@class,"post")]',
        '//div[contains(@class,"text")]',
        '//div[contains(@class,"story")]',
        '//div[contains(@class,"news")]',
        '//div[contains(@class,"entry")]',
        '//main',
        '//div[@role="main"]',
        '//div[contains(@class,"main")]',
        '//div[contains(@class,"body")]',
        '//div[contains(@itemtype,"Article")]',
    ];

    foreach ($contentSelectors as $sel) {
        $nodes = $xpath->query($sel);
        if ($nodes->length > 0) {
            $text = extractText($xpath, $nodes->item(0));
            if (mb_strlen($text) > $cfg['min_text_length']) {
                break;
            }
            $text = '';
        }
    }

    if (empty($text)) {
        $paragraphs = $xpath->query('//body//p');
        $parts = [];
        foreach ($paragraphs as $p) {
            $t = trim($p->textContent);
            if (mb_strlen($t) > 40) {
                $parts[] = $t;
            }
        }
        $text = implode("\n\n", $parts);
    }

    if (!empty($imageUrl) && !str_starts_with($imageUrl, 'http')) {
        $parsed   = parse_url($url);
        $baseUrl  = $parsed['scheme'] . '://' . $parsed['host'];
        $imageUrl = rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/');
    }

    return [
        'text'  => trim($text),
        'image' => $imageUrl,
    ];
}

function extractText(DOMXPath $xpath, DOMNode $container): string
{
    $paragraphs = $xpath->query('.//p', $container);
    $parts = [];
    foreach ($paragraphs as $p) {
        $t = trim($p->textContent);
        if (mb_strlen($t) > 30) {
            $parts[] = $t;
        }
    }
    return implode("\n\n", $parts);
}

// ============================================================
//  Slug и транслитерация
// ============================================================
function generateSlug(string $title): string
{
    $slug = transliterate($title);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');
    $slug = mb_strtolower($slug);
    return $slug ?: 'article-' . time();
}

function transliterate(string $text): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
        'ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu',
        'я'=>'ya',
        'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh',
        'З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O',
        'П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'Ts',
        'Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu',
        'Я'=>'Ya',
    ];
    return strtr($text, $map);
}

// ============================================================
//  Хеширование и дедупликация
// ============================================================
function linkHash(string $url): string
{
    return md5(strtolower(preg_replace('/^https?:\/\/(www\.)?/', '', $url)));
}

function articleExists(string $hash): bool
{
    global $cfg;
    return file_exists($cfg['data_dir'] . '/' . $hash . '.json');
}

// ============================================================
//  Сохранение статьи (JSON)
// ============================================================
function saveArticle(array $article): bool
{
    global $cfg;
    $hash = $article['hash'];
    $path = $cfg['data_dir'] . '/' . $hash . '.json';

    return (bool) file_put_contents(
        $path,
        json_encode($article, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

// ============================================================
//  Генерация статической HTML-страницы для статьи
// ============================================================
function generateStaticHTML(array $article, string $baseUrl): bool
{
    global $cfg;

    $slug     = $article['slug'] ?? 'article';
    $filename = $slug . '.html';
    $filepath = $cfg['news_dir'] . '/' . $filename;

    // Если файл уже есть — не перезаписываем (сохранён навсегда)
    if (file_exists($filepath)) {
        return true;
    }

    // Форматируем параграфы
    $paragraphsHTML = '';
    $paragraphs = array_filter(array_map('trim', explode("\n", $article['text'] ?? '')));
    foreach ($paragraphs as $p) {
        if (mb_strlen($p) > 20) {
            $paragraphsHTML .= '        <p>' . e($p) . "</p>\n";
        }
    }

    // Определяем изображение
    $heroHTML = '';
    if (!empty($article['local_image'])) {
        $heroHTML = <<<IMG
        <figure class="article-hero">
            <img src="../images/{$article['local_image']}" alt="{$article['title']}" loading="lazy">
        </figure>
IMG;
    }

    // Предыдущая / следующая
    $navHTML = '';
    $allArticles = loadAllArticles();
    $currentIndex = null;
    foreach ($allArticles as $i => $a) {
        if (($a['hash'] ?? '') === ($article['hash'] ?? '')) {
            $currentIndex = $i;
            break;
        }
    }

    $prevSlug = ($currentIndex !== null && isset($allArticles[$currentIndex + 1])) ? ($allArticles[$currentIndex + 1]['slug'] ?? '') : '';
    $nextSlug = ($currentIndex !== null && isset($allArticles[$currentIndex - 1])) ? ($allArticles[$currentIndex - 1]['slug'] ?? '') : '';

    if ($prevSlug) {
        $navHTML .= '        <a href="' . e($prevSlug) . '.html" class="nav-arrow nav-prev" title="Предыдущая">&larr;</a>' . "\n";
    }
    if ($nextSlug) {
        $navHTML .= '        <a href="' . e($nextSlug) . '.html" class="nav-arrow nav-next" title="Следующая">&rarr;</a>' . "\n";
    }

    $dateFormatted = formatDate($article['pub_date'] ?? $article['created_at'] ?? '');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$article['title']} &mdash; {$cfg['site_name']}</title>
    <meta name="description" content="{$article['description']}">
    <link rel="stylesheet" href="../style.css">
</head>
<body>

    <header class="site-header">
        <div class="container header-inner">
            <a href="../index.php" class="logo-back">&larr; {$cfg['site_name']}</a>
        </div>
    </header>

    <main class="container article-page">
        <div class="article-meta">
            <span class="article-source">{$article['source']}</span>
            <span class="article-date">{$dateFormatted}</span>
        </div>

        <h1 class="article-title">{$article['title']}</h1>

{$heroHTML}

        <div class="article-body">
{$paragraphsHTML}
        </div>

        <div class="article-original">
            <a href="{$article['link']}" target="_blank" rel="noopener noreferrer">Перейти к оригиналу &nearr;</a>
        </div>

        <nav class="article-nav">
{$navHTML}
        </nav>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; {$cfg['site_name']}</p>
        </div>
    </footer>

    <script>
        // Счётчик просмотров статьи (локальный, через localStorage)
        (function() {
            var key = 'viewed_{$article['hash']}';
            var countEl = document.getElementById('view-count');
            if (localStorage.getItem(key)) return;
            localStorage.setItem(key, '1');
            var views = parseInt(localStorage.getItem('views_' + key) || '0') + 1;
            localStorage.setItem('views_' + key, views);
        })();
    </script>

</body>
</html>
HTML;

    $result = (bool) file_put_contents($filepath, $html, LOCK_EX);
    if ($result) {
        logMsg("Статическая HTML: {$filename}");
    }
    return $result;
}

// ============================================================
//  Загрузка всех статей
// ============================================================
function loadAllArticles(): array
{
    global $cfg;
    $articles = [];
    $files    = glob($cfg['data_dir'] . '/*.json');

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $articles[] = $data;
        }
    }

    usort($articles, function ($a, $b) {
        $ta = strtotime($a['pub_date'] ?? '0');
        $tb = strtotime($b['pub_date'] ?? '0');
        return $tb - $ta;
    });

    return $articles;
}

function loadArticle(string $hash): ?array
{
    global $cfg;
    $path = $cfg['data_dir'] . '/' . $hash . '.json';
    if (!file_exists($path)) return null;
    return json_decode(file_get_contents($path), true);
}

// ============================================================
//  Подсчёт всех файлов на хостинге
// ============================================================
function countAllFiles(): int
{
    global $cfg;
    $count = 0;

    // Считаем файлы в рабочих папках
    $dirs = [$cfg['data_dir'], $cfg['images_dir'], $cfg['news_dir'], $cfg['cache_dir'], __DIR__];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        $count += iterator_count($files);
    }

    return $count;
}

// ============================================================
//  Очистка при приближении к лимиту файлов на хостинге
//  Удаляет самые старые статьи (JSON + фото + HTML)
//  пока не останется ниже safe_limit файлов
// ============================================================
function cleanupOldArticles(): void
{
    global $cfg;

    $maxFilesOnHosting = 25000;
    $safeLimit        = 24000;

    $currentFiles = countAllFiles();
    logMsg("Файлов на хостинге: {$currentFiles} / {$maxFilesOnHosting}");

    // Если далеко от лимита — просто очищаем JSON сверх max_articles
    $articles = loadAllArticles();
    $jsonCount = count($articles);

    if ($currentFiles < $safeLimit && $jsonCount <= $cfg['max_articles']) {
        return;
    }

    // Сортируем по дате (старые внизу) — loadAllArticles уже сортирует новые вверх
    // Значит старые в конце массива
    $deletedJson = 0;
    $deletedImg  = 0;
    $deletedHtml = 0;

    // 1) Удаляем лишние JSON (сверх max_articles)
    if ($jsonCount > $cfg['max_articles']) {
        $toDelete = array_slice($articles, $cfg['max_articles']);
        foreach ($toDelete as $a) {
            $hash = $a['hash'] ?? '';
            if (empty($hash)) continue;
            if (@unlink($cfg['data_dir'] . '/' . $hash . '.json')) {
                $deletedJson++;
            }
        }
    }

    // 2) Если файлов всё ещё много — начинаем удалять самые старые полностью
    $currentFiles = countAllFiles();
    if ($currentFiles >= $safeLimit) {
        // Перечитываем после удаления JSON
        $articles = loadAllArticles();
        $targetFree = $currentFiles - ($safeLimit - 1000); // освободить с запасом
        $freed = 0;

        foreach (array_reverse($articles) as $a) {
            if ($freed >= $targetFree) break;

            $hash = $a['hash'] ?? '';
            if (empty($hash)) continue;

            // Удаляем JSON
            $jsonPath = $cfg['data_dir'] . '/' . $hash . '.json';
            if (file_exists($jsonPath)) {
                @unlink($jsonPath);
                $deletedJson++;
                $freed++;
            }

            // Удаляем фото
            if (!empty($a['local_image'])) {
                $imgFiles = glob($cfg['images_dir'] . '/' . $hash . '.*');
                foreach ($imgFiles as $imgFile) {
                    if (@unlink($imgFile)) {
                        $deletedImg++;
                        $freed++;
                    }
                }
            }

            // Удаляем HTML
            $slug = $a['slug'] ?? '';
            if (!empty($slug)) {
                $htmlPath = $cfg['news_dir'] . '/' . $slug . '.html';
                if (file_exists($htmlPath)) {
                    @unlink($htmlPath);
                    $deletedHtml++;
                    $freed++;
                }
            }
        }
    }

    $afterFiles = countAllFiles();
    logMsg("Очистка: JSON={$deletedJson}, фото={$deletedImg}, HTML={$deletedHtml}");
    logMsg("Файлов после очистки: {$afterFiles} / {$maxFilesOnHosting}");
}

// ============================================================
//  Утилиты
// ============================================================
function truncateText(string $text, int $length = 180): string
{
    if (mb_strlen($text) <= $length) return $text;
    $truncated = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    return $truncated . '…';
}

function formatDate(string $dateStr): string
{
    $ts = strtotime($dateStr);
    if ($ts === false) return $dateStr;

    $now  = time();
    $diff = $now - $ts;

    if ($diff < 60)      return 'только что';
    if ($diff < 3600)    return floor($diff / 60) . ' мин. назад';
    if ($diff < 86400)   return floor($diff / 3600) . ' ч. назад';
    if ($diff < 604800)  return floor($diff / 86400) . ' дн. назад';

    $months = [
        'января','февраля','марта','апреля','мая','июня',
        'июля','августа','сентября','октября','ноября','декабря',
    ];

    $d = getdate($ts);
    return $d['mday'] . ' ' . $months[$d['mon'] - 1] . ' ' . $d['year'];
}

function getLastFetchTime(): string
{
    global $cfg;
    $path = $cfg['data_dir'] . '/last_fetch.txt';
    if (!file_exists($path)) return 'никогда';
    return file_get_contents($path);
}

function setLastFetchTime(): void
{
    global $cfg;
    $path = $cfg['data_dir'] . '/last_fetch.txt';
    file_put_contents($path, date('d.m.Y H:i:s'), LOCK_EX);
}

function getArticleCount(): int
{
    global $cfg;
    return count(glob($cfg['data_dir'] . '/*.json'));
}

function getStaticPageCount(): int
{
    global $cfg;
    return count(glob($cfg['news_dir'] . '/*.html'));
}

function getBaseUrl(): string
{
    global $cfg;
    if (!empty($cfg['site_url'])) return rtrim($cfg['site_url'], '/');

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    return $scheme . '://' . $host . $dir;
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ============================================================
//  Автозапуск сборщика при посещении сайта (poor man's cron)
//  Запускается в фоне, если прошло больше update_interval минут
// ============================================================
function autoRunIfNeeded(): void
{
    global $cfg;

    $lockFile = $cfg['data_dir'] . '/autocron.lock';
    $lastFile = $cfg['data_dir'] . '/last_fetch_ts.txt';

    // Если скрипт уже работает — не запускаем повторно
    if (file_exists($lockFile)) {
        // Блокировка старше 5 минут = завис, снимаем
        if (filemtime($lockFile) < time() - 300) {
            @unlink($lockFile);
        } else {
            return; // Уже работает
        }
    }

    // Проверяем сколько прошло времени
    if (file_exists($lastFile)) {
        $lastTs = (int) file_get_contents($lastFile);
        $elapsed = time() - $lastTs;
        if ($elapsed < $cfg['update_interval'] * 60) {
            return; // Слишком рано
        }
    }

    // Запускаем в фоне через curl к самому себе (работает на Beget)
    $scriptUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . dirname($_SERVER['SCRIPT_NAME'])
        . '/test_fetch.php';

    // Создаём lock-файл и запускаем
    @file_put_contents($lockFile, time());
    @file_put_contents($lastFile, time(), LOCK_EX);

    // Вариант 1: через exec (если доступен)
    $phpBin = '/usr/bin/php';
    $scriptPath = realpath(__DIR__ . '/test_fetch.php');

    if (file_exists($phpBin) && file_exists($scriptPath)) {
        // Nohup или просто exec в фоне
        @exec("{$phpBin} {$scriptPath} > /dev/null 2>&1 &");
    }

    // Fallback: через cURL асинхронно
    if (function_exists('curl_init')) {
        $ch = curl_init($scriptUrl);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT        => 1,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_RETURNTRANSFER => false,
        ]);
        @curl_exec($ch);
        curl_close($ch);
    }

    // Убираем lock через 5 секунд (достаточно чтобы процесс стартанул)
    register_shutdown_function(function() use ($lockFile) {
        @unlink($lockFile);
    });
}
