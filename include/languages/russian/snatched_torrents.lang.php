<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['snatched_torrents'] = array (
	// ── Page / header ──
	'page_title'           => 'Все скачивания торрентов',
	'sec_title'            => 'Скачивания торрентов',
	'sec_subtitle'         => 'Кто что скачал, сколько отдал и принял и остаётся ли на раздаче.',
	'badge_filtered'       => 'С фильтром',

	// ── KPI tiles ──
	'kpi_total'            => 'Всего скачиваний',
	'kpi_completed'        => 'Докачано',
	'kpi_seeding'          => 'Сейчас сидируют',
	'kpi_downloaded'       => 'скачано {1}', // {1} = size

	// ── Search form ──
	'lbl_username'         => 'Пользователь',
	'lbl_user_id'          => 'ID пользователя',
	'lbl_torrent_name'     => 'Название торрента',
	'lbl_torrent_id'       => 'ID торрента',
	'ph_username'          => 'Ник...',
	'ph_user_id'           => 'ID пользователя...',
	'ph_torrent_name'      => 'Название раздачи...',
	'ph_torrent_id'        => 'ID торрента...',
	'btn_search'           => 'Найти',
	'btn_reset'            => 'Сбросить',

	// ── Filter chips ──
	'lbl_active_filters'   => 'Активные фильтры:',
	'tip_remove_filter'    => 'Убрать фильтр',
	'chip_user'            => 'Пользователь',
	'chip_user_id'         => 'ID пользователя',
	'chip_torrent'         => 'Торрент',
	'chip_torrent_id'      => 'ID торрента',

	// ── Table columns ──
	'col_user'             => 'Пользователь',
	'col_torrent'          => 'Торрент',
	'col_uploaded'         => 'Отдано',
	'col_downloaded'       => 'Скачано',
	'col_ratio'            => 'Рейтинг',
	'col_started'          => 'Начал',
	'col_completed'        => 'Докачал',
	'col_seeding'          => 'Сидирует',
	'col_progress'         => 'Прогресс',

	// ── Table cells / statuses ──
	'txt_deleted_user'     => 'Удалённый пользователь',
	'txt_deleted_torrent'  => 'Торрент удалён',
	'txt_na'               => 'н/д',
	'st_in_progress'       => 'Качает',
	'st_not_started'       => 'Не начато',
	'opt_yes'              => 'Да',
	'opt_no'               => 'Нет',

	// ── Popovers (plain text, escaped) ──
	'pop_progress_title'   => 'Прогресс скачивания',
	'pop_completed'        => 'Скачано полностью',
	'pop_left'             => '{1}% — осталось {2}', // {1} = percent, {2} = size left

	// ── Pagination (HTML, output as is) ──
	'txt_showing'          => 'Показаны <b>{1}–{2}</b> из <b>{3}</b>',
	'txt_page_of'          => 'Страница <b>{1}</b> из <b>{2}</b>',

	// ── Empty state ──
	'empty_filtered_title' => 'Ничего не найдено',
	'empty_filtered_text'  => 'Нет скачиваний, подходящих под условия поиска.',
	'btn_reset_filters'    => 'Сбросить фильтры',
	'empty_title'          => 'Скачиваний пока нет',
	'empty_text'           => 'В базе пока нет ни одного скачивания.',
);
?>
