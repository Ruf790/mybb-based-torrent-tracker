<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// search.php (+ include/functions_search.php)
$language['search'] = array
(

// ── Navigation / page titles ──
'nav_search'            => "Поиск",
'nav_results'           => "Результаты",
'title_search'          => "Поиск по форуму",
'title_results_threads' => "Результаты поиска",
'title_results_posts'   => "Результаты поиска — сообщения",

// ── Search form: hero ──
'sec_hero_title' => "Поиск по форуму",
'hint_hero'      => "Ищите темы, сообщения и обсуждения по всему форуму",

// ── Search form: quick links ──
'lnk_new_posts'  => "Новые сообщения",
'lnk_today'      => "За сегодня",
'lnk_this_week'  => "За неделю",
'lnk_my_threads' => "Мои темы",
'lnk_my_posts'   => "Мои сообщения",

// ── Search form: main row ──
'ph_keywords'      => "Что ищем… (Ctrl+K)",
'btn_search'       => "Найти",
'btn_clear'        => "Очистить",
'lbl_show_as'      => "Показать как:",
'opt_show_threads' => "Темы",
'opt_show_posts'   => "Сообщения",
'lbl_advanced'     => "Расширенный поиск",

// ── Search form: advanced options ──
'lbl_author'          => "Автор",
'ph_author'           => "Ник…",
'lbl_exact_match'     => "Только точное совпадение ника",
'lbl_search_in'       => "Где искать",
'opt_subject_message' => "В заголовках и тексте",
'opt_subject_only'    => "Только в заголовках",
'lbl_sort_by'         => "Сортировка",
'opt_sortby_lastpost' => "По последнему ответу",
'opt_sortby_dateline' => "По дате сообщения",
'opt_sortby_subject'  => "По заголовку",
'opt_sortby_replies'  => "По числу ответов",
'opt_sortby_views'    => "По просмотрам",
'opt_sortby_starter'  => "По автору",
'opt_sortby_forum'    => "По форуму",
'lbl_order'           => "Порядок",
'opt_order_desc'      => "Сначала новые",
'opt_order_asc'       => "Сначала старые",
'lbl_date_from'       => "Дата с",
'lbl_date_to'         => "Дата по",
'ph_date'             => "ГГГГ-ММ-ДД",
'lbl_min_replies'     => "Минимум ответов",
'lbl_forums'          => "Форумы",
'hint_forums_multi'   => "Зажмите Ctrl, чтобы выбрать несколько",
'search_all_forums'   => "Во всех открытых форумах",

// ── Search form: moderator options (output as is) ──
'mod_options'              => "Опции модератора",
'display_all'              => "Показывать всё",
'display_only_approved'    => "Показывать только одобренное",
'display_only_unapproved'  => "Показывать только неодобренное",
'display_only_softdeleted' => "Показывать только скрытое (мягкое удаление)",
'results'                  => "в результатах",

// ── Results: header (HTML, output as is) ──
'sec_thread_results'   => "Найденные темы",
'sec_post_results'     => "Найденные сообщения",
'lbl_found_thread_one' => "Найдена <strong>{1}</strong> тема",
'lbl_found_threads'    => "Найдено тем: <strong>{1}</strong>",
'lbl_found_post_one'   => "Найдено <strong>{1}</strong> сообщение",
'lbl_found_posts'      => "Найдено сообщений: <strong>{1}</strong>",

// ── Results: sort bar ──
'opt_sort_date'    => "Дата",
'opt_sort_replies' => "Ответы",
'opt_sort_views'   => "Просмотры",
'opt_sort_rating'  => "Рейтинг",
'opt_sort_subject' => "Заголовок",
'opt_sort_author'  => "Автор",
'opt_sort_forum'   => "Форум",

// ── Results: cards ──
'badge_new'         => "Новое",
'badge_hot'         => "Горячая",
'badge_post'        => "Сообщение",
'lbl_replies_count' => "Ответов: {1}",
'lbl_views_count'   => "Просмотров: {1}",
'lbl_lastpost_by'   => "посл. ответ: {2}, {1}",
'btn_view'          => "Открыть",
'btn_view_post'     => "К сообщению",
'btn_thread'        => "Тема",

// ── Live suggestions (AJAX) ──
'suggest_meta_thread' => "{1} · ответов: {2}",
'suggest_meta_user'   => "Пользователь",

// ── JS strings (passed to AGS_LANG without the js_ prefix) ──
'js_type_thread' => "тема",
'js_type_post'   => "сообщение",
'js_type_user'   => "пользователь",
'js_type_forum'  => "форум",
'js_fp_locale'   => "ru",

// ── Messages / errors ──
'redirect_searchresults'   => "Спасибо, запрос принят — сейчас вы перейдёте к результатам поиска.",
'error_invalidforum'       => "Неверный форум.",
'error_closedinvalidforum' => "В этом форуме нельзя искать: он закрыт, является ссылкой на другой сайт или категорией.",
'error_no_search_support'  => "Используемая база данных не поддерживает поиск.",
'error_nosearchterms'      => "Вы не указали, что искать. Введите ключевые слова или ник автора.",
'error_nosearchresults'    => "По вашему запросу ничего не найдено. Измените условия поиска и попробуйте ещё раз.",
'error_minsearchlength'    => "Одно или несколько слов в запросе слишком короткие. Минимальная длина слова — {1} симв.<br /><br />Чтобы искать целую фразу, возьмите её в двойные кавычки, например: \"Съешь же ещё этих мягких французских булок\".",
'error_searchflooding_1'   => "Искать можно не чаще одного раза в {1} сек. Подождите ещё 1 секунду и повторите поиск.",
'error_searchflooding'     => "Искать можно не чаще одного раза в {1} сек. Подождите ещё {2} сек. и повторите поиск.",
'error_invalidsearch'      => "Такого поиска нет или он вам недоступен. Вернитесь назад и попробуйте снова.",

);
?>
