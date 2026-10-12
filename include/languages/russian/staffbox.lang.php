<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['staffbox'] = array (
	// ── Page titles ──
	'page_title_inbox'       => 'Сообщения администрации',
	'page_title_reply'       => 'Ответ на сообщение',

	// ── Section headers ──
	'sec_inbox_title'        => 'Сообщения администрации',
	'sec_inbox_sub'          => 'Вопросы и жалобы, которые пользователи отправили команде трекера',
	'sec_reply_title'        => 'Ответ на сообщение',
	'sec_view_sub'           => 'Сообщение #{1}',
	'sec_original'           => 'Исходное сообщение',
	'sec_preview'            => 'Предпросмотр',
	'sec_your_reply'         => 'Ваш ответ',
	'sec_message'            => 'Сообщение',
	'sec_answer'             => 'Ответ',

	// ── Labels ──
	'lbl_subject'            => 'Тема',
	'lbl_message'            => 'Сообщение',
	'lbl_from'               => 'От кого',
	'lbl_sent'               => 'Отправлено',
	'lbl_status'             => 'Статус',
	'lbl_answered_by'        => 'Кто ответил',
	'lbl_reply_to'           => 'Кому: {1}',
	'lbl_by_list'            => 'ответ: {1}',
	'lbl_answer_author'      => 'автор: {1}',
	'lbl_selected'           => 'Выбрано: {1}',
	'lbl_no_subject'         => 'Без темы',
	'lbl_system'             => 'Система',
	'lbl_deleted_user'       => '[Удалён]',
	'lbl_status_answered'    => 'Отвечено',
	'lbl_status_waiting'     => 'Ожидает',
	'lbl_note_no_reply'      => 'Отмечено как отвеченное, но ответ с этой страницы не отправлялся.',

	// ── KPI tiles ──
	'kpi_total'              => 'Всего сообщений',
	'kpi_open'               => 'Ждут ответа',
	'kpi_done'               => 'Отвечено',
	'kpi_day'                => 'За последние 24 часа',

	// ── Filter tabs ──
	'tab_all'                => 'Все',
	'tab_open'               => 'Ожидают',
	'tab_done'               => 'Отвеченные',

	// ── Table headers ──
	'th_message'             => 'Сообщение',
	'th_from'                => 'От кого',
	'th_sent'                => 'Отправлено',
	'th_status'              => 'Статус',

	// ── Accessibility (aria-label) ──
	'aria_tabs'              => 'Фильтр сообщений',
	'aria_select_all'        => 'Выбрать все',
	'aria_select_msg'        => 'Выбрать сообщение #{1}',
	'aria_close'             => 'Закрыть',

	// ── Buttons ──
	'btn_inbox'              => 'Входящие',
	'btn_reply'              => 'Ответить',
	'btn_mark_answered'      => 'Отметить отвеченным',
	'btn_delete'             => 'Удалить',
	'btn_back'               => 'Назад',
	'btn_preview'            => 'Предпросмотр',
	'btn_send'               => 'Отправить ответ',

	// ── Tooltips ──
	'tip_view'               => 'Открыть',
	'tip_reply'              => 'Ответить',
	'tip_delete'             => 'Удалить',

	// ── Confirmations (data-sb-confirm*, {n} is replaced by JS) ──
	'confirm_delete_one'     => 'Удалить это сообщение?',
	'confirm_delete_view'    => 'Сообщение и сохранённый ответ будут удалены.',
	'confirm_irreversible'   => 'Это действие нельзя отменить.',
	'confirm_delete_bulk'    => 'Удалить выбранные сообщения ({n})?',
	'confirm_btn_delete'     => 'Удалить',

	// ── Empty states ──
	'empty_open_title'       => 'Всё разобрано',
	'empty_open_text'        => 'Нет сообщений, ожидающих ответа.',
	'empty_done_title'       => 'Отвеченных пока нет',
	'empty_done_text'        => 'Здесь появятся сообщения, на которые уже ответили.',
	'empty_all_title'        => 'Входящие пусты',
	'empty_all_text'         => 'Здесь появятся сообщения, которые пользователи отправляют администрации.',

	// ── Flash messages ──
	'flash_deleted_one'      => 'Сообщение удалено.',
	'flash_deleted_many'     => 'Удалено сообщений: {1}.',
	'flash_answered_none'    => 'Уже отмечено как отвеченное.',
	'flash_answered_one'     => 'Отмечено как отвеченное.',
	'flash_answered_many'    => 'Отмечено как отвеченные: {1}.',
	'flash_sent'             => 'Ответ отправлен, сообщение отмечено как отвеченное.',
	'flash_none'             => 'Сначала выберите хотя бы одно сообщение.',
	'flash_csrf'             => 'Срок действия токена безопасности истёк. Обновите страницу и попробуйте ещё раз.',

	// ── Errors ──
	'err_title'              => 'Ошибка',
	'err_not_found'          => 'Сообщение не найдено.',
	'err_no_user'            => 'Пользователь с таким ID не найден.',
	'err_csrf_reply'         => 'Срок действия токена безопасности истёк. Скопируйте текст, обновите страницу и попробуйте ещё раз.',
	'err_empty_reply'        => 'Напишите ответ перед отправкой.',

	// ── PM defaults ──
	'pm_default_subject'     => 'Re: Сообщение администрации',

	// ── JS strings ──
	'js_confirm'             => 'Подтвердить',
	'js_cancel'              => 'Отмена',
);
?>
