<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['sendmail'] = array (
	// ── Page ──
	'page_title'          => 'Отправка письма',
	'head_title'          => 'Отправить письмо',
	'head_subtitle'       => 'Разовое письмо от имени {1} на любой адрес.',

	// ── Flash messages ──
	'flash_sent_title'    => 'Письмо отправлено.',
	'flash_sent_text'     => 'Сообщение передано почтовому серверу.',
	'flash_fail_title'    => 'Письмо не отправлено.',

	// ── Errors ──
	'err_token'           => 'Срок действия токена безопасности истёк. Обновите страницу и отправьте ещё раз.',
	'err_email'           => 'Укажите корректный адрес получателя.',
	'err_subject'         => 'Укажите тему письма.',
	'err_message_short'   => 'Текст письма должен быть не короче {1} символов.',
	'err_mail_server'     => 'Почтовый сервер не принял письмо. Проверьте почтовый лог и попробуйте снова.',

	// ── Panes ──
	'pane_compose'        => 'Новое письмо',
	'pane_preview'        => 'Предпросмотр во входящих',

	// ── Labels ──
	'lbl_recipient'       => 'Получатель',
	'lbl_subject'         => 'Тема',
	'lbl_message'         => 'Текст письма',
	'lbl_from'            => 'От',
	'lbl_to'              => 'Кому',
	'lbl_counter_suffix'  => 'симв.',

	// ── Placeholders ──
	'ph_recipient'        => 'recipient@example.com',
	'ph_subject'          => 'О чём это письмо?',
	'ph_message'          => 'Напишите текст письма. Можно использовать HTML.',

	// ── Validation feedback ──
	'inv_email'           => 'Укажите корректный e-mail.',
	'inv_subject'         => 'Укажите тему.',
	'inv_message'         => 'Напишите не меньше {1} символов.',

	// ── Toolbar ──
	'aria_toolbar'        => 'HTML-форматирование',
	'tip_bold'            => 'Жирный (Ctrl+B)',
	'tip_italic'          => 'Курсив (Ctrl+I)',
	'tip_underline'       => 'Подчёркнутый (Ctrl+U)',
	'tip_heading'         => 'Заголовок',
	'tip_paragraph'       => 'Абзац',
	'tip_list'            => 'Маркированный список',
	'tip_link'            => 'Ссылка (Ctrl+K)',
	'tip_image'           => 'Изображение',
	'tip_hr'              => 'Разделитель',
	'tip_br'              => 'Перенос строки',

	// ── Hints ──
	'hint_html'           => 'Письмо отправляется в формате HTML. В предпросмотре видно, как оно будет выглядеть во входящих.',
	// Выводится как есть (HTML допустим)
	'hint_send_html'      => '<kbd>Ctrl</kbd> + <kbd>Enter</kbd> — отправить',

	// ── Preview / action bar / overlay ──
	'title_preview_frame' => 'Предпросмотр письма',
	'btn_reset'           => 'Сбросить',
	'btn_send'            => 'Отправить письмо',
	'loading_sending'     => 'Отправляем письмо…',

	// ── JS strings ──
	'js_no_recipient'        => 'Получатель не указан',
	'js_no_subject'          => 'Тема не указана',
	'js_preview_placeholder' => 'Здесь появится текст письма.',
	'js_confirm_send_title'  => 'Отправить письмо?',
	'js_confirm_send_text'   => 'Письмо уйдёт на адрес {1}.',
	'js_btn_send'            => 'Отправить',
	'js_btn_keep_editing'    => 'Продолжить редактирование',
	'js_confirm_reset_title' => 'Отменить изменения?',
	'js_confirm_reset_text'  => 'Форма вернётся к состоянию на момент загрузки страницы.',
	'js_btn_discard'         => 'Сбросить',
);
?>
