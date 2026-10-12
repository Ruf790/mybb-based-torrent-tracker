<?php
if(!defined('IN_TRACKER')) die('Hacking attempt!');

$language['check_update'] = array (
	// ── Page ──
	'page_title'          => 'Проверка обновлений',
	'page_subtitle'       => 'Сверка трекера с репозиторием на GitHub — новые коммиты, изменённые файлы и миграции БД',

	// ── KPI tiles ──
	'kpi_commits'         => 'Новых коммитов',
	'kpi_files'           => 'Изменённых файлов',
	'kpi_migrations'      => 'Ожидающих миграций',
	'kpi_scan'            => 'Файлов отличается',

	// ── Versions ──
	'sec_version'         => 'Версия',
	'lbl_latest'          => 'Последний на GitHub',
	'lbl_installed'       => 'Установлено',
	'lbl_unknown'         => 'неизвестно',
	'src_git'             => 'из локального .git',
	'src_setting'         => 'отмечено вручную',
	'hint_git'            => 'Версия берётся из .git и меняется вместе с git pull.',
	'lbl_custom_sha'      => 'Установленный коммит (SHA)',
	'btn_save_sha'        => 'Сохранить',
	'hint_custom_sha'     => 'Коммит, с которого взяты файлы на этом сервере, — полный или короткий SHA (от 7 символов).',

	// ── State ──
	'state_current'       => 'У вас последняя версия.',
	'state_behind'        => 'Новых коммитов на GitHub: {1}.',
	'state_diverged'      => 'В установленной версии также есть коммиты, которых нет в {2}: {1}.',
	'state_ahead_local'   => 'Установленный коммит новее, чем {1} на GitHub (ещё не запушен?).',
	'state_base_unknown'  => 'GitHub не знает коммит {1} (история переписана или был force-push?). Отметьте текущее состояние заново.',
	'state_no_base'       => 'Установленный коммит пока неизвестен. Если файлы на сервере сейчас совпадают с GitHub — нажмите «Отметить как актуальную»; иначе укажите коммит, с которого заливали файлы, или проверьте файлы, чтобы увидеть отличия.',

	// ── Commits and files ──
	'sec_commits'         => 'Что нового',
	'sec_files'           => 'Изменённые файлы',
	'hint_cmp_limit'      => 'Сравнение показывает не более 250 коммитов и 300 файлов.',
	'lnk_full_diff'       => 'Полный diff на GitHub',
	'st_added'            => 'добавлен',
	'st_removed'          => 'удалён',
	'st_modified'         => 'изменён',
	'st_renamed'          => 'переименован',

	// ── Migrations ──
	'sec_migrations'      => 'Миграции БД',
	'lbl_schema'          => 'Версия схемы: {1}',
	'mig_new'             => 'Новые на GitHub — скачайте их в sql/migrations/',
	'mig_pending'         => 'Скачаны, но не применены — запустите upgrade.php',
	'mig_ok'              => 'Ожидающих миграций нет.',

	// ── File scan ──
	'sec_scan'            => 'Сравнение файлов с GitHub',
	'hint_scan'           => 'Считает git-хэши файлов на сервере и сравнивает их с {1}. Пропускаются: сгенерированные конфиги, cache/, uploads/, torrents/, error_logs/, docs/ и файлы .htaccess.',
	'scan_none'           => 'Проверка ещё не запускалась — кнопка «Проверить файлы» на панели внизу.',
	'scan_info'           => 'Сравнено с {1} в {2}: совпадают {3}, пропущено {4}.',
	'scan_truncated'      => 'GitHub обрезал дерево файлов — результат неполный.',
	'scan_modified'       => 'Отличаются от GitHub ({1})',
	'scan_missing'        => 'Отсутствуют на сервере ({1})',
	'scan_clean'          => 'Все проверенные файлы совпадают с GitHub.',

	// ── Bottom bar ──
	'hint_checked'        => 'Проверено в {1}, кэш {2} мин',
	'hint_rate'           => 'Осталось запросов к GitHub API: {1} из {2}',
	'btn_zip'             => 'Скачать ZIP',
	'btn_scan'            => 'Проверить файлы',
	'btn_scan_clear'      => 'Скрыть проверку',
	'btn_mark'            => 'Отметить как актуальную',
	'btn_refresh'         => 'Проверить сейчас',

	// ── Flash ──
	'flash_bad_key'       => 'Неверный или устаревший токен формы, попробуйте ещё раз.',
	'flash_bad_sha'       => 'Неверный SHA коммита.',
	'flash_sha_404'       => 'Коммит {1} не найден на GitHub.',
	'flash_saved'         => 'Установленная версия сохранена: {1}.',
	'flash_save_fail'     => 'Не удалось записать в таблицу settings.',

	// ── Errors ──
	'err_no_curl'         => 'PHP-расширение cURL недоступно.',
	'err_network'         => 'Не удаётся подключиться к GitHub: {1}',
	'err_rate_limit'      => 'Исчерпан лимит запросов к GitHub API, сброс в {1}. Добавьте $config[\'github_token\'] в include/config.php, чтобы поднять лимит.',
	'err_token'           => 'GitHub отклонил токен (HTTP 401) — проверьте $config[\'github_token\'] в include/config.php.',
	'err_api'             => 'Ошибка GitHub API (HTTP {1}).',
	'err_no_branch'       => 'Ветка {1} не найдена на GitHub.',

	// ── JS ──
	'js_yes'              => 'Да',
	'js_cancel'           => 'Отмена',
	'js_working'          => 'Выполняется…',
	'js_confirm_mark_title' => 'Отметить как актуальную?',
	'js_confirm_mark_text'  => 'Установленной версией станет коммит {1}. Делайте это, только если файлы на сервере действительно совпадают с GitHub.',
	'js_confirm_sha_title'  => 'Сохранить установленный коммит?',
	'js_confirm_sha_text'   => 'Установленной версией станет коммит {1}.',
);
