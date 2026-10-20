<?php

if (!defined('IN_TRACKER')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

$language['nuketorrent'] = array(
    // ── Page ──
    'page_title'           => 'Нюк раздачи: {1}',
    'sec_nuke'             => 'Нюкнуть раздачу',
    'sec_nuked'            => 'Раздача нюкнута',

    // ── Current state ──
    'lbl_current_reason'   => 'Текущая причина',
    'lbl_no_reason'        => 'причина не указана',
    'hint_replace_reason'  => 'Новая причина заменит текущую.',

    // ── Form ──
    'lbl_quick_reasons'    => 'Быстрый выбор причины',
    'lbl_reason'           => 'Причина',
    'ph_reason'            => 'Объясните, почему раздача нюкается (минимум 6 символов)',
    'hint_min_chars'       => 'Минимум 6 символов',

    // ── Quick reasons ──
    'opt_bad_quality'      => 'Плохое качество',
    'opt_bad_rip'          => 'Плохой рип',
    'opt_duplicate'        => 'Повтор (дубль)',
    'opt_fake'             => 'Фейк',
    'opt_wrong_category'   => 'Не та категория',
    'opt_broken_files'     => 'Нет или битые файлы',

    // ── Buttons ──
    'btn_back'             => 'К раздаче',
    'btn_unnuke'           => 'Снять нюк',
    'btn_update'           => 'Обновить причину',
    'btn_nuke'             => 'Нюкнуть',

    // ── Errors / messages ──
    'err_title'            => 'Ошибка',
    'err_invalid_torrent'  => 'Раздача не найдена!',
    'err_csrf'             => 'Неверный ключ безопасности или способ запроса. Воспользуйтесь формой.',
    'err_reason_short'     => 'Слишком короткая причина! Минимум 6 символов.',
    'flash_nuked'          => 'Раздача нюкнута!',
    'flash_unnuked'        => 'Нюк с раздачи снят!',

    // ── JS ──
    'js_unnuke_title'      => 'Снять нюк?',
    'js_unnuke_text'       => 'Раздача снова будет отображаться как обычная, причина будет удалена.',
    'js_unnuke_confirm'    => 'Да, снять',
    'js_cancel'            => 'Отмена',
);
