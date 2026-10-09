<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['passkeysearch'] = array (
	// ── Page ──
	'title'               => 'Поиск по пасскею',
	'subtitle'            => 'Узнайте, чей это аккаунт: вставьте пасскей, announce-ссылку или ссылку на .torrent',

	// ── Search form ──
	'ph_passkey'          => 'Пасскей из 32 символов или announce-ссылка…',
	'btn_clear'           => 'Очистить',
	'btn_search'          => 'Найти',
	'hint_format'         => '32 шестнадцатеричных символа (0-9, a-f)',

	// ── Flash messages (output as HTML) ──
	'flash_bad_method'    => 'Недопустимый метод запроса.',
	'flash_csrf'          => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте ещё раз.',
	'flash_reset_invalid' => 'Неверный пасскей — он должен состоять из 32 шестнадцатеричных символов.',
	'flash_reset_ok'      => 'Пасскей пользователя <strong>{1}</strong> сброшен. Ему понадобится новый пасскей, а все .torrent-файлы придётся скачать заново.',
	'flash_reset_none'    => 'Ни у одного пользователя нет такого пасскея (возможно, его уже сбросили).',
	'flash_empty'         => 'Введите пасскей.',
	'flash_invalid'       => 'Неверный пасскей — нужно ровно 32 шестнадцатеричных символа (0-9, a-f).',
	'flash_not_found'     => 'Ни у одного зарегистрированного пользователя нет такого пасскея.',
	'txt_unknown_user'    => '(неизвестен)',

	// ── Profile card ──
	'lbl_online'          => 'В сети',
	'lbl_offline'         => 'Не в сети',
	'lbl_online_now'      => 'Сейчас на сайте',
	'lbl_last_seen'       => 'Последний визит: {1}',
	'lbl_never'           => 'никогда',
	'lbl_member'          => 'Пользователь',
	'lbl_id'              => 'ID',
	'btn_open_profile'    => 'Открыть профиль',
	'lbl_email'           => 'Email',
	'lbl_ip'              => 'IP',
	'lbl_joined'          => 'Регистрация',
	'lbl_last_active'     => 'Последняя активность',

	// ── KPI tiles ──
	'kpi_uploaded'        => 'Отдано',
	'kpi_downloaded'      => 'Скачано',
	'kpi_ratio'           => 'Рейтинг',
	'kpi_active'          => 'Сейчас активно',
	'kpi_seed'            => 'сид',
	'kpi_leech'           => 'лич',

	// ── Passkey section ──
	'sec_passkey'         => 'Пасскей',
	'sec_passkey_sub'     => 'Привязан к этому аккаунту',
	'btn_copy'            => 'Копировать',

	// ── Reset warning ──
	'reset_title'         => 'Сброс пасскея',
	'reset_li_sessions'   => 'остановит все .torrent-файлы, скачанные с ним, — сейчас активных сессий: <strong>{1}</strong>;',
	'reset_li_redownload' => 'пользователю придётся заново скачать свои .torrent-файлы;',
	'reset_li_logged'     => 'будет записан в лог сайта.',
	'btn_reset'           => 'Сбросить пасскей',

	// ── Intro cards ──
	'intro_who_title'     => 'Чей это ключ?',
	'intro_who_text'      => 'У каждого аккаунта свой пасскей из 32 символов — он вшит в каждый скачанный им .torrent.',
	'intro_url_title'     => 'Вставьте ссылку',
	'intro_url_text'      => 'Подойдёт и announce-ссылка вида …/announce.php?passkey=… — ключ извлечётся автоматически.',
	'intro_leak_title'    => 'Ключ утёк?',
	'intro_leak_text'     => 'Сбросьте его прямо из результатов поиска: старые .torrent-файлы сразу перестанут работать.',

	// ── JS strings ──
	'js_confirm_reset'    => 'Сбросить пасскей пользователя {1}?',
	'js_confirm_files'    => '• Все его .torrent-файлы перестанут работать',
	'js_confirm_sessions' => '• Будут разорваны активные сессии: {1}',
	'js_copy'             => 'Копировать',
	'js_copied'           => 'Скопировано',
	'js_hint_valid'       => 'Похоже на корректный пасскей',
	'js_hint_format'      => '32 шестнадцатеричных символа (0-9, a-f)',
);
?>
