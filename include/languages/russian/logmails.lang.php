<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['logmails'] = array (
	// ── Page ──
	'page_title'          => 'Логи писем',

	// ── Flash messages ──
	'flash_cleared'       => 'Журнал писем полностью очищен!',
	'flash_deleted'       => 'Удалено: {1} {2}.',
	// формы через | (русский: одна|несколько|много)
	'plural_entries'      => 'запись|записи|записей',

	// ── Search ──
	'sec_search'          => 'Поиск по логам писем',
	'ph_search'           => 'Email, тема или текст письма…',
	'aria_search'         => 'Поиск по логам писем',
	'btn_search'          => 'Найти',
	'btn_clear_all'       => 'Очистить все логи',
	'btn_help'            => 'Справка',
	// выводится как есть (HTML допустим)
	'hint_search'         => '<strong>Поиск ведётся по:</strong> адресу отправителя, адресу получателя и тексту письма.',

	// ── Statistics ──
	'lbl_total'           => 'Всего записей',
	'lbl_last_update'     => 'Обновлено',

	// ── Table ──
	'sec_history'         => 'История писем',
	'lbl_showing'         => 'Показано {1}–{2} из {3}',
	'btn_select_all'      => 'Выбрать все',
	'th_date'             => 'Дата',
	'th_message'          => 'Письмо',
	'th_actions'          => 'Действия',
	'lbl_id'              => 'ID: {1}',
	'lbl_from'            => 'От: {1}',
	'lbl_to'              => 'Кому: {1}',
	'title_view'          => 'Просмотр',
	'title_delete'        => 'Удалить',
	'btn_delete_selected' => 'Удалить выбранные',
	'empty_title'         => 'Логов писем пока нет',
	'empty_text'          => 'Здесь будут отображаться все письма, отправленные трекером.',

	// ── Accessibility ──
	'aria_select_all'     => 'Выбрать все записи',
	'aria_select_row'     => 'Выбрать запись №{1}',
	'aria_close'          => 'Закрыть',

	// ── Modal ──
	'modal_title'         => 'Подробности письма',
	'lbl_loading'         => 'Загрузка…',

	// ── JS strings ──
	'js_selected'                => 'Выбрано: {1}',
	'js_show_more'               => 'Показать полностью',
	'js_collapse'                => 'Свернуть',
	'js_confirm_clear'           => 'Вы уверены, что хотите полностью очистить все логи писем?',
	'js_confirm_delete_selected' => 'Удалить выбранные записи?',
	'js_confirm_delete_one'      => 'Удалить эту запись?',
	'js_sender'                  => 'Отправитель',
	'js_recipient'               => 'Получатель',
	'js_send_date'               => 'Дата отправки',
	'js_message_content'         => 'Текст письма',
);
?>
