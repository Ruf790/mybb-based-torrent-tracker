<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['convert_innodb'] = array (
	// ── Page / header ──
	'page_title'        => 'Конвертация таблиц в InnoDB + utf8mb4',
	'sec_subtitle'      => 'Перевод старых таблиц на движок InnoDB и кодировку utf8mb4.',
	'badge_to_convert'  => 'В очереди: {1}',
	'badge_all_done'    => 'Всё в порядке',

	// ── KPI tiles ──
	'kpi_pending'       => 'Таблиц к конвертации',
	'kpi_engine'        => 'Нужна смена движка',
	'kpi_charset'       => 'Нужен utf8mb4',
	'kpi_rows'          => '≈ строк: {1}',

	// ── Warning (warn_text is output as-is, HTML allowed) ──
	'warn_title'        => 'Перед началом',
	'warn_text'         => 'На время конвертации каждая таблица блокируется. Большие таблицы могут обрабатываться долго &mdash; сначала сделайте бэкап и по возможности запускайте в часы минимальной нагрузки.',

	// ── Empty state ──
	'empty_title'       => 'Конвертировать нечего',
	'empty_text'        => 'Все таблицы уже на InnoDB и utf8mb4.',

	// ── Toolbar ──
	'toolbar_pending'   => 'Ожидают конвертации: {1}',

	// ── Table columns ──
	'col_table'         => 'Таблица',
	'col_engine'        => 'Движок',
	'col_collation'     => 'Сопоставление',
	'col_rows'          => 'Строк',
	'col_size'          => 'Размер',
	'col_status'        => 'Статус',

	// ── Labels ──
	'lbl_status_pending' => 'Ожидает',
	'aria_select_all'    => 'Выбрать все таблицы',
	'aria_select_table'  => 'Выбрать таблицу {1}',

	// ── Buttons ──
	'btn_start'         => 'Начать конвертацию',

	// ── AJAX responses ──
	'err_invalid_token' => 'Неверный ключ безопасности',
	'err_not_needed'    => 'Таблица «{1}» не требует конвертации',
	'err_alter_failed'  => 'Не удалось выполнить ALTER TABLE',
	'msg_converted_in'  => 'Готово за {1} с',

	// ── JS ──
	'js_btn_converting'    => 'Конвертация...',
	'js_status_converting' => 'Конвертация...',
	'js_network_error'     => 'Ошибка сети',
	'js_progress'          => 'Обработано {1} из {2}',
	'js_progress_failed'   => ' (ошибок: {1})',
	'js_done'              => 'Готово',
	'js_done_errors'       => 'Готово, но с ошибками',
);
?>
