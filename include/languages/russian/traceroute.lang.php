<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['traceroute'] = array (
	// ── Page ──
	'page_title'       => 'Трассировка',

	// ── Header ──
	'sec_title'        => 'Трассировка маршрута',
	'sec_subtitle'     => 'Путь пакетов до хоста по узлам, с выводом в реальном времени.',
	'badge_your_ip'    => 'Ваш IP: {1}',

	// ── Form ──
	'lbl_host'         => 'IP / хост',
	'lbl_unknown_ip'   => 'Неизвестно',
	'ph_host'          => 'например, 8.8.8.8 или example.com',
	'hint_host'        => 'Доменное имя или IPv4-адрес, не более 30 узлов.',
	'btn_start'        => 'Запустить трассировку',

	// ── Output ──
	'sec_output'       => 'Результат',
	'empty_text'       => 'Укажите хост и запустите трассировку — результат появится здесь.',

	// ── Errors (AJAX JSON) ──
	'err_invalid_host' => 'Некорректный хост',

	// ── JS ──
	'js_status_ready'   => 'Готово к запуску',
	'js_status_running' => 'Выполняется…',
	'js_status_done'    => 'Завершено',
);
?>
