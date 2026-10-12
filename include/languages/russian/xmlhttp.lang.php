<?php


if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// xmlhttp.php *** Re-Coded in v4.1 ***
$language['xmlhttp'] = array 
(

'no_new_subject' => "Вы не ввели новую тему.",
'post_moderation' => "Ваше сообщение отправлено на модерацию.",
'thread_moderation' => "Ваша тема отправлена на модерацию.",
'post_doesnt_exist' => "Указанное сообщение не существует.",
'thread_doesnt_exist' => "Указанная тема не существует.",
'thread_closed_edit_subjects' => "Тема закрыта, изменять заголовки нельзя.",
'no_permission_edit_subject' => "У вас нет прав на изменение заголовка этой темы.",
'thread_closed_edit_message' => "Тема закрыта, редактировать сообщения в ней нельзя.",
'no_permission_edit_post' => "У вас нет прав на редактирование этого сообщения.",
'edit_time_limit' => "Сообщения можно редактировать только в течение {1} мин. после публикации.",
'postbit_edited' => "Сообщение изменено: {1}, автор правки:",
'postbit_editreason' => "Причина правки",
'save_changes' => "Сохранить изменения",
'cancel_edit' => "Отменить редактирование",
'answer_valid_not_exists' => "Вопрос, на который вы пытаетесь ответить, не существует.",
'captcha_not_exists' => "Изображение проверочного кода, которое вы пытаетесь обновить, не существует.",
'captcha_valid_not_exists' => "Изображение проверочного кода, которое вы пытаетесь проверить, не найдено.",
'captcha_does_not_match' => "Код с картинки введён неверно. Введите код точно так, как он показан на изображении.",
'captcha_matches' => "Код с картинки введён верно.",
'answer_does_not_match' => "Ответ неверный.",
'banned_username' => "Введённое имя пользователя запрещено администратором",
'banned_characters_username' => "Имя пользователя содержит недопустимые символы",
'complex_password_fails' => "Пароль должен содержать заглавную букву, строчную букву и цифру",
'username_taken' => "Имя {1} уже занято другим пользователем",
'username_available' => "Имя {1} свободно",
'invalid_username' => "Пользователя с именем {1} не существует",
'valid_username' => "Пользователь {1} может быть указан как пригласивший.",
'buddylist_error' => "В вашем списке друзей никого нет. Добавьте друзей, прежде чем использовать эту функцию.",
'close' => "Закрыть",
'select_buddies' => "Выбор друзей",
'select_buddies_desc' => "Чтобы добавить друзей в получатели, отметьте их ниже и нажмите OK.",
'selected_recipients' => "Выбранные получатели",
'ok' => "OK",
'cancel' => "Отмена",
'online' => "В сети",
'offline' => "Не в сети",
'edited_post' => "Правка сообщения",
'usergroup' => "Группа",

// ── Added: security / access ──
'err_invalid_post_code' => "Недействительный токен безопасности. Обновите страницу и попробуйте снова.",
'err_not_logged_in' => "Вы не авторизованы",
'err_invalid_request_method' => "Недопустимый метод запроса",
'err_invalid_parameters' => "Некорректные параметры",
'err_access_denied' => "Доступ запрещён",
'err_forbidden' => "Недостаточно прав для этого действия",
'err_comment_not_allowed' => "Вам запрещено оставлять комментарии",

// ── Added: threads ──
'err_invalid_thread_id' => "Некорректный ID темы",
'err_thread_not_found' => "Тема не найдена",
'err_thread_no_view_permission' => "У вас нет прав на просмотр этой темы",
'lbl_guest' => "Гость",
'lbl_unknown' => "Неизвестно",

// ── Added: torrent editing ──
'err_name_empty' => "Название не может быть пустым",
'err_descr_empty' => "Описание не может быть пустым",
'err_no_torrent_id' => "Не указан ID раздачи",
'err_torrent_not_found' => "Раздача не найдена",
'flash_torrent_updated' => "Данные обновлены",
'err_torrent_update_failed' => "Не удалось обновить данные",

);
