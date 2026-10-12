<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// Админка → Бонусы (admin/bonuspoints.php)
// Строки с пометкой [raw] выводятся как есть и могут содержать HTML; остальные — чистый текст.
$language['bonuspoints'] = array (
	// ── Page / header ──
	'page_title'           => 'Бонусы {1} — {2}',
	'head_title'           => 'Бонусы',
	'head_sub'             => 'Товары бонус-магазина, балансы пользователей и журнал начислений',

	// ── Tabs ──
	'tab_items'            => 'Магазин',
	'tab_add'              => 'Новый товар',
	'tab_users'            => 'Пользователи',
	'tab_log'              => 'Журнал',
	'tab_reset'            => 'Обнуление',

	// ── KPI tiles ──
	'kpi_items'            => 'Товаров в магазине',
	'kpi_users'            => 'Пользователей с бонусами',
	'kpi_total'            => 'Всего бонусов',
	'kpi_top'              => 'Самый большой баланс',

	// ── Section headings ──
	'sec_items'            => 'Товары магазина',
	'sec_new_item'         => 'Новый товар',
	'sec_edit_item'        => 'Редактирование: {1}',
	'sec_users'            => 'Пользователи с бонусами',
	'sec_edit_balance'     => 'Изменить баланс',
	'sec_log'              => 'Журнал бонусов',
	'sec_reset'            => 'Обнуление бонусов',
	'sub_items'            => 'Товаров: {1}, сначала дешёвые',
	'sub_users'            => 'Пользователей: {1}, по убыванию баланса',
	'sub_reset'            => 'Обнуляет баланс активных подтверждённых пользователей',

	// ── Table headers ──
	'th_item'              => 'Товар',
	'th_type'              => 'Тип',
	'th_amount'            => 'Объём',
	'th_price'             => 'Цена',
	'th_user'              => 'Пользователь',
	'th_points'            => 'Бонусы',
	'th_uploaded'          => 'Отдано',
	'th_date'              => 'Дата',
	'th_reason'            => 'За что',
	'th_change'            => 'Изменение',
	'th_balance'           => 'Баланс',

	// ── Field labels ──
	'lbl_name'             => 'Название',
	'lbl_price'            => 'Цена',
	'lbl_points_unit'      => 'бонусов',
	'lbl_description'      => 'Описание',
	'lbl_traffic'          => 'Объём аплоада',
	'lbl_delete_item'      => 'Удалить товар',
	'lbl_new_balance'      => 'Новый баланс',
	'lbl_balance_now'      => 'сейчас {1}',
	'lbl_id'               => 'ID {1}',
	'lbl_user'             => 'Пользователь',
	'lbl_type'             => 'Тип',
	'lbl_period'           => 'Период',
	'lbl_which_group'      => 'Группа',
	'lbl_usergroup'        => 'Группа',
	'lbl_all_groups'       => 'ВСЕ группы',
	'lbl_group_fallback'   => 'Группа {1}',
	'lbl_deleted_user'     => '#{1} (удалён)',
	'lbl_whole_day'        => '(за сутки)',
	'lbl_by_actor'         => 'выдал {1}',

	// ── Hints / placeholders / titles ──
	'hint_delete_item'     => 'Потребуется подтверждение',
	'hint_bytes'           => '= {1} байт · раньше вводилось в байтах: 1 GB = 1 073 741 824', // [raw] {1} = счётчик байт
	'hint_user'            => 'ник или #id',
	'ph_name'              => 'например, 5 GB аплоада',
	'ph_description'       => 'Что получит пользователь',
	'ph_find_user'         => 'Найти пользователя…',
	'ph_everyone'          => 'Все',
	'title_edit'           => 'Изменить',
	'title_edit_item'      => 'Изменить товар',
	'title_history'        => 'История бонусов',
	'title_edit_balance'   => 'Изменить баланс',
	'title_only_user'      => 'Только этот пользователь',
	'alert_reset'          => '<strong>Отменить нельзя.</strong> Балансы будут обнулены; подтверждение — на следующем шаге.', // [raw]

	// ── Buttons ──
	'btn_items'            => 'Магазин',
	'btn_users'            => 'Пользователи',
	'btn_cancel'           => 'Отмена',
	'btn_add_item'         => 'Добавить товар',
	'btn_save_item'        => 'Сохранить товар',
	'btn_save_balance'     => 'Сохранить баланс',
	'btn_set_zero'         => 'Обнулить',
	'btn_show'             => 'Показать',
	'btn_reset_filters'    => 'Сбросить',
	'btn_continue'         => 'Далее',
	'btn_yes_delete'       => 'Да, удалить',
	'btn_yes_reset'        => 'Да, обнулить',

	// ── Select options ──
	'opt_all_types'        => 'Все типы',
	'opt_period_today'     => 'Сегодня',
	'opt_period_7'         => '7 дней',
	'opt_period_30'        => '30 дней',
	'opt_period_90'        => '90 дней',
	'opt_period_all'       => 'За всё время',

	// ── Bonus log summary ──
	'log_entries_one'      => 'Записей: {1}',
	'log_entries'          => 'Записей: {1}',
	'log_net'              => 'итого <strong>{1}</strong>', // [raw]

	// ── Empty states ──
	'empty_items'          => 'В магазине пока нет товаров',
	'empty_users'          => 'Бонусов пока ни у кого нет',
	'empty_users_search'   => 'По запросу «{1}» никого не найдено', // [raw] {1} экранируется
	'empty_log'            => 'По этим фильтрам записей нет',

	// ── Confirmations ──
	'confirm_delete_title' => 'Удалить товар?',
	'confirm_delete_text'  => '<strong>{1}</strong> будет навсегда удалён из бонус-магазина.', // [raw]
	'confirm_reset_title'  => 'Обнулить бонусы?',
	'confirm_reset_text'   => '{1} — у пользователей ({2}) будет списано бонусов: {3}.', // [raw] {1} = <strong>группа</strong>

	// ── Results (flash) ──
	'res_not_found'        => 'Не найдено',
	'res_item_missing'     => 'Такого товара нет.',
	'res_user_missing'     => 'Пользователь не найден.',
	'res_not_saved'        => 'Не сохранено',
	'res_need_name'        => 'У товара должно быть название.',
	'res_db_error'         => 'Ошибка базы данных.',
	'res_added'            => 'Товар добавлен',
	'res_add_failed'       => 'Не удалось добавить товар',
	'res_added_text'       => '«{1}» теперь в бонус-магазине.', // [raw]
	'res_deleted'          => 'Товар удалён',
	'res_delete_failed'    => 'Не удалось удалить',
	'res_deleted_text'     => '«{1}» удалён.', // [raw]
	'res_saved'            => 'Товар сохранён',
	'res_save_failed'      => 'Не удалось сохранить',
	'res_saved_text'       => 'Изменения в «{1}» вступили в силу.', // [raw]
	'res_balance_updated'  => 'Баланс изменён',
	'res_update_failed'    => 'Не удалось изменить',
	'res_balance_text'     => 'Баланс {1}: {2} → <strong>{3}</strong>', // [raw]
	'res_reset'            => 'Бонусы обнулены',
	'res_reset_failed'     => 'Не удалось обнулить',
	'res_reset_text'       => '{1}: баланс теперь 0.', // [raw] {1} = <strong>группа</strong>

	// ── Errors ──
	'err_security_title'   => 'Ошибка безопасности',
	'err_security_text'    => 'Неверный токен безопасности. Обновите страницу и попробуйте ещё раз.',
	'err_title'            => 'Ошибка',
	'err_method'           => 'Недопустимый метод запроса',

	// ── JS (edit balance) ──
	'js_no_change'         => 'Без изменений',
	'js_diff_points'       => '{1}',
	'js_diff_vs_now'       => '{1} к текущему балансу',
);
?>
