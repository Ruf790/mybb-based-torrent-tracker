<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cheat_attempts'] = array (
	// ── Page ──────────────────────────────────────────────
	'page_title'          => 'Попытки читерства',
	'pane_title'          => 'Попытки читерства',

	// ── Stats tiles / filters ─────────────────────────────
	'lbl_total_records'   => 'Всего записей',
	'lbl_high_severity'   => 'Высокая опасность',
	'lbl_medium_severity' => 'Средняя опасность',
	'btn_filter_high'     => 'Только высокая',
	'btn_filter_all'      => 'Все',

	// ── Table columns ─────────────────────────────────────
	'th_user'             => 'Пользователь',
	'th_date'             => 'Дата',
	'th_torrent'          => 'Раздача',
	'th_reason'           => 'Причина',
	'th_detail'           => 'Подробности',
	'th_severity'         => 'Опасность',
	'th_ip'               => 'IP',
	'th_ban'              => 'Бан',
	'th_warn'             => 'Пред.',
	'th_delete'           => 'Удал.',

	// ── Severity options ──────────────────────────────────
	'opt_severity_high'   => 'Высокая',
	'opt_severity_medium' => 'Средняя',
	'opt_severity_low'    => 'Низкая',

	// ── Buttons ───────────────────────────────────────────
	'btn_all_ban'         => 'Забанить всех',
	'btn_all_warn'        => 'Предупредить всех',
	'btn_all_delete'      => 'Удалить все',
	'btn_apply'           => 'Применить',
	'btn_autoban'         => 'Автобан (5+/ч)',

	// ── Flash messages ────────────────────────────────────
	'flash_banned'        => 'Пользователи забанены',
	'flash_warned'        => 'Пользователям выдано предупреждение',
	'flash_deleted'       => 'Записи удалены',
	'flash_autoban'       => 'Автобан выполнен, забанено пользователей: {1}',

	// ── Warning PM ────────────────────────────────────────
	'pm_warn_subject'     => 'Предупреждение: обнаружена подозрительная активность',
	'pm_warn_message'     => 'Ваш аккаунт отмечен из-за подозрительной статистики отдачи. Если вы считаете, что это ошибка, свяжитесь с администрацией.',

	// ── Reason labels ─────────────────────────────────────
	'reason_fake_completed_event'         => '🎭 Ложное завершение',
	'reason_completed_without_download'   => '📥 Завершение без скачивания',
	'reason_fake_seeding'                 => '🌱 Фальшивый сид',
	'reason_peer_id_changed'              => '🔄 Смена клиента',
	'reason_suspicious_peer_id'           => '🕵️ Подозрительный клиент',
	'reason_negative_values'              => '➖ Отрицательные значения',
	'reason_completed_while_seeding'      => '⚡ Уже на раздаче',
	'reason_speed_anomaly'                => '🚀 Невозможная скорость',
	'reason_port_changed'                 => '🔌 Смена порта',
	'reason_announce_spam'                => '📢 Спам анонсами',
	'reason_banned_cheat_client'          => '🚫 Запрещённый клиент',
	'reason_instant_stop_after_complete'  => '⏱️ Мгновенный уход с раздачи',
	'reason_impossible_ratio_new_torrent' => '📊 Невозможный рейтинг',
	'reason_extreme_ratio'                => '📈 Аномальный рейтинг',
	'reason_empty_user_agent'             => '👻 Без User-Agent',
	'reason_seed_with_left'               => '🌱 Сид с недокачкой',
	'reason_fake_completed_no_data'       => '🎭 Ложное завершение (без данных)',
	'reason_multi_ip_same_peer_id'        => '🌐 Несколько IP',
	'reason_too_many_torrents_single_ip'  => '🌊 Флуд с одного IP',

	// ── Detail descriptions (output as-is) ────────────────
	'detail_announce_spam'                => 'Повторный анонс всего через {1} с (минимум — 30 с)',
	'detail_speed_anomaly'                => 'Средняя скорость отдачи: {1} МБ/с — физически невозможно',
	'detail_negative_values'              => 'Клиент передал отрицательные значения отдачи/загрузки — возможная попытка эксплойта',
	'detail_peer_id_changed'              => 'Клиент сменился: {1} → {2}',
	'detail_fake_completed_event'         => 'Сообщил о завершении загрузки, но осталось ещё {1}',
	'detail_fake_completed_no_data'       => 'Отправил событие «completed», но за сессию ничего не скачал',
	'detail_fake_seeding'                 => 'Числится сидом, но раздача не докачана (осталось {1})',
	'detail_multi_ip_same_peer_id'        => 'Один и тот же ID клиента замечен с разных IP-адресов: {1}',
	'detail_extreme_ratio'                => 'Подозрительно высокий рейтинг: {1}:1',
	'detail_instant_stop_after_complete'  => 'Ушёл с раздачи через {1} с после завершения загрузки',
	'detail_banned_cheat_client'          => 'Используется известный клиент для накрутки рейтинга',
	'unit_mb'                             => 'МБ',

	// ── JS strings ────────────────────────────────────────
	'js_autoban_confirm'  => 'Забанить пользователей, у которых 5+ нарушений высокой опасности за последний час?',
);
?>
