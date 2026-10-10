<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

// forumdisplay.php (russian)
$language['forumdisplay'] = array
(

// ── Errors / access ──
    'err_invalid_forum' => 'Неверный форум',
    'err_forum_not_found' => '{1} - Форум не найден',
    'err_contains_no_forums' => 'К сожалению, в просматриваемом разделе нет подфорумов.',
    'err_no_permission' => 'К сожалению, у вас нет прав на просмотр тем в этом разделе.',
    'hint_no_permission' => 'Доступ к темам этого раздела для вас закрыт.',

// ── Section headings / empty states ──
    'sec_subforums_in' => 'Форумы в разделе {1}',
    'msg_no_threads_title' => 'Тем пока нет',
    'msg_no_threads_hint' => 'Нет тем, подходящих под выбранные условия. Попробуйте изменить фильтры.',

// ── Labels ──
    'lbl_by' => 'от',
    'lbl_moderated_by' => 'Модераторы:',
    'lbl_thread' => 'Тема',
    'lbl_author' => 'Автор',
    'lbl_replies' => 'Ответов',
    'lbl_last_post' => 'Последнее сообщение',
    'lbl_pages' => 'Страницы:',
    'lbl_moved' => 'Перемещена',
    'lbl_guest' => 'Гость',
    'lbl_sort_asc' => 'по возр.',
    'lbl_sort_desc' => 'по убыв.',
    'lbl_users_browsing' => 'Сейчас этот раздел просматривают:',
    'lbl_guests_browsing' => 'Гостей: {1}',
    'lbl_invis_browsing' => 'Скрытых пользователей: {1}',
    'lbl_clear_stored_password' => 'Удалить сохранённый пароль раздела',

// ── Buttons / links ──
    'btn_post_thread' => 'Создать тему',
    'btn_subscribe' => 'Подписаться на раздел',
    'btn_unsubscribe' => 'Отписаться от раздела',
    'btn_go' => 'Показать',
    'btn_inline_go' => 'Выполнить',
    'btn_clear' => 'Сбросить',
    'btn_clear_selection' => 'Снять выделение.',
    'btn_cancel_return' => 'Отмена',
    'btn_delete_threads' => 'Удалить темы навсегда',

// ── Placeholders / aria ──
    'ph_search_keywords' => 'Введите ключевые слова...',
    'aria_close' => 'Закрыть',

// ── Tooltips / hints ──
    'tip_mark_read' => 'Отметить раздел прочитанным',
    'tip_goto_first_unread' => 'Перейти к первому непрочитанному сообщению',
    'tip_attachment_one' => 'В теме 1 вложение',
    'tip_attachment_many' => 'Вложений в теме: {1}',
    'tip_unapproved_one' => 'В теме 1 неодобренное сообщение.',
    'tip_unapproved_many' => 'Неодобренных сообщений в теме: {1}.',
    'tip_rss_latest' => 'Последние темы в разделе {1}',

// ── Thread status icons (spaces are intentional: labels are concatenated) ──
    'tip_icon_dot' => 'Есть ваши сообщения. ',
    'tip_icon_no_new' => 'Нет новых сообщений.',
    'tip_icon_new' => 'Есть новые сообщения.',
    'tip_icon_hot' => ' Популярная тема.',
    'tip_icon_close' => ' Тема закрыта.',

// ── Sort / filter options ──
    'opt_sort_subject' => 'Сортировка: по названию',
    'opt_sort_lastpost' => 'Сортировка: по последнему сообщению',
    'opt_sort_starter' => 'Сортировка: по автору',
    'opt_sort_started' => 'Сортировка: по дате создания',
    'opt_sort_replies' => 'Сортировка: по ответам',
    'opt_sort_views' => 'Сортировка: по просмотрам',
    'opt_order_asc' => 'Порядок: по возрастанию',
    'opt_order_desc' => 'Порядок: по убыванию',
    'opt_date_1day' => 'Период: сегодня',
    'opt_date_5days' => 'Период: 5 дней',
    'opt_date_10days' => 'Период: 10 дней',
    'opt_date_20days' => 'Период: 20 дней',
    'opt_date_50days' => 'Период: 50 дней',
    'opt_date_75days' => 'Период: 75 дней',
    'opt_date_100days' => 'Период: 100 дней',
    'opt_date_lastyear' => 'Период: последний год',
    'opt_date_beginning' => 'Период: всё время',

// ── Inline moderation options ──
    'opt_delayed_moderation' => 'Отложенная модерация',
    'opt_group_standard' => 'Стандартные инструменты',
    'opt_close_threads' => 'Закрыть темы',
    'opt_open_threads' => 'Открыть темы',
    'opt_stick_threads' => 'Закрепить темы',
    'opt_unstick_threads' => 'Открепить темы',
    'opt_approve_threads' => 'Одобрить темы',
    'opt_unapprove_threads' => 'Снять одобрение с тем',
    'opt_delete_threads' => 'Удалить темы навсегда',
    'opt_move_threads' => 'Переместить / копировать темы',

// ── Select-all rows (HTML, output as is) ──
    'sel_page_html' => 'Выбраны все <strong>{1}</strong> тем на этой странице.',
    'sel_select_all_html' => 'Выбрать все <strong>{1}</strong> тем в этом разделе.',
    'sel_all_html' => 'Выбраны все <strong>{1}</strong> тем в этом разделе.',

// ── Delete threads modal ──
    'modal_del_title' => 'Удалить темы навсегда',
    'modal_del_subtitle' => 'Необратимое действие — будьте осторожны',
    'modal_del_selected' => 'Тем выбрано для удаления',
    'modal_del_warn_title' => 'Критическое предупреждение',
    'modal_del_warn_html' => 'Вы собираетесь <strong>безвозвратно удалить</strong> выбранные темы. Это действие <strong>нельзя отменить!</strong>',
    'modal_del_item_posts' => 'Все сообщения в этих темах будут удалены без возможности восстановления',
    'modal_del_item_attach' => 'Все вложения будут удалены с сервера',
    'modal_del_item_polls' => 'Опросы и данные голосования будут стёрты',
    'modal_del_item_history' => 'История и статистика тем будут утеряны',
    'modal_del_item_norecover' => 'Восстановление невозможно',
    'modal_del_dataloss_title' => 'Потеря данных:',
    'modal_del_dataloss_text' => 'Содержимое будет окончательно удалено из базы данных.',
    'modal_del_preview_title' => 'Темы, которые будут удалены:',
    'modal_del_confirm_strong' => 'Я понимаю, что это действие необратимо.',
    'modal_del_confirm_text' => 'Я проверил(а) выбор и действительно хочу удалить эти темы.',

// ── JS strings (exported to AGS_LANG without the js_ prefix) ──
    'js_click_hold_edit' => '(Зажмите, чтобы изменить)',
    'js_saving' => 'Сохранение...',
    'js_error_msg' => 'Ошибка: {1}',
    'js_subject_updated' => 'Название темы обновлено',
    'js_error_updating' => 'Не удалось обновить название темы',
    'js_whoposted_failed' => 'Не удалось загрузить. Повторите попытку.',
    'js_select_thread' => 'Выберите хотя бы одну тему.',
    'js_deleting' => 'Удаление...',
    'js_delete_threads' => 'Удалить темы навсегда',
    'js_thread_id' => 'ID темы: {1}',
    'js_tid_badge' => 'TID: {1}',

);
?>
