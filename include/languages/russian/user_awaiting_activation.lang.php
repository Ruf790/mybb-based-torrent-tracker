<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['user_awaiting_activation'] = array
(

// ── Page header ──
'pane_title'              => "Неподтверждённые аккаунты",
'pane_subtitle'           => "Управление заявками на регистрацию. Неподтверждённые аккаунты удаляются автоматически согласно настройкам системы.",
'lbl_manage'              => "Ожидают активации",

// ── KPI tiles ──
'lbl_kpi_total'           => "Ожидают активации",
'lbl_kpi_email'           => "Ждут подтверждения e-mail",
'lbl_kpi_admin'           => "Ждут активации администрацией",
'lbl_kpi_oldest'          => "Самая старая заявка",

// ── Toolbar ──
'sec_pending_users'       => "Неактивированные пользователи",
'hint_filter'             => "Поиск по нику, e-mail или IP…",
'lbl_select_all'          => "Выбрать всех",

// ── Table ──
'lbl_col_user'            => "Пользователь",
'lbl_col_registered'      => "Регистрация",
'lbl_col_last_active'     => "Последний визит",
'lbl_col_ip'              => "IP-адрес",
'lbl_col_status'          => "Статус",
'lbl_col_actions'         => "Действия",
'lbl_na'                  => "Нет данных",
'lbl_no_match'            => "Никого не найдено по этому фильтру.",
'tip_select_user'         => "Выбрать {1}",
'tip_activate_user'       => "Активировать {1}",
'tip_delete_user'         => "Удалить {1}",

// ── Status badges ──
'opt_status_email'        => "Ждёт подтверждения e-mail",
'opt_status_admin'        => "Ждёт активации администрацией",

// ── Action bar ──
'lbl_selected'            => "Выбрано:",
'lbl_activate_users'      => "Активировать",
'lbl_delete_users'        => "Удалить",

// ── Empty state ──
'sec_empty_title'         => "Очередь пуста",
'hint_empty'              => "Все аккаунты подтверждены — активации никто не ждёт.",

// ── Flash messages ──
'flash_csrf_failed'       => "Ошибка проверки безопасности. Попробуйте ещё раз.",
'flash_no_users_selected' => "Вы не выбрали ни одного пользователя.",
'flash_none_pending'      => "Ни один из выбранных аккаунтов уже не ожидает активации.",
'flash_users_deleted'     => "Выбранные пользователи удалены.",
'flash_users_activated'   => "Выбранные пользователи активированы.",

// ── E-mail to activated user ──
'email_subject'           => "Активация аккаунта на {1}",
'email_body'              => "{1}, здравствуйте!

Администрация активировала ваш аккаунт на {2}.

Чтобы продолжить, перейдите по ссылке:

{3}

Войти можно с логином и паролем, указанными при регистрации.

Спасибо,
команда {2}",

// ── PM to activated user ──
'pm_activated_subject' => "Ваш аккаунт активирован",
'pm_activated_body'    => "Здравствуйте!

Администрация {1} активировала ваш аккаунт. Теперь вам доступны все возможности трекера: скачивание и раздачи, комментарии и форум.

Начать можно отсюда: [url={2}]{1}[/url]",

// ── JS (SweetAlert2 confirmations) ──
'js_none_title'            => "Никто не выбран",
'js_none_text'             => "Сначала выберите хотя бы одного пользователя.",
'js_activate_title'        => "Активировать пользователей: {1}?",
'js_activate_text'         => "Им придёт письмо на e-mail и личное сообщение, и они сразу смогут войти.",
'js_delete_title'          => "Удалить пользователей: {1}?",
'js_delete_text'           => "Аккаунты будут удалены безвозвратно.",
'js_delete_irreversible'   => "Отменить это действие нельзя.",
'js_delete_confirm_plain'  => "Удалить пользователей: {1}? Отменить это действие нельзя.",
'js_delete_checkbox'       => "Я понимаю, что удаление необратимо",
'js_delete_validator'      => "Отметьте галочку, чтобы подтвердить.",
'js_more'                  => "и ещё {1}",
'js_btn_activate'          => "Активировать",
'js_btn_delete'            => "Удалить",
'js_btn_cancel'            => "Отмена",

);
