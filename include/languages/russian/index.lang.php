<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// index.php
$language['index'] = array 
(
	
'bottomlinks_contactus' => "Связаться с нами",

'bottomlinks_forumteam' => "Команда форума",
	
	
'forum_unapproved_posts_count' => "В настоящее время на этом форуме {1} непроверенных сообщений.",
'forum_unapproved_post_count' => "В настоящее время на этом форуме 1 непроверенное сообщение.",
'forum_unapproved_threads_count' => "В настоящее время на этом форуме {1} непроверенных тем.",
'forum_unapproved_thread_count' => "В настоящее время на этом форуме 1 непроверенная тема.",	
	
'stats_posts_threads' => 'Наши участники оставили в общей сложности {1} сообщений в {2} темах.',
'stats_numusers' => 'В настоящее время у нас зарегистрировано {1} участников.',
'stats_newestuser' => 'Поприветствуйте нашего нового участника, <b>{1}</b>',	
	
	
	
	'latest_threads' => "Последние темы",
'latest_threads_replies' => "Ответы:",
'latest_threads_views' => "Просмотры:",
'latest_threads_lastpost' => "Последнее сообщение:",
'forum' => "Форум:",
	
	
	'comma' => ", ",
	'whos_online' => "Кто онлайн",
	'no_new_posts' => "На форуме нет новых сообщений",
    'click_mark_read' => "Нажмите, чтобы отметить этот форум как прочитанный",
	
	'nolast24visits'		=>'За последние 24 часа не было активных пользователей.',	
	'welcome'			=>'Добро пожаловать в {1}',
	'recentnews'		=>'Последние новости',
	'newspage'			=>'<b>Управление новостями</b>',
	'shoutbox'			=>'Чат',
	'message'			=>'Сообщение: ',		
	'del'					=>'удалить',
	'edit'					=>'редактировать',
	'pm'					=>'лс',
	'latesttopics'		=>'Последние {1} тем',
	'topictitle'			=>'Название темы',
	'views'				=>'Просмотры',
	'replies'				=>'Ответы',
	'author'				=>'Автор',
	'lastposter'			=>'Последний ответивший',
	'postedat'			=>'Опубликовано',
	'lasttorrents'		=>'Последние {1} загруженных торрентов', // Изменено в v3.6
	'name'				=>'Название',
	'seeders'				=>'Раздающие',
	'leechers'			=>'Скачивающие',
	'trackerstats'		=>'Статистика трекера',
	'registeredusers'	=>'Участники / Лимит',
	'unconfirmedusers'=>'Неподтверждённые пользователи',
	'torrents'				=>'Торренты',
	'deadtorrents'		=>'Мёртвые торренты',
	'warnedusers'		=>'Предупреждённые пользователи',
	'bannedusers'		=>'Забаненные пользователи',	
	'vips'					=>'VIP\'ы',
	'donors'				=>'Донатеры',
	'peers'				=>'Пиры',
	'seedleechratio'	=>'Соотношение раздающих/скачивающих (%)',
	'totaldown'			=>'Всего скачано',
	'uotalup'				=>'Всего загружено',
	'trackerload'		=>'Нагрузка трекера',
	'ourtrackerload'	=>'Нагрузка нашего трекера: ',
	'loadav'				=>'Средняя нагрузка за 10 минут',
	'globaltrackerload'	=>'Глобальная нагрузка сервера (все сайты на текущих хост-серверах): ',
	'whatsgoinon'		=>'Что происходит?',
	'last24visits'		=>'{1} участник(ов) посетили за последние 24 часа: ',	
	'newestmember'	=>'Поприветствуйте нашего нового участника, <b>{1}</b>',
	'activeusers'		=>'Активные пользователи: ',	
	'diclaimer'			=>'Отказ от ответственности',
	'diclaimermessage'=>'Ни один из файлов, показанных здесь, на самом деле не размещён на этом сервере. Ссылки предоставляются исключительно пользователями этого сайта. Администратор этого сайта ({1}) не может нести ответственность за то, что публикуют его пользователи, или за любые другие действия его пользователей. Вы не можете использовать этот сайт ({1}) для распространения или скачивания любых материалов, если у вас нет законных прав на это. Соблюдение этих условий — ваша личная ответственность.',
	'note'					=>'Этот сайт лучше всего просматривать в <a href="https://www.mozilla.com/en-US/firefox/" title="Get Firefox - The Browser, Reloaded."><img src="https://www.mozilla.org/products/firefox/buttons/firefox_80x15.png" alt="Get Firefox" border="0" height="15" width="80" style="vertical-align: middle;" /></a> и с разрешением 1280*1024. Рекомендуемые BitTorrent-клиенты: <a href="https://www.utorrent.com/download.php" title="Get uTorrent"><img src="{1}utorrent.png" alt="Get uTorrent" border="0" height="15" width="80" style="vertical-align: middle;" /></a> <a href="https://www.getazureus.com/download.php" title="Get Azureus"><img src="{1}azureus.png" alt="Get Azureus" border="0" height="15" width="80" style="vertical-align: middle;" /></a>', // Обновлено в v3.8
	'uploaddat'			=>'Загружено',
	'showlast'			=>'Показать последние сообщения чата', // Добавлено v3.6	
	'size'=>'Размер',//Добавлено v4.1
	'dactiveusers' => ' ({1} гостей, {2} участников, {3} скрытых участников)', // Добавлено V4.2
	'members'=>'Участники',// Добавлено в v5.0
	'threads'=>'Темы',// Добавлено в v5.0
	'posts'=>'Сообщения',// Добавлено в v5.0
	'by'=>'от {1}',// Добавлено в v5.0
	'last'=>'Перейти к последнему сообщению',// Добавлено в v5.0
	'online'=>'Больше всего пользователей одновременно было онлайн: {1}, {2} в {3}',// Добавлено в v5.0
	'llogin'=>'Последний вход: {1}',// Добавлено в v5.0
	'pmessages'=>'Личные сообщения:',// Добавлено в v5.0
	'unreadmessages'=>'{1} непрочитанных.',// Добавлено в v5.0
	'logout'=>'Выйти',// Добавлено в v5.0
	'show'=>'Показать список',// Добавлено в v5.6
	'legend'=>'Легенда пользователей',// Добавлено в v5.6

	// ── index2.php / functions_forumlist.php (новый стиль) ──
	'sec_forums'         => 'Форумы',
	'lnk_new_posts'      => 'Новые сообщения',
	'lnk_search'         => 'Поиск',
	'lbl_total_posts'    => 'Сообщений',
	'lbl_total_threads'  => 'Тем',
	'lbl_total_members'  => 'Участников',
	'lbl_online'         => 'Онлайн',
	'sec_whos_online'    => 'Кто онлайн',
	'lbl_active_minutes' => 'активны за последние {1} мин.',
	'lbl_members'        => 'Участники',
	'lbl_guests'         => 'Гости',
	'lbl_invisible'      => 'Скрытые',
	'lbl_bots'           => 'Боты',
	'lbl_nobody_online'  => 'Сейчас нет участников онлайн',
	'todays_birthdays'   => 'Дни рождения сегодня',
	'birthdayhidden'     => 'скрыто',
	'sec_board_stats'    => 'Статистика форума',
	'btn_full_stats'     => 'Вся статистика',
	'lbl_new_today'      => 'Новых сегодня',
	'lbl_peak_online'    => 'Рекорд онлайна',
	'lbl_newest_member'  => 'Новый участник',
	'lbl_no_forums'      => 'Форумы не найдены',
	'expcol_collapse'    => 'Свернуть / развернуть',
	'lbl_cat_empty'      => 'В этой категории нет форумов',
	'lbl_never'          => 'Сообщений пока нет',
	'lbl_threads'        => 'Тем',
	'lbl_posts'          => 'Сообщений',
	'viewing_one'        => '1 смотрит',
	'viewing_multiple'   => '{1} смотрят',
);
?>