<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');
$language['bonuslog'] = array (
    // ── Page ──
    'page_title'      => 'История бонусов',
    'head_title'      => 'История бонусов',
    'head_desc'       => 'Все изменения ваших бонусных очков: за что, на сколько и какой баланс после операции.',
    'btn_my_bonuses'  => 'Мои бонусы',

    // ── KPI cards ──
    'lbl_balance_now' => 'Баланс сейчас',
    'lbl_received_30' => 'Получено за 30 дней',
    'lbl_spent_30'    => 'Потрачено за 30 дней',

    // ── Tabs ──
    'tab_all'         => 'Все',

    // ── Table ──
    'th_date'         => 'Дата',
    'th_what_for'     => 'За что',
    'th_change'       => 'Изменение',
    'th_balance'      => 'Баланс',
    'lbl_whole_day'   => '(за сутки)',
    'empty_nothing'   => 'Пока ничего нет.',

    // ── Types (keys = BONUS_LOG_TYPES) ──
    'type_seeding'    => 'Раздача',
    'type_shop'       => 'Магазин',
    'type_gift'       => 'Подарки',
    'type_exam'       => 'Экзамены и задания',
    'type_request'    => 'Запросы',
    'type_claim'      => 'Претензии',
    'type_staff'      => 'Администрация',
    'type_kps'        => 'Активность',
    'type_other'      => 'Прочее',

    // ── Reasons written by the system ──
    'reason_seeding'  => 'Сидирование',
    'reason_gift_to'   => 'Подарок для {1}: {2} + комиссия {3}',
    'reason_gift_from' => 'Подарок от {1}',
    'reason_req_new'             => 'Награда за новый запрос #{1}',
    'reason_req_added'           => 'Добавлено к награде запроса #{1}',
    'reason_req_filled'          => 'Награда за выполнение запроса #{1}',
    'reason_req_refund'          => 'Возврат награды: запрос #{1} удалён',
    'reason_claim_give_up'       => 'Отказ от claim: раздача #{1}',
    'reason_claim_reached'       => 'Claims за {1}: норма выполнена по {2} раздач(ам)',
    'reason_claim_removed'       => 'Claims за {1}: снято {2} раздач(и) за невыполнение нормы',
    'reason_task_done'           => 'Задание выполнено: {1}',
    'reason_task_failed'         => 'Задание провалено: {1}',
    'reason_task_abandoned'      => 'Отказ от задания: {1}',
    'reason_exam_passed'         => 'Экзамен сдан: {1}',
    'reason_comment'             => 'Комментарий к «{1}» (#{2}, комментарий #{3})',
    'reason_comment_deleted'     => 'Комментарий удалён: раздача #{1} (комментарий #{2})',
    'reason_comment_deleted_mod' => 'Комментарий удалён модератором {1}: раздача #{2} (комментарий #{3})',
    'reason_comments_removed'    => 'Комментарии удалены вместе с раздачей {1} (#{2}, комментариев: {3})',
    'reason_torrent_uploaded'    => 'Раздача залита: {1} (#{2})',
    'reason_torrent_deleted'     => 'Раздача удалена: {1} (#{2})',
    'reason_torrent_deleted_mod' => 'Раздача удалена модератором {1}: {2} (#{3})',
    'reason_forum_thread'        => 'Новая тема на форуме: {1} (#{2})',
    'reason_forum_reply'         => 'Ответ на форуме: {1} (тема #{2}, пост #{3})',
    'reason_rated_torrent'       => 'Оценка раздачи #{1} ({2}/10)',
    'reason_rated_thread'        => 'Оценка темы форума #{1} ({2}/10)',
    'reason_activity'            => 'Бонус за активность',
    'reason_from_staff'          => 'От администрации: {1}',
    'reason_from_staff_group'    => 'От администрации ({1})',
    'reason_balance_set'         => 'Баланс изменён администрацией: {1} → {2}',
    'reason_balance_reset'       => 'Баланс обнулён администрацией ({1})',
    'reason_all_groups'          => 'все группы',
);
?>
