<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// stats.php
$language['stats'] = array
(

// ── Page ──
'nav_stats'          => "Статистика форума",
'pane_title'         => "Статистика форума",
'pane_desc'          => "Активность форума в цифрах",

// ── Errors ──
'err_not_enough_info' => "Пока недостаточно данных для статистики. Чтобы она появилась, на форуме должны быть хотя бы 1 пользователь и 1 тема.",

// ── Header chips ──
'chip_days_online'   => "Онлайн уже {1} дн.",
'chip_newest'        => "Новичок:",

// ── KPI tiles ──
'kpi_posts'          => "Сообщения",
'kpi_threads'        => "Темы",
'kpi_members'        => "Пользователи",
'kpi_new_members'    => "Новых пользователей в день",
'kpi_sub_per_day'    => "{1} в день",
'kpi_sub_posted'     => "{1} уже писали на форуме",
'kpi_sub_over_days'  => "за {1} дн.",

// ── Section headings ──
'sec_most_replied'   => "Самые обсуждаемые темы",
'sec_most_viewed'    => "Самые просматриваемые темы",
'sec_averages'       => "В среднем",
'sec_records'        => "Рекорды",

// ── Empty states ──
'empty_replied'      => "Пока нет тем с ответами",
'empty_viewed'       => "Пока нет просмотренных тем",

// ── Thread rankings ──
'tip_count_replies'  => "Ответов: {1}",
'tip_count_views'    => "Просмотров: {1}",

// ── Averages ──
'avg_ppd'            => "Сообщений в день",
'avg_tpd'            => "Тем в день",
'avg_mpd'            => "Регистраций в день",
'avg_ppm'            => "Сообщений на пользователя",
'avg_tpm'            => "Тем на пользователя",
'avg_rpt'            => "Ответов на тему",

// ── Records ──
'rec_top_poster'     => "Самый активный сегодня",
'rec_popular_forum'  => "Самый популярный раздел",
'rec_newest_member'  => "Новый пользователь",
'rec_members_posted' => "Писали на форуме",
'rec_top_referrer'   => "Лучший по инвайтам",
'pill_posts'         => "Сообщений: {1}",
'pill_of'            => "{1} из {2}",
'pill_invited'       => "Приглашено: {1}",
'tip_posts_threads'  => "Сообщения / темы",

// ── Fallback names ──
'lbl_none'           => "Нет",
'lbl_nobody'         => "Никто",
'lbl_guest'          => "Гость",

// ── Summary strings (output as-is, HTML allowed; available to stats_end plugins) ──
'txt_todays_top_poster' => "Самый активный сегодня: <b>{1}</b> (сообщений: <b>{2}</b>)",
'txt_popular_forum'     => "Самый популярный раздел: <b>{1}</b> (сообщений: <b>{2}</b>, тем: <b>{3}</b>)",
'txt_top_referrer'      => "Лучший по инвайтам: <strong>{1}</strong> (приглашено: <strong>{2}</strong>)",

);
?>
