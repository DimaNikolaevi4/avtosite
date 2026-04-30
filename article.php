<?php
/**
 * ============================================================
 *  Самонаполняющийся новостной сайт — Страница статьи
 * ============================================================
 */

$cfg = require __DIR__ . '/config.php';
define('CONFIG_LOADED', true);
require __DIR__ . '/functions.php';
initDirectories();

$baseUrl = getBaseUrl();
$hash    = $_GET['h'] ?? '';

if (empty($hash)) {
    header('Location: index.php');
    exit;
}

$article = loadArticle($hash);

if ($article === null) {
    http_response_code(404);
    $pageTitle = 'Статья не найдена';
    $pageContent = '<p>Запрашиваемая статья не найдена. Возможно, она была удалена.</p>
                    <a href="index.php" class="btn btn-primary">&larr; На главную</a>';
    $showMeta = false;
} else {
    $pageTitle   = $article['title'];
    $pageContent = $article['text'];
    $showMeta    = true;

    // Форматируем текст: разбиваем на абзацы
    $paragraphs = array_filter(array_map('trim', explode("\n", $pageContent)));
    $pageContent = '';
    foreach ($paragraphs as $p) {
        if (mb_strlen($p) > 20) {
            $pageContent .= '<p>' . e($p) . '</p>' . "\n";
        }
    }
}

// Предыдущая / следующая статья
$allArticles = loadAllArticles();
$currentIndex = null;
foreach ($allArticles as $i => $a) {
    if (($a['hash'] ?? '') === $hash) {
        $currentIndex = $i;
        break;
    }
}
$prevArticle = ($currentIndex !== null && isset($allArticles[$currentIndex + 1])) ? $allArticles[$currentIndex + 1] : null;
$nextArticle = ($currentIndex !== null && isset($allArticles[$currentIndex - 1])) ? $allArticles[$currentIndex - 1] : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($cfg['site_name']) ?></title>
    <meta name="description" content="<?= e(truncateText($article['description'] ?? '', 160)) ?>">
    <link rel="stylesheet" href="style.css">
    <?php if ($showMeta && !empty($article['local_image'])): ?>
        <meta property="og:image" content="<?= e($baseUrl) ?>/images/<?= e($article['local_image']) ?>">
        <meta property="og:title" content="<?= e($article['title']) ?>">
        <meta property="og:description" content="<?= e(truncateText($article['description'] ?? '', 200)) ?>">
        <meta property="og:type" content="article">
    <?php endif; ?>
</head>
<body>

    <!-- ===== Шапка ===== -->
    <header class="site-header">
        <div class="container header-inner">
            <a href="<?= e($baseUrl) ?>" class="logo"><?= e($cfg['site_name']) ?></a>
            <p class="tagline"><?= e($cfg['site_description']) ?></p>
        </div>
    </header>

    <!-- ===== Контент статьи ===== -->
    <main class="container article-page">

        <?php if (!$showMeta): ?>
            <div class="article-not-found">
                <?= $pageContent ?>
            </div>
        <?php else: ?>

            <!-- Навигация назад -->
            <a href="index.php" class="back-link">&larr; Назад к ленте</a>

            <!-- Мета-информация -->
            <div class="article-meta">
                <span class="article-source"><?= e($article['source']) ?></span>
                <span class="article-date"><?= formatDate($article['pub_date']) ?></span>
            </div>

            <!-- Заголовок -->
            <h1 class="article-title"><?= e($article['title']) ?></h1>

            <!-- Изображение -->
            <?php if (!empty($article['local_image'])): ?>
                <figure class="article-hero-image">
                    <img
                        src="images/<?= e($article['local_image']) ?>"
                        alt="<?= e($article['title']) ?>"
                    >
                    <figcaption>Источник: <?= e($article['source']) ?></figcaption>
                </figure>
            <?php endif; ?>

            <!-- Текст статьи -->
            <div class="article-body">
                <?= $pageContent ?>
            </div>

            <!-- Ссылка на оригинал -->
            <div class="article-original">
                <a href="<?= e($article['link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                    Перейти к оригиналу &nearr;
                </a>
            </div>

            <!-- Навигация по статьям -->
            <nav class="article-nav">
                <?php if ($prevArticle): ?>
                    <a href="article.php?h=<?= e($prevArticle['hash']) ?>" class="article-nav-link prev">
                        <span class="nav-label">&larr; Предыдущая</span>
                        <span class="nav-title"><?= e(truncateText($prevArticle['title'], 60)) ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($nextArticle): ?>
                    <a href="article.php?h=<?= e($nextArticle['hash']) ?>" class="article-nav-link next">
                        <span class="nav-label">Следующая &rarr;</span>
                        <span class="nav-title"><?= e(truncateText($nextArticle['title'], 60)) ?></span>
                    </a>
                <?php endif; ?>
            </nav>

        <?php endif; ?>

    </main>

    <!-- ===== Подвал ===== -->
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e($cfg['site_name']) ?>. Автоматический агрегатор новостей.</p>
        </div>
    </footer>

</body>
</html>
