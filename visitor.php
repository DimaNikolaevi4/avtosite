<?php
/**
 * ============================================================
 *  Самонаполняющийся новостной сайт — Счётчик посетителей
 * ============================================================
 *
 *  Хранит статистику в файле data/visitors.json
 *  Считает уникальных посетителей по IP + дата
 */

$cfg = require __DIR__ . '/config.php';
define('CONFIG_LOADED', true);
require __DIR__ . '/functions.php';
initDirectories();

// Возвращаем JSON — используется через AJAX с главной
header('Content-Type: application/json; charset=utf-8');

$statsFile = $cfg['data_dir'] . '/visitors.json';

// Загружаем текущую статистику
if (file_exists($statsFile)) {
    $stats = json_decode(file_get_contents($statsFile), true) ?: [];
} else {
    $stats = [
        'total'    => 0,
        'today'    => 0,
        'days'     => [],
        'visitors' => [],
    ];
}

// Текущий день
$today = date('Y-m-d');

// Инициализируем день
if (!isset($stats['days'][$today])) {
    $stats['days'][$today] = 0;
}

// Инициализируем visitors
if (!isset($stats['visitors'])) {
    $stats['visitors'] = [];
}

// Определяем IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Проверяем, был ли этот IP сегодня
$visitorKey = $today . '_' . $ip;
if (!isset($stats['visitors'][$visitorKey])) {
    $stats['visitors'][$visitorKey] = [
        'ip'        => $ip,
        'timestamp' => time(),
        'user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    $stats['total'] = ($stats['total'] ?? 0) + 1;
    $stats['days'][$today] = ($stats['days'][$today] ?? 0) + 1;
}

// today = visitors today
$stats['today'] = $stats['days'][$today] ?? 0;

// Храним данные за последние 30 дней
$cutoff = strtotime('-30 days');
foreach ($stats['visitors'] as $key => $data) {
    if (($data['timestamp'] ?? 0) < $cutoff) {
        unset($stats['visitors'][$key]);
    }
}
foreach ($stats['days'] as $day => $count) {
    if (strtotime($day) < $cutoff) {
        unset($stats['days'][$day]);
    }
}

// Сохраняем
@file_put_contents($statsFile, json_encode($stats, JSON_UNESCAPED_UNICODE), LOCK_EX);

// Для отображения — отдаём только публичные данные
echo json_encode([
    'total' => $stats['total'],
    'today' => $stats['today'],
    'days'  => $stats['days'],
], JSON_UNESCAPED_UNICODE);
