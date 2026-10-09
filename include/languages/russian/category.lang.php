<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['category'] = array (
	// ── Page titles (<title>) ──
	'title_list'          => 'Управление категориями трекера',
	'title_form'          => 'Управление категориями — {1}',
	'title_add_sub'       => 'Новая подкатегория',

	// ── Headers / panes ──
	'pane_list'           => 'Категории трекера',
	'sub_list'            => 'Главные категории и их подкатегории — в том виде, как они показаны в списке раздач',
	'pane_add_category'   => 'Новая категория',
	'pane_edit_category'  => 'Редактирование категории',
	'sub_new_form'        => 'Создайте главную категорию или подкатегорию',
	'sub_edit_form'       => 'ID {1} · {2}',
	'pane_add_sub'        => 'Новая подкатегория',
	'sub_inside'          => 'В категории {1}',
	'pane_delete'         => 'Удаление категории',
	'modal_add_category'  => 'Новая категория',
	'modal_edit_category' => 'Редактирование категории',

	// ── Field labels ──
	'lbl_name'            => 'Название',
	'lbl_parent'          => 'Родительская категория',
	'lbl_icon'            => 'Иконка',
	'lbl_loading'         => 'Загрузка…',

	// ── KPI tiles ──
	'stat_main'           => 'Главных категорий',
	'stat_subs'           => 'Подкатегорий',
	'stat_torrents'       => 'Раздач',
	'stat_empty'          => 'Пустых',

	// ── Hints (hint_icon is printed as-is: HTML allowed) ──
	'hint_parent_none'    => 'Оставьте «Нет», чтобы создать главную категорию',
	'hint_icon'           => 'Классы Font Awesome, например <code>fa-solid fa-film</code>',
	'lnk_browse_icons'    => 'каталог иконок',
	'hint_delete_meta'    => 'ID {1} · раздач и подкатегорий нет',
	'hint_cannot_undo'    => 'Это действие нельзя отменить.',

	// ── Select options ──
	'opt_all'             => '— Все категории —',
	'opt_none'            => '— Нет (главная категория) —',

	// ── Buttons ──
	'btn_pick'            => 'Выбрать',
	'btn_back'            => 'Назад',
	'btn_reset'           => 'Сбросить',
	'btn_save'            => 'Сохранить',
	'btn_save_changes'    => 'Сохранить изменения',
	'btn_cancel'          => 'Отмена',
	'btn_delete'          => 'Удалить',
	'btn_close'           => 'Закрыть',
	'btn_add_category'    => 'Добавить категорию',
	'btn_add_sub'         => 'Добавить подкатегорию',

	// ── Tooltips (title=) ──
	'tip_total_torrents'  => 'Раздачи в этой категории и её подкатегориях',
	'tip_view_browse'     => 'Открыть в списке раздач',
	'tip_view'            => 'Открыть',
	'tip_edit'            => 'Редактировать',
	'tip_delete'          => 'Удалить',
	'tip_locked_torrents' => 'Есть раздачи — сначала перенесите их',
	'tip_locked_subs'     => 'Есть подкатегории — сначала удалите их',

	// ── Empty states / filter ──
	'empty_title'         => 'Категорий пока нет',
	'empty_text'          => 'Создайте первую, чтобы начать раскладывать раздачи.',
	'empty_no_subs'       => 'Подкатегорий нет',
	'empty_no_match'      => 'Ничего не найдено',
	'ph_filter'           => 'Поиск по категориям…',

	// ── Flash messages ──
	'flash_added'         => 'Категория добавлена!',
	'flash_updated'       => 'Категория обновлена!',
	'flash_deleted'       => 'Категория удалена!',
	'flash_sub_added'     => 'Подкатегория добавлена!',

	// ── Errors ──
	'err_title'           => 'Ошибка',
	'err_token'           => 'Неверный ключ безопасности',
	'err_not_found'       => 'Категория с таким ID не найдена!',
	'err_main_not_found'  => 'Главная категория с таким ID не найдена!',
	'err_ajax_not_found'  => 'Категория не найдена',
	'err_has_torrents'    => 'В категории ещё есть раздачи ({1}). Перенесите их в другую категорию или удалите, прежде чем удалять категорию.',
	'err_has_subs'        => 'У категории ещё есть подкатегории ({1}). Сначала удалите или перенесите их.',
	'err_name_empty'      => 'Название категории не может быть пустым',
	'err_parent_missing'  => 'Выбранная родительская категория не существует',
	'err_own_parent'      => 'Категория не может быть родителем самой себя',
	'err_has_subs_demote' => 'У этой категории есть подкатегории — перенесите или удалите их, прежде чем делать её подкатегорией',
	'err_db_save'         => 'Ошибка базы данных при сохранении категории',
	'err_cache_write'     => 'Не удалось записать файл кэша',

	// ── JS (admin_category.js → AGS_LANG, without the js_ prefix) ──
	'js_lbl_name'         => 'Название',
	'js_lbl_parent'       => 'Родительская категория',
	'js_lbl_icon'         => 'Иконка',
	'js_hint_has_subs'    => 'Есть подкатегории — должна остаться главной',
	'js_edit_title'       => 'Редактирование «{1}»',
	'js_err_load'         => 'Не удалось загрузить данные категории',
);
?>
