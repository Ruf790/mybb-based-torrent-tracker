<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['invites'] = array
(

// ── Page header ──
'page_title'          => "Мои инвайты",
'pane_subtitle'       => "Пригласите друзей на {1}",
'lbl_invites_left'    => "инвайтов осталось",

// ── KPI ──
'lbl_kpi_total'       => "Всего отправлено",
'lbl_kpi_pending'     => "Ожидают",
'lbl_kpi_used'        => "Принято",

// ── Create form ──
'sec_create'          => "Новый инвайт",
'lbl_email'           => "E-mail (необязательно)",
'ph_email'            => "friend@example.com",
'hint_email'          => "Если указать e-mail, инвайт придёт другу письмом.",
'lbl_note'            => "Заметка (необязательно)",
'ph_note'             => "например: для Ивана с работы",
'btn_generate'        => "Создать инвайт",
'hint_no_invites'     => "Инвайты закончились. Их можно получить за активность или купить за бонусные очки.",

// ── History ──
'sec_history'         => "История инвайтов",
'hint_empty'          => "Инвайтов пока нет. Создайте первый выше!",
'lbl_col_code'        => "Код",
'lbl_col_email'       => "E-mail",
'lbl_col_note'        => "Заметка",
'lbl_col_invitee'     => "Приглашённый",
'lbl_col_status'      => "Статус",
'lbl_col_expires'     => "Действует до",
'lbl_col_created'     => "Создан",
'tip_copy'            => "Нажмите, чтобы скопировать ссылку-приглашение",
'tip_revoke'          => "Отозвать инвайт",
'fmt_date'            => "d.m.Y",

// ── Status badges ──
'opt_status_pending'  => "Ожидает",
'opt_status_used'     => "Использован",
'opt_status_expired'  => "Истёк",
'opt_status_revoked'  => "Отозван",

// ── Flash / errors ──
'flash_created'       => "Инвайт создан!",
'flash_revoked'       => "Инвайт отозван и возвращён вам.",
'err_email'           => "Неверный e-mail.",
'err_no_invites'      => "У вас не осталось инвайтов.",
'err_create_failed'   => "Не удалось создать инвайт. Возможно, у вас не осталось инвайтов.",
'err_revoke_failed'   => "Не удалось отозвать инвайт.",

// ── E-mail to the invited person ──
'email_subject'       => "Вас пригласили на {1}",
'email_body'          => "Здравствуйте!

{1} приглашает вас на {2}.

Зарегистрироваться можно здесь:
{3}

Приглашение действует {4} дн.

— Команда {2}",

// ── JS ──
'js_revoke_confirm'   => "Отозвать этот инвайт?",
'js_copied'           => "Скопировано!",
'js_copied_toast'     => "Ссылка-приглашение скопирована!",

);
