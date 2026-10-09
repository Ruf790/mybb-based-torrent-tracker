<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cache2'] = array (
	// ── Page titles & headers ──
	'title_manager'            => 'Менеджер кэша',
	'title_view'               => 'Кэш: {1}',
	'sub_manager'              => 'Сохранённые кэши данных — просмотр содержимого и перестройка из базы',
	'sub_lines'                => 'строк: {1}',
	'sec_all_caches'           => 'Все кэши',

	// ── KPI tiles ──
	'kpi_caches'               => 'Кэшей',
	'kpi_total_size'           => 'Общий размер',
	'kpi_rebuildable'          => 'Перестраиваемых',
	'kpi_largest'              => 'Самый большой',

	// ── Table headers ──
	'th_cache'                 => 'Кэш',
	'th_size'                  => 'Размер',
	'th_rebuild'               => 'Обновление',
	'th_actions'               => 'Действия',

	// ── Buttons ──
	'btn_rebuild'              => 'Перестроить',
	'btn_reload'               => 'Перезагрузить',
	'btn_rebuild_all'          => 'Перестроить все',
	'btn_back'                 => 'Назад',
	'btn_wrap'                 => 'Перенос строк',
	'btn_copy'                 => 'Копировать',

	// ── Tags, tooltips, placeholders ──
	'tag_static'               => 'статичный',
	'tag_rebuild'              => 'перестройка',
	'tag_reload'               => 'перезагрузка',
	'size_live'                => 'на лету',
	'tip_view'                 => 'Просмотреть содержимое',
	'ph_filter'                => 'Фильтр кэшей…',
	'ph_highlight'             => 'Подсветить текст…',

	// ── Empty states & confirmations ──
	'empty_cache'              => 'Этот кэш пуст',
	'no_matches'               => 'Ничего не найдено',
	'confirm_rebuild_all'      => 'Перестроить все кэши ({1}) прямо сейчас?',

	// ── Flash messages ──
	'flash_no_cache_specified' => 'Кэш не указан',
	'flash_cache_not_found'    => 'Кэш не найден',
	'flash_invalid_token'      => 'Неверный ключ безопасности',
	'flash_no_such_cache'      => 'Такого кэша нет',
	'flash_settings_reloaded'  => 'Кэш настроек перезагружен',
	'flash_cannot_rebuild'     => 'Этот кэш нельзя перестроить',
	'flash_cache_rebuilt'      => 'Кэш «{1}» перестроен',
	'flash_cache_reloaded'     => 'Кэш «{1}» перезагружен',
	'flash_all_rebuilt'        => 'Перестроено кэшей: {1}',

	// ── Cache descriptions ──
	'desc_settings'            => 'Настройки сайта',
	'desc_usergroups'          => 'Группы пользователей и их права',
	'desc_forums'              => 'Список и структура форумов',
	'desc_forumpermissions'    => 'Права групп в отдельных форумах',
	'desc_moderators'          => 'Модераторы форумов',
	'desc_attachtypes'         => 'Разрешённые типы вложений',
	'desc_smilies'             => 'Список смайлов',
	'desc_badwords'            => 'Фильтр слов',
	'desc_bannedips'           => 'Заблокированные IP-адреса',
	'desc_bannedemails'        => 'Заблокированные e-mail адреса',
	'desc_birthdays'           => 'Ближайшие дни рождения',
	'desc_stats'               => 'Статистика трекера',
	'desc_statistics'          => 'Расширенная статистика',
	'desc_plugins'             => 'Активные плагины',
	'desc_mycode'              => 'Пользовательские MyCode',
	'desc_posticons'           => 'Иконки сообщений',
	'desc_profilefields'       => 'Дополнительные поля профиля',
	'desc_reportedcontent'     => 'Счётчики жалоб',
	'desc_awaitingactivation'  => 'Аккаунты, ожидающие активации',
	'desc_mostonline'          => 'Рекорд посещаемости',
	'desc_spiders'             => 'Поисковые роботы',
	'desc_tasks'               => 'Задачи планировщика',
	'desc_update_check'        => 'Результат проверки обновлений',
	'desc_version'             => 'Установленная версия',
	'desc_internal_settings'   => 'Внутренние настройки',
	'desc_threadprefixes'      => 'Префиксы тем',
	'desc_forumsdisplay'       => 'Параметры отображения форумов',
	'desc_groupleaders'        => 'Лидеры групп',
	'desc_default_theme'       => 'Тема по умолчанию',
	'desc_kps'                 => 'Настройки кармы и бонусов',

	// ── JS strings ──
	'js_hits'                  => 'Совпадений: {1}',
	'js_copy'                  => 'Копировать',
	'js_copied'                => 'Скопировано',
);
?>
