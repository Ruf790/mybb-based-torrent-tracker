<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['warned'] = array (
	// ── Meta ──
	// Правило множественного числа для flash_removed_* / js_confirm_text_*: 'en' (1 / прочее) или 'ru' (1, 2–4, 5+)
	'js_plural_rule'      => 'ru',

	// ── Page ──
	'page_title'          => 'Предупреждённые пользователи',
	'sec_header'          => 'Предупреждённые пользователи',
	'hint_header'         => 'Активные участники с обычным предупреждением или предупреждением за низкий рейтинг. Отметьте строки, чтобы снять предупреждения разом.',

	// ── KPI tiles ──
	'lbl_kpi_all'         => 'Всего с предупреждениями',
	'lbl_kpi_normal'      => 'Обычные предупреждения',
	'lbl_kpi_leech'       => 'За низкий рейтинг',
	'lbl_kpi_expiring'    => 'Истекают в течение 7 дней',
	'tip_kpi_show'        => 'Показать: {1}',

	// ── Flash messages ──
	// {1} = число пользователей (строка выводится как есть, HTML допустим)
	'flash_removed_one'   => 'Предупреждения сняты с <b>{1}</b> пользователя.',
	'flash_removed_few'   => 'Предупреждения сняты с <b>{1}</b> пользователей.',
	'flash_removed_many'  => 'Предупреждения сняты с <b>{1}</b> пользователей.',
	'lbl_close'           => 'Закрыть',

	// ── Errors ──
	'err_security'        => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.',

	// ── Table headers ──
	'lbl_col_user'        => 'Пользователь',
	'lbl_col_registered'  => 'Регистрация',
	'lbl_col_lastseen'    => 'Последний визит',
	'lbl_col_traffic'     => 'Трафик',
	'lbl_col_ratio'       => 'Рейтинг',
	'lbl_col_expires'     => 'Истекает',
	'lbl_col_type'        => 'Тип',
	'tip_select_all_page' => 'Выбрать всех на этой странице',
	'lbl_select_all'      => 'Выбрать всех',

	// ── Table rows ──
	'lbl_never'           => 'Никогда',
	'tip_no_download'     => 'Ничего не скачано',
	'tip_remove_warning'  => 'Снять предупреждение',
	'lbl_select_user'     => 'Выбрать {1}',

	// ── Warning types / expiry ──
	'opt_type_normal'     => 'Обычное',
	'opt_type_leech'      => 'Лич',
	'lbl_no_expiry'       => 'Бессрочно',
	'lbl_expired_cron'    => 'Истекло, ждёт крона',
	'lbl_time_left'       => 'осталось {1}',

	// ── Empty state ──
	'sec_empty'           => 'Предупреждённых нет',
	'hint_empty'          => 'В этом разделе ни у кого нет активных предупреждений.',

	// ── Action bar ──
	// {1} = элемент счётчика (HTML, выводится как есть), {2} = строк на странице
	'lbl_selected'        => 'Выбрано: {1} из {2}',
	'btn_clear'           => 'Сбросить',
	'btn_remove'          => 'Снять предупреждения',

	// ── JS (confirmation dialog) ──
	'js_confirm_title'    => 'Снять предупреждения?',
	'js_confirm_text_one' => 'Предупреждения будут сняты с {1} пользователя.',
	'js_confirm_text_few' => 'Предупреждения будут сняты с {1} пользователей.',
	'js_confirm_text_many'=> 'Предупреждения будут сняты с {1} пользователей.',
	'js_confirm_btn'      => 'Снять',
	'js_cancel_btn'       => 'Отмена',
	'js_continue'         => 'Продолжить?',
);
?>
