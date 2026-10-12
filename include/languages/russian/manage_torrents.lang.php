<?php
if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// Manage Torrents (admin/manage_torrents.php + admin/manage_torrents_ajax.php)
// {1}, {2}… — placeholders for ags_fmt() / t()

$language['manage_torrents'] = array
(
    // ── Page ──
    'page_title'            => 'Управление раздачами',
    'sec_title'             => 'Управление раздачами',
    'sec_subtitle'          => 'Поиск, фильтры и массовые действия над раздачами',
    'lbl_query_ms'          => '{1} мс',

    // ── KPI tiles ──
    'kpi_torrents'          => 'Раздач',
    'kpi_total_size'        => 'Общий объём',
    'kpi_freeleech'         => 'Фрилич',
    'kpi_dead'              => 'Мёртвые / скрытые',

    // ── Search ──
    'lbl_name'              => 'Название',
    'ph_search'             => 'Часть названия раздачи…',
    'tip_clear_search'      => 'Очистить',
    'lbl_category'          => 'Категория',
    'btn_apply_filters'     => 'Применить',
    'tip_reset'             => 'Сбросить',
    'opt_all_categories'    => 'Все категории',

    // ── Filter chips ──
    'flt_all'               => 'Все',
    'flt_free'              => 'Фрилич',
    'flt_silver'            => 'Серебро',
    'flt_thirty'            => '30%',
    'flt_double'            => '2× отдача',
    'flt_sticky'            => 'Закреплённые',
    'flt_dead'              => 'Мёртвые',

    // ── Torrent list ──
    'lbl_select_page'       => 'Выбрать страницу',
    'lbl_found'             => 'Найдено: {1} · стр. {2}',
    'col_torrent'           => 'Раздача',
    'col_status'            => 'Статус',
    'col_uploader'          => 'Автор',
    'col_category'          => 'Категория',
    'col_added'             => 'Добавлена',
    'col_select'            => 'Выбор',
    'tip_manage'            => 'Управление',
    'tip_select'            => 'Выбрать',
    'lbl_deleted_user'      => 'удалённый пользователь',
    'empty_title'           => 'Раздачи не найдены',
    'empty_hint'            => 'Попробуйте изменить фильтры',
    'btn_reset_filters'     => 'Сбросить фильтры',

    // ── Bulk actions bar ──
    'lbl_selected_count'    => 'Выбрано: {1}',
    'opt_choose_action'     => '— Выберите действие —',
    'grp_organise'          => 'Упорядочить',
    'grp_promotions'        => 'Промо',
    'grp_danger'            => 'Опасные',
    'opt_move'              => 'Перенести в категорию',
    'opt_sticky'            => 'Закрепить / открепить',
    'opt_visible'           => 'Показать / скрыть',
    'opt_anonymous'         => 'Анонимность вкл./выкл.',
    'opt_free'              => 'Фрилич вкл./выкл.',
    'opt_silver'            => 'Серебро (50%) вкл./выкл.',
    'opt_thirty'            => '30% лич вкл./выкл.',
    'opt_double'            => 'Двойная отдача вкл./выкл.',
    'opt_banned'            => 'Бан вкл./выкл.',
    'opt_delete'            => 'Удалить раздачи',
    'opt_choose_category'   => 'Выберите категорию…',
    'btn_apply'             => 'Выполнить',
    'btn_clear'             => 'Снять выбор',
    'lbl_page_summary'      => 'На странице: {1} · {2}',

    // ── Modals ──
    'mdl_manage_title'      => 'Управление раздачей',
    'tip_close'             => 'Закрыть',
    'lbl_loading'           => 'Загрузка…',
    'lbl_loading_info'      => 'Загрузка информации о раздаче…',
    'mdl_confirm_title'     => 'Вы уверены?',
    'mdl_confirm_msg'       => 'Это действие нельзя отменить.',
    'btn_cancel'            => 'Отмена',
    'btn_confirm'           => 'Подтвердить',

    // ── Errors ──
    'err_direct_title'      => 'Ошибка!',
    'err_direct_access'     => 'Прямой запуск этого файла запрещён.',
    'err_security'          => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.',
    'err_no_action'         => 'Выберите действие!',
    'err_no_torrents'       => 'Выберите хотя бы одну раздачу!',
    'err_too_many'          => 'Слишком много раздач за раз (максимум {1}). Сузьте выбор и повторите.',
    'err_sysop_required'    => 'Для действия «{1}» нужен более высокий уровень доступа (сисоп).',
    'err_unknown_action'    => 'Неизвестное или ещё не реализованное действие: {1}',
    'err_invalid_category'  => 'Выбрана неверная категория!',

    // ── Flash messages (toasts) ──
    'flash_done'            => 'Действие выполнено!',
    'flash_bulk_done'       => 'Действие выполнено! Затронуто раздач: {1}',
    'flash_quick_edit'      => 'Раздача #{1} обновлена!',

    // ── Torrent modal (manage_torrents_ajax.php): errors ──
    'err_no_permission'     => 'Ошибка! У вас нет доступа к этой странице.',
    'err_invalid_id'        => 'Неверный ID раздачи',
    'err_not_found'         => 'Раздача не найдена',

    // ── Torrent modal: info ──
    'info_unknown_user'     => 'Неизвестно',
    'info_uncategorized'    => 'Без категории',
    'info_poster'           => 'Постер',
    'info_no_image'         => 'Нет изображения',
    'info_no_poster'        => 'Нет постера',
    'info_view_full'        => 'Открыть в полном размере',
    'info_torrent'          => 'О раздаче',
    'info_id'               => 'ID: #{1}',
    'info_added'            => 'Добавлена: {1}',
    'info_seeders'          => 'Сиды',
    'info_leechers'         => 'Личи',
    'info_completed'        => 'Скачали',
    'info_uploader'         => 'Автор раздачи',
    'info_owner_id'         => 'ID автора: {1}',
    'info_category'         => 'Категория',
    'info_category_id'      => 'ID категории: {1}',
    'info_file'             => 'Файл',
    'info_size'             => 'Размер:',
    'info_hash_short'       => 'Инфо-хеш:',
    'info_filename'         => 'Файл: {1}',
    'info_status'           => 'Статус',
    'info_quick_edit'       => 'Быстрая правка',
    'info_torrent_name'     => 'Название раздачи',
    'btn_save'              => 'Сохранить',
    'info_total_size'       => 'Общий размер',
    'info_seed_ratio'       => 'Доля сидов',
    'info_technical'        => 'Техническая информация',
    'info_hash'             => 'Инфо-хеш',
    'info_magnet'           => 'Magnet-ссылка',
    'tip_copy'              => 'Копировать',
    'info_description'      => 'Описание',

    // ── Torrent modal: status badges ──
    'badge_active'          => 'Активна',
    'badge_dead'            => 'Мёртвая',
    'badge_free'            => 'Фрилич',
    'badge_silver'          => 'Серебро',
    'badge_sticky'          => 'Закреплена',
    'badge_double'          => '2× отдача',
    'badge_banned'          => 'Забанена',
    'badge_anonymous'       => 'Анонимно',

    // ── Torrent modal: action buttons ──
    'act_sticky'            => 'Закрепить',
    'act_free'              => 'Фрилич',
    'act_silver'            => 'Серебро',
    'act_double'            => '2× отдача',
    'act_visible'           => 'Показать / скрыть',
    'act_delete'            => 'Удалить',
    'act_view_page'         => 'Страница раздачи',

    // ── JS strings (AGS_LANG, prefix js_ is stripped) ──
    'js_selected_count'     => 'Выбрано: {1}',
    'js_confirm_delete'     => 'Удалить раздачу #{1}?',
    'js_cannot_undo'        => 'Это действие нельзя отменить.',
    'js_confirm_toggle'     => 'Переключить «{1}» для раздачи #{2}?',
    'js_field_sticky'       => 'Закреп',
    'js_field_free'         => 'Фрилич',
    'js_field_silver'       => 'Серебро',
    'js_field_doubleupload' => '2× отдача',
    'js_field_visible'      => 'Видимость',
    'js_loading'            => 'Загрузка…',
    'js_loading_torrent'    => 'Загрузка раздачи #{1}…',
    'js_load_failed'        => 'Не удалось загрузить раздачу #{1}. Попробуйте ещё раз.',
    'js_bulk_delete_title'  => 'Удалить выбранные раздачи?',
    'js_bulk_delete_msg'    => 'Торрент-файлы, постеры, скриншоты, комментарии и все связанные данные будут удалены безвозвратно.',
    'js_bulk_ban_title'     => 'Переключить бан у выбранных раздач?',
    'js_bulk_ban_msg'       => 'Забаненные раздачи скрыты от пользователей, скачать их нельзя.',
    'js_bulk_selected_one'  => 'выбрана 1 раздача',
    'js_bulk_selected_many' => 'выбрано раздач: {1}',
);
?>
