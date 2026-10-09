<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['rules'] = array (
	// ── Meta ── (which DB columns to use: ru => title_ru / text_ru)
	'lang_code'           => 'ru',

	// ── Header ──
	'pane_title'          => 'Свод правил',
	'pane_subtitle'       => 'Условия, с которыми каждый согласился при регистрации. Прочитай. Запомни. Нарушишь — и ссылаться будут именно сюда.',
	'lbl_viewing_as'      => 'Вы смотрите как {1}',

	// ── KPI tiles ──
	'kpi_rules_for_you'   => 'Правил для вас',
	'kpi_for_all'         => 'Для всех',
	'kpi_group_specific'  => 'Для отдельных групп',
	'kpi_reading_time'    => 'Время чтения',
	'kpi_minutes'         => '~{1} мин',

	// ── Usergroups ──
	'grp_1'               => 'Гость',
	'grp_2'               => 'Пользователь',
	'grp_3'               => 'Опытный пользователь',
	'grp_4'               => 'VIP',
	'grp_5'               => 'Аплоадер',
	'grp_6'               => 'Модератор',
	'grp_7'               => 'Администратор',
	'grp_8'               => 'Сисоп',
	'grp_9'               => 'Забаненный',
	'grp_unknown'         => 'Группа №{1}',
	'grp_all_users'       => 'Все пользователи',

	// ── Search card ──
	'ph_search'           => 'Поиск по правилам…  (клавиша / )',
	'aria_search'         => 'Поиск по правилам',
	'btn_expand_all'      => 'Развернуть все',
	'btn_collapse_all'    => 'Свернуть все',
	'btn_print'           => 'Печать',
	'lbl_showing'         => 'Показано {1} из {2}',
	'lbl_search_chip'     => 'Поиск: {1}',
	'tip_remove_filter'   => 'Сбросить фильтр',

	// ── Contents ──
	'sec_contents'        => 'Содержание',
	'aria_contents'       => 'Содержание правил',

	// ── Rule card ──
	'lbl_untitled'        => 'Правило без названия',
	'lbl_rule_num'        => 'Правило {1}',
	'lbl_min_read'        => '~{1} мин чтения',
	'tip_copy_link'       => 'Скопировать ссылку на правило',
	'lbl_applies_to'      => 'Для кого:',
	'lbl_applies_to_you'  => 'Касается вас',

	// ── No match ──
	'sec_no_match'        => 'Ничего не найдено',
	'msg_no_match'        => 'По вашему запросу правил нет. Попробуйте другое слово.',
	'btn_reset_search'    => 'Сбросить поиск',
	'aria_back_to_top'    => 'Наверх',

	// ── Empty state ──
	'sec_empty'           => 'Правил нет',
	'msg_empty'           => 'Сейчас для вашего аккаунта нет действующих правил.',
	'btn_return_home'     => 'На главную',

	// ── Error state ──
	'sec_error'           => 'Не удалось загрузить правила',
	'msg_error'           => 'При загрузке правил произошла ошибка. Попробуйте позже.',
	'lbl_error'           => 'Ошибка:',
	'err_contact_admin'   => 'Обратитесь к администрации',
	'btn_retry'           => 'Повторить',
	'btn_go_home'         => 'На главную',

	// ── JS ──
	'js_rule_collapse'    => 'Свернуть правило',
	'js_rule_expand'      => 'Развернуть правило',
);
?>