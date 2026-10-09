<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['ipsearch'] = array (
	// ── Page / header ──
	'page_title'         => 'Поиск по IP',
	'pane_title'         => 'Поиск по IP-адресу',
	'pane_sub'           => 'Поиск аккаунтов по IP регистрации и истории входов · IPv4 и IPv6',

	// ── Search form ──
	'lbl_placeholder'    => 'Введите IPv4 или IPv6 адрес',
	'btn_search'         => 'Найти',
	'hint_searched'      => 'Поиск идёт по IP регистрации (users) и истории входов (login_log). Например:',

	// ── Alerts / flash ──
	'err_invalid_title'  => 'Некорректный IP-адрес',
	'err_invalid_text'   => 'Введите корректный IPv4 или IPv6 адрес.',
	'flash_reset_ok'     => 'Passkey сброшен',
	'flash_reset_ok_txt' => 'Сгенерирован новый passkey. Пользователю нужно заново скачать .torrent-файлы своих раздач.',
	'flash_reset_fail'   => 'Passkey не сброшен',
	'flash_reset_fail_txt' => 'Истёк токен безопасности или пользователь уже удалён. Попробуйте ещё раз.',
	'err_title'          => 'Ошибка',
	'err_invalid_date'   => 'Неверная дата',

	// ── KPI tiles ──
	'kpi_registered'     => 'Регистраций с IP',
	'kpi_logins'         => 'Входов с IP',
	'kpi_unique'         => 'Уникальных аккаунтов',
	'opt_scope_private'  => 'Локальный / зарезервированный',
	'opt_scope_public'   => 'Публичный',

	// ── Empty state ──
	'sec_empty_title'    => 'Аккаунты не найдены',
	// {1} = IP (уже экранирован), выводится как есть
	'sec_empty_text'     => 'С адреса <code>{1}</code> никто не регистрировался и не входил.',

	// ── Result sections ──
	'sec_registered'       => 'Зарегистрировались с этого IP',
	'sec_registered_empty' => 'С этого IP никто не регистрировался.',
	'sec_logins'           => 'Входили с этого IP',
	'sec_logins_empty'     => 'В логе нет входов с этого IP.',

	// ── Table headers ──
	'lbl_username'       => 'Пользователь',
	'lbl_email'          => 'Email',
	'lbl_last_ip'        => 'Последний IP',
	'lbl_passkey'        => 'Passkey',
	'lbl_last_seen'      => 'Был на сайте',
	'lbl_registered'     => 'Регистрация',
	'lbl_uploaded'       => 'Отдал',
	'lbl_downloaded'     => 'Скачал',
	'lbl_ratio'          => 'Рейтинг',

	// ── Tooltips ──
	'tip_same_ip'        => 'Совпадает с искомым IP',
	'tip_search_ip'      => 'Искать по этому IP',
	'tip_copy_passkey'   => 'Скопировать passkey',
	'tip_reset_passkey'  => 'Сбросить passkey',

	// ── Action bar ──
	'btn_copy_ip'        => 'Скопировать IP',
	'btn_new_search'     => 'Новый поиск',

	// ── JS ──
	'js_invalid_ip'      => 'Введите корректный IPv4 или IPv6 адрес',
	'js_reset_title'     => 'Сбросить passkey?',
	// {1} = имя пользователя
	'js_reset_text'      => 'Сбросить passkey пользователю {1}? Ему придётся заново скачать все .torrent-файлы.',
	'js_reset_confirm'   => 'Да, сбросить',
	'js_reset_cancel'    => 'Отмена',
);
?>
