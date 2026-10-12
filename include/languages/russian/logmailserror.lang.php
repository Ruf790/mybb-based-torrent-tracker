<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['logmailserror'] = array (
	// ── Page ──
	'page_title'              => 'Лог ошибок отправки почты',

	// ── Flash messages ──
	'flash_cleared'           => 'Лог ошибок почты полностью очищен!',
	'flash_deleted'           => 'Успешно удалено: {1} {2}.',
	'plural_entry_1'          => 'запись об ошибке',
	'plural_entry_2'          => 'записи об ошибках',
	'plural_entry_5'          => 'записей об ошибках',

	// ── Search ──
	'sec_search'              => 'Поиск по логу ошибок почты',
	'ph_search'               => 'Поиск по e-mail, тексту ошибки или содержимому письма…',
	'btn_search'              => 'Найти',
	'btn_clear_all'           => 'Очистить весь лог',
	'confirm_clear_all'       => 'Точно очистить весь лог ошибок почты?',
	'btn_help'                => 'Справка',
	'hint_search_html'        => '<strong>Где ищем:</strong> адреса e-mail, тексты ошибок и содержимое писем. Здесь показываются только неудачные попытки отправки.',

	// ── Statistics ──
	'kpi_total_errors'        => 'Всего ошибок',
	'kpi_failed_emails'       => 'Неотправленных писем',
	'kpi_last_check'          => 'Последняя проверка',
	'fmt_last_check'          => 'd.m.Y H:i:s',

	// ── Pagination ──
	'lbl_showing'             => 'Ошибки {1}–{2} из {3}',

	// ── Table ──
	'sec_failures'            => 'Сбои доставки писем',
	'btn_select_all'          => 'Выбрать все',
	'th_datetime'             => 'Дата и время',
	'th_details'              => 'Подробности ошибки',
	'th_actions'              => 'Действия',
	'empty_title'             => 'Ошибок почты нет',
	'empty_text'              => 'Отлично! Все письма доставляются без проблем.',
	'lbl_from'                => 'От:',
	'lbl_to'                  => 'Кому:',
	'lbl_error_message'       => 'Текст ошибки',
	'lbl_email_content'       => 'Содержимое письма',
	'title_view'              => 'Подробнее',
	'title_delete'            => 'Удалить запись',
	'title_retry'             => 'Отправить повторно',

	// ── Severity ──
	'sev_critical'            => 'КРИТИЧНО',
	'sev_high'                => 'ВЫСОКАЯ',
	'sev_medium'              => 'СРЕДНЯЯ',
	'sev_low'                 => 'НИЗКАЯ',

	// ── Footer actions ──
	'btn_delete_selected'     => 'Удалить выбранные',
	'confirm_delete_selected' => 'Удалить выбранные записи об ошибках?',

	// ── Modal ──
	'modal_title'             => 'Подробности ошибки отправки',
	'modal_loading'           => 'Загружаем подробности…',

	// ── JS strings ──
	'js_selected_count'       => 'Выбрано: {1}',
	'js_critical_selected'    => '⚠ критичных: {1}',
	'js_show_full'            => 'Показать письмо целиком',
	'js_collapse'             => 'Свернуть письмо',
	'js_show_critical'        => 'Только критичные',
	'js_show_all'             => 'Показать все',
	'js_confirm_delete_one'   => 'Удалить эту запись об ошибке?',
	'js_confirm_retry'        => 'Попробовать отправить это письмо ещё раз?',
	'js_resend_ok'            => 'Письмо успешно отправлено повторно!',
	'js_resend_failed'        => 'Повторная отправка не удалась: {1}',
	'js_error'                => 'Ошибка: {1}',
	'js_modal_sender'         => 'Отправитель',
	'js_modal_recipient'      => 'Получатель',
	'js_modal_time'           => 'Время ошибки',
	'js_modal_occurred'       => 'Ошибка произошла:',
	'js_modal_error'          => 'Текст ошибки',
	'js_modal_content'        => 'Исходное письмо',
	'js_tips_title'           => 'Что проверить:',
	'js_tip_1'                => 'настройки почтового сервера;',
	'js_tip_2'                => 'правильность адреса получателя;',
	'js_tip_3'                => 'параметры SMTP-авторизации;',
	'js_tip_4'                => 'нет ли в письме недопустимых символов.',
);
?>
