<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['viewunbaniprequest'] = array (
	// ── Page header ──
	'page_title'      => 'Заявки на разбан',
	'page_subtitle'   => 'Рассмотрение заявок пользователей на снятие бана по IP',
	'crumb_staff'     => 'Панель персонала',
	'crumb_current'   => 'Заявки на разбан',
	'aria_breadcrumb' => 'Навигация',
	'badge_total'     => 'Заявок: {1}',

	// ── Sections ──
	'sec_list'   => 'Список заявок',
	'sec_legend' => 'Обозначения',

	// ── Empty state ──
	'empty_title' => 'Заявок нет',
	'empty_text'  => 'Сейчас нет ни одной заявки на разбан.',

	// ── Buttons ──
	'btn_print'          => 'Печать',
	'btn_export'         => 'Экспорт',
	'btn_cancel'         => 'Отмена',
	'btn_delete_request' => 'Удалить заявку',

	// ── Table columns ──
	'col_id'        => 'ID',
	'col_ip'        => 'IP-адрес',
	'col_realip'    => 'Реальный IP',
	'col_email'     => 'E-mail',
	'col_comment'   => 'Комментарий',
	'col_submitted' => 'Подана',
	'col_actions'   => 'Действия',

	// ── Cell values ──
	'val_na'         => 'н/д',
	'val_no_email'   => 'Не указан',
	'val_no_comment' => 'Без комментария',

	// ── Tooltips / legend ──
	'tip_edit_login'     => 'Изменить запись о неудачных входах',
	'tip_delete_login'   => 'Удалить запись о неудачных входах',
	'tip_delete_request' => 'Удалить заявку на разбан',

	// ── Hints (HTML, output as is) ──
	'hint_legend_note'     => '<strong>Примечание:</strong> если кнопки редактирования нет, этот IP не найден в базе неудачных попыток входа.',
	'hint_modal_no_notify' => 'Пользователь <strong>не</strong> получит уведомления об удалении.',

	// ── Delete modal ──
	'modal_title'    => 'Удаление заявки на разбан',
	'modal_subtitle' => 'Это действие нельзя отменить',
	'modal_warning'  => 'Заявка на разбан будет удалена безвозвратно.',
	'lbl_request_id' => 'Номер заявки',

	// ── Flash messages (HTML, output as is) ──
	'flash_deleted' => '<strong>Готово!</strong> Заявка на разбан #{1} удалена.',
	'redirect_in'   => 'Перенаправление через {1} сек.…',

	// ── Errors (plain text, escaped) ──
	'err_title'               => 'Ошибка',
	'err_invalid_id'          => 'Неверный номер заявки',
	'err_delete_failed_title' => 'Не удалось удалить',
	'err_delete_failed'       => 'Не удалось удалить заявку на разбан #{1}',
	'err_db_title'            => 'Ошибка базы данных',
	'err_db'                  => 'Не удалось удалить заявку: {1}',

	// ── JS strings ──
	'js_confirm_delete_login' => 'Удалить запись о неудачных попытках входа?',
);
?>
