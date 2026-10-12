<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['uploadadd'] = array (
	// ── Page ──
	'page_title'          => 'Управление отдачей',
	'page_subtitle'       => 'Начисление и снятие отдачи — одному пользователю или целой группе',

	// ── Mode ──
	'aria_operation'      => 'Действие',
	'opt_mode_add'        => 'Начислить отдачу',
	'hint_mode_add'       => 'Прибавить к отданному',
	'opt_mode_remove'     => 'Снять отдачу',
	'hint_mode_remove'    => 'Никогда не уходит в минус',
	'lbl_verb_add'        => 'Начислить',
	'lbl_verb_remove'     => 'Снять',

	// ── KPI tiles ──
	'kpi_users'           => 'Активных пользователей',
	'kpi_uploaded'        => 'Всего отдано',
	'kpi_downloaded'      => 'Всего скачано',
	'kpi_ratio'           => 'Рейтинг трекера',

	// ── Single user ──
	'sec_single'          => 'Один пользователь',
	'hint_single_max'     => 'До {1} GB',
	'lbl_username'        => 'Ник',
	'ph_username'         => 'Точный ник пользователя',
	'lbl_uploaded'        => 'Отдано',
	'hint_inactive'       => 'Аккаунт отключён или не подтверждён — изменить нельзя',
	'btn_single_suffix'   => 'отдачу',

	// ── Whole group ──
	'sec_group'           => 'Вся группа',
	'hint_group'          => 'Одинаковый объём каждому активному участнику',
	'lbl_group'           => 'Группа',
	'lbl_per_user'        => 'На каждого',
	'lbl_group_n'         => 'Группа {1}',
	'opt_all_users'       => 'Все пользователи',
	'opt_choose'          => 'Выберите…',
	'btn_group_suffix'    => 'для группы',

	// ── Recent changes ──
	'sec_recent'          => 'Последние изменения',
	'hint_recent'         => 'Из лога сайта',
	'empty_recent'        => 'В логе пока нет изменений.',

	// ── Flash messages ──
	'flash_single_added'     => 'Пользователю {2} начислено {1} GB',
	'flash_single_removed'   => 'У пользователя {2} снято {1} GB',
	'flash_group_added'      => 'Каждому в группе «{2}» начислено по {1} GB',
	'flash_group_removed'    => 'У каждого в группе «{2}» снято по {1} GB',
	'flash_all_added'        => 'Всем пользователям начислено по {1} GB',
	'flash_all_removed'      => 'У всех пользователей снято по {1} GB',
	'flash_mass_detail'      => 'Пользователей: {1} · всего {2} · записано в комментарии модератора и лог сайта',
	'flash_single_detail'    => 'Отдано {1} → {2} · записано в комментарий модератора',
	'btn_profile'            => 'Профиль',

	// ── Errors ──
	'err_csrf'            => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.',
	'err_bulk_amount'     => 'Выберите объём от 1 до {1} GB.',
	'err_username'        => 'Введите ник пользователя.',
	'err_single_amount'   => 'Объём должен быть от 1 до {1} GB.',
	'err_user_not_found'  => 'Пользователь не найден, отключён или не подтверждён.',
	'err_update_users'    => 'Не удалось обновить пользователей.',
	'err_update_user'     => 'Не удалось обновить пользователя.',
	'err_unexpected'      => 'Произошла непредвиденная ошибка. Попробуйте ещё раз.',

	// ── JS: common ──
	'js_verb_add'         => 'Начислить',
	'js_verb_remove'      => 'Снять',
	'js_all_users'        => 'Все пользователи',
	'js_cancel'           => 'Отмена',
	'js_working'          => 'Выполняется…',
	'js_applying'         => 'Применяем…',
	'js_note_add'         => 'Будет записано в комментарий модератора и лог сайта',
	'js_note_remove'      => 'Не ниже нуля · будет записано в комментарий модератора и лог сайта',

	// ── JS: group preview ──
	'js_group_active'     => 'Активных пользователей: {1}',
	'js_group_total'      => 'всего {1}',
	'js_group_withup'     => 'с отдачей: {1}',

	// ── JS: single user confirm ──
	'js_single_sub'       => 'Отдано {1} → {2}',
	'js_not_checked'      => 'Пользователь ещё не проверен',
	'js_single_title_add'    => 'Начислить {1} GB?',
	'js_single_title_remove' => 'Снять {1} GB?',
	'js_single_btn_add'      => 'Начислить {1} GB',
	'js_single_btn_remove'   => 'Снять {1} GB',
	'js_single_fb_add'       => 'Начислить {1} GB пользователю {2}?',
	'js_single_fb_remove'    => 'Снять {1} GB у пользователя {2}?',

	// ── JS: group confirm ──
	'js_amount_title'     => 'Выберите объём',
	'js_amount_text'      => 'Сколько GB на каждого пользователя?',
	'js_amount_alert'     => 'Выберите объём на пользователя.',
	'js_mass_title_add'      => 'Начислить по {1} GB всей группе?',
	'js_mass_title_remove'   => 'Снять по {1} GB со всей группы?',
	'js_mass_sub'            => 'Пользователей: {1} · всего {2}',
	'js_mass_btn_add'        => 'Да, начислить ({1} чел.)',
	'js_mass_btn_remove'     => 'Да, снять ({1} чел.)',
	'js_mass_fb_add'         => 'Начислить по {1} GB каждому в «{2}»?',
	'js_mass_fb_remove'      => 'Снять по {1} GB у каждого в «{2}»?',
);
?>
