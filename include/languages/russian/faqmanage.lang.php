<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

//  admin/faqmanage.php
$language['faqmanage'] = array (
	// ── Meta ── (plural rules: en / ru)
	'lang_code'               => 'ru',

	// ── Main page ──
	'pane_title'              => 'Управление FAQ',
	'pane_subtitle'           => 'Категории и вопросы, которые видны на публичной странице FAQ.',
	'btn_new_question'        => 'Новый вопрос',
	'btn_new_category'        => 'Новая категория',

	// ── KPI tiles ──
	'kpi_categories'          => 'Категорий',
	'kpi_categories_hint'     => 'Разделов на странице FAQ',
	'kpi_questions'           => 'Вопросов',
	'kpi_questions_hint'      => 'Внутри категорий',
	'kpi_empty'               => 'Пустых категорий',
	'kpi_empty_hint'          => 'Пока без вопросов',
	'kpi_unlinked'            => 'Вопросов без категории',
	'kpi_unlinked_hint'       => 'Не видны на сайте',

	// ── No categories ──
	'sec_no_categories'       => 'Категорий пока нет',
	'msg_no_categories'       => 'Сначала создайте категорию, потом добавляйте в неё вопросы.',
	'btn_first_category'      => 'Создать первую категорию',

	// ── Categories table ──
	'sec_categories'          => 'Категории',
	'lbl_total'               => 'всего: {1}',
	'col_order'               => 'Порядок',
	'col_category'            => 'Категория',
	'col_questions'           => 'Вопросы',
	'col_actions'             => 'Действия',
	'aria_order_for'          => 'Порядок для «{1}»',
	'tip_open_questions'      => 'Открыть вопросы',
	'tip_add_question'        => 'Добавить вопрос',
	'tip_edit_category'       => 'Редактировать категорию',
	'tip_edit_question'       => 'Редактировать вопрос',
	'tip_delete'              => 'Удалить',
	'aria_delete'             => 'Удалить «{1}»',
	'hint_order_categories'   => 'Чем меньше число, тем выше категория на странице FAQ.',
	'btn_save_order'          => 'Сохранить порядок',

	// ── Unlinked questions ──
	'sec_unlinked'            => 'Вопросы без категории',
	'hint_unlinked'           => 'Откройте вопрос и выберите категорию, чтобы он снова появился на сайте',

	// ── Category view ──
	'lnk_all_categories'      => 'Все категории',
	'lbl_in_category_one'     => 'В этой категории {1} вопрос.',
	'lbl_in_category_few'     => 'В этой категории {1} вопроса.',
	'lbl_in_category_many'    => 'В этой категории {1} вопросов.',
	'btn_edit_category'       => 'Редактировать категорию',
	'btn_add_question'        => 'Добавить вопрос',
	'sec_cat_empty'           => 'В этой категории нет вопросов',
	'msg_cat_empty'           => 'Добавьте вопрос с ответом, чтобы категория появилась на странице FAQ.',
	'btn_first_question'      => 'Добавить первый вопрос',
	'ph_filter'               => 'Фильтр по вопросам',
	'btn_expand_all'          => 'Развернуть все',
	'btn_collapse_all'        => 'Свернуть все',
	'msg_no_match'            => 'Под фильтр не подходит ни один вопрос.',
	'hint_order_questions'    => 'Чем меньше число, тем выше вопрос внутри категории.',

	// ── Russian version ──
	'sec_ru_version'          => 'Русская версия',
	'hint_ru_fallback'        => 'Необязательно. Если оставить пустым, будет показан английский вариант.',
	'lbl_question_ru'         => 'Вопрос (рус.)',
	'lbl_category_name_ru'    => 'Название категории (рус.)',
	'lbl_answer_ru'           => 'Ответ (рус.)',
	'lbl_description_ru'      => 'Описание (рус.)',
	'tip_ru_ok'               => 'Русская версия заполнена',
	'tip_ru_missing'          => 'Нет русской версии — показывается английская',

	// ── Form ──
	'form_new_category'       => 'Новая категория',
	'form_new_category_sub'   => 'Категории объединяют вопросы на странице FAQ.',
	'btn_create_category'     => 'Создать категорию',
	'form_new_question'       => 'Новый вопрос',
	'form_new_question_sub'   => 'Добавьте вопрос с ответом в одну из категорий.',
	'form_edit_question'      => 'Редактирование вопроса',
	'form_edit_question_sub'  => 'Вопрос №{1}',
	'form_edit_category'      => 'Редактирование категории',
	'form_edit_category_sub'  => 'Категория №{1}',
	'btn_save_changes'        => 'Сохранить изменения',
	'opt_choose_category'     => 'Выберите категорию',
	'lbl_category'            => 'Категория',
	'lbl_question'            => 'Вопрос',
	'lbl_category_name'       => 'Название категории',
	'lbl_answer'              => 'Ответ',
	'lbl_description'         => 'Описание',
	'lbl_optional'            => 'необязательно',
	'hint_html'               => 'Можно использовать HTML — на странице FAQ он выводится как есть.',
	'sec_placement'           => 'Размещение',
	'lbl_disporder'           => 'Порядок',
	'hint_disporder'          => 'Чем меньше число, тем выше.',
	'hint_shortcut'           => '{1} — сохранить форму',

	// ── Save bar ──
	'status_no_changes'       => 'Несохранённых изменений нет.',
	'status_required'         => 'Поля со звёздочкой * обязательны.',
	'btn_cancel'              => 'Отмена',
	'btn_undo'                => 'Отменить правки',

	// ── Flash messages ──
	'flash_order'             => 'Порядок сохранён.',
	'flash_cat_created'       => 'Категория создана.',
	'flash_q_created'         => 'Вопрос добавлен.',
	'flash_updated'           => 'Изменения сохранены.',
	'flash_deleted'           => 'Удалено.',
	'aria_close'              => 'Закрыть',

	// ── Errors ──
	'lbl_error'               => 'Ошибка',
	'sec_error_page'          => 'Что-то пошло не так',
	'msg_error_page'          => 'Не удалось выполнить запрос.',
	'btn_back_to_faq'         => 'Вернуться к FAQ',
	'err_not_found'           => 'Запись FAQ не найдена.',
	'err_csrf'                => 'Проверка безопасности не пройдена. Обновите страницу и попробуйте ещё раз.',
	'err_no_order'            => 'Не передано ни одного значения порядка.',
	'err_category_first'      => 'Сначала создайте категорию, потом добавляйте вопросы.',
	'err_category_name'       => 'Введите название категории.',
	'err_question'            => 'Введите вопрос.',
	'err_answer'              => 'Введите ответ.',
	'err_choose_category'     => 'Выберите существующую категорию.',
	// ── JS ──
	'js_btn_cancel'           => 'Отмена',
	'js_btn_delete'           => 'Удалить',
	'js_del_cat_title'        => 'Удалить категорию?',
	'js_del_q_title'          => 'Удалить вопрос?',
	'js_del_text'             => '«{1}» удалится безвозвратно.',
	'js_del_cat_text_one'     => '«{1}» и {2} вопрос в ней удалятся безвозвратно.',
	'js_del_cat_text_few'     => '«{1}» и {2} вопроса в ней удалятся безвозвратно.',
	'js_del_cat_text_many'    => '«{1}» и {2} вопросов в ней удалятся безвозвратно.',
	'js_unsaved_one'          => '{1} несохранённое изменение',
	'js_unsaved_few'          => '{1} несохранённых изменения',
	'js_unsaved_many'         => '{1} несохранённых изменений',
	'js_chars_one'            => '{1} символ',
	'js_chars_few'            => '{1} символа',
	'js_chars_many'           => '{1} символов',
);
?>
