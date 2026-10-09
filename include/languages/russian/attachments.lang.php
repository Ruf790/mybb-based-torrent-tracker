<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['attachments'] = array (

    // ── Page titles (<title>) ──
    'ptitle_find'           => 'Вложения — поиск',
    'ptitle_results'        => 'Вложения — результаты поиска',
    'ptitle_stats'          => 'Вложения — статистика',
    'ptitle_comments'       => 'Вложения — файлы в комментариях',
    'ptitle_orphans'        => 'Бесхозные вложения',

    // ── Nav tabs ──
    'tab_find'              => 'Поиск вложений',
    'tab_find_desc'         => 'Поиск по файлам, которые пользователи прикрепили к сообщениям на форуме.',
    'tab_orphans'           => 'Бесхозные вложения',
    'tab_orphans_desc'      => 'Бесхозные — это вложения, у которых по какой-то причине нет записи в базе или файла на диске.',
    'tab_stats'             => 'Статистика',
    'tab_stats_desc'        => 'Общая статистика по вложениям на трекере',
    'tab_comments'          => 'Файлы в комментариях',
    'tab_comments_desc'     => 'Просмотр и удаление файлов, прикреплённых к комментариям раздач.',

    // ── Page headers (hero) — output as-is, HTML allowed ──
    'hero_find_title'       => 'Поиск вложений',
    'hero_find_sub'         => 'Файлы, которые пользователи прикрепили к сообщениям форума и комментариям',
    'hero_results_title'    => 'Результаты поиска',
    'hero_results_sub'      => 'Вложений: {1} · всего {2}',
    'hero_stats_title'      => 'Статистика вложений',
    'hero_stats_sub'        => 'Место на диске, трафик и самые тяжёлые файлы — по форуму и комментариям',
    'hero_comments_title'   => 'Файлы в комментариях',
    'hero_comments_sub'     => 'Файлы из комментариев к раздачам — оба хранилища: <code>attachments</code> и <code>comment_files</code>',
    'hero_orphans_title'    => 'Бесхозные вложения',
    'hero_orphans_sub'      => 'Файлы и записи, потерявшие свою пару, — их можно смело удалять',

    // ── Section headers ──
    'sec_what'              => 'Что ищем',
    'sec_results'           => 'Вывод результатов',
    'sec_most_downloaded'   => 'Чаще всего скачивают',
    'sec_largest'           => 'Самые большие файлы',
    'sec_top_users'         => 'Кто занимает больше всего места',
    'sec_db_rows'           => 'Записи вложений',
    'sec_cf_rows'           => 'Записи comment_files',
    'sec_disk_files'        => 'Файлы на диске без записи в базе',

    // ── KPI tiles ──
    'stat_attachments'      => 'Вложений',
    'stat_in_comments'      => 'из них в комментариях: {1}',
    'stat_disk'             => 'Место на диске',
    'stat_on_server'        => 'на сервере',
    'stat_bandwidth'        => 'Трафик',
    'stat_downloads_n'      => 'скачиваний: {1}',
    'stat_avg'              => 'Средний размер',
    'stat_per_file'         => 'на один файл',
    'stat_files'            => 'Файлов',
    'stat_space_used'       => 'Занято места',
    'stat_downloads'        => 'Скачиваний',
    'stat_orph_files'       => 'Файлы без записи',
    'stat_broken'           => 'Битые записи',
    'stat_cf'               => 'comment_files',
    'stat_freeable'         => 'Можно освободить',
    'stat_stale_n'          => 'старых черновиков: {1}',

    // ── Form labels and inline text ──
    'lbl_file_name'         => 'Имя файла',
    'lbl_username'          => 'Пользователь',
    'lbl_mime'              => 'MIME-тип',
    'lbl_filename_contains' => 'Имя файла содержит',
    'lbl_filetype_contains' => 'Тип файла содержит',
    'lbl_poster'            => 'Ник автора',
    'lbl_poster_is'         => 'Кто загрузил',
    'lbl_in_forums'         => 'В разделах форума',
    'lbl_sortby'            => 'Сортировать по',
    'lbl_order'             => 'Порядок',
    'lbl_perpage'           => 'На странице',
    'lbl_drafts_older'      => 'Черновики старше',
    'lbl_days'              => 'дн.',
    'lbl_found'             => 'найдено',
    'lbl_orphans_found'     => 'найдено бесхозных',
    'lbl_guest'             => 'Гость',
    'lbl_no_subject'        => 'Без темы',
    'lbl_comment_n'         => 'Комментарий #{1}',
    'lbl_draft'             => 'Черновик',
    'lbl_torrent_n'         => 'Раздача #{1}',
    'lbl_unknown'           => 'Неизвестно',
    'lbl_id'                => 'ID {1}',
    'lbl_files_n'           => 'файлов: {1}',
    'lbl_storage_std'       => 'обычное',
    'lbl_storage_cf'        => '.attach',

    // ── Selection toolbar — output as-is ({1} = counter markup, {2} = label, {3} = total) ──
    'tb_selected'           => 'Выбрано: {1} · {2}: {3}',

    // ── Table columns ──
    'col_file'              => 'Файл',
    'col_size'              => 'Размер',
    'col_storage'           => 'Хранилище',
    'col_uploaded_by'       => 'Загрузил',
    'col_torrent'           => 'Раздача',
    'col_date'              => 'Дата',
    'col_attachment'        => 'Вложение',
    'col_posted_by'         => 'Автор',
    'col_location'          => 'Где находится',
    'col_downloads'         => 'Скачиваний',
    'col_uploaded'          => 'Загружено',
    'col_reason'            => 'Причина',

    // ── Placeholders, titles, aria-labels (plain text, escaped) ──
    'ph_contains'           => 'содержит…',
    'ph_mime'               => 'например, image/',
    'ph_filename'           => 'например, screenshot',
    'ph_filetype'           => 'например, image/ или pdf',
    'tip_reset'             => 'Сбросить фильтры',
    'tip_storage_cf'        => 'Таблица comment_files',
    'tip_storage_std'       => 'Таблица attachments',
    'tip_user_atts'         => 'Показать вложения этого пользователя',
    'aria_select_all'       => 'Выбрать все',
    'aria_select'           => 'Выбрать',
    'aria_close'            => 'Закрыть',
    'hint_forums'           => 'Оставьте пустым для всех разделов · Ctrl+клик — выбрать несколько',

    // ── Buttons and quick links ──
    'btn_filter'            => 'Найти',
    'btn_new_search'        => 'Новый поиск',
    'btn_find'              => 'Найти вложения',
    'btn_delete_selected'   => 'Удалить выбранные',
    'btn_rescan'            => 'Пересканировать',
    'btn_back'              => 'К вложениям',
    'btn_cancel'            => 'Отмена',
    'btn_delete'            => 'Удалить',
    'quick_comments'        => 'Файлы в комментариях',
    'quick_orphans'         => 'Найти бесхозные',
    'quick_stats'           => 'Статистика',

    // ── Select options ──
    'opt_sort_filename'     => 'имени файла',
    'opt_sort_filesize'     => 'размеру',
    'opt_sort_downloads'    => 'числу скачиваний',
    'opt_sort_date'         => 'дате загрузки',
    'opt_sort_username'     => 'нику',
    'opt_user_any'          => 'Пользователь или гость',
    'opt_user_users'        => 'Только пользователи',
    'opt_user_guests'       => 'Только гости',
    'opt_asc'               => 'По возрастанию',
    'opt_desc'              => 'По убыванию',

    // ── Orphan reasons (plain text, escaped) ──
    'reason_post'           => 'Сообщение удалено',
    'reason_comment'        => 'Комментарий удалён',
    'reason_torrent'        => 'Раздача удалена',
    'reason_file'           => 'Нет файла',
    'reason_stale'          => 'Старый черновик',
    'reason_no_record'      => 'Нет записи в БД',

    // ── Empty states ──
    'empty_stats_title'     => 'Вложений пока нет',
    'empty_stats_text'      => 'Как только что-нибудь загрузят, здесь появится статистика.',
    'empty_comments_title'  => 'Файлы в комментариях не найдены',
    'empty_comments_text'   => 'Попробуйте изменить фильтры.',
    'empty_sync_title'      => 'Всё в порядке',
    'empty_sync_text'       => 'Файлы на диске, записи в базе и черновики полностью совпадают.',
    'empty_nothing'         => 'Пока пусто.',
    'empty_no_broken'       => 'Битых записей вложений нет.',
    'empty_no_broken_cf'    => 'Битых записей comment_files нет.',
    'empty_no_orph_files'   => 'Бесхозных файлов на диске нет.',

    // ── Delete confirmation modal — modal_question output as-is ({1} = counter markup) ──
    'modal_title'           => 'Удаление вложений',
    'modal_question'        => 'Удалить безвозвратно: {1} шт.?',
    'modal_text'            => 'Файлы удаляются с диска вместе с записями в базе. Отменить это действие нельзя.',

    // ── Flash messages and errors ──
    'flash_csrf'            => 'Проверка безопасности не пройдена. Попробуйте ещё раз.',
    'flash_none_selected'   => 'Не выбрано ни одного вложения для удаления',
    'flash_deleted'         => 'Выбранные вложения удалены',
    'flash_orphans_partial' => 'Не удалось удалить: {1}<br />Успешно удалено: {2}',
    'flash_orphans_failed'  => 'Не удалось удалить вложений: {1}',
    'flash_orphans_deleted' => 'Выбранные бесхозные вложения удалены',
    'err_no_results'        => 'По заданным условиям вложения не найдены',

    // ── JS strings (passed to AGS_LANG without the js_ prefix) ──
    'js_deleting'           => 'Удаление…',
);
?>
