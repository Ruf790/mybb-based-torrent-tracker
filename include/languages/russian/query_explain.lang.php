<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['query_explain'] = array (
	// ── Страница ──
	'page_title'           => 'РЕЖИМ ОТЛАДКИ',
	'no_queries'           => 'Нет запросов для показа.',
	'err_security'         => 'Неверный ключ безопасности. Обновите страницу и попробуйте снова.',

	// ── Оценка скорости запроса ──
	'speed_excellent'      => 'Отлично',
	'speed_good'           => 'Хорошо',
	'speed_regular'        => 'Средне',
	'speed_bad'            => 'Плохо',

	// ── Карточки запросов ──
	'hdr_select'           => '#{1} — Запрос SELECT',
	'hdr_read'             => '#{1} — Запрос на чтение',
	'hdr_write'            => '#{1} — Запрос {2}',
	'hdr_deep'             => '#{1} — Подробный план (EXPLAIN ANALYZE)',
	'badge_read_only'      => 'Только чтение',
	'badge_write'          => 'Запись',
	'badge_deep'           => 'Глубокий анализ',
	'explain_rows'         => 'Строк в EXPLAIN: {1}',
	'query_num'            => 'Запрос #{1}',
	'measured_time'        => 'Замеренное время:',
	'executed_analyze'     => 'Выполнен с ANALYZE',
	'affected_rows'        => 'Затронуто строк: {1}',
	'insert_id'            => 'ID вставки: {1}',
	'warn_level'           => 'Уровень',
	'warn_code'            => 'Код',
	'warn_message'         => 'Сообщение',

	// ── Ошибки запроса ──
	'err_no_db'            => 'Нет соединения с базой данных',
	'err_empty_query'      => 'Пустой запрос.',
	'err_multi_statements' => 'Несколько запросов подряд не допускаются.',
	'err_explain_failed'   => 'Ошибка EXPLAIN:',

	// ── Статистика производительности ──
	'stats_title'          => 'Статистика производительности',
	'time_distribution'    => 'Распределение времени',
	'total_queries'        => 'Всего запросов:',
	'displayed'            => 'Показано:',
	'hidden'               => 'Скрыто:',
	'total_time'           => 'Общее время:',
	'system_info'          => 'Сведения о системе',
	'php_version'          => 'Версия PHP:',
	'mysql_version'        => 'Версия MySQL:',
	'server_load'          => 'Нагрузка сервера:',
	'memory_usage'         => 'Память:',
	'memory_peak'          => 'Пик памяти:',
	'gzip'                 => 'Сжатие GZip:',
	'enabled'              => 'Включено',
	'disabled'             => 'Выключено',
	'unknown'              => 'Неизвестно',
	'php_extensions'       => 'Расширения PHP',
	'ext_loaded'           => 'есть',
	'ext_missing'          => 'нет',

	// ── Сводка ──
	'summary_title'        => 'Сводка по скорости',
	'fast_queries'         => 'Быстрые запросы (<10 мс)',
	'medium_queries'       => 'Средние запросы (10–100 мс)',
	'slow_queries'         => 'Медленные запросы (>100 мс)',
	'hidden_non_select'    => 'Скрыто запросов не-SELECT: {1}',

	// ── Обращения к кэшу ──
	'cache_calls_title'    => 'Обращения к кэшу (всего {1}, {2})',
	'hdr_cache_call'       => '#{1} — {2}',
	'call_time'            => 'Время:',
	'call_num'             => 'Обращение #{1}',

	// ── Подключённые файлы ──
	'included_files'       => 'Подключённые файлы',
	'files_count'          => 'Файлов: {1}',
	'badge_core'           => 'Ядро',
);
