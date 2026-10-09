<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['announcements_forum'] = array (
	// ── Page titles ──
	'pane_list'              => 'Объявления форума',
	'pane_add'               => 'Новое объявление на форуме',
	'pane_edit'              => 'Редактирование объявления',
	'pane_view'              => 'Просмотр: {1}',

	// ── List ──
	'sec_announcements'      => 'Объявления',
	'lbl_total'              => 'Всего: {1}',
	'col_id'                 => 'ID',
	'col_subject'            => 'Заголовок',
	'col_forum'              => 'Форум',
	'col_status'             => 'Статус',
	'col_start'              => 'Начало',
	'col_end'                => 'Окончание',
	'col_actions'            => 'Действия',
	'lbl_by_author'          => 'автор: {1}',
	'lbl_unknown'            => 'Неизвестно',
	'lbl_forum_n'            => 'Форум #{1}',
	'lbl_empty'              => 'Объявлений на форуме пока нет',

	// ── Forum / type badges ──
	'badge_global'           => '🌐 Везде',
	'badge_tracker'          => 'Трекер',
	'opt_global_all'         => '🌐 Глобальное (все форумы)',
	'opt_type_global'        => 'глобальное',
	'opt_type_forum'         => 'форум',

	// ── Status ──
	'status_scheduled'       => 'Запланировано',
	'status_expired'         => 'Истекло',
	'status_active'          => 'Активно',

	// ── Buttons / titles ──
	'btn_new'                => 'Новое объявление',
	'btn_view'               => 'Просмотр',
	'btn_edit'               => 'Изменить',
	'btn_duplicate'          => 'Дублировать',
	'btn_delete'             => 'Удалить',
	'btn_back'               => 'Назад',
	'btn_cancel'             => 'Отмена',
	'btn_close'              => 'Закрыть',
	'btn_publish'            => 'Опубликовать объявление',
	'btn_update'             => 'Сохранить изменения',

	// ── View ──
	'aria_breadcrumb'        => 'навигация',
	'lbl_views_count'        => 'Просмотров: {1}',
	'lbl_active_period'      => 'Показывается:',
	'lbl_now'                => 'сейчас',
	'lbl_no_end'             => '∞ (бессрочно)',
	'sec_actions'            => 'Действия',
	'sec_details'            => 'Сведения',
	'lbl_id'                 => 'ID',
	'lbl_type'               => 'Тип',
	'lbl_views'              => 'Просмотры',
	'lbl_words'              => 'Слов',

	// ── Form ──
	'sec_basic'              => 'Основные настройки',
	'sec_schedule'           => 'Расписание показа',
	'sec_message'            => 'Текст объявления',
	'lbl_subject'            => 'Заголовок',
	'lbl_forum'              => 'Форум',
	'lbl_start_date'         => 'Начало показа',
	'lbl_end_date'           => 'Окончание показа',
	'opt_end_infinite'       => 'Бессрочно (постоянное)',
	'opt_end_finite'         => 'Указать дату окончания',
	'hint_time'              => 'ЧЧ:ММ',
	'hint_message'           => 'Напишите объявление, можно использовать BBCode...',
	'hint_char_count'        => '{1} / 5000 символов',

	// ── Months ──
	'opt_month_01'           => 'Январь',
	'opt_month_02'           => 'Февраль',
	'opt_month_03'           => 'Март',
	'opt_month_04'           => 'Апрель',
	'opt_month_05'           => 'Май',
	'opt_month_06'           => 'Июнь',
	'opt_month_07'           => 'Июль',
	'opt_month_08'           => 'Август',
	'opt_month_09'           => 'Сентябрь',
	'opt_month_10'           => 'Октябрь',
	'opt_month_11'           => 'Ноябрь',
	'opt_month_12'           => 'Декабрь',

	// ── Delete modal ──
	'modal_delete_title'     => 'Удаление объявления',
	'modal_delete_text'      => 'Вы собираетесь удалить:',
	'modal_delete_warn'      => 'Это действие нельзя отменить.',

	// ── Validation errors ──
	'err_subject_required'   => 'Укажите заголовок.',
	'err_message_required'   => 'Введите текст объявления.',
	'err_start_invalid'      => 'Неверная дата начала.',
	'err_end_invalid'        => 'Неверная дата окончания.',
	'err_end_before_start'   => 'Дата окончания должна быть позже даты начала.',

	// ── Flash messages ──
	'flash_not_found'        => 'Объявление не найдено.',
	'flash_invalid_id'       => 'Неверный ID.',
	'flash_added'            => 'Объявление опубликовано.',
	'flash_updated'          => 'Объявление обновлено.',
	'flash_deleted'          => 'Объявление удалено.',
	'flash_delete_cancelled' => 'Удаление отменено.',

	// ── AJAX (duplicate) ──
	'json_invalid_request'   => 'Неверный запрос.',
	'json_not_found'         => 'Не найдено.',
	'json_duplicated'        => 'Копия создана.',
	'val_copy_of'            => 'Копия: {1}',

	// ── JS ──
	'js_confirm_duplicate'   => 'Создать копию этого объявления?',
);
?>
