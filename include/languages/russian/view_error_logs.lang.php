<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['view_error_logs'] = array (
	// ── Page ──
	'page_title'               => 'Логи ошибок',
	'head_title'               => 'Просмотр логов ошибок',
	'head_subtitle'            => 'Логи PHP и трекера из <code>/error_logs</code>, самый свежий файл — первым',

	// ── Errors / alerts ──
	'err_invalid_log'          => 'Такого лог-файла нет или к нему нет доступа. Выберите файл из списка ниже.',
	'err_not_found'            => 'Лог-файл не найден.',

	// ── Flash messages ──
	'flash_csrf'               => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте ещё раз.',
	'flash_deleted'            => 'Файл {1} удалён.',
	'flash_delete_failed'      => 'Не удалось удалить {1}.',
	'flash_deleted_all'        => 'Удалено лог-файлов: {1}.',
	'flash_none_deleted'       => 'Ни один лог не был удалён.',

	// ── KPI tiles ──
	'kpi_files'                => 'Лог-файлов',
	'kpi_total_size'           => 'Занято на диске',
	'kpi_last_write'           => 'Последняя запись',
	'kpi_errors'               => 'Ошибок в этом файле',

	// ── Time / units ──
	'time_just_now'            => 'только что',
	'time_min_ago'             => '{1} мин назад',
	'time_h_ago'               => '{1} ч назад',
	'time_d_ago'               => '{1} дн. назад',
	'unit_b'                   => 'Б',
	'unit_kb'                  => 'КБ',
	'unit_mb'                  => 'МБ',
	'unit_gb'                  => 'ГБ',

	// ── Toolbar ──
	'lbl_log_file'             => 'Лог-файл',
	'opt_choose_log'           => '— выберите лог-файл —',
	'hint_search'              => 'Поиск по логу — поддерживается regex',
	'lbl_search'               => 'Поиск по логу',
	'tip_search_key'           => 'Нажмите /, чтобы перейти к поиску',
	'tip_clear'                => 'Очистить (Esc)',
	'lbl_clear'                => 'Очистить поиск',

	// ── Log panel ──
	'meta_lines'               => 'Строк: {1}',
	'lbl_filter_level'         => 'Фильтр по уровню',
	'chip_all'                 => 'Все',
	'tip_copy_line'            => 'Копировать строку',
	'no_match'                 => 'Совпадений нет',

	// ── Log levels ──
	'lvl_error'                => 'Ошибки',
	'lvl_warning'              => 'Предупреждения',
	'lvl_security'             => 'Безопасность',
	'lvl_install'              => 'Установка',
	'lvl_notice'               => 'Уведомления',
	'lvl_default'              => 'Прочее',

	// ── Empty states ──
	'empty_read_failed_title'  => 'Не удалось прочитать файл',
	'empty_read_failed_text'   => 'Проверьте права доступа к файлу на сервере.',
	'empty_log_title'          => 'Лог пуст',
	'empty_log_text'           => 'Сюда пока ничего не записано.',
	'empty_none_title'         => 'Лог-файлов нет',
	'empty_none_text'          => 'Каталог логов ошибок пуст.',
	'empty_select_title'       => 'Лог-файл не выбран',
	'empty_select_text'        => 'Выберите файл в списке выше, чтобы посмотреть его содержимое.',

	// ── Action bar ──
	'bar_files_summary'        => 'Файлов: {1} · {2}',
	'tip_jump_top'             => 'К первой строке',
	'tip_jump_bottom'          => 'К последней строке',
	'btn_download'             => 'Скачать',
	'btn_delete'               => 'Удалить',
	'btn_delete_all'           => 'Удалить все',
	'lbl_close'                => 'Закрыть',

	// ── JS strings ──
	'js_invalid_regex'         => 'Некорректный regex — поиск как по обычному тексту',
	'js_found'                 => 'Показано {1} из {2}',
	'js_cancel'                => 'Отмена',
	'js_del_one_title'         => 'Удалить этот лог?',
	'js_del_one_note'          => 'Это действие нельзя отменить.',
	'js_del_one_plain'         => 'Удалить {1}? Это действие нельзя отменить.',
	'js_del_one_confirm'       => 'Удалить файл',
	'js_del_all_title'         => 'Удалить все лог-файлы ({1})?',
	'js_del_all_note'          => 'Будет безвозвратно удалено {1} истории логов.',
	'js_del_all_plain'         => 'Удалить все лог-файлы ({1})? Это действие нельзя отменить.',
	'js_del_all_confirm'       => 'Удалить все',
);
?>
