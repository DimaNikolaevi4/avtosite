<?php
/**
 * Генератор XML-карты сайта (Sitemap)
 */

$cfg = require __DIR__ . '/config.php';
define('CONFIG_LOADED', true);
require __DIR__ . '/functions.php';
initDirectories();

$baseUrl = getBaseUrl();
$articles = loadAllArticles();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= e($baseUrl) ?>/</loc>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>
    <?php foreach ($articles as $article): ?>
    <url>
        <loc><?= e($baseUrl) ?>/article.php?h=<?= e($article['hash']) ?></loc>
        <lastmod><?= e(date('Y-m-d', strtotime($article['pub_date'] ?? $article['created_at'] ?? 'now'))) ?></lastmod>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
</urlset>
