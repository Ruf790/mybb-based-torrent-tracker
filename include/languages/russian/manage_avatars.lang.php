<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['manage_avatars'] = array (

    // ── Page ──
    'page_title'          => 'Управление аватарами - {1}',
    'sec_title'           => 'Управление аватарами',
    'sec_subtitle'        => 'Просмотр загруженных аватаров: поиск файлов без владельца и подозрительных, удаление',
    'lbl_range'           => '{1}–{2} из {3}',

    // ── KPI tiles ──
    'kpi_files'           => 'Файлов аватаров',
    'kpi_disk'            => 'Занято на диске',
    'kpi_orphans'         => 'Без владельца',
    'kpi_flagged'         => 'Помечено на странице',

    // ── File size units ──
    'unit_b'              => 'Б',
    'unit_kb'             => 'КБ',
    'unit_mb'             => 'МБ',
    'unit_gb'             => 'ГБ',

    // ── Deletion result (flash) ──
    'sec_result'          => 'Результат удаления',
    'flash_profiles_cleared' => 'Очищено профилей: {1}',
    'flash_deleted'       => 'Удалено',
    'flash_skipped_shared'=> 'Пропущено — аватар используется несколькими аккаунтами',
    'flash_not_found'     => 'Не найдено',
    'flash_unlink_failed' => 'Не удалось удалить — проверьте права на папку',

    // ── Toolbar / filters ──
    'lbl_select_all'      => 'Выбрать все видимые',
    'aria_filters'        => 'Фильтр аватаров',
    'opt_all'             => 'Все',
    'opt_owned'           => 'Используются',
    'opt_orphan'          => 'Без владельца',
    'opt_flagged'         => 'Помеченные',

    // ── Avatar card ──
    'aria_select'         => 'Выбрать {1}',
    'chip_not_image'      => 'Не изображение',
    'chip_suspicious'     => 'Подозрительный',
    'chip_shared'         => 'Общий ×{1}',
    'chip_orphaned'       => 'Без владельца',
    'tip_zoom'            => 'Открыть в полном размере',
    'tip_size'            => 'Размер файла',
    'tip_dims'            => 'Разрешение',
    'tip_type'            => 'Тип',
    'tip_scan_ok'         => 'Проверка содержимого пройдена',
    'lbl_clean'           => 'Чистый',
    'lbl_na'              => 'Н/Д',
    'lbl_more'            => 'и ещё {1}',
    'lbl_no_owner'        => 'Нет владельца',

    // ── Empty states ──
    'empty_filter_title'  => 'На этой странице нет аватаров под этот фильтр',
    'empty_filter_hint'   => 'Переключитесь на <strong>Все</strong> или перейдите на другую страницу.', // output as-is (HTML)
    'empty_title'         => 'В {1} нет файлов аватаров',
    'empty_hint'          => 'Аватары появятся здесь, как только пользователи начнут их загружать.',

    // ── Action bar ──
    'lbl_selected'        => 'Выбрано: {1}',
    'btn_select_orphans'  => 'Выбрать без владельца',
    'btn_clear'           => 'Сбросить',
    'btn_delete'          => 'Удалить выбранные',

    // ── Errors ──
    'err_invalid_token'   => 'Неверный ключ безопасности. Обновите страницу и попробуйте снова.',

    // ── JS ──
    'js_confirm_title'    => 'Удалить выбранные аватары?',
    'js_confirm_files'    => 'Будет безвозвратно удалено файлов: {1}.',
    'js_confirm_owned'    => 'Пользователей, которые лишатся аватара: {1}.',
    'js_confirm_orphans'  => 'Файлов без владельца: {1}.',
    'js_btn_delete'       => 'Удалить',
    'js_btn_cancel'       => 'Отмена',
);
?>
