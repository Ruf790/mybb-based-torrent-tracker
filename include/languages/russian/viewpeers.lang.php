<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['viewpeers'] = array (
	// ── Page ──
	'page_title'        => 'Список пиров',
	'page_sub'          => 'Текущие подключения пиров к трекеру, новые сверху',
	'lbl_live'          => 'Онлайн',

	// ── KPI tiles ──
	'kpi_active'        => 'Активных пиров',
	'kpi_seeders'       => 'Сиды',
	'kpi_leechers'      => 'Личи',
	'kpi_users'         => 'Уникальных пользователей',
	'kpi_pages'         => 'Страниц: {1}',
	'kpi_pct_of'        => '{1}% от всех пиров',
	'kpi_connectable'   => 'Доступных пиров: {1}',

	// ── Toolbar ──
	'ph_search'         => 'Фильтр по странице: пользователь, раздача, IP или клиент',
	'aria_filter'       => 'Фильтр по статусу',
	'opt_all'           => 'Все',
	'opt_seed'          => 'Раздают',
	'opt_leech'         => 'Качают',
	'lbl_shown'         => 'Показано на странице: {1}',

	// ── Table headers ──
	'col_user'          => 'Пользователь',
	'col_torrent'       => 'Раздача',
	'col_address'       => 'Адрес',
	'col_traffic'       => 'Трафик',
	'col_client'        => 'Клиент',
	'col_connectable'   => 'Доступность',
	'col_status'        => 'Статус',
	'col_started'       => 'Старт',
	'col_activity'      => 'Активность',
	'col_offsets'       => 'Смещения',
	'col_to_go'         => 'Осталось',

	// ── Row cells ──
	'lbl_deleted_user'  => 'удалённый пользователь',
	'lbl_unknown_torrent' => 'Неизвестная раздача',
	'lbl_unknown_client'  => 'Неизвестно',
	'lbl_na'            => 'н/д',
	'lbl_port'          => 'порт {1}',
	'tip_complete_pct'  => 'Скачано {1}%',
	'tip_complete'      => 'Скачано полностью',
	'tip_copy_ip'       => 'Скопировать IP',

	// ── Badges ──
	'badge_yes'         => 'да',
	'badge_no'          => 'нет',
	'badge_seed'        => 'сид',
	'badge_leech'       => 'лич',

	// ── Relative time ──
	'ago_sec'           => '{1} сек назад',
	'ago_min'           => '{1} мин назад',
	'ago_hour'          => '{1} ч назад',
	'ago_day'           => '{1} дн. назад',

	// ── Empty states ──
	'msg_no_match'      => 'На этой странице нет пиров, подходящих под фильтр.',
	'msg_empty_title'   => 'Активных пиров нет',
	'msg_empty_text'    => 'Пиры появятся здесь, как только клиент отправит анонс на трекер.',

	// ── Legend ──
	'legend_fresh'      => 'анонс ≤ 30 мин',
	'legend_late'       => '≤ 60 мин',
	'legend_stale'      => 'давно не анонсировался',

	// ── JS ──
	'js_copied'         => 'Скопировано: {1}',
	'js_copy_failed'    => 'Не удалось скопировать в буфер обмена',
);
?>
