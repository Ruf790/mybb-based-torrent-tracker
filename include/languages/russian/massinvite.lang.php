<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['massinvite'] = array (
	// ── Header ──
	'title'                => 'Массовая выдача инвайтов',
	'subtitle'             => 'Начислить или списать инвайты сразу у всех активных участников группы',

	// ── Result (after redirect) ──
	'flash_added'          => 'Начислено по {1} инв. — {2}',
	'flash_removed'        => 'Списано до {1} инв. у каждого — {2}',
	'flash_affected'       => 'Затронуто пользователей: {1} · записано в лог сайта',
	'lbl_all_groups'       => 'все группы',
	'lbl_group_n'          => 'Группа {1}',

	// ── KPI tiles ──
	'kpi_total'            => 'Инвайтов на руках',
	'kpi_holders'          => 'Есть инвайты',
	'kpi_average'          => 'В среднем',
	'kpi_top'              => 'Максимум у одного',

	// ── Operation section ──
	'sec_operation'        => 'Операция',
	'sec_operation_sub'    => 'Выберите действие, количество и группу',
	'opt_add'              => 'Начислить инвайты',
	'opt_add_hint'         => 'Каждому +N',
	'opt_remove'           => 'Списать инвайты',
	'opt_remove_hint'      => 'До N у каждого, ниже нуля не уходит',
	'lbl_amount'           => 'Инвайтов на пользователя',
	'lbl_amount_unit'      => 'инв.',
	'lbl_group'            => 'Группа',
	'hint_group'           => '«-» — все активные подтверждённые пользователи',

	// ── Preview ──
	'lbl_impact'           => 'Охват',
	'lbl_affected'         => 'польз. затронуто',
	'lbl_total_change'     => 'Итоговое изменение',
	'lbl_change_initial'   => '+0 инв.',

	// ── Action bar ──
	'hint_bulk'            => 'Массовое изменение — автоматически не откатывается',
	'btn_reset'            => 'Сбросить',
	'btn_preview'          => 'Предпросмотр',
	'btn_apply'            => 'Применить',

	// ── Errors ──
	'err_title'            => 'Ошибка',
	'err_amount'           => 'Укажите количество от 1 до {1}.',
	'err_csrf'             => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.',

	// ── JS: preview ──
	'js_all_users'         => 'все пользователи',
	'js_no_changes'        => 'Без изменений',
	'js_change'            => '{1}{2} инв.',

	// ── JS: errors ──
	'js_error_title'       => 'Ошибка',
	'js_error_generic'     => 'Что-то пошло не так',
	'js_amount_range'      => 'Укажите количество от 1 до {1}.',
	'js_nobody'            => 'Под условие никто не попадает — инвайты не изменятся.',

	// ── JS: confirmation ──
	'js_confirm_add'       => 'Начислить по {1} инв.?',
	'js_confirm_remove'    => 'Списать по {1} инв.?',
	'js_confirm_summary'   => 'Пользователей: {1} · всего {2}{3} инв.',
	'js_confirm_plain'     => '{1}: пользователей — {2}, всего {3}{4} инв.',
	'js_never_below_zero'  => 'Ниже нуля не уходит',
	'js_logged'            => 'пишется в лог',
	'js_cant_undo'         => 'отменить нельзя',
	'js_btn_add'           => 'Начислить {1}',
	'js_btn_remove'        => 'Списать {1}',
	'js_cancel'            => 'Отмена',
	'js_applying'          => 'Применяем…',
);
?>
