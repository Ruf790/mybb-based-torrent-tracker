<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['amountbonus'] = array (
	// ── Access ──
	'err_access_denied'   => 'Доступ запрещён!',
	'err_no_permission'   => 'У вас нет прав для просмотра этой страницы.',

	// ── Page header ──
	'page_title'          => 'Начисление бонусов',
	'page_subtitle'       => 'Выдача сид-бонусов одному пользователю или целой группе',

	// ── KPI tiles ──
	'kpi_confirmed'       => 'Подтверждённых пользователей',
	'kpi_circulation'     => 'Бонусов в обороте',
	'kpi_avg'             => 'В среднем на пользователя',
	'kpi_max'             => 'Самый большой баланс',

	// ── Section headers ──
	'sec_single'          => 'Одному пользователю',
	'sec_single_sub'      => 'Точный ник',
	'sec_bulk'            => 'Массовое начисление',
	'sec_bulk_sub'        => 'Всем подтверждённым участникам группы',
	'sec_recent'          => 'Последние начисления',
	'sec_recent_sub'      => 'Из лога сайта',

	// ── Labels ──
	'lbl_username'        => 'Ник',
	'lbl_points'          => 'Бонусы',
	'lbl_points_per_user' => 'Бонусов каждому',
	'lbl_group'           => 'Группа',
	'lbl_unit'            => 'бонусов',
	'lbl_close'           => 'Закрыть',
	// {1} — пользователей, {2} — бонусов на каждого (подставляются HTML-span, вывод как есть)
	'lbl_impact'          => 'Получателей: {1} · по {2} каждому',

	// ── Placeholders / tooltips ──
	'ph_username'         => 'Точный ник',
	'ph_amount'           => 'Количество',
	'tip_myself'          => 'Себе',

	// ── Buttons ──
	'btn_clear'           => 'Очистить',
	'btn_send'            => 'Начислить',
	'btn_distribute'      => 'Начислить всем',

	// ── Target group select ──
	'opt_all_confirmed'   => 'Все подтверждённые пользователи ({1})',
	'opt_staff_protected' => ' — персонал, защищено',
	'opt_banned_group'    => ' — группа забаненных',

	// ── Legacy group select (generateGroupSelect) ──
	'opt_grp_all'         => '👥 Все группы',
	'opt_grp_2'           => '👤 Пользователь',
	'opt_grp_3'           => '⚡ Опытный пользователь',
	'opt_grp_4'           => '⭐ VIP',
	'opt_grp_5'           => '📤 Аплоадер',
	'opt_grp_6'           => '🛡️ Модератор',
	'opt_grp_7'           => '👑 Администратор',
	'opt_grp_8'           => '🔧 Сисоп',
	'opt_grp_protected'   => ' (персонал — защищено)',

	// ── Recent log ──
	'txt_nothing_yet'     => 'Начислений пока не было.',

	// ── Flash messages (plain text — escaped on output) ──
	'flash_error_title'   => 'Ошибка:',
	'flash_success_title' => 'Готово!',
	'flash_bad_token'     => '⚠️ Неверный токен безопасности. Обновите страницу и попробуйте ещё раз.',
	'flash_bad_amount'    => '❌ Укажите количество бонусов — целое число от 1 до 1 000 000.',
	'flash_protected'     => '🚫 Массовое начисление группам персонала и администрации запрещено.',
	'flash_no_target'     => 'Укажите ник пользователя или выберите «Все пользователи».',
	'flash_unexpected'    => '⚠️ Произошла непредвиденная ошибка. Попробуйте ещё раз.',
	'flash_critical'      => 'Произошла критическая ошибка. Обратитесь к администратору.',
	// {1} — бонусы, {2} — кому (flash_target_all / flash_target_group, дательный падеж)
	'flash_bulk_done'     => '✅ Начислено по {1} бонусов {2}.',
	'flash_target_all'    => 'всем подтверждённым пользователям',
	'flash_target_group'  => 'группе «{1}»',
	'flash_group_id'      => 'Группа {1}',

	// ── Errors (shown in flash after "❌ ") ──
	'err_protected_group' => 'Массовое начисление группам персонала и администрации запрещено.',
	'err_update_users'    => 'Не удалось обновить записи пользователей.',
	'err_enter_username'  => 'Введите ник пользователя.',
	'err_update_user'     => 'Не удалось обновить аккаунт пользователя.',
	'err_user_not_found'  => 'Пользователь «{1}» не найден.',
	'err_user_fetch'      => 'Не удалось получить данные пользователя.',

	// ── JS (admin/scripts/amountbonus.js, plain text) ──
	'js_sending'          => 'Отправка…',
	'js_yes'              => 'Да',
	'js_cancel'           => 'Отмена',
	'js_all_who'          => 'ВСЕ подтверждённые пользователи',
	'js_title_all'        => 'Начислить всем пользователям?',
	'js_title_group'      => 'Начислить бонусы группе?',
	// {1} — бонусов каждому, {2} — получателей, {3} — группа
	'js_confirm_body'     => 'Получателей: {2} ({3}), по {1} бонусов каждому.',
	'js_confirm_total'    => 'Всего к начислению: {1}.',
	'js_confirm_undo'     => 'Автоматически отменить это нельзя.',
	'js_confirm_ok'       => 'Начислить',
	'js_user_not_found'   => 'Пользователь не найден',
	'js_check_spelling'   => 'Проверьте написание ника',
	// {1} — ID пользователя, {2} — группа
	'js_user_meta'        => 'ID {1} · {2}',
	'js_total'            => 'Всего к начислению: {1}',
	// {1} — js_warn_all_who (жирным)
	'js_warn_all'         => 'Бонусы получит {1} сайта.',
	'js_warn_all_who'     => 'каждый подтверждённый пользователь',
	'js_warn_group'       => 'Бонусы получит каждый подтверждённый участник этой группы. Автоматически отменить это нельзя.',
);
?>
