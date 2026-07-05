<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

$language['global'] = array(


'postbit_attachments' => "Прикреплённые файлы",

'change_user' => "сменить пользователя",

'please_correct_errors' => "Пожалуйста, исправьте следующие ошибки перед продолжением:",

'error_invalidpost' => "Указанное сообщение не существует.",

'error_invalidthread' => "Указанная тема не существует.",

'error_closedinvalidforum' => "Вы не можете публиковать сообщения в этом разделе, так как он закрыт, является перенаправлением или категорией.",

'enter_password_below' => "Пожалуйста, введите пароль ниже:",

'verify_forum_password' => "Проверка пароля раздела",

'forum_password_note' => "Администратор установил обязательный пароль для доступа к этому разделу.",

'password_required' => "Требуется пароль",

'wrong_forum_password' => "Введённый пароль неверен. Пожалуйста, попробуйте снова.",

'more_subforums' => "и ещё {1}.",

'forumbit_threads' => "Темы",
'forumbit_posts' => "Сообщения",

'forum_closed' => "Раздел закрыт",

'no_new_posts' => "Нет новых сообщений",

'new_posts' => "Есть новые сообщения",

'forum_redirect' => "Перенаправление",

'postbit_button_reply_all' => 'Ответить всем',

'postbit_button_quote' => 'Ответить',

'toplinks_help' => "Помощь",

'view_attachments' => "[Просмотреть мои вложения]",

'awaiting_message_link' => " <a href=\"{1}/admin/index.php?act=awaiting_activation\">Перейти в панель управления</a>.",
'awaiting_message_single' => "Есть 1 аккаунт, ожидающий активации. Перейдите в панель управления для активации.",
'awaiting_message_plural' => "Есть {1} аккаунтов, ожидающих активации. Перейдите в панель управления для активации.",

'error_banned' => "К сожалению, вы заблокированы. Вы не можете писать сообщения, читать темы или пользоваться форумом. Обратитесь к администратору, если у вас есть вопросы.",

'error_nopermission_user_1' => "У вас нет доступа к этой странице. Это может быть по одной из следующих причин:",
'error_nopermission_user_ajax' => "У вас нет доступа к этой странице.",
'error_nopermission_user_2' => "Ваш аккаунт заблокирован или вам запрещён доступ к этому ресурсу.",
'error_nopermission_user_3' => "У вас нет прав для доступа к этой странице. Проверьте правила трекера.",
'error_nopermission_user_4' => "Ваш аккаунт ещё ожидает активации или проверки.",
'error_nopermission_user_5' => "Вы открыли эту страницу напрямую, а не через соответствующие формы или ссылки.",
'error_nopermission_user_resendactivation' => "Повторно отправить код активации",
'error_nopermission_user_username' => "Вы вошли под именем пользователя: '{1}'",

'error_nopermission_guest_1' => "Вы не вошли в систему или у вас нет прав для просмотра этой страницы. Возможные причины:",
'error_nopermission_guest_2' => "Вы не вошли в систему или не зарегистрированы. Пожалуйста, войдите и попробуйте снова.",
'error_nopermission_guest_3' => "У вас нет прав для доступа к этой странице. Проверьте правила трекера.",
'error_nopermission_guest_4' => "Ваш аккаунт мог быть отключён администратором или ожидает активации.",
'error_nopermission_guest_5' => "Вы открыли эту страницу напрямую, а не через соответствующие формы или ссылки.",

'emailsubject_activateaccount' => "Активация аккаунта на {1}",

'email_adminactivateaccount' => "{1},

Администратор активировал ваш аккаунт на форуме {2}.

Для входа перейдите по ссылке:

{3}

Вы можете войти с данными, указанными при регистрации.

Спасибо,
Команда {2}",

'postbit_multiquote' => "Цитировать это сообщение",

'postbit_button_multiquote' => 'Цитата',

'welcome_logout' => "Выйти",

'lastvisit_never' => "Никогда",
'lastvisit_hidden' => "(Скрыто)",

'banned_warning' => "Ваш аккаунт на трекере заблокирован.",
'banned_warning2' => "Причина блокировки",
'banned_warning3' => "Блокировка будет снята",
'banned_lifted_never' => "Никогда",

'mod_notice' => "Ожидает проверки: {1}.",
'unapproved_thread' => "1 непроверенная тема",
'unapproved_threads' => "{1} непроверенных тем",
'unapproved_post' => "1 непроверенное сообщение",
'unapproved_posts' => "{1} непроверенных сообщений",

'unapproved_attachment' => "1 непроверенное вложение",
'unapproved_attachments' => "{1} непроверенных вложений",

'postbit_unapproved_attachments' => "{1} непроверенных вложений.",
'postbit_unapproved_attachment' => "1 непроверенное вложение.",

'postbit_attachment_filename' => "Имя файла:",
'postbit_attachment_size' => "Размер:",

'welcome_newposts' => "Новые сообщения",
'welcome_todaysposts' => "Сообщения за сегодня",

'post_fetch_error' => 'Ошибка при загрузке сообщений.',

'click_mark_read' => "Нажмите, чтобы отметить раздел как прочитанный",

'comma' => ", ",

'index_logout' => "Выйти",

'by' => "от",

'search_button' => 'Поиск',

'multipage_jump' => "Перейти на страницу",
'multipage_link_start' => " &hellip;",
'multipage_link_end' => "&hellip; ",

'go' => "Перейти",

'attachments' => "Вложения",

'thread_subscription_method' => "Подписка на тему:",
'thread_subscription_method_desc' => "Укажите тип уведомлений и подписки на эту тему (только для зарегистрированных пользователей).",
'no_subscribe' => "Не подписываться на тему",
'no_subscribe_notification' => "Подписаться без получения уведомлений о новых ответах",
'instant_email_subscribe' => "Подписаться и получать уведомления на email",
'instant_pm_subscribe' => "Подписаться и получать уведомления в личные сообщения",

'postbit_editreason' => "Причина редактирования",

'postbit_button_qdelete' => 'Удалить',

'postbit_qdelete_post' => "Удалить это сообщение",

'postbit_button_edit' => 'Редактировать',

'postbit_quick_edit' => "Быстрое редактирование",
'postbit_full_edit' => "Полное редактирование",

'dismiss_notice' => "Закрыть это уведомление",

'welcome_modcp' => "Панель модератора",

'welcome_admin' => "Панель администратора",

'welcome_usercp' => "Личный кабинет",

'select2_match' => "Найден один результат, нажмите Enter для выбора.",
'select2_matches' => "Найдено {1} результатов, используйте стрелки для навигации.",
'select2_nomatches' => "Совпадений не найдено",
'select2_inputtooshort_single' => "Введите один или несколько символов",
'select2_inputtooshort_plural' => "Введите не менее {1} символов",
'select2_inputtoolong_single' => "Удалите один символ",
'select2_inputtoolong_plural' => "Удалите {1} символов",
'select2_selectiontoobig_single' => "Можно выбрать только один элемент",
'select2_selectiontoobig_plural' => "Можно выбрать не более {1} элементов",
'select2_loadmore' => "Загрузка результатов&hellip;",
'select2_searching' => "Поиск&hellip;",

'ajax_loading' => "Загрузка. <br />Пожалуйста, подождите&hellip;",
'saving_changes' => "Сохранение изменений&hellip;",

'deleteevent_confirm' => "Вы уверены, что хотите удалить это событие?",
'removeattach_confirm' => "Вы уверены, что хотите удалить выбранное вложение из этого сообщения?",

'unknown_error' => "Произошла неизвестная ошибка.",
'confirm_title' => "Подтверждение",

'expcol_collapse' => '[-]',
'expcol_expand' => '[+]',

'toplinks_portal' => "Портал",
'bottomlinks_forumteam' => "Команда форума",
'bottomlinks_contactus' => "Связаться с нами",
'bottomlinks_returntop' => "Вернуться наверх",
'bottomlinks_syndication' => "RSS-синдикация",
'bottomlinks_litemode' => "Лёгкий режим (архив)",
'bottomlinks_markread' => "Отметить все разделы как прочитанные",

'postbit_show_ignored_post' => "Показать это сообщение",
'postbit_post_unapproved' => "Это сообщение ожидает проверки.",
'postbit_thread_unapproved' => "Эта тема ожидает проверки.",

'postbit_status_online' => "В сети",
'postbit_status_offline' => "Не в сети",

'postbit_status' => "Статус:",

'debug_generated_in' => "Сгенерировано за {1}",
'debug_weight' => "({1} PHP / {2} {3})",
'debug_sql_queries' => "SQL запросов: {1}",
'debug_server_load' => "Нагрузка сервера: {1}",
'debug_memory_usage' => "Использование памяти: {1}",
'debug_advanced_details' => "Подробная информация",

'postbit_button_forward' => 'Переслать',
'postbit_button_delete_pm' => 'Удалить',

'welcome_pms_usage' => "(Непрочитанных {1}, всего {2})",
'welcome_pms' => "Личные сообщения",

'newpm_notice_one' => "<strong>У вас одно непрочитанное личное сообщение</strong> от {1} с темой <a href=\"{2}/private.php?action=read&amp;pmid={3}\" style=\"font-weight: bold;\">{4}</a>",
'newpm_notice_multiple' => "<strong>У вас {1} непрочитанных личных сообщений.</strong> Последнее от {2} с темой <a href=\"{3}/private.php?action=read&amp;pmid={4}\" style=\"font-weight: bold;\">{5}</a>",

'redirect_width' => "50%",
'invalid_comm' => 'Указанный комментарий не существует.',
'error' => 'Произошла ошибка!',
'permission' => 'К сожалению, у вас нет прав для просмотра этой страницы.',
'notavailable' => 'К сожалению, эта функция отключена.',
'nopermission' => 'Извините, доступ запрещён!',
'permissionlogmessage' => 'Обнаружен несанкционированный доступ.<br />Страница: {1},<br /> Строка запроса: {2} <br />Пользователь: {3},<br />IP: {4}.<br />Доступ заблокирован.',
'print_no_permission' => '<table border="0" cellspacing="0" cellpadding="4" class="tborder">
<tr>
<td class="thead"><span class="smalltext"><strong>{1}</strong></span></td>
</tr>
<tr>
<td class="trow1">
У вас нет доступа к этой странице. Возможные причины:
<ol>
	<li>Ваш аккаунт заблокирован или вам запрещён доступ к этому ресурсу.</li>
	<li>У вас нет прав для доступа к этой странице. Проверьте правила трекера.</li>
	<li>Ваш аккаунт ещё ожидает активации или проверки.</li>
	<li>{2}</li>
</ol>
</td>
</tr>
</table>',
'print_no_permission_i' => 'Если у вас есть вопросы, пожалуйста, свяжитесь с нами.',
'invalidid' => 'Неверный ID!',
'invalididlogged' => 'Неверный ID! В целях безопасности это действие было зарегистрировано!',
'invalididlogged2' => '<div class="error" align="center"><b>Ошибка: Неверный ID! В целях безопасности это действие было зарегистрировано!</b></div>',
'invalididlogmsg' => 'Попытка с неверным ID: URL: {1} - Пользователь: {2} - IP: {3} в {4}.',
'noresultswiththisid' => 'По данному ID результатов не найдено!',
'invalidimagecode' => 'Введённый код не совпадает с отображаемым.<br />У вас осталось <b>{1}</b> попыток.',
'nouserid' => 'Пользователь с таким ID не найден!',
'nousername' => 'Пользователь с таким именем не найден!',
'notorrentid' => 'Торрент с таким ID не найден!',
'notorrentname' => 'Торрент с таким именем не найден!',
'accountdisabled' => '<b><font color="red">Этот аккаунт отключён!</font></b>',
'sorry' => 'Извините',
'invalidaction' => 'Неизвестное действие!',
'dberror' => 'Ошибка базы данных, пожалуйста, попробуйте позже.',
'trylater' => 'Произошла ошибка. Пожалуйста, попробуйте позже.',
'nothingfound' => 'Ничего не найдено',
'accessdenied' => 'Доступ запрещён!',
'permissiondenied' => 'Доступ запрещён!',
'flooderror' => 'Трекер требует подождать <b>{1}</b> секунд между отправкой {2}. Пожалуйста, попробуйте через <b>{3}</b> секунд.',
'dontleavefieldsblank' => 'Пожалуйста, не оставляйте обязательные поля пустыми!',
'allfieldsrequired' => 'Все поля обязательны для заполнения!',
'viptorrent' => 'У вас нет прав для просмотра этого торрента. Он доступен только для <b><a href="donate.php">VIP УЧАСТНИКОВ</a></b>.',
'torrentbanned' => 'Этот торрент заблокирован!',
'welcomeback' => 'С возвращением,',
'logout' => '[выйти]',
'ratio' => 'Рейтинг:',
'bonus' => 'Бонус:',
'uploaded' => 'Отдано:',
'downloaded' => 'Скачано:',
'whencompleted' => 'Когда завершено',
'donate' => 'Нажмите здесь для пожертвования',
'inboxnonew' => 'Входящие (нет новых сообщений)',
'enterusername' => 'Введите имя пользователя',
'inboxnew' => 'Входящие (есть новое сообщение с вашего последнего визита, нажмите здесь для чтения.)',
'home' => 'Главная',
'forums' => 'Форумы',
'browse' => 'Обзор',
'requests' => 'Запросы',
'upload' => 'Загрузить',
'usercp' => 'Личный кабинет',
'irc' => 'IRC',
'top10' => 'Топ-10',
'help' => 'Помощь',
'extra' => 'Дополнительно',
'staff' => 'Персонал',
'redirect' => 'Сейчас вы будете перенаправлены...',
'msgsend' => 'Сообщение успешно отправлено!',
'staffmenu' => 'Меню персонала',
'fakeaccount' => 'Мы считаем, что вы используете поддельный аккаунт, поэтому это действие было зарегистрировано!',
'alreadylogged' => 'Вы уже вошли в систему!',
'nowaitmessage' => 'Нажмите здесь, если не хотите больше ждать.',
'cachedmessage' => '<div align="center" class="smalltext">Этот контент был кэширован <strong>{1}</strong>. Статистика обновляется каждые <strong>{2}</strong> минут.</div>',
'browsermessage' => '<p class="error" align="justify">Если куки включены, но вы всё равно не можете войти, возможно, возникла проблема с вашим cookie для входа. Рекомендуем удалить куки и попробовать снова.</p>',
'mailerror' => 'Не удалось отправить письмо. Пожалуйста, свяжитесь с администратором.',
'success' => 'Успешно',
'mailsent' => 'Письмо с подтверждением отправлено на <b>{1}</b>. Пожалуйста, подождите несколько минут.',
'mailsent2' => 'Данные нового аккаунта отправлены на <b>{1}</b>. Пожалуйста, подождите несколько минут.',
'xlocked' => '{1} заблокирован! (достигнуто максимальное количество неудачных попыток {1} при повторной аутентификации)',
'xlocked2' => 'Мы считаем, что вы пытаетесь обойти нашу систему, поэтому ваш IP заблокирован!<br /><br />Нажмите {1}здесь</a>, чтобы заполнить форму запроса на разблокировку.<br />Нажмите {2}здесь</a>, чтобы связаться с нами!',
'warning' => 'Предупреждение!',
'accountwarn' => "С момента вашего последнего входа кто-то пытался получить доступ к вашему аккаунту. Попытка не удалась.\nДанные попытки входа:\n\nИмя пользователя: {1}\nПароль: {2} (MD5: {3})\n\nIP адрес: {4}\nИмя хоста: {5}\n\nЕсли вы считаете, что это ошибка, свяжитесь с персоналом.\nСпасибо.",
'incorrectlogin' => '<b>Ошибка</b>: Неверное имя пользователя или пароль!<br /><br />Не помните пароль? <b>{1}Восстановить</a></b> пароль!',
'invitedisabled' => 'Система приглашений временно отключена.',
'inviteonly' => 'Для регистрации на нашем трекере необходимо приглашение. Всего хорошего.',
'signupdisabled' => 'Регистрация временно отключена.',
'signuplimitreached' => 'Достигнут максимальный лимит аккаунтов. Неактивные аккаунты периодически удаляются, попробуйте позже.',
'nodupeaccount' => 'IP {1} уже используется на другом аккаунте! Дублирующие аккаунты запрещены.',
'nodupeaccount2' => 'Извините, этот IP адрес уже использовался ранее. Если вы считаете, что это ошибка, свяжитесь с нами!',
'secimage' => 'Защитное изображение:<br />(учитывается регистр)',
'seccode' => 'Защитный код: ',
'slots' => 'Слоты: <font color="white">{1}</font>&nbsp;&nbsp;',
'serverload' => '<html><head><meta http-equiv="refresh" content="5 {1}"></head><body><table border=0 width=100% height=100%><tr><td><h3 align=center>Нагрузка на сервер очень высокая. Повторная попытка, пожалуйста, подождите...</h3></td></tr></table></body></html>',
'toomanyusers' => 'Слишком много пользователей. Нажмите кнопку обновления в браузере для повторной попытки.',
'ipbanned' => '<html><body><h1>403 Запрещено</h1>Несанкционированный IP адрес.</body></html>',
'trackerclosed' => '<font color="red"><b>Извините, сайт временно недоступен для технического обслуживания. Пожалуйста, зайдите позже...</b></font>',
'newmessage' => 'входящие (есть новое сообщение с вашего последнего визита, нажмите здесь для чтения.)',
'nonewmessage' => 'входящие (нет новых сообщений)',
'annoucementempty' => 'Пусто!',
'nonewannoucement' => 'В данный момент новых объявлений нет.',
'edit' => 'Редактировать',
'deletecomment' => 'Удалить',
'vieworj' => 'Просмотреть оригинал',
'lastedited' => 'Последнее редактирование: ',
'sendmessageto' => 'Написать сообщение ',
'reportcomment' => 'Пожаловаться на комментарий',
'type' => 'Тип',
'name' => 'Название',
'added' => 'Добавлено',
'dl' => 'Скачать',
'wait' => 'Ожидание',
'visible' => 'Видимость',
'avprogress' => 'Здоровье',
'progress' => 'Прогресс',
'speed' => 'Скорость',
'notraffic' => 'Нет трафика',
'size' => 'Размер',
'ttl' => 'TTL',
'free' => 'Бесплатно',
'rec' => 'Рек.',
'views' => 'Просмотры',
'hits' => 'Хиты',
'lastaction' => 'Последнее действие',
'leechers' => 'Личеры',
'seeders' => 'Сидеры',
'snatched' => 'Скачано',
'uploader' => 'Загрузчик',
'action' => 'Действие',
'none' => 'Нет',
'greenyes' => '<font color="green">Да</font>',
'redno' => '<font color="red">Нет</font>',
'yes' => '<b>да</b>',
'no' => '<b>нет</b>',
'anonymous' => '<i>[Анонимно]</i>',

'unknown' => '<i>(неизвестно)</i>',
'unknown2' => "Неизвестно",

'freedownload' => '<b>Бесплатный торрент</b> (записывается только статистика раздачи!)',
'newtorrent' => '<b>Новый торрент</b> (новый релиз)',
'disabled' => 'Отключено',
'parked' => 'Ваш аккаунт припаркован.',
'legend' => '<fieldset class="fieldset"><legend><b>Легенда</b></legend><center>
&nbsp;<b><font color="darkred">Руководитель персонала</font>&nbsp;&nbsp;<font color=#2587A7>Сисоп</font>&nbsp;&nbsp;<font color=#B000B0>Администратор</font>&nbsp;&nbsp;<font color=#ff5151>Модератор</font>&nbsp;&nbsp;<font color=#6464FF>Загрузчик</font>&nbsp;&nbsp;<font color=#009F00>VIP</font>&nbsp;&nbsp;<font color=#f9a200>Продвинутый пользователь</font>&nbsp;&nbsp;<font color=black>Пользователь</font>&nbsp;&nbsp;Донор\'s<img src="{1}star.gif" border=0 style="vertical-align: middle;">&nbsp;&nbsp;Предупреждённые<img src="{1}warned.gif" border=0 style="vertical-align: middle;">&nbsp;&nbsp;Заблокированные<img src="{1}disabled.gif" border=0 style="vertical-align: middle;"></b></center></fieldset>',
'pagedown' => 'Извините, эта страница недоступна для технического обслуживания. Пожалуйста, зайдите позже...',
'pleasewait' => 'Пожалуйста, подождите ...',
'sqlerror' => 'ОШИБКА SQL',
'sqlerror2' => 'Произошла ошибка! Пожалуйста, свяжитесь с администратором.',
'quote' => 'Написал:',
'quote2' => 'Цитата:',
'quote3' => 'ЦИТАТА',
'code' => 'КОД',
'user' => 'Пользователь',
'poweruser' => 'Продвинутый пользователь',
'vip' => 'VIP',
'uploader' => 'Загрузчик',
'moderator' => 'Модератор',
'sysop' => 'Сисоп',
'administrator' => 'Администратор',
'staffleader' => 'Руководитель персонала',
'guest' => 'Гость',
'supermod' => 'Супер-модератор',
'awaitingactivation' => 'Ожидает активации',
'banned' => 'Заблокирован',
'betatester' => 'Бета-тестер',
'sendtousername' => 'Кому (имя пользователя): ',
'subject' => 'Тема:',
'message' => 'Ваше сообщение:',
'pmspace' => 'использовано места для ЛС.',
'reached_warning' => 'Внимание. Вы достигли лимита сообщений.',
'reached_warning2' => 'Для получения новых сообщений необходимо удалить старые.',
'pmlimitmsg' => 'У вас хранится <strong>{1}</strong> сообщений из <strong>{2}</strong> допустимых.',
'pmmsg' => '{1} содержит {2} сообщений.',
'moresmiles' => 'Больше смайлов',
'moresmilestitle' => 'Больше кликабельных смайлов',
'color' => 'Цвет',
'font' => 'Шрифт',
'size' => 'Размер',
'closealltags' => 'Закрыть все теги',
'list' => 'СПИСОК',
'finduser' => 'Найти пользователя',
'redirectto' => 'Перенаправить на',
'invalidlink' => 'Неверная ссылка?',
'clicktoreport' => 'Нажмите здесь для жалобы',
'shouterror' => 'Извините, у вас нет прав для использования чата!',
'proxydetected' => 'Обнаружен прокси-сервер. Регистрация через прокси запрещена.',
'buttonsearch' => 'поиск',
'buttoncheckall' => 'выбрать все',
'buttonsave' => 'Сохранить',
'buttonreset' => 'Сбросить',
'buttonpreview' => 'Предпросмотр',
'buttonshout' => 'отправить',
'buttonclear' => 'очистить',
'buttonrate' => 'оценить',
'buttonthanks' => 'Сказать спасибо!',
'buttonsubmit' => 'Отправить',
'buttonrevert' => 'Отменить изменения',
'buttonselect' => 'Выбрать пользователя',
'buttonclosewindow' => 'Закрыть окно',
'buttondelete' => 'Удалить!',
'buttonlogin' => 'Войти',
'buttongo' => 'Перейти!',
'buttongoback' => 'Назад!',
'buttonrecover' => 'Восстановить',
'buttonreport' => 'Пожаловаться!',
'buttonremoveframe' => 'Убрать этот фрейм',
'buttonsend' => 'Отправить',
'imgdonated' => 'Донор',
'imgdisabled' => 'Этот аккаунт отключён!',
'imgwarned' => 'Предупреждён',
'imgupdated' => 'Обновлено',
'imgshowhide' => 'Показать/Скрыть',
'imgnew' => 'Новое',
'modnotice' => '<strong><a href="userdetails.php?id={1}"><span style="color: darkred;"><strong><em>{2}</em></strong></span></a> отредактировал это сообщение {3} по причине:</strong>
	<br /><p>{4}</p>',
'usergroup' => 'Группа пользователей:',
'smilies' => 'Смайлы',
'postoptions' => 'Параметры сообщения:',
'title' => 'Заголовок:',
'silverdownload' => '<b>Серебряный торрент</b> (записывается только 50% статистики скачивания!)',
'started' => 'Начато',
'imgcommentpos' => 'Комментарии отключены!',
'imgsendpmpos' => 'ЛС отключены!',
'imgchatpost' => 'Чат/Шаутбокс отключён!',
'imgdownloadpos' => 'Скачивание отключено!',
'imguploadpos' => 'Загрузка отключена!',
'previous' => 'Предыдущая',
'first' => 'Первая',
'next' => 'Следующая',
'last' => 'Последняя',
'navigation' => 'Страница {1} из {2} — результаты с {3} по {4} из {5}',
'secimagehint' => 'Изображение сложно прочитать? Нажмите здесь для загрузки нового.',
'weaktorrents' => 'Слабые торренты (торренты, которым нужны сидеры)',
'isnuked' => '<b>Нукнут</b> (торрент помечен как нукнутый)',
'isrequest' => '<b>Запрос</b> (запрошенный торрент)',
'nukedetails' => 'Этот торрент помечен как нукнутый. Причина: {1}',
'year' => 'Год',
'years' => 'Лет',
'month' => 'Месяц',
'months' => 'Месяцев',
'week' => 'Неделя',
'weeks' => 'Недель',
'day' => 'День',
'days' => 'Дней',
'hour' => 'Час',
'hours' => 'Часов',
'minute' => 'Минута',
'minutes' => 'Минут',
'second' => 'Секунда',
'seconds' => 'Секунд',
'GMT' => 'GMT',
'today' => 'Сегодня',
'yesterday' => 'Вчера',
'rel_less_than' => 'Менее ',
'rel_minutes_single' => 'минуты',
'rel_in' => 'Через ',
'rel_ago' => 'назад',
'rel_time' => '<span title=\"{5}{6}\">{1}{2} {3} {4}</span>',
'today_rel' => '<span title=\"{1}\">Сегодня</span>',
'yesterday_rel' => '<span title=\"{1}\">Вчера</span>',
'rel_hours_single' => 'час',
'rel_hours_plural' => 'часов',

'noactiveusersonline' => 'За последние 15 минут активных пользователей не было.',
'logout_error' => 'Произошла ошибка при попытке выйти из системы. Нажмите <a href="logout.php?logouthash={1}" target="_self">здесь</a> для выхода.',
'click_to_add' => 'Нажмите на смайл, чтобы вставить его в сообщение',
'smilies_listing' => 'Список смайлов',
'more_smilies' => 'больше смайлов',
'loading' => 'Загрузка. Пожалуйста, подождите...',
'external' => '(внешний)',
'updateexternal' => 'Обновить внешний торрент',
'externalupdated' => 'Внешний торрент обновлён.',
'recentlyupdated' => 'Этот торрент уже был обновлён.',
'seclisten' => 'Прослушайте аудио и введите услышанные цифры.',
'refresh' => 'обновить',
'noenter' => 'К сожалению, эта кнопка отключена!\n\nПожалуйста, используйте кнопку \'Отправить\'!',
'newmessagebox' => 'Есть новое сообщение с вашего последнего визита, нажмите ОК для чтения.',
'connectablealert' => 'Вы отображаетесь как недоступный на {1} из ваших торрентов. Посетите <a href="{2}">Форумы</a> или страницу <a href="{3}">FAQ</a> для получения советов.',
'advancedbutton' => 'Расширенный режим',
'quickmenu' => 'Быстрое меню',
'qinfo1' => 'Просмотреть публичный профиль',
'qinfo2' => 'Отправить личное сообщение {1}',
'qinfo3' => 'Найти все сообщения {1}',
'qinfo4' => 'Найти все темы {1}',
'qinfo5' => 'Добавить {1} в список друзей',
'qinfo6' => 'Редактировать пользователя',
'qinfo7' => 'Предупредить пользователя',
'qinfo8' => 'Найти все комментарии {1}',
'qinfo9' => 'Найти все загрузки {1}',

'qinfo22' => 'Отправить личное сообщение',
'qinfo33' => 'Найти все сообщения',
'qinfo44' => 'Найти все темы',
'qinfo55' => 'Добавить в список друзей',

'vkeyword' => 'Пожалуйста, используйте виртуальную клавиатуру для ввода/изменения пароля/пин-кода!',
'warningweeks' => '{1} недель(и).',
'warningmessage2' => "Вы были [url=rules.php#warning]предупреждены[/url] за {1} пользователем {2}\n\nПричина: {3}",
'modcommentwarning2' => "{1} - Предупреждён за {2} пользователем {3}\nПричина: {4}\n{5}",
'warningsubject' => 'Вы получили предупреждение!',
'modcommentwarningremovedby' => "{1} - Предупреждение снято пользователем {2}\n{3}",
'warningremovedbysubject' => 'Предупреждение снято.',
'warningremovedbymessage' => 'Ваше предупреждение снято пользователем {1}',
'gotopage' => 'Перейти на страницу...',
'snotice' => 'Уведомление: ',
'times' => 'раз(а)',
'cancel' => 'Отмена',
'sys_message' => 'Системное сообщение',
'show_results' => 'Показаны результаты с {1} по {2} из {3}',
'showing_results' => 'Показаны результаты с {1} по {2} из {3}',
'first_page' => 'Первая страница',
'last_page' => 'Последняя страница',
'next_page' => 'Следующая страница',
'prev_page' => 'Предыдущая страница',
'buttonthanks2' => 'Убрать благодарность',
'storrent' => 'Поиск торрентов',
'storrent22' => 'Поиск',
'storrent2' => 'Ключевые слова:',
'unregistered' => 'Вы не зарегистрированы, пожалуйста <a href="{1}/signup.php?"><u>зарегистрируйтесь</u></a> или <a href="javascript:void(0);" onclick="showLoginBox(\'loginbox\');"><u>войдите</u></a> для полного доступа',
'h1' => 'Необходимо ответить, чтобы увидеть скрытое содержимое.',
'h2' => 'Скрытое содержимое',
'h3' => 'Раскрытое содержимое',
);
?>