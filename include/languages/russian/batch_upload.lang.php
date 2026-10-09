<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['batch_upload'] = array (

	// ── Page / header ──
	'page_title'              => 'Пакетная загрузка раздач',
	'hdr_subtitle'            => 'До {1} раздач за раз — с постерами, скриншотами, тегами и данными с IMDb',
	'hdr_parser_ready'        => 'Парсер торрентов готов',

	// ── KPI tiles ──
	'kpi_per_batch'           => 'Раздач в пачке',
	'kpi_max_image'           => 'Макс. размер картинки',
	'kpi_mb'                  => '{1} МБ',
	'kpi_screens'             => 'Скриншотов на раздачу',
	'kpi_categories'          => 'Категорий',

	// ── Announce URL ──
	'lbl_announce'            => 'Ваш анонс-URL',
	'btn_copy'                => 'Копировать',

	// ── Info cards (sec_ = headers, step_ = how-it-works list) ──
	'sec_how'                 => 'Как это работает',
	'step_1'                  => 'Перетащите или выберите .torrent-файлы',
	'step_2'                  => 'Добавьте к каждой раздаче постер и скриншоты',
	'step_3'                  => 'Укажите категорию, описание и теги',
	'step_4'                  => 'При желании подтяните данные с IMDb',
	'step_5_html'             => 'Нажмите <b>Загрузить всё</b>',
	'sec_global'              => 'Общие настройки',
	'lbl_anonymous'           => 'Анонимная раздача',
	'hint_anonymous'          => 'Действует на все раздачи в этой пачке',
	'sec_csv'                 => 'Метаданные из CSV',
	'hint_csv_sub'            => 'Необязательно, перекрывает поля формы',
	'hint_csv_columns_html'   => 'Колонки: <code>torrent_filename, name, category, description, tags</code> (tags — по желанию)',
	'btn_csv_template'        => 'Скачать шаблон',

	// ── Torrents card / drop zone ──
	'sec_torrents'            => 'Раздачи',
	'hint_torrents_sub'       => 'Перетащите сразу несколько файлов или добавляйте по одному',
	'drop_aria'               => 'Выбрать торрент-файлы',
	'drop_title'              => 'Перетащите сюда .torrent-файлы',
	'drop_or'                 => 'или',
	'drop_browse'             => 'выберите на диске',
	'drop_limit'              => ' — не больше {1} файлов',
	'btn_add_more'            => 'Добавить ещё раздачу',

	// ── Sticky action bar ──
	'lbl_in_batch'            => 'В пачке:',
	'btn_back'                => 'Назад',
	'btn_upload_all'          => 'Загрузить всё',

	// ── Progress modal ──
	'sec_progress'            => 'Идёт загрузка',
	'lbl_overall'             => 'Общий прогресс',
	'sec_results'             => 'Результаты',
	'btn_close'               => 'Закрыть',
	'btn_view_torrents'       => 'Открыть раздачи',

	// ── BBCode toolbar (title / aria-label) ──
	'bb_aria'                 => 'Панель BBCode',
	'bb_bold'                 => 'Жирный',
	'bb_italic'               => 'Курсив',
	'bb_underline'            => 'Подчёркнутый',
	'bb_strike'               => 'Зачёркнутый',
	'bb_link'                 => 'Ссылка',
	'bb_image'                => 'Картинка',
	'bb_youtube'              => 'YouTube',
	'bb_left'                 => 'По левому краю',
	'bb_center'               => 'По центру',
	'bb_right'                => 'По правому краю',
	'bb_quote'                => 'Цитата',
	'bb_code'                 => 'Код',
	'bb_spoiler'              => 'Спойлер',
	'bb_preview'              => 'Предпросмотр',
	'bb_hide'                 => 'Скрыть панель',

	// ── Preview modal ──
	'sec_preview'             => 'Предпросмотр описания',
	'lbl_loading'             => 'Загрузка...',

	// ── Torrent block — shared by PHP and JS (passed to AGS_LANG as-is) ──
	'item_remove'             => 'Убрать раздачу из пачки',
	'item_torrent_file'       => 'Торрент-файл',
	'item_optional'           => 'необязательно',
	'item_poster'             => 'Постер',
	'item_poster2'            => 'Постер 2',
	'item_screenshots'        => 'Скриншоты',
	'item_screens_opt'        => 'необязательно, до {1} шт.',
	'item_name'               => 'Название раздачи',
	'item_name_opt'           => 'если пусто — имя файла',
	'item_name_ph'            => 'Оставьте пустым, чтобы взять имя файла',
	'item_category'           => 'Категория',
	'item_descr'              => 'Описание',
	'item_descr_opt'          => 'поддерживается BBCode',
	'item_descr_ph'           => 'Описание раздачи...',
	'item_tags'               => 'Теги',
	'item_tags_opt'           => 'колонка tags из CSV важнее',
	'item_tags_ph'            => 'Action, Comedy, Drama...',
	'item_clear'              => 'Очистить',
	'item_imdb_url'           => 'Ссылка на IMDb',
	'item_fetch'              => 'Получить данные',
	'item_add_descr'          => 'Вставить в описание',

	// ── Genre button labels — shared by PHP and JS (data-genre stays English) ──
	'genre_action'            => 'Боевик',
	'genre_adventure'         => 'Приключения',
	'genre_animation'         => 'Мультфильм',
	'genre_biography'         => 'Биография',
	'genre_comedy'            => 'Комедия',
	'genre_crime'             => 'Криминал',
	'genre_documentary'       => 'Документальный',
	'genre_drama'             => 'Драма',
	'genre_family'            => 'Семейный',
	'genre_fantasy'           => 'Фэнтези',
	'genre_history'           => 'История',
	'genre_horror'            => 'Ужасы',
	'genre_music'             => 'Музыка',
	'genre_mystery'           => 'Детектив',
	'genre_romance'           => 'Мелодрама',
	'genre_sci_fi'            => 'Фантастика',
	'genre_sport'             => 'Спорт',
	'genre_thriller'          => 'Триллер',
	'genre_war'               => 'Военный',
	'genre_western'           => 'Вестерн',

	// ── Server errors (JSON responses) ──
	'err_csrf'                => 'Неверный CSRF-токен, обновите страницу',
	'err_no_file'             => 'Файл не получен',
	'err_imdb_no_url'         => 'Ссылка не указана',
	'err_imdb_invalid'        => 'Неверная ссылка на IMDb',
	'err_internal'            => 'Внутренняя ошибка сервера',
	'err_mkdir'               => 'Не удалось создать папку: {1}',
	'err_not_writable'        => 'Нет прав на запись в папку: {1}',
	'err_no_files'            => 'Выберите хотя бы один торрент-файл',
	'err_too_many'            => 'Можно не больше {1} файлов, а выбрано {2}',
	'err_upload_code'         => 'ошибка загрузки, код {1}',
	'err_screenshot'          => 'скриншот {1}',
	'err_exists'              => 'Такая раздача уже есть на трекере',
	'err_db_insert'           => 'Ошибка БД при добавлении раздачи: {1}',
	'err_db_no_id'            => 'Не удалось добавить раздачу в базу',
	'err_copy_torrent'        => 'Не удалось скопировать торрент-файл',
	'err_upload'              => 'Ошибка загрузки файла',
	'err_file_type'           => 'Недопустимый тип файла: {1}',
	'err_save_file'           => 'Не удалось сохранить файл',

	// ── Screenshot errors ({1} = file name) ──
	'lbl_shot_default'        => 'скриншот',
	'err_shot_limit'          => '{1}: пропущен — лимит {2} скриншотов на раздачу',
	'err_shot_type'           => '{1}: неподдерживаемый тип файла',
	'err_shot_size'           => '{1}: файл слишком большой (макс. {2} МБ)',
	'err_shot_mime'           => '{1}: содержимое не похоже на допустимую картинку',
	'err_shot_save'           => '{1}: не удалось сохранить файл',
	'err_shot_recode'         => '{1}: картинка повреждена или не поддерживается (перекодирование не удалось)',
	'err_shot_db'             => '{1}: ошибка записи в БД ({2})',

	// ── JS: announce copy ──
	'js_copy'                 => 'Копировать',
	'js_copied'               => 'Скопировано!',

	// ── JS: validation / alerts ──
	'js_err_image_type'       => '{1}: недопустимый формат картинки',
	'js_err_image_size'       => '{1}: слишком большой файл (макс. {2} МБ)',
	'js_max_torrents'         => 'Не больше {1} раздач в пачке',
	'js_no_files'             => 'Выберите хотя бы один торрент-файл',

	// ── JS: duplicate check ──
	'js_dup_title'            => 'Дубль!',
	'js_dup_text'             => 'На трекере уже есть раздача с таким же info_hash:',
	'js_dup_added'            => ', залита {1}.',

	// ── JS: IMDb ──
	'js_imdb_enter_url'       => 'Сначала вставьте ссылку на IMDb.',
	'js_imdb_invalid'         => 'Неверная ссылка на IMDb. Пример: https://www.imdb.com/title/tt0000000/',
	'js_imdb_fetching'        => 'Получаю...',
	'js_imdb_error'           => 'Ошибка IMDb: {1}',
	'js_unknown'              => 'неизвестно',
	'js_imdb_failed'          => 'Не удалось получить данные с IMDb. Попробуйте ещё раз.',
	'js_descr_year'           => 'Год: {1}',
	'js_descr_genre'          => 'Жанр: {1}',
	'js_descr_rating'         => 'Рейтинг IMDb: {1}',

	// ── JS: remove / clear confirmation ──
	'js_confirm_clear_title'  => 'Очистить блок раздачи?',
	'js_confirm_remove_title' => 'Убрать раздачу из пачки?',
	'js_confirm_clear_text'   => 'все поля этого блока будут очищены.',
	'js_confirm_remove_text'  => 'блок со всем, что в нём заполнено, будет убран из пачки.',
	'js_confirm_clear'        => 'Очистить',
	'js_confirm_remove'       => 'Убрать',
	'js_cancel'               => 'Отмена',

	// ── JS: upload button / modal state ──
	'js_btn_upload_one'       => 'Загрузить раздачу',
	'js_btn_upload_many'      => 'Загрузить раздачи: {1}',
	'js_state_working'        => 'Идёт загрузка',
	'js_state_done'           => 'Загрузка завершена',
	'js_state_error'          => 'Загрузка не удалась',

	// ── JS: per-file status badges ──
	'js_st_waiting'           => 'В очереди',
	'js_st_uploading'         => 'Загрузка',
	'js_st_processing'        => 'Обработка',
	'js_st_done'              => 'Готово',
	'js_st_error'             => 'Ошибка',

	// ── JS: transport errors ──
	'js_err_non_json'         => 'Сервер вернул ответ не в формате JSON',
	'js_err_server'           => 'Ошибка сервера',
	'js_err_response'         => 'Ошибка ответа: {1}',
	'js_err_network'          => 'Ошибка сети',
	'js_err_timeout'          => 'Сервер не ответил вовремя',
	'js_err_label'            => 'Ошибка:',

	// ── JS: results ──
	'js_sum_uploaded'         => 'Загружено раздач: {1} из {2}',
	'js_sum_posters'          => 'С постером: {1}',
	'js_sum_posters2'         => 'С постером 2: {1}',
	'js_sum_screens'          => 'Скриншотов: {1}',
	'js_sum_csv'              => 'Записей из CSV: {1}',
	'js_res_files'            => 'Файлов: {1}',
	'js_res_view'             => 'Открыть',
	'js_errors_title'         => 'Ошибки ({1})',

);
?>
