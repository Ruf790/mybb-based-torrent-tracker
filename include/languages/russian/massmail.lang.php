<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['massmail'] = array (

	// ── Page titles (browser tab) ──
	'title_start'           => 'Рассылка',
	'title_send'            => 'Отправка',

	// ── Page headers ──
	'head_compose'          => 'Массовая рассылка',
	'head_compose_sub'      => 'Письмо получат все подтверждённые и активные пользователи выбранных групп. Отправка идёт пакетами с паузой между ними.',
	'head_sending'          => 'Идёт рассылка',
	'head_finished'         => 'Рассылка завершена',
	'head_subject'          => '«{1}»',

	// ── KPI tiles ──
	'kpi_eligible'          => 'Подходящих пользователей',
	'kpi_selected'          => 'Выбрано получателей',
	'kpi_batches'           => 'Пакетов',
	'kpi_eta'               => 'Примерное время',
	'kpi_recipients'        => 'Получателей',
	'kpi_batch'             => 'Пакет',
	'kpi_delivered'         => 'Доставлено',
	'kpi_failed'            => 'Ошибок',

	// ── Section titles ──
	'sec_message'           => 'Письмо',
	'sec_recipients'        => 'Получатели',
	'sec_pace'              => 'Скорость отправки',
	'sec_last'              => 'Последняя рассылка',
	'sec_progress'          => 'Прогресс',
	'sec_batch'             => 'Пакет {1} из {2}',

	// ── Field labels ──
	'lbl_subject'           => 'Тема',
	'lbl_body'              => 'Текст письма',
	'lbl_all_groups'        => 'Все группы',
	'lbl_batch_size'        => 'Размер пакета',
	'lbl_pause'             => 'Пауза',
	'lbl_started'           => 'Начата',
	'lbl_delivered'         => 'Доставлено',
	'lbl_failed'            => 'Ошибок',

	// ── Hints / tooltips ──
	'hint_subject_count'    => 'Символов: {1}',
	'hint_batch_size'       => 'Писем в одном пакете',
	'hint_pause'            => 'Секунд между пакетами',
	'hint_html'             => 'Можно использовать HTML. Стандартные шапка и подпись добавляются к каждому письму автоматически.',
	'hint_processed'        => 'Обработано {1} из {2}',
	'tip_group_count'       => 'Подтверждённые активные пользователи',

	// ── Placeholders ──
	'ph_subject'            => 'О чём это письмо?',
	'ph_message'            => 'Напишите текст письма. Можно использовать HTML.',

	// ── Accessibility (aria-label / title) ──
	'aria_view_tabs'        => 'Режим просмотра письма',
	'aria_preview_frame'    => 'Предпросмотр письма',
	'aria_sent'             => 'Отправлено',
	'aria_failed'           => 'Ошибка',

	// ── Tabs ──
	'tab_write'             => 'Редактор',
	'tab_preview'           => 'Предпросмотр',

	// ── Buttons ──
	'btn_clear'             => 'Очистить',
	'btn_send'              => 'Отправить',
	'btn_reuse'             => 'Повторить это письмо',
	'btn_new'               => 'Новая рассылка',
	'btn_pause'             => 'Пауза',
	'btn_stop'              => 'Остановить рассылку',

	// ── Status badges ──
	'status_stopped'        => 'Остановлена',
	'status_completed'      => 'Завершена',
	'status_unfinished'     => 'Не завершена',
	'status_batch_sent'     => 'Отправлено: {1}',
	'status_batch_failed'   => 'Ошибок: {1}',

	// ── Bottom bar ──
	'bar_pick_group'        => 'Выберите хотя бы одну группу, чтобы увидеть, кто получит письмо.',
	'bar_finished'          => 'Все пакеты обработаны. Доставлено: {1}, ошибок: {2}.',
	'bar_countdown'         => 'Пакет {1} начнётся через {2} с',

	// ── Empty states / alerts ──
	'empty_groups'          => 'Группы не найдены.',
	'empty_batch'           => 'В этом пакете нет адресов.',
	'alert_skipped'         => 'Этот пакет уже был отправлен, поэтому пропущен. Обновление страницы никогда не отправит один пакет дважды.',

	// ── Errors ──
	'err_title'             => 'Ошибка',
	'err_csrf'              => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте ещё раз.',
	'err_not_writable'      => 'Файл {1} не существует или недоступен для записи.',
	'err_write'             => 'Не удалось записать {1}. Проверьте права доступа.',
	'err_empty'             => 'Заполните тему и текст письма.',
	'err_no_groups'         => 'Выберите хотя бы одну группу.',
	'err_no_members'        => 'В выбранных группах нет подтверждённых активных пользователей.',
	'err_no_mailing'        => 'Нет рассылки, которую можно продолжить. Начните новую ниже.',

	// ── Notices ──
	'notice_was_stopped'    => 'Эта рассылка была остановлена. Больше ничего отправлено не будет.',
	'notice_finished'       => 'Эта рассылка уже завершена.',
	'notice_stopped'        => 'Рассылка остановлена. Больше ничего отправлено не будет.',

	// ── Durations ──
	'dur_h_m'               => '{1} ч {2} мин',
	'dur_m_s'               => '{1} мин {2} с',
	'dur_m'                 => '{1} мин',
	'dur_s'                 => '{1} с',

	// ── JS: general ──
	'js_locale'             => 'ru',
	'js_cancel'             => 'Отмена',
	'js_dur_h_m'            => '{1} ч {2} мин',
	'js_dur_m_s'            => '{1} мин {2} с',
	'js_dur_m'              => '{1} мин',
	'js_dur_s'              => '{1} с',

	// ── JS: plural forms of "batch" (Intl.PluralRules categories) ──
	'js_batches_one'        => 'пакет',
	'js_batches_few'        => 'пакета',
	'js_batches_many'       => 'пакетов',
	'js_batches_other'      => 'пакета',

	// ── JS: compose form ──
	'js_summary'            => 'Получателей: {1}, отправка в {2} {3}, примерно {4}.',
	'js_summary_no_members' => 'В выбранных группах нет подтверждённых активных пользователей.',
	'js_summary_pick'       => 'Выберите хотя бы одну группу, чтобы увидеть, кто получит письмо.',
	'js_err_subject_title'  => 'Не указана тема',
	'js_err_subject'        => 'Укажите тему перед отправкой.',
	'js_err_message_title'  => 'Пустое письмо',
	'js_err_message'        => 'Напишите текст письма перед отправкой.',
	'js_err_recipients_title' => 'Нет получателей',
	'js_err_no_groups'      => 'Выберите хотя бы одну группу.',
	'js_err_no_members'     => 'В выбранных группах нет подтверждённых активных пользователей.',

	// ── JS: dialogs ──
	'js_clear_title'        => 'Очистить форму?',
	'js_clear_text'         => 'Очистить тему, текст и выбранные группы?',
	'js_clear_body'         => 'Тема, текст письма и выбранные группы будут очищены.',
	'js_clear_confirm'      => 'Очистить',
	'js_send_title'         => 'Отправить это письмо?',
	'js_send_text'          => 'Отправить «{1}»? Получателей: {2}.',
	'js_send_body'          => 'Получателей: {1}, отправка в {2} {3}, примерно {4}. Не закрывайте вкладку, пока идёт рассылка.',
	'js_send_confirm'       => 'Отправить',
	'js_starting'           => 'Запуск…',
	'js_stop_title'         => 'Остановить рассылку?',
	'js_stop_text'          => 'Остановить рассылку? Уже отправленные пакеты останутся отправленными.',
	'js_stop_body'          => 'Оставшиеся пакеты отправлены не будут. Уже ушедшие письма отозвать нельзя.',
	'js_stop_confirm'       => 'Остановить',

	// ── JS: sending page ──
	'js_pause'              => 'Пауза',
	'js_resume'             => 'Продолжить',
	
	// ── Mail header / footer (plain text; {1} = site name, {2} = date in GMT; in mail_footer_team {1} = link to the site) ──
	'mail_header'          => 'Сообщение от {1}, отправлено {2} GMT.',
	'mail_footer_greeting' => 'С уважением,',
	'mail_footer_team'     => 'команда {1}.',
	
	
);
?>
