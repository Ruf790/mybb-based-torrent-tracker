<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// admin/groups.php — Группы пользователей

$language['groups'] = array (

	// ── Breadcrumb / page titles ──
	'crumb_root'              => 'Группы пользователей',
	'crumb_add'               => 'Новая группа',
	'title_add'               => 'Новая группа пользователей',
	'title_edit'              => 'Редактирование группы',
	'title_manage'            => 'Управление группами',

	// ── Hero ──
	'hero_add_sub'            => 'Создайте группу — права детально настроите на следующем шаге',
	'hero_edit_title'         => 'Редактирование группы: {1}',
	'hero_gid'                => 'GID {1}',
	'hero_main_sub'           => 'Права, стили ников и порядок на странице команды для каждой группы',

	// ── Errors ──
	'err_heading'             => 'Исправьте следующие ошибки:',
	'err_no_title_new'        => 'Не указано название новой группы',
	'err_no_title'            => 'Не указано название группы',
	'err_namestyle_username'  => 'Стиль ника должен содержать {username}',
	'err_namestyle_tags'      => 'В стиле ника нельзя использовать теги script, meta и base',
	'err_moderate_invite'     => 'Группа не может быть одновременно «с одобрением заявок» и «только по приглашению»',

	// ── Flash / AJAX messages ──
	'flash_created'           => 'Группа успешно создана',
	'flash_invalid'           => 'Выбранная группа не существует',
	'flash_updated'           => 'Группа успешно обновлена',
	'flash_default_nodelete'  => 'Стандартные группы удалять нельзя',
	'flash_order_updated'     => 'Порядок отображения групп сохранён',
	'msg_deleted'             => 'Группа удалена',

	// ── Tabs ──
	'tab_general'             => 'Основное',
	'tab_forums'              => 'Форумы и сообщения',
	'tab_messaging'           => 'Переписка',
	'tab_moderation'          => 'Модерация',

	// ── Section headers ──
	'sec_identity'            => 'Название и оформление',
	'sec_start_perms'         => 'Исходные права',
	'sec_general'             => 'Общие настройки',
	'sec_joining'             => 'Вступление в группу',
	'sec_admin'               => 'Администрирование',
	'sec_viewing'             => 'Просмотр',
	'sec_posting'             => 'Публикация',
	'sec_polls'               => 'Опросы',
	'sec_editing'             => 'Редактирование',
	'sec_attach'              => 'Вложения и скриншоты',
	'sec_pms'                 => 'Личные сообщения',
	'sec_limits'              => 'Лимиты',
	'sec_email'               => 'E-mail',
	'sec_approval'            => 'Премодерация',
	'sec_deletion'            => 'Удаление',

	// ── Identity fields: labels ──
	'lbl_title'               => 'Название группы',
	'lbl_desc'                => 'Краткое описание',
	'lbl_namestyle'           => 'Стиль ника',
	'lbl_usertitle'           => 'Статус по умолчанию',
	'lbl_image'               => 'Значок группы',
	'lbl_preview'             => 'Предпросмотр',
	'lbl_preview_name'        => 'Ник',
	'lbl_preview_user'        => 'Пользователь',
	'lbl_preview_image'       => 'Значок',
	'lbl_copyfrom'            => 'Скопировать права из группы',

	// ── Identity fields: placeholders ──
	'ph_title'                => 'например, Опытные',
	'ph_desc'                 => 'Показывается на странице команды',
	'ph_usertitle'            => 'например, Опытный',

	// ── Hints (output as-is, HTML allowed) ──
	'hint_namestyle'          => 'Должен содержать <code>{username}</code>, например <code>&lt;b style="color:#e67e22"&gt;{username}&lt;/b&gt;</code>',
	'hint_image'              => 'HTML-иконка (<code>&lt;i class="fa-solid fa-star"&gt;</code>) или путь к картинке; <code>{lang}</code> — язык пользователя',
	'hint_copyfrom'           => 'Из выбранной группы копируются все права группы и права доступа к форумам',
	'hint_red_switches'       => 'Красные переключатели дают серьёзные полномочия — включайте их только проверенным группам администрации.',
	'hint_savebar'            => 'Изменения применяются ко всем участникам группы',
	'hint_order_footer'       => 'Порядок учитывается только для групп, показанных на странице команды',

	// ── Select options ──
	'opt_copy_default'        => 'Стандартные права (не копировать)',

	// ── Buttons ──
	'btn_cancel'              => 'Отмена',
	'btn_create'              => 'Создать группу',
	'btn_save'                => 'Сохранить группу',
	'btn_add'                 => 'Добавить группу',
	'btn_save_order'          => 'Сохранить порядок',

	// ── Permission switches ──
	'sw_showforumteam'        => 'Показывать группу на странице команды',
	'sw_isbannedgroup'        => 'Это группа забаненных',
	'sw_canviewwolinvis'      => 'Видит пользователей в режиме невидимки',
	'sw_joinable'             => 'Пользователи могут вступать в группу сами',
	'sw_moderate'             => 'Заявки на вступление требуют одобрения',
	'sw_invite'               => 'Только по приглашению',
	'sw_issupermod'           => 'Участники — супермодераторы',
	'sw_canstaffpanel'        => 'Доступ к панели администрации',
	'sw_cansettingspanel'     => 'Доступ к настройкам трекера',
	'sw_canview'              => 'Может просматривать сайт',
	'sw_canviewthreads'       => 'Может просматривать темы',
	'sw_cansearch'            => 'Может пользоваться поиском по форуму',
	'sw_candlattachments'     => 'Может скачивать вложения',
	'sw_canviewboardclosed'   => 'Видит сайт, когда он закрыт',
	'sw_canpostthreads'       => 'Может создавать темы',
	'sw_canpostreplys'        => 'Может отвечать в темах',
	'sw_canpostpolls'         => 'Может создавать опросы',
	'sw_canvotepolls'         => 'Может голосовать в опросах',
	'sw_canundovotes'         => 'Может отменять свой голос в опросе',
	'sw_caneditposts'         => 'Может редактировать свои сообщения',
	'sw_candeleteposts'       => 'Может удалять свои сообщения',
	'sw_candeletethreads'     => 'Может удалять свои темы',
	'sw_caneditattachments'   => 'Может редактировать свои вложения',
	'sw_canpostattachments'   => 'Может прикреплять файлы',
	'sw_canusepms'            => 'Может пользоваться ЛС',
	'sw_cansendpms'           => 'Может отправлять ЛС',
	'sw_cantrackpms'          => 'Может отслеживать прочтение ЛС',
	'sw_candenypmreceipts'    => 'Может не отправлять уведомление о прочтении',
	'sw_canoverridepm'        => 'Игнорирует лимиты ЛС',
	'sw_cansendemail'         => 'Может писать пользователям на e-mail',
	'sw_cansendemailoverride' => 'Не ограничен антифлудом e-mail',
	'sw_modposts'             => 'Новые сообщения',
	'sw_modthreads'           => 'Новые темы',
	'sw_mod_edit_posts'       => 'Отредактированные сообщения',
	'sw_modattachments'       => 'Новые вложения',
	'sw_candeletetorrent'     => 'Может удалять раздачи',

	// ── Number fields ──
	'lbl_attachquota'         => 'Квота вложений',
	'lbl_max_screenshots'     => 'Скриншотов на раздачу',
	'lbl_pmquota'             => 'Квота ЛС',
	'lbl_maxpmrecipients'     => 'Макс. получателей ЛС',
	'hint_unlimited'          => '0 = без ограничений',
	'hint_not_allowed'        => '0 = запрещено',
	'hint_per_message'        => 'В одном сообщении',
	'unit_kb'                 => 'КБ',
	'unit_messages'           => 'сообщ.',
	'unit_users'              => 'польз.',

	// ── Group tags ──
	'tag_default'             => 'Стандартная',
	'tag_custom'              => 'Своя',
	'tag_staff'               => 'Администрация',
	'tag_banned'              => 'Бан',
	'tag_invite'              => 'По приглашению',
	'tag_request'             => 'По заявке',
	'tag_open'                => 'Открытая',
	'tag_team'                => 'В команде',

	// ── KPI tiles ──
	'kpi_groups'              => 'Всего групп',
	'kpi_custom'              => 'Своих групп',
	'kpi_staff'               => 'Групп администрации',
	'kpi_users'               => 'Пользователей',

	// ── Groups table ──
	'th_group'                => 'Группа',
	'th_flags'                => 'Метки',
	'th_members'              => 'Участников',
	'th_order'                => 'Порядок в команде',
	'th_actions'              => 'Действия',
	'lbl_additional'          => 'доп. группа: {1}',
	'aria_order'              => 'Порядок отображения',
	'tip_order_na'            => 'Сортируются только группы, показанные на странице команды',
	'tip_edit'                => 'Изменить',
	'tip_list_users'          => 'Список участников',
	'tip_delete'              => 'Удалить',
	'tip_default_locked'      => 'Стандартные группы удалять нельзя',
	'empty_groups'            => 'Группы не найдены.',

	// ── JS: usergroups.js (preview) ──
	'js_preview_username'     => 'Пользователь',
	'js_img_not_found'        => 'не найдено',

	// ── JS: deleteGroup.js (delete modal) ──
	'js_del_title'            => 'Удаление группы',
	'js_del_confirm'          => 'Удалить эту группу? Отменить это действие будет невозможно.',
	'js_btn_no'               => 'Нет',
	'js_btn_delete'           => 'Удалить!',
	'js_deleting'             => 'Удаление…',
	'js_no_token'             => 'Ключ безопасности не найден на странице. Обновите страницу и попробуйте снова.',
	'js_del_error'            => 'Не удалось удалить группу. Попробуйте ещё раз.',
	'js_btn_ok'               => 'ОК',
);
?>
