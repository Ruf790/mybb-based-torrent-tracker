<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cronjobs'] = array (
	// ── Page titles ──────────────────────────────────────────────
	'page_title'          => 'Задачи cron',
	'page_title_running'  => 'Запуск задачи...',

	// ── Header / KPI ─────────────────────────────────────────────
	'pane_title'          => 'Задачи cron',
	'pane_sub'            => 'Плановые задачи, на которых держится трекер, — интервалы, запуски и время выполнения',
	'btn_new_job'         => 'Новая задача',
	'kpi_jobs'            => 'Задач',
	'kpi_active'          => 'Активных',
	'kpi_overdue'         => 'Просрочено',
	'kpi_avg'             => 'Среднее время',
	'val_seconds'         => '{1} с',

	// ── Sections ─────────────────────────────────────────────────
	'sec_jobs'            => 'Плановые задачи',
	'sec_jobs_sub'        => 'Задач: {1} · активных: {2}',
	'sec_log'             => 'Журнал выполнения',
	'sec_log_sub'         => 'Последние 50 запусков',

	// ── Table headers ────────────────────────────────────────────
	'th_job'              => 'Задача',
	'th_every'            => 'Интервал',
	'th_last_run'         => 'Последний запуск',
	'th_next_run'         => 'Следующий запуск',
	'th_status'           => 'Статус',
	'th_file'             => 'Файл',
	'th_queries'          => 'Запросов',
	'th_duration'         => 'Длительность',
	'th_when'             => 'Когда',

	// ── Job rows: tags & labels ──────────────────────────────────
	'tag_missing'         => 'нет файла',
	'tag_overdue'         => 'просрочена',
	'tag_active'          => 'Активна',
	'tag_disabled'        => 'Отключена',
	'lbl_queries'         => 'запросов: {1}',
	'lbl_never'           => 'ни разу',
	'lbl_unknown'         => 'неизвестно',

	// ── Tooltips (title) ─────────────────────────────────────────
	'tip_missing'         => 'Файл не найден в /cron/',
	'tip_logged'          => 'Выполнение записывается в журнал',
	'tip_run'             => 'Запустить сейчас',
	'tip_edit'            => 'Изменить',
	'tip_enable'          => 'Включить',
	'tip_disable'         => 'Отключить',
	'tip_delete'          => 'Удалить',

	// ── Empty states / filter ────────────────────────────────────
	'empty_jobs'          => 'Задач cron пока нет',
	'btn_create_first'    => 'Создать первую задачу',
	'empty_log'           => 'Запусков в журнале пока нет',
	'ph_log_filter'       => 'Фильтр по файлу…',

	// ── Modal: Create / Edit ─────────────────────────────────────
	'pane_modal_new'      => 'Новая задача cron',
	'lbl_close'           => 'Закрыть',
	'lbl_loading'         => 'Загрузка…',
	'lbl_file'            => 'Файл',
	'hint_file'           => 'PHP-файл из папки <code>/cron/</code>, без пути', // raw HTML
	'lbl_description'     => 'Описание',
	'ph_description'      => 'Что делает эта задача?',
	'lbl_run_every'       => 'Интервал запуска',
	'lbl_active'          => 'Активна',
	'hint_active'         => 'Запускается по расписанию',
	'lbl_loglevel'        => 'Вести журнал',
	'hint_loglevel'       => 'Время выполнения и число запросов в журнале',
	'btn_cancel'          => 'Отмена',
	'btn_create'          => 'Создать задачу',

	// ── Modal: interval unit labels ──────────────────────────────
	'lbl_unit_months'     => 'месяцев',
	'lbl_unit_weeks'      => 'недель',
	'lbl_unit_days'       => 'дней',
	'lbl_unit_hours'      => 'часов',
	'lbl_unit_minutes'    => 'минут',

	// ── Modal: interval presets ──────────────────────────────────
	'opt_preset_5m'       => '5 мин',
	'opt_preset_15m'      => '15 мин',
	'opt_preset_30m'      => '30 мин',
	'opt_preset_1h'       => '1 час',
	'opt_preset_6h'       => '6 часов',
	'opt_preset_1d'       => '1 день',
	'opt_preset_1w'       => '1 неделя',

	// ── "Running cron" page ──────────────────────────────────────
	'run_heading'         => 'Выполняется задача cron',
	'run_wait'            => 'Подождите, пока задача выполнится...',
	'run_redirect'        => 'Возврат к списку через {1} с...', // raw HTML: {1} = countdown <span>

	// ── Status badges (render_status_badge) ──────────────────────
	'badge_active'        => 'АКТИВНА',
	'badge_disabled'      => 'ОТКЛЮЧЕНА',
	'badge_yes'           => 'ДА',
	'badge_no'            => 'НЕТ',

	// ── Errors ───────────────────────────────────────────────────
	'err_security_title'  => 'Ошибка безопасности',
	'err_security_token'  => 'Неверный ключ безопасности. Обновите страницу и попробуйте ещё раз.',
	'err_not_found'       => 'Не найдено',

	// ── Flash messages ───────────────────────────────────────────
	'flash_bad_filename'  => 'Недопустимое имя файла. Разрешены только латинские буквы, цифры, подчёркивание, дефис и расширение .php (без пути).',
	'flash_min_interval'  => 'Интервал запуска должен быть не меньше 1 минуты.',
	'flash_created'       => 'Новая задача cron создана!',
	'flash_updated'       => 'Задача cron обновлена!',
	'flash_enabled'       => 'Задача cron включена!',
	'flash_disabled'      => 'Задача cron отключена!',
	'flash_deleted'       => 'Задача cron удалена!',

	// ── JS strings (AGS_LANG, prefix js_ is stripped) ────────────
	'js_locale'           => 'ru', // Intl.PluralRules locale for unit words
	'js_sum_min_interval' => 'Выберите интервал не меньше 1 минуты',
	'js_sum_runs_every'   => 'Интервал запуска: {1}',
	'js_modal_title_new'  => 'Новая задача cron',
	'js_modal_title_edit' => 'Изменение задачи cron',
	'js_btn_create'       => 'Создать задачу',
	'js_btn_save'         => 'Сохранить',
	'js_btn_saving'       => 'Сохранение…',
	'js_load_failed'      => 'Не удалось загрузить задачу cron',
	'js_confirm_delete'   => 'Удалить задачу cron {1}?',

	// ── JS: unit words by plural form (one / few / many) ─────────
	'js_unit_months_one'  => 'месяц',
	'js_unit_months_few'  => 'месяца',
	'js_unit_months_many' => 'месяцев',
	'js_unit_weeks_one'   => 'неделя',
	'js_unit_weeks_few'   => 'недели',
	'js_unit_weeks_many'  => 'недель',
	'js_unit_days_one'    => 'день',
	'js_unit_days_few'    => 'дня',
	'js_unit_days_many'   => 'дней',
	'js_unit_hours_one'   => 'час',
	'js_unit_hours_few'   => 'часа',
	'js_unit_hours_many'  => 'часов',
	'js_unit_minutes_one' => 'минута',
	'js_unit_minutes_few' => 'минуты',
	'js_unit_minutes_many'=> 'минут',
	
	
	
);
?>
