<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_vip'] = array (
	// ── Page ──
	'page_title'          => 'Управление VIP-аккаунтами (всего найдено: {1})',
	'sec_title'           => 'Управление VIP-аккаунтами',
	'sec_subtitle'        => 'Продление VIP, начисление бонусов и инвайтов или снятие VIP у выбранных пользователей.',
	'tip_version'         => 'Версия модуля',

	// ── KPI tiles ──
	'kpi_total'           => 'VIP-аккаунтов',
	'kpi_unlimited'       => 'Бессрочно',
	'kpi_expiring'        => 'Истекают в течение 7 дней',
	'kpi_expired'         => 'Истекли, ждут крона',

	// ── Search toolbar ──
	'hint_search'         => 'Найти VIP по нику',
	'lbl_username'        => 'Ник',
	'btn_search'          => 'Найти',
	'btn_clear'           => 'Сбросить',
	'lbl_range'           => '{1}–{2} из {3}',
	'lbl_none_found'      => 'Ничего не найдено',
	'lbl_pager_top'       => 'Страницы списка VIP',
	'lbl_pager_bottom'    => 'Страницы списка VIP (внизу)',

	// ── Table ──
	'lbl_select_all'      => 'Выбрать всех на этой странице',
	'lbl_select_user'     => 'Выбрать {1}',
	'col_member'          => 'Пользователь',
	'col_vip_until'       => 'VIP до',
	'col_seedbonus'       => 'Бонусы',
	'col_invites'         => 'Инвайты',
	'lbl_vip_member'      => 'VIP',

	// ── VIP until column ──
	'lbl_unlimited'       => 'Бессрочно',
	'hint_time_left'      => 'осталось {1}',
	'hint_expired'        => 'Истёк, ждёт крона',

	// ── Empty state ──
	'sec_empty_search'    => 'Нет VIP-аккаунтов по запросу «{1}»',
	'hint_empty_search'   => 'Проверьте написание или введите часть ника.',
	'btn_clear_search'    => 'Сбросить поиск',
	'sec_empty'           => 'VIP-аккаунтов пока нет',
	'hint_empty'          => 'Пользователи появятся здесь, когда купят VIP или администрация переведёт их в группу VIP.',

	// ── User popover ──
	'pop_online'          => 'В сети',
	'pop_offline'         => 'Не в сети',
	'pop_joined'          => 'Регистрация',
	'pop_last_seen'       => 'Был(а) в сети',
	'pop_ratio'           => 'Рейтинг',
	'pop_seedbonus'       => 'Бонусы',
	'pop_uploaded'        => 'Отдано',
	'pop_downloaded'      => 'Скачано',
	'pop_invites'         => 'Инвайты',
	'pop_invites_value'   => 'доступно: {1}',

	// ── Action bar ──
	'tip_selected'        => 'Выбранные аккаунты',
	'lbl_selected'        => 'Выбрано: {1}',
	'lbl_action'          => 'Действие',
	'hint_amount'         => 'Кол-во',
	'btn_apply'           => 'Применить к выбранным',

	// ── Actions (labels + amount units) ──
	'opt_donoruntil'      => 'Продлить VIP',
	'opt_seedbonus'       => 'Начислить бонусы',
	'opt_invites'         => 'Выдать инвайты',
	'opt_remove_vip'      => 'Снять VIP',
	'unit_donoruntil'     => 'нед.',
	'unit_seedbonus'      => 'бон.',
	'unit_invites'        => 'шт.',
	'unit_remove_vip'     => '',

	// ── Flash messages ──
	'flash_dismiss'       => 'Закрыть',
	'flash_donoruntil'    => 'VIP продлён на {1} нед. Аккаунтов: {2}.',
	'flash_seedbonus'     => 'Начислено {1} бон. Аккаунтов: {2}.',
	'flash_invites'       => 'Выдано инвайтов: {1} шт. Аккаунтов: {2}.',
	'flash_remove_vip'    => 'VIP снят, аккаунтов: {1}. Пользователям возвращена прежняя группа.',
	'flash_none_selected' => 'Ничего не изменено: отметьте в таблице хотя бы один VIP-аккаунт.',
	'flash_bad_amount'    => 'Ничего не изменено: укажите количество от 1 до {1} {2}',
	'flash_bad_amount_nu' => 'Ничего не изменено: укажите количество от 1 до {1}.',
	'flash_bad_action'    => 'Ничего не изменено: неизвестное действие.',
	'flash_csrf'          => 'Ничего не изменено: форма устарела. Обновите страницу и попробуйте ещё раз.',

	// ── JS strings ──
	'js_select_first'     => 'Сначала отметьте хотя бы один VIP-аккаунт.',
	'js_remove_title_one' => 'Снять VIP с 1 аккаунта?',
	'js_remove_title_many'=> 'Снять VIP с выбранных аккаунтов ({1})?',
	'js_remove_text'      => 'Пользователи сразу вернутся в прежнюю группу. Отменить это нельзя.',
	'js_bad_amount'       => 'Укажите количество от 1 до {1} {2}',
	'js_apply_title'      => '{1}?',
	'js_apply_text_one'   => '{1}: {2} {3} — для 1 аккаунта.',
	'js_apply_text_many'  => '{1}: {2} {3} — выбрано аккаунтов: {4}.',
	'js_btn_remove'       => 'Снять VIP',
	'js_btn_apply'        => 'Применить',
	'js_btn_cancel'       => 'Отмена',
	'js_btn_ok'           => 'OK',
	'js_applying'         => 'Применяем…',
);
?>
