<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['changemail'] = array (
	// ── Page ──
	'page_title'         => 'Смена e-mail пользователя',
	'pane_title'         => 'Смена e-mail',
	'pane_sub'           => 'Новый адрес электронной почты для аккаунта',
	'footer_note'        => 'E-mail супер-администратора может сменить только другой супер-администратор · каждое изменение пишется в лог',

	// ── Form: labels ──
	'lbl_user'           => 'Пользователь',
	'lbl_email'          => 'Новый e-mail',

	// ── Form: placeholders ──
	'ph_user'            => 'ID или ник',
	'ph_email'           => 'user@example.com',

	// ── Form: hints ──
	'hint_user'          => 'Числовой ID или точный ник',
	'hint_email'         => 'На этот адрес пользователь будет получать письма',

	// ── Preview ──
	'flag_protected'     => 'Защищён',

	// ── Buttons ──
	'btn_clear'          => 'Очистить',
	'btn_submit'         => 'Сменить e-mail',
	'btn_another'        => 'Сменить другому',
	'btn_profile'        => 'Открыть профиль',

	// ── Result ──
	'res_title'          => 'E-mail изменён',
	'res_id'             => 'ID {1}',
	'res_logged'         => 'Записано в лог сайта',

	// ── Messages ──
	'flash_success'      => 'E-mail успешно обновлён.',
	'err_security_title' => 'Ошибка безопасности',
	'err_security_token' => 'Недействительный ключ безопасности. Обновите страницу и попробуйте ещё раз.',
	'err_required'       => 'Заполните все обязательные поля.',
	'err_user_not_found' => 'Пользователь с таким ID или ником не найден.',
	'err_super_admin'    => 'У вас нет прав менять e-mail супер-администратора.',
	'err_invalid_email'  => 'Неверный формат адреса e-mail.',
	'err_same_email'     => 'Это и так текущий e-mail пользователя.',
	'err_email_taken'    => 'Этот адрес уже привязан к другому аккаунту.',
	'err_db'             => 'Ошибка базы данных: не удалось обновить e-mail.',

	// ── JS: preview ──
	'js_not_found'       => 'Пользователь не найден',
	'js_not_found_hint'  => 'Проверьте ID или ник',
	'js_none'            => '(не указан)',
	'js_meta'            => 'ID {1} · регистрация: {2}',

	// ── JS: address check ──
	'js_same'            => 'Совпадает с текущим e-mail',
	'js_invalid'         => 'Некорректный адрес',
	'js_taken'           => 'Уже занят другим аккаунтом',
	'js_available'       => 'Свободен',

	// ── JS: confirmation ──
	'js_confirm_title'   => 'Сменить e-mail?',
	'js_confirm_ok'      => 'Сменить e-mail',
	'js_cancel'          => 'Отмена',
	'js_confirm_who'     => 'Сменить e-mail пользователю {1}',
	'js_confirm_from'    => 'с {1}',
	'js_confirm_to'      => 'на {1}?',
	'js_saving'          => 'Сохранение…',
);
?>
