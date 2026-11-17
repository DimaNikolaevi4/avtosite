<?php
/**
 * ============================================================
 *  Самонаполняющийся новостной сайт — Конфигурация
 * ============================================================
 */

$config = [
    // --- Название сайта ---
    'site_name'        => 'АвтоНовости',
    'site_description' => 'Автоматический агрегатор новостей',
    'site_url'         => '', // Оставьте пустым для автоопределения

    // --- Интервал обновления (минуты) ---
    'update_interval'  => 180, // 3 часа

    // --- Максимальное кол-во статей на главной ---
    'max_articles'     => 100,

    // --- Кол-во статей на одной странице ---
    'per_page'         => 15,

    // --- Папки (относительно корня сайта) ---
    'data_dir'         => __DIR__ . '/data',
    'images_dir'       => __DIR__ . '/images',
    'cache_dir'        => __DIR__ . '/cache',
    'news_dir'         => __DIR__ . '/news',   // Статические HTML-страницы

    // --- RSS-источники ---
    'rss_feeds' => [
        [
            'url'   => 'https://lenta.ru/rss',
            'name'  => 'Лента.ру',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://tass.ru/rss/v2.xml',
            'name'  => 'ТАСС',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://russian.rt.com/rss/',
            'name'  => 'RT на русском',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://www.vesti.ru/export/rss/vesti.xml',
            'name'  => 'Вести.ру',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://news.rambler.ru/rss/head/',
            'name'  => 'Рамблер Новости',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://www.fontanka.fi/xml/rss.xml',
            'name'  => 'Fontanka',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://life.ru/rss.xml',
            'name'  => 'Life.ru',
            'lang'  => 'ru',
        ],
        [
            'url'   => 'https://news.mail.ru/rss/',
            'name'  => 'Mail.ru Новости',
            'lang'  => 'ru',
        ],
    ],

    // --- Настройки HTTP-запросов ---
    'request_timeout'  => 15,
    'user_agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',

    // --- Логирование ---
    'log_file'         => __DIR__ . '/data/fetch.log',
    'log_enabled'      => true,

    // --- Защита от дубликатов ---
    'dedup_enabled'    => true,

    // --- Минимальная длина текста статьи (символов) ---
    'min_text_length'  => 200,

    // --- Запуск сборщика ---
    'update_key'      => '',    // ключ для запуска через браузер; задавать в config.local.php
    'allow_web_run'   => false, // true — разрешить запуск update.php/run.php по ключу из браузера
];

// Локальные переопределения (не коммитятся): config.local.php
if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

return $config;
