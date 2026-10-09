<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

/**
 * Языковой файл: admin/usersearch.php (Поиск пользователей) + admin/scripts/usersearch.js
 * {1}, {2}… — плейсхолдеры, подставляются через ags_fmt() в PHP и t() в JS.
 * Все строки — чистый текст (без HTML).
 */

$language['usersearch'] = array (

	// ── Page ──
	'page_title'          => 'Поиск пользователей',
	'btn_latest'          => 'Новые пользователи',
	'sec_latest'          => 'Последние зарегистрированные',
	'sec_found'           => 'Найдено пользователей: {1}',
	'msg_no_users'        => 'Пользователи не найдены.',

	// ── Stats (KPI tiles) ──
	'stat_total'          => 'Всего пользователей',
	'stat_active'         => 'Активные',
	'stat_banned'         => 'Забаненные',
	'stat_new7'           => 'Новые (7 дн.)',
	'stat_today'          => 'Новые за сутки',
	'stat_online'         => 'Онлайн (15 мин)',

	// ── Quick filters ──
	'qf_title'            => 'Быстрые фильтры:',
	'qf_banned'           => 'Забаненные',
	'qf_today'            => 'Новые за сутки',
	'qf_new7'             => 'Новые за 7 дней',
	'qf_latest10'         => 'Последние 10',
	'qf_active'           => 'Активные',

	// ── Search form: labels ──
	'lbl_username'        => 'Ник',
	'lbl_email'           => 'Email',
	'lbl_group'           => 'Группа',
	'lbl_status'          => 'Статус',
	'lbl_regip'           => 'IP при регистрации',
	'lbl_lastip'          => 'Последний IP',
	'lbl_country'         => 'Страна',
	'lbl_exactmatch'      => 'Точное совпадение ника',
	'lbl_reg_from'        => 'Регистрация с',
	'lbl_reg_to'          => 'Регистрация по',
	'lbl_active_from'     => 'Активность с',
	'lbl_active_to'       => 'Активность по',
	'lbl_min_up'          => 'Отдано от (МБ)',
	'lbl_max_up'          => 'Отдано до (МБ)',
	'lbl_min_ratio'       => 'Рейтинг от',
	'lbl_max_ratio'       => 'Рейтинг до',
	'lbl_warnings'        => 'Предупреждения',
	'lbl_orderby'         => 'Сортировка',
	'lbl_direction'       => 'Порядок',

	// ── Search form: placeholders ──
	'ph_username'         => 'Ник',
	'ph_email'            => 'Email',
	'ph_regip'            => 'IP при регистрации',
	'ph_lastip'           => 'Последний IP',
	'ph_country'          => 'Страна',
	'ph_date'             => 'ГГГГ-ММ-ДД',

	// ── Select options ──
	'opt_all_groups'      => 'Все группы',
	'opt_all'             => 'Все',
	'opt_active'          => 'Активные',
	'opt_banned'          => 'Забаненные',
	'opt_username'        => 'Ник',
	'opt_email'           => 'Email',
	'opt_id'              => 'ID',
	'opt_asc'             => 'По возрастанию',
	'opt_desc'            => 'По убыванию',
	'opt_change_group'    => 'Сменить группу...',

	// ── Buttons ──
	'btn_search'          => 'Найти',
	'btn_clear'           => 'Сбросить',
	'btn_ban'             => 'Забанить',
	'btn_unban'           => 'Разбанить',
	'btn_pm'              => 'Написать ЛС',
	'btn_apply'           => 'Применить',
	'btn_delete'          => 'Удалить',
	'btn_clear_sel'       => 'Снять выбор',

	// ── Bulk action bar ──
	// {1} = элемент-счётчик (выводится как есть)
	'bulk_selected'       => 'Выбрано пользователей: {1}',

	// ── Tooltips / title / aria-label / alt ──
	'tip_clear'           => 'Очистить',
	'tip_online'          => 'Сейчас на сайте',
	'tip_last_seen'       => 'Был(а) на сайте: {1}',
	'tip_delete'          => 'Удалить',
	'tip_change_avatar'   => 'Нажмите, чтобы сменить аватар',
	'tip_toggle_passkey'  => 'Показать/скрыть',
	'tip_copy_passkey'    => 'Копировать',
	'tip_select_all'      => 'Выбрать всех',
	'alt_avatar'          => 'аватар',

	// ── Table headers ──
	'th_id'               => 'ID',
	'th_avatar'           => 'Аватар',
	'th_username'         => 'Ник',
	'th_email'            => 'Email',
	'th_group'            => 'Группа',
	'th_ips'              => 'IP рег./последний',
	'th_updown'           => 'Отдано/Скачано',
	'th_ratio'            => 'Рейтинг',
	'th_info'             => 'Инфо',
	'th_actions'          => 'Действия',

	// ── Badges ──
	'badge_warned'        => 'Предупреждён ×{1}',
	'badge_donor'         => 'Донор',

	// ── Bulk actions: results (JSON → toast) ──
	'flash_banned'        => 'Забанено пользователей: {1}',
	'flash_unbanned'      => 'Разбанено пользователей: {1}',
	'flash_pm_sent'       => 'Отправлено ЛС: {1}',
	'flash_group_changed' => 'Перенесено в группу {2}: {1}',
	'flash_group_skipped' => '(часть пропущена: суперадмин или ваш собственный аккаунт)',
	'flash_deleted'       => 'Удалено пользователей: {1}',
	'flash_del_super'     => '(у вас нет прав удалять аккаунт суперадминистратора)',
	'flash_del_self'      => '(часть пропущена: ваш собственный аккаунт)',

	// ── Errors ──
	'err_prefix'          => 'Ошибка: {1}',
	'err_security'        => 'Ошибка проверки безопасности',
	'err_security_refresh'=> 'Ошибка проверки безопасности. Обновите страницу и попробуйте ещё раз.',
	'err_no_users'        => 'Не выбрано ни одного пользователя',
	'err_pm_required'     => 'Укажите тему и текст сообщения',
	'err_invalid_group'   => 'Неверная группа',
	'err_no_eligible'     => 'Нет подходящих пользователей (суперадмины и ваш собственный аккаунт защищены)',
	'err_unknown_action'  => 'Неизвестное действие',
	'err_count_query'     => 'Ошибка запроса подсчёта: {1}',
	'err_data_query'      => 'Ошибка запроса данных: {1}',

	// ── Avatar upload (server) ──
	'err_av_not_logged'   => 'Вы не авторизованы',
	'err_av_no_uid'       => 'Не указан uid профиля',
	'err_av_no_perm'      => 'Нет прав менять этот аватар',
	'err_av_no_file'      => 'Файл не загружен',
	'msg_av_updated'      => 'Аватар обновлён',

	// ── JS: passkey / common ──
	'js_passkey_copied'   => 'Пасскей скопирован!',
	'js_copy_failed'      => 'Не удалось скопировать',
	'js_select_group'     => 'Сначала выберите группу',
	'js_users_selected'   => 'Выбрано пользователей: {1}',
	'js_ids'              => 'ID: {1}',
	'js_cancel'           => 'Отмена',
	'js_processing'       => 'Обработка пользователей: {1}…',
	'js_error'            => 'Произошла ошибка',
	'js_request_failed'   => 'Ошибка запроса',

	// ── JS: ban modal ──
	'js_ban_title'        => 'Бан пользователей',
	'js_ban_duration'     => 'Срок бана',
	'js_ban_reason'       => 'Причина бана',
	'js_ban_reason_ph'    => 'Укажите причину бана...',
	'js_ban_reason_hint'  => 'Необязательно. Не более 255 символов.',
	'js_ban_confirm'      => 'Забанить ({1})',
	'js_banning'          => 'Баним...',

	// ── JS: ban durations ──
	'js_bt_1d'            => '1 день',
	'js_bt_2d'            => '2 дня',
	'js_bt_3d'            => '3 дня',
	'js_bt_4d'            => '4 дня',
	'js_bt_5d'            => '5 дней',
	'js_bt_6d'            => '6 дней',
	'js_bt_1w'            => '1 неделя',
	'js_bt_2w'            => '2 недели',
	'js_bt_3w'            => '3 недели',
	'js_bt_1m'            => '1 месяц',
	'js_bt_2m'            => '2 месяца',
	'js_bt_3m'            => '3 месяца',
	'js_bt_4m'            => '4 месяца',
	'js_bt_5m'            => '5 месяцев',
	'js_bt_6m'            => '6 месяцев',
	'js_bt_1y'            => '1 год',
	'js_bt_2y'            => '2 года',
	'js_bt_perm'          => 'Навсегда',

	// ── JS: delete modal ──
	'js_del_title'        => 'Массовое удаление',
	'js_del_question'     => 'Вы уверены, что хотите удалить эти аккаунты?',
	'js_del_warning'      => 'Действие необратимо. Все данные пользователей будут удалены безвозвратно.',
	'js_del_confirm'      => 'Да, удалить аккаунты ({1})',
	'js_deleting'         => 'Удаляем...',

	// ── JS: PM modal ──
	'js_pm_title'         => 'Отправить ЛС',
	'js_pm_subject'       => 'Тема',
	'js_pm_subject_ph'    => 'Тема...',
	'js_pm_message'       => 'Сообщение',
	'js_pm_message_ph'    => 'Текст сообщения...',
	'js_pm_confirm'       => 'Отправить ({1})',
	'js_pm_fill'          => 'Заполните тему и текст сообщения',
	'js_sending'          => 'Отправляем...',

	// ── JS: avatar upload ──
	'js_av_bad_type'      => 'Допустимы JPG/JPEG/PNG/GIF/WebP',
	'js_av_too_big'       => 'Файл слишком большой (макс. {1} МБ)',
	'js_uploading'        => 'Загрузка…',
	'js_av_failed'        => 'Не удалось загрузить',
	'js_av_error'         => 'Ошибка загрузки',
	'js_alt_avatar'       => 'аватар',

);
?>
