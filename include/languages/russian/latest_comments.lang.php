<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// Comments Admin (admin/latest_comments.php)
// Ключи с окончанием _html выводятся как есть (может быть разметка); остальные — чистый текст.
$language['latest_comments'] = array (

	// ── Page ──
	'page_title'           => 'Управление комментариями',
	'page_subtitle'        => 'Модерация, правка, перенос, копирование и объединение комментариев ко всем раздачам',

	// ── KPI tiles ──
	'kpi_total'            => 'Всего комментариев',
	'kpi_today'            => 'Сегодня',
	'kpi_week'             => 'За 7 дней',
	'kpi_authors'          => 'Активных авторов (30 дн.)',

	// ── Filters ──
	'lbl_username'         => 'Пользователь',
	'ph_username'          => 'Поиск по нику…',
	'lbl_torrent'          => 'Раздача',
	'ph_torrent'           => 'Поиск по названию раздачи…',
	'lbl_date_from'        => 'Дата с',
	'lbl_date_to'          => 'Дата по',
	'btn_filter'           => 'Применить',
	'tip_reset_filters'    => 'Сбросить фильтры',

	// ── Loading / empty state ──
	'lbl_loading'          => 'Загрузка…',
	'lbl_loading_comments' => 'Загрузка комментариев…',
	'sec_empty'            => 'Комментарии не найдены',
	'hint_empty'           => 'Измените условия поиска или сбросьте фильтры.',

	// ── Comments table ──
	'sec_comments'         => 'Комментарии',
	'hint_comments_page'   => 'Сначала новые · страница {1} из {2}',
	'lbl_found'            => 'Найдено: {1}',
	'col_id'               => 'ID',
	'col_user'             => 'Автор',
	'col_torrent'          => 'Раздача',
	'col_comment'          => 'Комментарий',
	'col_date'             => 'Дата',
	'col_actions'          => 'Действия',
	'tip_select_all_page'  => 'Выбрать все на странице',
	'tip_open_comment'     => 'Открыть комментарий в новой вкладке',
	'tip_edit_comment'     => 'Редактировать комментарий',
	'tip_delete_comment'   => 'Удалить комментарий',
	'lbl_deleted_user'     => 'Удалённый пользователь',
	'lbl_deleted_torrent'  => 'Удалённая раздача #{1}',
	'lbl_edited'           => 'изменён {1}',
	'btn_select_all'       => 'Выбрать все',
	'btn_move'             => 'Перенести',
	'btn_copy'             => 'Копировать',
	'btn_merge'            => 'Объединить',
	'btn_delete_selected'  => 'Удалить выбранные',
	'pager_showing_html'   => 'Показаны <b>{1}</b> – <b>{2}</b> из <b>{3}</b>',

	// ── Modals: shared ──
	'btn_cancel'           => 'Отмена',
	'tip_close'            => 'Закрыть',
	'lbl_target_tid'       => 'ID целевой раздачи',
	'ph_target_tid'        => 'Введите ID раздачи',
	'note_selected_html'   => '<strong>Выбрано комментариев:</strong> {1}.',
	'note_irreversible'    => 'Отменить это действие будет нельзя.',

	// ── Modal: move ──
	'sec_move'             => 'Перенос комментариев',
	'hint_move'            => 'Привязать выбранные комментарии к другой раздаче',
	'btn_move_confirm'     => 'Перенести',

	// ── Modal: copy ──
	'sec_copy'             => 'Копирование комментариев',
	'hint_copy'            => 'Дубликаты создаются вместе с вложениями',
	'note_copy'            => 'Оригиналы останутся на месте.',
	'btn_copy_confirm'     => 'Скопировать',

	// ── Modal: merge ──
	'sec_merge'            => 'Объединение в один комментарий',
	'hint_merge'           => 'Тексты склеиваются в порядке написания',
	'note_merge'           => 'Тексты объединяются в хронологическом порядке в один новый комментарий на целевой раздаче; его автором становится автор самого раннего из выбранных комментариев. Оригиналы удаляются.',
	'btn_merge_confirm'    => 'Объединить',

	// ── Modal: edit ──
	'sec_edit'             => 'Редактирование комментария',
	'hint_edit'            => 'Поддерживается BBCode · предпросмотр ниже',
	'tip_insert_torrent'   => 'Вставить ссылку на раздачу',
	'btn_torrent'          => 'Раздача',
	'lbl_torrent_id_url'   => 'ID или ссылка на раздачу',
	'ph_torrent_id'        => 'Например, 17 или вставьте ссылку на раздачу',
	'btn_insert'           => 'Вставить',
	'ph_edit_comment'      => 'Текст комментария…',
	'lbl_live_preview'     => 'Предпросмотр',
	'btn_save'             => 'Сохранить',

	// ── Modal: bulk delete ──
	'sec_bulk_delete'      => 'Подтверждение удаления',
	'hint_bulk_delete'     => 'Вложения тоже будут удалены',
	'lbl_bulk_delete'      => 'Удалить выбранные комментарии?',
	'btn_yes_delete'       => 'Да, удалить',

	// ── BBCode toolbar ──
	'bb_bold'              => 'Жирный',
	'bb_italic'            => 'Курсив',
	'bb_underline'         => 'Подчёркнутый',
	'bb_strike'            => 'Зачёркнутый',
	'bb_left'              => 'По левому краю',
	'bb_center'            => 'По центру',
	'bb_right'             => 'По правому краю',
	'bb_color'             => 'Красный цвет',
	'bb_size'              => 'Размер шрифта',
	'bb_url'               => 'Ссылка',
	'bb_email'             => 'E-mail',
	'bb_img'               => 'Картинка',
	'bb_video'             => 'Видео',
	'bb_youtube'           => 'YouTube',
	'bb_quote'             => 'Цитата',
	'bb_code'              => 'Код',
	'bb_php'               => 'PHP-код',
	'bb_nfo'               => 'NFO',
	'bb_spoiler'           => 'Спойлер',
	'bb_list'              => 'Маркированный список',
	'bb_list_num'          => 'Нумерованный список',
	'bb_list_item'         => 'Пункт списка',

	// ── AJAX errors (JSON) ──
	'err_csrf'             => 'Неверный ключ безопасности. Обновите страницу.',
	'err_method'           => 'Недопустимый метод запроса',
	'err_no_selection'     => 'Не выбрано ни одного комментария',
	'err_no_valid_ids'     => 'Нет корректных ID комментариев',
	'err_not_found'        => 'Комментарий не найден',
	'err_comments_not_found' => 'Комментарии не найдены',
	'err_text_short'       => 'Комментарий должен содержать осмысленный текст (не короче 3 символов)',
	'err_invalid_target'   => 'Неверный ID целевой раздачи',
	'err_target_not_found' => 'Целевая раздача не найдена',
	'err_merge_min'        => 'Для объединения выберите хотя бы 2 комментария',
	'err_unknown_action'   => 'Неизвестное действие',

	// ── JS: loading / list ──
	'js_loading'           => 'Загрузка…',
	'js_loading_comments'  => 'Загрузка комментариев…',
	'js_load_failed'       => 'Не удалось загрузить комментарии: {1}',
	'js_error_title'       => 'Ошибка',
	'js_error_prefix'      => 'Ошибка: {1}',
	'js_ajax_error'        => 'Ошибка запроса: {1}',
	'js_cancel'            => 'Отмена',
	'js_invalid_target'    => 'Введите корректный ID целевой раздачи',

	// ── JS: bulk delete ──
	'js_select_one'        => 'Выберите хотя бы один комментарий',
	'js_bulk_confirm_one'  => 'Удалить выбранный комментарий?',
	'js_bulk_confirm_many' => 'Удалить выбранные комментарии ({1} шт.)?',
	'js_deleting'          => 'Удаление…',
	'js_bulk_deleted'      => 'Удалено комментариев: {1}',
	'js_err_delete_many'   => 'Ошибка при удалении комментариев',

	// ── JS: copy ──
	'js_select_copy'       => 'Выберите хотя бы один комментарий для копирования',
	'js_copy_title'        => 'Скопировать комментарии?',
	'js_copy_text'         => 'Скопировать выбранные комментарии ({1} шт.) в раздачу с ID {2}?',
	'js_copy_btn'          => 'Скопировать',
	'js_copying'           => 'Копирование…',
	'js_copied'            => 'Скопировано комментариев: {1}',

	// ── JS: merge ──
	'js_select_merge'      => 'Для объединения выберите хотя бы 2 комментария',
	'js_merge_title'       => 'Объединить комментарии?',
	'js_merge_text'        => 'Объединить выбранные комментарии ({1} шт.) в один на раздаче с ID {2}? Отменить это будет нельзя.',
	'js_merge_btn'         => 'Объединить',
	'js_merging'           => 'Объединение…',
	'js_merged'            => 'Объединено комментариев: {1}, итоговый — #{2}',

	// ── JS: move ──
	'js_select_move'       => 'Выберите хотя бы один комментарий для переноса',
	'js_move_title'        => 'Перенести комментарии?',
	'js_move_text'         => 'Перенести выбранные комментарии ({1} шт.) в раздачу с ID {2}?',
	'js_move_btn'          => 'Перенести',
	'js_moving'            => 'Перенос…',
	'js_moved'             => 'Перенесено комментариев: {1}',

	// ── JS: edit / save ──
	'js_err_load_comment'  => 'Не удалось загрузить комментарий',
	'js_preview_failed'    => 'Не удалось построить предпросмотр',
	'js_text_min'          => 'Комментарий должен быть не короче 3 символов',
	'js_text_meaningful'   => 'Комментарий должен содержать осмысленный текст',
	'js_saving'            => 'Сохранение…',
	'js_saved'             => 'Комментарий сохранён',
	'js_unknown_error'     => 'Неизвестная ошибка',
	'js_err_save'          => 'Ошибка при сохранении комментария',
	'js_network_error'     => 'Ошибка сети — проверьте подключение',

	// ── JS: single delete ──
	'js_delete_title'      => 'Удалить комментарий?',
	'js_delete_text'       => 'Вы уверены, что хотите удалить этот комментарий?',
	'js_delete_btn'        => 'Удалить',
	'js_deleted'           => 'Комментарий удалён',
	'js_err_delete_one'    => 'Ошибка при удалении комментария',

	// ── JS: torrent embed preview ──
	'js_preview_loading'   => 'Загрузка предпросмотра…',
	'js_preview_load_failed' => 'Не удалось загрузить предпросмотр',
	'js_preview_seeders'   => 'Сиды: {1}',
	'js_preview_leechers'  => 'Личи: {1}',
);
?>
