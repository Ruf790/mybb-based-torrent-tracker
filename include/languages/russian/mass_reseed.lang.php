<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['mass_reseed'] = array (
	// ── Page titles & headers ──
	'title_list'        => 'Раздачи без сидов — пересид',
	'title_form'        => 'Запрос пересида',
	'pane_list'         => 'Массовый пересид',
	'pane_list_sub'     => 'Раздачи без сидов — попросите авторов или скачавших снова встать на раздачу',
	'pane_form'         => 'Запрос пересида',
	'pane_form_sub'     => 'Личное сообщение с просьбой снова встать на раздачу',
	'btn_back'          => 'К списку',

	// ── KPI tiles ──
	'kpi_dead'          => 'Мёртвые раздачи',
	'kpi_waiting'       => 'Ждут личи',
	'kpi_snatched'      => 'Всего скачиваний',
	'kpi_per_request'   => 'За один запрос',
	'kpi_up_to'         => 'до {1}',

	// ── Toolbar ──
	'lbl_sort'          => 'Сортировка:',
	'opt_sort_snatched' => 'Больше скачиваний',
	'opt_sort_leechers' => 'Больше личей',
	'opt_sort_added'    => 'Новые',
	'opt_sort_name'     => 'По названию',
	'ph_filter'         => 'Фильтр по названию…',

	// ── Table ──
	'col_torrent'       => 'Раздача',
	'col_uploader'      => 'Автор',
	'col_leech'         => 'Личи',
	'col_snatched'      => 'Скачали',
	'aria_select_all'   => 'Выбрать все',
	'aria_select'       => 'Выбрать',
	'lbl_deleted'       => 'удалён',
	'tip_snatches'      => 'Кто скачал',
	'tip_edit'          => 'Редактировать',
	'tip_delete'        => 'Удалить',
	'empty_title'       => 'Мёртвых раздач нет',
	'empty_text'        => 'На каждой видимой раздаче есть хотя бы один сид.',
	'hint_list_limit'   => 'Показаны первые {1} — смените сортировку, чтобы увидеть остальные.',
	'lbl_selected'      => 'Выбрано: {1}',
	'lbl_max'           => '(максимум {1})',
	'btn_request'       => 'Запросить пересид',

	// ── Reseed form ──
	'hint_truncated'    => 'В один запрос попадают только первые {1} выбранных раздач.',
	'sec_message'       => 'Сообщение',
	'sec_message_sub'   => 'Одно ЛС на каждого получателя и каждую раздачу',
	'lbl_subject'       => 'Тема',
	'lbl_text'          => 'Текст',
	'lbl_insert'        => 'Вставить:',
	'sec_recipients'    => 'Получатели',
	'opt_owner'         => 'Только авторы раздач',
	'opt_all'           => 'Все, кто скачал',
	'lbl_msg_count'     => 'Сообщений: {1}',
	'sec_options'       => 'Параметры',
	'lbl_send_as'       => 'Отправитель',
	'opt_system'        => 'Система',
	'opt_me'            => 'Я',
	'lbl_double'        => 'Двойная отдача',
	'hint_double'       => 'Отдача ×2 в награду тем, кто встанет на раздачу',
	'sec_torrents_one'  => 'Раздач: {1}',
	'sec_torrents_many' => 'Раздач: {1}',
	'lbl_will_send'     => 'Будет отправлено сообщений: {1}',
	'btn_send'          => 'Отправить запросы',

	// ── Default PM ({username} and {torrentname} are replaced per recipient) ──
	'msg_subject'       => 'Просьба встать на раздачу',
	'msg_body'          => "Здравствуйте, {username}!\n\nНа раздаче {torrentname} сейчас нет ни одного сида. Если файлы у вас сохранились, пожалуйста, встаньте на раздачу снова.\n\nСпасибо!",

	// ── Errors & flash messages ──
	'err_security_title'  => 'Ошибка безопасности',
	'err_security'        => 'Неверный токен безопасности. Обновите страницу и попробуйте ещё раз.',
	'flash_fill'          => 'Заполните тему и текст сообщения.',
	'flash_none_selected' => 'Не выбрано ни одной раздачи.',
	'flash_sent'          => 'Запросы на пересид отправлены: сообщений — {1}, раздач — {2}.',
	'flash_sent_double'   => 'Двойная отдача включена.',
	'flash_nobody'        => 'Некого уведомлять — у выбранных раздач не осталось ни автора, ни скачавших.',

	// ── JS strings ──
	'js_double_note'    => 'Бонус: за сидирование этой раздачи отдача засчитывается в двойном размере!',
	'js_nobody_title'   => 'Некого уведомлять',
	'js_nobody_text'    => 'Попробуйте вариант «Все, кто скачал».',
	'js_confirm_send'   => 'Отправить сообщений: {1}?',
	'js_confirm_torrents' => 'Раздач: {1}',
	'js_confirm_double' => 'Будет включена двойная отдача',
	'js_btn_send'       => 'Отправить',
	'js_btn_cancel'     => 'Отмена',
	'js_sending'        => 'Отправка…',
	'js_delete_title'   => 'Удалить раздачу?',
	'js_delete_btn'     => 'Удалить',
	'js_delete_confirm' => 'Удалить раздачу «{1}»?',
	'js_too_many_title' => 'Выбрано слишком много',
	'js_too_many_text'  => 'В запрос попадут только первые {1}. Продолжить?',
	'js_continue'       => 'Продолжить',
);
?>
