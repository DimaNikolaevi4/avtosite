<?php
/**
 * ============================================================
 *  Самонаполняющийся новостной сайт — Главная страница
 * ============================================================
 */

$cfg = require __DIR__ . '/config.php';
define('CONFIG_LOADED', true);
require __DIR__ . '/functions.php';
initDirectories();

// Автозапуск сборщика при посещении (каждые 3 часа)
autoRunIfNeeded();

$baseUrl       = getBaseUrl();
$articles      = loadAllArticles();
$totalArticles = count($articles);
$lastFetch     = getLastFetchTime();
$staticCount   = getStaticPageCount();

// Пагинация
$page      = max(1, intval($_GET['page'] ?? 1));
$per_page  = $cfg['per_page'];
$totalPages = max(1, ceil($totalArticles / $per_page));
$page      = min($page, $totalPages);
$offset    = ($page - 1) * $per_page;
$pageArticles = array_slice($articles, $offset, $per_page);

// Источники
$sources = [];
foreach ($articles as $a) {
    $src = $a['source'] ?? 'Другое';
    $sources[$src] = ($sources[$src] ?? 0) + 1;
}
arsort($sources);

// Текущий час для приветствия
$hour = (int) date('H');
if ($hour >= 5 && $hour < 12)      $greeting = 'Доброе утро';
elseif ($hour >= 12 && $hour < 18) $greeting = 'Добрый день';
elseif ($hour >= 18 && $hour < 23) $greeting = 'Добрый вечер';
else                               $greeting = 'Доброй ночи';

// Формируем дату заголовка
$todayFormatted = date('d') . ' ' .
    ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'][(int)date('m')-1] .
    ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($cfg['site_name']) ?> &mdash; <?= e($cfg['site_description']) ?></title>
    <meta name="description" content="<?= e($cfg['site_description']) ?>">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- ===== Фоновая анимация ===== -->
    <div class="bg-glow"></div>

    <!-- ===== Шапка ===== -->
    <header class="header">
        <div class="container header-grid">
            <div class="header-left">
                <a href="<?= e($baseUrl) ?>" class="logo">
                    <span class="logo-icon">&#9889;</span>
                    <span class="logo-text"><?= e($cfg['site_name']) ?></span>
                </a>
                <span class="header-date"><?= $todayFormatted ?></span>
            </div>
            <div class="header-center">
                <h1 class="header-greeting"><?= $greeting ?>!</h1>
                <p class="header-subtitle"><?= e($cfg['site_description']) ?></p>
            </div>
            <div class="header-right">
                <div class="stats-panel">
                    <div class="stat-box">
                        <div class="stat-value" id="stat-articles"><?= $totalArticles ?></div>
                        <div class="stat-label">статей</div>
                    </div>
                    <div class="stat-box stat-box-accent">
                        <div class="stat-value" id="stat-visitors">&mdash;</div>
                        <div class="stat-label">посетителей</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" id="stat-pages"><?= $staticCount ?></div>
                        <div class="stat-label">страниц</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ===== Полоса с обновлением ===== -->
    <div class="update-bar">
        <div class="container update-bar-inner">
            <span class="update-dot"></span>
            <span>Обновлено: <strong><?= e($lastFetch) ?></strong></span>
            <span class="update-bar-spacer"></span>
            <a href="test_fetch.php" class="update-btn">&#8635; Обновить сейчас</a>
        </div>
    </div>

    <!-- ===== Контент ===== -->
    <main class="container main">

        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <div class="empty-icon-wrap">
                    <span class="empty-icon">&#128240;</span>
                    <div class="empty-pulse"></div>
                </div>
                <h2>Новостей пока нет</h2>
                <p>Сайт создан и готов к работе. Дождитесь первого запуска сборщика.</p>
                <a href="test_fetch.php" class="btn-glow">
                    <span>&#9889;</span> Запустить сбор новостей
                </a>
                <div class="empty-hint">
                    Авто-обновление каждые 3 часа при посещении сайта.
                    Ручной запуск: <code>test_fetch.php</code>
                </div>
            </div>
        <?php else: ?>

            <!-- Фильтры / навигация -->
            <div class="filters-bar">
                <div class="filters-left">
                    <span class="filter-active">Все новости</span>
                </div>
                <div class="filters-right">
                    <?php foreach (array_slice($sources, 0, 5, true) as $name => $count): ?>
                        <span class="filter-chip"><?= e($name) ?> <small><?= $count ?></small></span>
                    <?php endforeach; ?>
                    <?php if (count($sources) > 5): ?>
                        <span class="filter-chip filter-chip-more">+<?= count($sources) - 5 ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Сетка статей -->
            <div class="news-grid">

                <?php foreach ($pageArticles as $i => $article):
                    $isFeatured = ($i === 0 && $page === 1);
                    $hasImage = !empty($article['local_image']);
                    $articleUrl = 'news/' . e($article['slug']) . '.html';
                ?>
                <a href="<?= $articleUrl ?>" class="news-card <?= $isFeatured ? 'news-card-featured' : '' ?> <?= !$hasImage ? 'news-card-noimg' : '' ?>">
                    <div class="card-img-wrap">
                        <?php if ($hasImage): ?>
                            <img src="images/<?= e($article['local_image']) ?>" alt="<?= e($article['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="card-img-placeholder">
                                <span>&#128240;</span>
                            </div>
                        <?php endif; ?>
                        <div class="card-overlay">
                            <span class="card-source-badge"><?= e($article['source']) ?></span>
                            <span class="card-time-badge"><?= formatDate($article['pub_date']) ?></span>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-heading"><?= e($article['title']) ?></h3>
                        <p class="card-desc"><?= e($article['description']) ?></p>
                        <div class="card-footer">
                            <span class="card-read">Читать &rarr;</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>

            </div>

            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
            <nav class="pager">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pager-btn pager-prev">
                        <span>&larr;</span> Назад
                    </a>
                <?php endif; ?>

                <div class="pager-pages">
                    <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        if ($start > 1) echo '<span class="pager-dots">...</span>';
                        for ($p = $start; $p <= $end; $p++):
                            $active = ($p === $page) ? ' pager-btn-active' : '';
                    ?>
                        <a href="?page=<?= $p ?>" class="pager-btn <?= $active ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages) echo '<span class="pager-dots">...</span>'; ?>
                </div>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="pager-btn pager-next">
                        Далее <span>&rarr;</span>
                    </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- ===== Блок источников ===== -->
    <?php if (!empty($sources)): ?>
    <section class="sources-section">
        <div class="container">
            <h3 class="sources-heading">Источники</h3>
            <div class="sources-grid">
                <?php foreach ($sources as $name => $count): ?>
                <div class="source-badge">
                    <span class="source-name"><?= e($name) ?></span>
                    <span class="source-count"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ===== Подвал ===== -->
    <footer class="footer">
        <div class="container footer-inner">
            <p class="footer-brand">&copy; <?= date('Y') ?> <?= e($cfg['site_name']) ?></p>
            <p class="footer-note">Автоматический агрегатор новостей &bull; <?= count($sources) ?> источников &bull; <?= $staticCount ?> постоянных страниц</p>
        </div>
    </footer>

    <!-- ===== Скрипты ===== -->
    <script>
    // Загрузка статистики посетителей
    (function() {
        fetch('visitor.php')
            .then(r => r.json())
            .then(data => {
                if (data && data.total !== undefined) {
                    document.getElementById('stat-visitors').textContent = data.total;
                }
            })
            .catch(() => {});
    })();
    </script>

</body>
</html>
