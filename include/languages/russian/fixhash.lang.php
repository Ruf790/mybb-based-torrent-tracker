<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['fixhash'] = array (
	// ── Page ──
	'title'              => 'Исправление хешей торрентов',
	'subtitle'           => 'Сверяет info_hash в базе данных с реальным v1-хешем каждого .torrent-файла.',

	// ── KPI ──
	'kpi_total'          => 'Всего торрентов',
	'kpi_mismatch'       => 'Нужно исправить на странице',
	'kpi_ok'             => 'Совпадают на странице',
	'kpi_missing'        => 'Нет файла или не читается',

	// ── Toolbar ── (hint_preview выводится как есть, HTML допустим)
	'hint_preview'       => 'Здесь расхождения только <strong>показываются</strong>. В базу ничего не пишется, пока вы не примените исправления.',
	'lbl_auto'           => 'Автоисправление каждые 10 с',
	'btn_apply_auto'     => 'Применить',

	// ── Table ──
	'th_torrent'         => 'Торрент',
	'th_stored'          => 'Хеш в базе',
	'th_real'            => 'Реальный хеш',
	'th_status'          => 'Статус',
	'empty'              => 'На этой странице нет торрентов.',
	'tip_copy'           => 'Скопировать хеш',
	'note_file_missing'  => 'Файл отсутствует',

	// ── Status badges ──
	'badge_ok'           => 'Совпадает',
	'badge_mismatch'     => 'Нужно исправить',
	'badge_missing'      => 'Нет файла',
	'badge_nov1'         => 'Только v2',
	'badge_error'        => 'Не читается',

	// ── Pagination ──
	'aria_pagination'    => 'Навигация по страницам',
	'tip_first'          => 'Первая страница',
	'tip_last'           => 'Последняя страница',
	'btn_prev'           => 'Назад',
	'btn_next'           => 'Вперёд',

	// ── Action bar ── (progress_page выводится как есть, HTML допустим)
	'progress_page'      => 'Страница <strong>{1}</strong> из <strong>{2}</strong>',
	'progress_to_fix'    => 'К исправлению здесь: {1}',
	'auto_running'       => 'Автоисправление запущено',
	'btn_stop'           => 'Остановить',
	'btn_apply'          => 'Исправить на этой странице',

	// ── Errors (JSON) ──
	'err_token'          => 'Неверный ключ безопасности',

	// ── JS: confirm / results ──
	'js_confirm_title'   => 'Применить исправления?',
	'js_confirm_text'    => 'Сохранённый info_hash будет заменён у торрентов на странице {2}: {1} шт.',
	'js_btn_confirm'     => 'Исправить',
	'js_btn_cancel'      => 'Отмена',
	'js_applying'        => 'Исправляем…',
	'js_result_fixed'    => 'Исправлено торрентов: {1}',
	'js_result_errors'   => ', ошибок: {1}',
	'js_result_skipped'  => ', пропущено: {1}',
	'js_applied_title'   => 'Исправления применены',
	'js_not_applied'     => 'Исправления не применены',
	'js_unknown_error'   => 'Неизвестная ошибка',
	'js_req_failed'      => 'Ошибка запроса',
	'js_req_failed_text' => 'Сервер вернул некорректный ответ. Попробуйте ещё раз.',

	// ── JS: copy ──
	'js_copied'          => 'Хеш скопирован',
	'js_copy_failed'     => 'Не удалось скопировать',

	// ── JS: Auto Fix ──
	'js_auto_fix_in'     => 'Исправление через {1} с',
	'js_auto_next_in'    => 'Следующая страница через {1} с',
	'js_auto_applying'   => 'Применяем исправления…',
	'js_auto_moving'     => 'Переходим дальше…',
	'js_auto_finished'   => 'Готово',
	'js_finished_title'  => 'Автоисправление завершено',
	'js_finished_text'   => 'Обработано страниц: {1}.',
);
?>
