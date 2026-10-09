<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['hit_and_run'] = array (
	// ── Page titles ──
	'title_main'            => 'Hit & Run — поиск нарушителей',
	'title_compose'         => 'Hit & Run — отправка предупреждений',

	// ── Header ──
	'pane_main'             => 'Поиск Hit &amp; Run',
	'pane_main_sub'         => 'Скачали раздачу, ушли с неё и не выполнили правила раздачи',
	'pane_compose'          => 'Предупреждения за Hit &amp; Run',
	'pane_compose_sub'      => 'Выбрано скачиваний: <b>{1}</b> — каждому придёт ЛС',

	// ── KPI tiles ──
	'stat_violations'       => 'Нарушений',
	'stat_users'            => 'Пользователей',
	'stat_warned_7d'        => 'Предупреждено · 7 дней',
	'stat_ban_threshold'    => 'Порог бана',
	'stat_warns_value'      => '{1} пред.',

	// ── Filters & search ──
	'tab_low_ratio'         => 'Низкий рейтинг',
	'tab_seedtime'          => 'Время сидирования',
	'ph_search'             => 'Поиск...',
	'opt_username'          => 'Ник',
	'opt_userid'            => 'ID пользователя',
	'opt_torrentid'         => 'ID торрента',
	'btn_search'            => 'Найти',
	'lbl_filters'           => 'Фильтры:',
	'chip_torrent'          => 'Торрент #{1}',
	'chip_user'             => 'Пользователь #{1}',
	'chip_search'           => 'Поиск: {1}',
	'tip_remove_filter'     => 'Убрать фильтр',

	// ── Selection mode ──
	'type_seedtime'         => 'Сидировал меньше, чем качал',
	'type_ratio'            => 'Рейтинг ниже {1}',

	// ── Toolbar ──
	'lbl_selected'          => 'Выбрано:',
	'btn_warn'              => 'Предупредить',
	'btn_ban'               => 'Забанить',

	// ── Table ──
	'aria_select_all'       => 'Выбрать все',
	'aria_select'           => 'Выбрать',
	'col_user'              => 'Пользователь',
	'col_torrent'           => 'Торрент',
	'col_uploaded'          => 'Отдано / сид',
	'col_downloaded'        => 'Скачано / лич',
	'col_ratio'             => 'Рейтинг',
	'col_warns'             => 'Пред.',
	'tip_already_warned'    => 'Уже предупреждён за последние 7 дней',
	'tip_only_user'         => 'Показать только этого пользователя',
	'tip_only_torrent'      => '{1} — показать только эту раздачу',
	'tip_seeders'           => 'Сиды',
	'tip_leechers'          => 'Личи',
	'tip_open_torrent'      => 'Открыть раздачу',
	'lbl_never_seeded'      => 'ни разу не сидировал',
	'tip_seed_bar'          => 'Сид {1}% / лич {2}%',
	'lbl_warned_ago'        => 'Предупреждён {1}',
	'empty_title'           => 'Ничего не найдено',
	'empty_text'            => 'Под текущий фильтр нарушений нет.',
	'hint_table'            => 'Предупреждённых за последние 7 дней повторно выбрать нельзя. Клик по пользователю или раздаче — фильтр по ним.',

	// ── Ban modal ──
	'modal_ban_title'       => 'Бан пользователей',
	'modal_ban_question'    => 'Забанить пользователей: {1}?',
	'modal_ban_text'        => 'Аккаунты будут отключены и переведены в группу забаненных. Банится весь аккаунт, а не только эта раздача.',
	'btn_cancel'            => 'Отмена',
	'aria_close'            => 'Закрыть',

	// ── Compose page ──
	'lbl_message'           => 'Сообщение',
	'lbl_placeholders'      => 'Подстановки (клик — вставить):',
	'hint_compose'          => 'Рейтинг считается на сервере; кого уже предупредили за последние 7 дней — пропускаются.',
	'btn_back'              => 'Назад',
	'btn_reset'             => 'Сбросить',
	'btn_send'              => 'Отправить предупреждения ({1})',
	// {1} = мин. рейтинг (настройка hr_min_ratio), {2} = лимит предупреждений (ban_user_limit); {torrentinfo} и т.п. — для каждого пользователя
	'msg_default_warn'      => "Привет!\n\nМы заметили Hit & Run на раздаче:\n{torrentinfo}\nВаш текущий рейтинг на этой раздаче: {showratio}\n\nУ вас есть неделя, чтобы поднять рейтинг на этой раздаче до {1}, иначе вы получите ещё одно предупреждение.\n\nЕсли торрента уже нет на вашем компьютере, скачайте его заново по ссылке:\n{torrentdownloadinfo}\n\nОбратите внимание: при {2} предупреждениях аккаунт будет забанен.\n\nХорошего дня!",
	'pm_subject'            => '⚠️ Предупреждение!',

	// ── Flash messages ──
	'flash_error'           => 'Ошибка!',
	'flash_success'         => 'Готово!',
	'flash_warning'         => 'Внимание!',
	'flash_csrf'            => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте ещё раз.',
	'flash_banned'          => 'Пользователи забанены.',
	'flash_no_valid'        => 'Среди выбранных нет реальных нарушителей Hit & Run.',
	'flash_warned'          => 'Предупреждено пользователей: {1}',

	// ── JS ──
	'js_banning'            => 'Баним...',
);
?>
