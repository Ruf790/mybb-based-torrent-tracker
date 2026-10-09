<?php if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['cleartable'] = array (

    // ── Page titles (stdhead, plain text) ──
    'title_page' => 'Очистка таблиц MySQL',
    'title_results' => 'Очистка таблиц MySQL — результаты',

    // ── Pane titles ──
    'pane_form' => 'Очистка таблиц базы данных',
    'pane_confirm' => 'Подтверждение очистки',
    'pane_success' => 'Готово',
    'pane_failed' => 'Ошибка',

    // ── Tags ──
    'tag_irreversible' => 'НЕОБРАТИМО',
    'tag_step2' => 'ШАГ 2 ИЗ 2',
    'tag_protected' => 'защищена',

    // ── Hints / notices  (*_html keys are printed as-is; {1} there is HTML built by PHP) ──
    'hint_form_warn_html' => '<strong>TRUNCATE удаляет все строки в выбранных таблицах. Отменить это невозможно.</strong> Перед продолжением сделайте резервную копию базы данных.',
    'hint_confirm_warn_html' => '<strong>Вы собираетесь безвозвратно удалить все данные в таблицах: {1}.</strong> Это действие нельзя отменить — перед продолжением убедитесь, что у вас есть резервная копия.',
    'hint_locked' => 'Заблокировано таблиц: {1}. Очистить их отсюда нельзя:',

    // ── Labels ──
    'lbl_selected_html' => 'Выбрано таблиц: {1}',
    'lbl_filter_placeholder' => 'Фильтр таблиц…',
    'lbl_all' => 'Все',
    'lbl_clear' => 'Сбросить',
    'lbl_invert' => 'Инвертировать',
    'lbl_success_lead' => 'Операция выполнена успешно!',
    'lbl_success_sub' => 'Следующие таблицы очищены:',
    'lbl_table_truncated' => '{1} — успешно очищена!',
    'lbl_total_html' => 'Всего очищено таблиц: {1}',
    'lbl_failed_intro' => 'Не удалось очистить следующие таблицы:',
    'lbl_optimizing' => 'Оптимизация…',
    'lbl_opt_done' => 'Все таблицы оптимизированы.',
    'lbl_opt_reminder' => 'Не забудьте оптимизировать таблицы!',

    // ── Buttons ──
    'btn_truncate_selected' => 'Очистить выбранные таблицы',
    'btn_confirm_yes' => 'Да, очистить эти таблицы',
    'btn_optimize' => 'Оптимизировать таблицы',
    'btn_cancel' => 'Отмена',
    'btn_go_back' => 'Назад',
    'btn_back' => 'Назад',

    // ── Flash / error messages ──
    'flash_none_selected' => 'Не выбрано ни одной таблицы для очистки.',
    'flash_bad_token_page' => 'Неверный или отсутствующий токен безопасности. Используйте кнопку подтверждения ниже, а не прямую ссылку.',
    'flash_bad_token' => 'Неверный токен безопасности',
    'flash_bad_table' => 'Недопустимое имя таблицы',

    // ── JS strings (js_ prefix is stripped by PHP → AGS_LANG; **bold** and `code` are rendered by JS as DOM nodes) ──
    'js_none_title' => 'Таблицы не выбраны',
    'js_none_text' => 'Отметьте хотя бы одну таблицу для очистки.',
    'js_confirm_title' => 'Очистить выбранные таблицы ({1})?',
    'js_confirm_warn' => 'Все строки в этих таблицах будут удалены. **Отменить это невозможно** — убедитесь, что у вас есть резервная копия.',
    'js_confirm_type' => 'Для подтверждения введите `TRUNCATE`',
    'js_confirm_btn' => 'Очистить',
    'js_confirm_tail' => 'Это действие нельзя отменить.',
    'js_cancel' => 'Отмена',
    'js_type_exact' => 'Введите TRUNCATE в точности как указано',
    'js_truncating' => 'Очистка…',
    'js_trunc_done' => 'Очищено таблиц: {1}',
    'js_optimizing' => 'Оптимизация {1} ({2}/{3})…',
    'js_opt_ok' => 'Оптимизировано',
    'js_opt_err' => 'Ошибка',
    'js_unknown_error' => 'неизвестная ошибка',
    'js_opt_errors_title' => 'Оптимизация завершена с ошибками',
    'js_opt_all_done' => 'Все таблицы оптимизированы',
    'js_fail_title' => 'Не удалось очистить таблицы: {1}',
    'js_fail_partial' => 'Остальные таблицы очищены успешно — они перечислены на странице.',
    'js_fail_log' => 'Проверьте журнал ошибок.',
    'js_error_title' => 'Очистка таблиц базы данных',
    'js_go_back' => 'Назад',
);
?>
