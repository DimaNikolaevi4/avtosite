<?php
/**
 * Роботс — разрешаем индексацию всех страниц,
 * но запрещаем системные директории
 */

header('Content-Type: text/plain; charset=utf-8');
?>
User-agent: *
Allow: /
Disallow: /data/
Disallow: /cache/
Disallow: /images/
Disallow: /fetch.php

Sitemap: <?= e(getBaseUrl()) ?>/sitemap.php
