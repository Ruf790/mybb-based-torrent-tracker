<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['staffmess'] = array (
    // ── Page ──
    'page_title'          => 'Массовая рассылка персоналу и/или пользователям',
    'sec_title'           => 'Массовая рассылка',
    'sec_subtitle'        => 'Личное сообщение всем участникам выбранных групп.',
    'badge_section'       => 'Рассылки',

    // ── KPI ──
    'kpi_users'           => 'Всего пользователей',
    'kpi_staff'           => 'Персонал',
    'kpi_groups'          => 'Групп',
    'kpi_recipients'      => 'Выбрано получателей',

    // ── Sections ──
    'sec_groups'          => 'Получатели',
    'sec_sender'          => 'Отправитель',
    'sec_message'         => 'Сообщение',

    // ── Labels ──
    'lbl_check_all'       => 'Отметить все',
    'lbl_subject'         => 'Тема',
    'lbl_message'         => 'Текст сообщения',
    'lbl_char_count'      => 'Символов: {1}',
    'lbl_close'           => 'Закрыть',
    'lbl_recipients'      => 'Получателей:',

    // ── Placeholders ──
    'ph_subject'          => 'Короткая и понятная тема',

    // ── Hints / tooltips ──
    'tip_group_users'     => 'Пользователей в группе: {1}',
    'hint_groups'         => 'Каждый пользователь выбранных групп получит сообщение в ЛС.',
    'hint_sender'         => 'На системное сообщение ответить нельзя — у него нет отправителя.',
    'hint_bbcode'         => 'Поддерживаются BBCode и смайлы.',

    // ── Options ──
    'opt_sender_system'   => 'Системное сообщение (от имени трекера)',

    // ── Buttons ──
    'btn_send'            => 'Отправить рассылку',

    // ── Flash / errors ──
    'flash_csrf_failed'   => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте ещё раз.',
    'flash_fields_blank'  => 'Заполните все поля.',
    'flash_sent'          => 'Рассылка завершена. Отправлено сообщений: {1}.',

    // ── JS ──
    'js_char_count'       => 'Символов: {1}',
    'js_confirm_title'    => 'Отправить рассылку?',
    'js_confirm_text'     => 'Сообщение уйдёт в ЛС получателям: {1}.',
    'js_confirm_yes'      => 'Отправить',
    'js_confirm_no'       => 'Отмена',
    'js_no_groups_title'  => 'Группы не выбраны',
    'js_no_groups_text'   => 'Отметьте хотя бы одну группу получателей.',
    'js_ok'               => 'OK',
);
?>
