<?php

declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


define('ADMIN_DIR', TSDIR.'/admin/');

foreach(array('action', 'do', 'module') as $input)
{
    if(!isset($mybb->input[$input]))
    {
        $mybb->input[$input] = '';
    }
}

function render_inline_error($error="", $title="")
{
    global $plugins;

    $error = $plugins->run_hooks("error", $error);
    if(!$error)
    {
        $error = 'unknown_error';
    }

    echo '
    <div class="container error-container">
        <div class="card error-card">
            <div class="card-body text-center p-5">
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 class="card-title text-danger mb-3">Error</h3>
                <p class="card-text">'.$error.'</p>
                <a href="javascript:history.back()" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Go Back
                </a>
            </div>
        </div>
    </div>
    <style>
        .error-container {
            min-height: 30vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 3rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
    </style>';
}

/** Общие стили страницы (всё под .bk) */
function bk_styles(): void
{
    global $BASEURL;
	
	echo '<link rel="stylesheet" href="' . $BASEURL . '/admin/templates/backupdb.css">';
	
}

/** Скрипты страницы (выбор таблиц + модалка удаления) */
function bk_scripts(): void
{
    global $BASEURL;

    $v = (int)@filemtime(ADMIN_DIR . 'templates/backupdb.js');
    echo '<script src="' . $BASEURL . '/admin/scripts/backupdb.js?v=' . $v . '" defer></script>';
}

/** Шапка страницы */
function bk_hero(string $sub, string $right = ''): void
{
    echo '<div class="bk-card mb-3"><div class="bk-head">'
       . '<span class="bk-head-icon"><i class="fa-solid fa-database"></i></span>'
       . '<div style="min-width:0"><h1 class="bk-title">Database Backups</h1><div class="bk-sub">' . $sub . '</div></div>'
       . ($right !== '' ? '<div class="ms-auto d-flex flex-wrap gap-2">' . $right . '</div>' : '')
       . '</div></div>';
}

/** Список файлов бэкапов. Раньше ключом массива было время изменения файла —
 *  два бэкапа, созданные в одну секунду, затирали друг друга в списке. */
function bk_list_backups(): array
{
    $list = [];
    $dir  = ADMIN_DIR . 'backup/';
    if (is_dir($dir) && ($h = opendir($dir)) !== false) {
        while (($file = readdir($h)) !== false) {
            if (@filetype($dir . $file) !== 'file') continue;
            $ext = get_extension($file);
            if ($ext !== 'gz' && $ext !== 'sql') continue;
            $list[] = ['file' => $file, 'time' => (int)@filemtime($dir . $file), 'size' => (int)@filesize($dir . $file), 'type' => $ext];
        }
        closedir($h);
    }
    usort($list, fn($a, $b) => $b['time'] <=> $a['time']);
    return $list;
}


/**
 * Allows us to refresh cache to prevent over flowing
 */
function clear_overflow($fp, &$contents)
{
    global $mybb;

    if($mybb->input['method'] == 'disk')
    {
        if($mybb->input['filetype'] == 'gzip')
        {
            gzwrite($fp, $contents);
        }
        else
        {
            fwrite($fp, $contents);
        }
    }
    else
    {
        if($mybb->input['filetype'] == "gzip")
        {
            echo gzencode($contents);
        }
        else
        {
            echo $contents;
        }
    }

    $contents = '';
}

$plugins->run_hooks("admin_tools_backupdb_begin");

// Download backup action
if($mybb->input['action'] == "dlbackup")
{
    if(empty($mybb->input['file']))
    {
        flash_message('You did not specify a database backup to download', 'error');
        redirect($_this_script_);
    }

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Invalid or expired link. Please try downloading again from this page.', 'error');
        admin_redirect($_this_script_);
    }

    $plugins->run_hooks("admin_tools_backupdb_dlbackup");

    $file = basename($mybb->input['file']);
    $ext = get_extension($file);

    if(file_exists(ADMIN_DIR.'backup/'.$file) && filetype(ADMIN_DIR.'backup/'.$file) == 'file' && ($ext == 'gz' || $ext == 'sql'))
    {
        $plugins->run_hooks("admin_tools_backupdb_dlbackup_commit");

        // Log admin action
        log_admin_action($file);

        header('Content-Disposition: attachment; filename="'.$file.'"');
        // Раньше Content-type был просто «gz» / «sql» — не MIME-тип
        header('Content-Type: '.($ext == 'gz' ? 'application/gzip' : 'application/sql'));
        header("Content-length: ".filesize(ADMIN_DIR.'backup/'.$file));

        $handle = fopen(ADMIN_DIR.'backup/'.$file, 'rb');
        while(!feof($handle))
        {
            echo fread($handle, 8192);
        }
        fclose($handle);
        exit;
    }
    else
    {
        flash_message('The back up file you selected is either invalid or does not exist', 'error');
        admin_redirect($_this_script_);
    }
}


// Delete backup action
if($mybb->input['action'] == "delete")
{
    if($mybb->get_input('no'))
    {
        admin_redirect($_this_script_);
    }

    $file = basename($mybb->input['file']);
    $ext = get_extension($file);

    if(!trim($mybb->input['file']) || !file_exists(ADMIN_DIR.'backup/'.$file) || filetype(ADMIN_DIR.'backup/'.$file) != 'file' || ($ext != 'gz' && $ext != 'sql'))
    {
        flash_message('The specified backup does not exist', 'error');
        admin_redirect($_this_script_);
    }

    $plugins->run_hooks("admin_tools_backupdb_delete");

    if($mybb->request_method == "post")
    {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die('Invalid security token');
        }

        $delete = @unlink(ADMIN_DIR.'backup/'.$file);

        if($delete)
        {
            $plugins->run_hooks("admin_tools_backupdb_delete_commit");

            // Log admin action
            log_admin_action($file);

            flash_message('The backup has been deleted successfully', 'success');
            admin_redirect($_this_script_);
        }
        else
        {
            flash_message('The backup has not been deleted', 'error');
            admin_redirect($_this_script_);
        }
    }
    else
    {
        stdhead('Delete backup');
        bk_styles();
        $fileE = htmlspecialchars($file, ENT_QUOTES);
        echo '<div class="container mt-3 mb-4 bk"><div class="bk-card bk-confirm">'
           . '<span class="bk-confirm-icon"><i class="fa-solid fa-trash-can"></i></span>'
           . '<h2 class="h4 fw-bold mb-1">Delete this backup?</h2>'
           . '<div class="text-body-secondary">The file is removed from the server. This can\'t be undone.</div>'
           . '<div class="bk-target"><i class="fa-solid fa-file-zipper me-2 text-body-secondary"></i>' . $fileE . '</div>'
           . '<form action="' . $_this_script_ . '&amp;action=delete&amp;file=' . rawurlencode($file) . '" method="post" class="d-flex flex-wrap justify-content-center gap-2">'
           . '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />'
           . '<a href="' . $_this_script_ . '" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-xmark me-1"></i>Cancel</a>'
           . '<button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-trash me-1"></i>Yes, delete</button>'
           . '</form></div></div>';
        stdfoot();
        exit;
    }
}




// Create backup action
if($mybb->input['action'] == "backup")
{
    $plugins->run_hooks("admin_tools_backupdb_backup");

    if($mybb->request_method == "post")
    {
        if (!verify_post_check($mybb->get_input('my_post_key'))) {
            http_response_code(403);
            die('Invalid security token');
        }

        $mybb->input['method']          = $mybb->get_input('method') === 'disk' ? 'disk' : 'download';
        $mybb->input['filetype']        = $mybb->get_input('filetype') === 'gzip' ? 'gzip' : 'plain';
        $mybb->input['contents']        = in_array($mybb->get_input('contents'), ['both', 'structure', 'data'], true) ? $mybb->get_input('contents') : 'both';
        $mybb->input['analyzeoptimize'] = (int)$mybb->get_input('analyzeoptimize') === 1 ? 1 : 0;

        if(empty($mybb->input['tables']) || !is_array($mybb->input['tables']))
        {
            flash_message('You did not select any tables to backup', 'error');
            admin_redirect('' . $_this_script_ . '&action=backup');
        }

        @set_time_limit(0);

        // create an array for checks, as full table names are accepted
        $binary_fields_prefixed = array();
        foreach($mybb->binary_fields as $table => $fields)
        {
            $binary_fields_prefixed[$table] = $fields;
        }

        if($mybb->input['method'] == 'disk')
        {
            $file = ADMIN_DIR.'backup/backup_'.date("_Ymd_His_").random_str(16);

            if($mybb->input['filetype'] == 'gzip')
            {
                if(!function_exists('gzopen'))
                {
                    flash_message('The zlib library for PHP is not enabled - you cannot create GZIP compressed backups', 'error');
                    admin_redirect('' . $_this_script_ . '&action=backup');
                }

                $fp = gzopen($file.'.incomplete.sql.gz', 'w9');
            }
            else
            {
                $fp = fopen($file.'.incomplete.sql', 'w');
            }
        }
        else
        {
            $file = 'backup_'.substr(md5($CURUSER['id'].TIMENOW), 0, 10).random_str(54);
            if($mybb->input['filetype'] == 'gzip')
            {
                if(!function_exists('gzopen'))
                {
                    flash_message('The zlib library for PHP is not enabled - you cannot create GZIP compressed backups', 'error');
                    admin_redirect('' . $_this_script_ . '&action=backup');
                }

                header('Content-Type: application/x-gzip');
                header('Content-Disposition: attachment; filename="'.$file.'.sql.gz"');
            }
            else
            {
                header('Content-Type: text/x-sql');
                header('Content-Disposition: attachment; filename="'.$file.'.sql"');
            }
        }
        

        $time = date('dS F Y \a\t H:i', TIMENOW);
        $header = "-- {$SITENAME} Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";
        $contents = $header;
        
        foreach($mybb->input['tables'] as $table)
        {
            if(!$db->table_exists($db->escape_string($table)))
            {
                continue;
            }
            if($mybb->input['analyzeoptimize'] == 1)
            {
                $db->optimize_table($table);
                $db->analyze_table($table);
            }

            $field_list = array();
            $fields_array = $db->show_fields_from($table);
            foreach($fields_array as $field)
            {
                $field_list[] = $field['Field'];
            }

            $fields = "`".implode("`,`", $field_list)."`";
            if($mybb->input['contents'] != 'data')
            {
                $structure = $db->show_create_table($table).";\n";
                $contents .= $structure;

                if(isset($fp))
                {
                    clear_overflow($fp, $contents);
                }
            }

            if($mybb->input['contents'] != 'structure')
            {
                if($db->engine == 'mysqli')
                {
                    $query = mysqli_query($db->read_link, "SELECT * FROM {$table}", MYSQLI_USE_RESULT);
                }
                else
                {
                    $query = $db->sql_query_prepared("SELECT * FROM {$table}");
                }

                while($row = $db->fetch_array($query))
                {
                    $insert = "INSERT INTO {$table} ($fields) VALUES (";
                    $comma = '';
                    foreach($field_list as $field)
                    {
                        if(!isset($row[$field]) || is_null($row[$field]))
                        {
                            $insert .= $comma."NULL";
                        }
                        else
                        {
                            if($db->engine == 'mysqli')
                            {
                                if(!empty($binary_fields_prefixed[$table][$field]))
                                {
                                    $insert .= $comma."X'".mysqli_real_escape_string($db->read_link, bin2hex($row[$field]))."'";
                                }
                                else
                                {
                                    $insert .= $comma."'".mysqli_real_escape_string($db->read_link, $row[$field])."'";
                                }
                            }
                            else
                            {
                                if(!empty($binary_fields_prefixed[$table][$field]))
                                {
                                    $insert .= $comma.$db->escape_binary($db->unescape_binary($row[$field]));
                                }
                                else
                                {
                                    $insert .= $comma."'".$db->escape_string($row[$field])."'";
                                }
                            }
                        }
                        $comma = ',';
                    }
                    $insert .= ");\n";
                    $contents .= $insert;

                    if(isset($fp))
                    {
                        clear_overflow($fp, $contents);
                    }
                }
                $db->free_result($query);
            }
        }

       

        if($mybb->input['method'] == 'disk')
        {
            if($mybb->input['filetype'] == 'gzip')
            {
                gzwrite($fp, $contents);
                gzclose($fp);
                rename($file.'.incomplete.sql.gz', $file.'.sql.gz');
            }
            else
            {
                fwrite($fp, $contents);
                fclose($fp);
                rename($file.'.incomplete.sql', $file.'.sql');
            }

            if($mybb->input['filetype'] == 'gzip')
            {
                $ext = '.sql.gz';
            }
            else
            {
                $ext = '.sql';
            }

            $plugins->run_hooks("admin_tools_backupdb_backup_disk_commit");

            // Log admin action
            log_admin_action("disk", $file.$ext);

            
			
			
$file_from_admindir = $_this_script_ . '&action=dlbackup&amp;file=' . basename($file) . $ext . '&amp;my_post_key=' . $mybb->post_code;

flash_message('
<div class="d-flex align-items-start">
    <div class="me-3 mt-1 text-success">
        <i class="fas fa-check-circle fa-2x"></i>
    </div>
    <div>
        <h6 class="mb-1 fw-semibold text-success">Backup created successfully</h6>
        <p class="mb-2 small text-muted">The backup file has been saved to:</p>
        <code class="d-block mb-2 text-break">' . htmlspecialchars($file . $ext) . '</code>
        <a href="' . $file_from_admindir . '" class="btn btn-sm btn-success">
            <i class="fas fa-download me-1"></i> Download
        </a>
    </div>
</div>
', 'success', true);



			
			


			
			
			
			
			
			
			
            admin_redirect($_this_script_);
        }
        else
        {
            $plugins->run_hooks("admin_tools_backupdb_backup_download_commit");

            // Log admin action
            log_admin_action("download");

            if($mybb->input['filetype'] == 'gzip')
            {
                echo gzencode($contents);
            }
            else
            {
                echo $contents;
            }
        }

        exit;
    }

    stdhead('New database backup');
    bk_styles();

    $cannot_write = !is_writable(ADMIN_DIR . 'backup');
    $has_gzip     = function_exists('gzopen');

    // Таблицы с размером и числом строк (одна команда SHOW TABLE STATUS)
    $tables = [];
    foreach ($db->list_tables($config['database']['database']) as $t) {
        $tables[(string)$t] = ['rows' => null, 'size' => null];
    }
    $st = @$db->sql_query_prepared('SHOW TABLE STATUS');
    while ($st && ($r = $db->fetch_array($st))) {
        $n = (string)($r['Name'] ?? '');
        if (isset($tables[$n])) {
            $tables[$n] = ['rows' => (int)($r['Rows'] ?? 0), 'size' => (int)($r['Data_length'] ?? 0) + (int)($r['Index_length'] ?? 0)];
        }
    }
    $db_size = array_sum(array_map(fn($t) => (int)$t['size'], $tables));

    echo '<div class="container mt-3 mb-4 bk">';
    bk_hero('Choose tables and options, then create a backup', '<a href="' . $_this_script_ . '" class="btn btn-sm btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i>Back to list</a>');

    if ($cannot_write) {
        echo '<div class="alert alert-warning d-flex gap-2 rounded-4"><i class="fa-solid fa-folder-closed mt-1"></i><div>The <code>admin/backup</code> folder is not writable — backups can only be <strong>downloaded</strong>, not saved on the server.</div></div>';
    }
    if (!$has_gzip) {
        echo '<div class="alert alert-info d-flex gap-2 rounded-4"><i class="fa-solid fa-circle-info mt-1"></i><div>PHP zlib is not enabled — GZIP compression is unavailable.</div></div>';
    }

    echo '<form action="' . $_this_script_ . '&amp;action=backup" method="post" id="table_selection">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="bk-card overflow-hidden h-100">
                    <div class="bk-sec-head">
                        <span class="bk-sec-icon ic-blue"><i class="fa-solid fa-table-list"></i></span>
                        <div><h2 class="bk-sec-title">Tables</h2><div class="bk-muted"><span id="bkSel">0</span> of ' . count($tables) . ' selected · <span id="bkSelSize">0 B</span></div></div>
                        <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                            <div class="position-relative bk-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" class="form-control form-control-sm" id="bkFilter" placeholder="Filter tables…"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-sel="all"><i class="fa-solid fa-check-double me-1"></i>All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sel="none"><i class="fa-solid fa-xmark me-1"></i>None</button>
                        </div>
                    </div>
                    <div class="bk-tables">';
    // Раньше: список <select multiple> на 20 строк, выбор только с зажатым Ctrl
    foreach ($tables as $name => $t) {
        $nE = htmlspecialchars($name, ENT_QUOTES);
        echo '<label class="bk-trow" data-name="' . strtolower($nE) . '">'
           . '<input type="checkbox" class="form-check-input" name="tables[]" value="' . $nE . '" data-size="' . (int)$t['size'] . '" checked>'
           . '<span class="n">' . $nE . '</span>'
           . ($t['rows'] !== null ? '<span class="s">' . number_format((int)$t['rows']) . ' rows</span><span class="s">' . mksize((float)$t['size']) . '</span>' : '')
           . '</label>';
    }
    echo '      </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="bk-card h-100 p-3">
                    <div class="bk-grp"><i class="fa-solid fa-file-zipper me-1"></i>File type</div>
                    <div class="row g-2">
                        <div class="col-6"><label class="bk-opt position-relative"><input type="radio" name="filetype" value="gzip" ' . ($has_gzip ? 'checked' : 'disabled') . '><i class="fa-solid fa-file-zipper"></i><b>GZIP</b><small>Compressed · much smaller</small></label></div>
                        <div class="col-6"><label class="bk-opt position-relative"><input type="radio" name="filetype" value="plain" ' . ($has_gzip ? '' : 'checked') . '><i class="fa-solid fa-file-code"></i><b>Plain SQL</b><small>Readable text file</small></label></div>
                    </div>
                    <div class="bk-grp"><i class="fa-solid fa-floppy-disk me-1"></i>Save to</div>
                    <div class="row g-2">
                        <div class="col-6"><label class="bk-opt position-relative"><input type="radio" name="method" value="download" checked><i class="fa-solid fa-download"></i><b>Download</b><small>Straight to your computer</small></label></div>
                        <div class="col-6"><label class="bk-opt position-relative"><input type="radio" name="method" value="disk" ' . ($cannot_write ? 'disabled' : '') . '><i class="fa-solid fa-server"></i><b>Server</b><small>admin/backup folder</small></label></div>
                    </div>
                    <div class="bk-grp"><i class="fa-solid fa-layer-group me-1"></i>Contents</div>
                    <div class="row g-2">
                        <div class="col-4"><label class="bk-opt position-relative"><input type="radio" name="contents" value="both" checked><i class="fa-solid fa-cubes"></i><b>Full</b><small>Structure + data</small></label></div>
                        <div class="col-4"><label class="bk-opt position-relative"><input type="radio" name="contents" value="structure"><i class="fa-solid fa-sitemap"></i><b>Structure</b><small>Tables only</small></label></div>
                        <div class="col-4"><label class="bk-opt position-relative"><input type="radio" name="contents" value="data"><i class="fa-solid fa-table"></i><b>Data</b><small>Rows only</small></label></div>
                    </div>
                    <div class="bk-grp"><i class="fa-solid fa-broom me-1"></i>Maintenance</div>
                    <label class="d-flex align-items-center gap-2 p-2 rounded-3" style="border:1px solid var(--bs-border-color-translucent);cursor:pointer">
                        <input type="hidden" name="analyzeoptimize" value="0">
                        <input class="form-check-input m-0" type="checkbox" role="switch" name="analyzeoptimize" value="1" checked style="width:2.5em;height:1.35em">
                        <span><b class="d-block">Analyze &amp; optimize</b><small class="bk-muted">Runs OPTIMIZE / ANALYZE on each selected table first</small></span>
                    </label>
                </div>
            </div>
        </div>
        <div class="bk-card bk-savebar">
            <span class="bk-muted"><i class="fa-solid fa-circle-info me-1"></i>Large databases can take a while — keep the page open</span>
            <button type="submit" class="btn btn-primary px-4" id="bkGo"><i class="fa-solid fa-play me-1"></i>Create backup</button>
        </div>
    </form>
    </div>';
    bk_scripts();

    stdfoot();
}


// Main page - list backups
if(!$mybb->input['action'])
{
    stdhead('Database Backups');
    bk_styles();
    $plugins->run_hooks("admin_tools_backupdb_start");

    $backups   = bk_list_backups();
    $total     = array_sum(array_column($backups, 'size'));
    $latest    = $backups[0]['time'] ?? 0;
    $writable  = is_writable(ADMIN_DIR . 'backup');
    $age_cls   = !$latest ? 'ic-slate' : ((TIMENOW - $latest) < 7 * 86400 ? 'ic-green' : ((TIMENOW - $latest) < 30 * 86400 ? 'ic-amber' : 'ic-purple'));

    echo '<div class="container mt-3 mb-4 bk">';
    bk_hero('Create, download and remove SQL backups of the tracker database',
        '<a href="' . $_this_script_ . '&amp;action=backup" class="btn btn-primary px-3"><i class="fa-solid fa-plus me-1"></i>New backup</a>');

    if (!$writable) {
        echo '<div class="alert alert-warning d-flex gap-2 rounded-4"><i class="fa-solid fa-folder-closed mt-1"></i><div>The <code>admin/backup</code> folder is not writable — new backups can only be downloaded.</div></div>';
    }

    echo '<div class="row g-3 mb-3">';
    foreach ([
        ['fa-box-archive', 'ic-blue',  'Backups',      number_format(count($backups))],
        ['fa-hard-drive',  'ic-teal',  'Space used',   mksize((float)$total)],
        ['fa-clock',       $age_cls,   'Latest',       $latest ? my_datee('relative', $latest) : 'never'],
        ['fa-folder-open', $writable ? 'ic-green' : 'ic-amber', 'Folder', $writable ? 'Writable' : 'Read-only'],
    ] as [$ic, $cls, $label, $val]) {
        echo '<div class="col-6 col-lg-3"><div class="bk-card bk-kpi"><span class="bk-kpi-icon ' . $cls . '"><i class="fa-solid ' . $ic . '"></i></span>'
           . '<div style="min-width:0"><div class="bk-kpi-label">' . $label . '</div><div class="bk-kpi-value">' . $val . '</div></div></div></div>';
    }
    echo '</div>';

    if (!$backups) {
        echo '<div class="bk-card"><div class="bk-empty"><i class="fa-solid fa-inbox"></i><div class="fw-semibold">No backups yet</div>'
           . '<div class="small mb-3">Backups saved to the server show up here.</div>'
           . '<a href="' . $_this_script_ . '&amp;action=backup" class="btn btn-primary px-3"><i class="fa-solid fa-plus me-1"></i>Create the first backup</a></div></div>';
    } else {
        echo '<div class="bk-card overflow-hidden"><div class="bk-sec-head"><span class="bk-sec-icon ic-slate"><i class="fa-solid fa-list"></i></span>'
           . '<h2 class="bk-sec-title">Saved backups</h2></div>'
           . '<div class="table-responsive"><table class="table bk-table"><thead><tr>'
           . '<th><i class="fa-solid fa-file"></i>File</th><th><i class="fa-solid fa-weight-hanging"></i>Size</th>'
           . '<th><i class="fa-solid fa-clock"></i>Created</th><th class="text-end"><i class="fa-solid fa-bolt"></i></th>'
           . '</tr></thead><tbody>';
        foreach ($backups as $i => $b) {
            $dl    = $_this_script_ . '&amp;action=dlbackup&amp;file=' . rawurlencode($b['file']) . '&amp;my_post_key=' . $mybb->post_code;
            $fileE = htmlspecialchars($b['file'], ENT_QUOTES);
            $gz    = $b['type'] === 'gz';
            echo '<tr>'
               . '<td><div class="d-flex align-items-center gap-3"><span class="bk-ficon ' . ($gz ? 'ic-amber' : 'ic-teal') . '"><i class="fa-solid ' . ($gz ? 'fa-file-zipper' : 'fa-file-code') . '"></i></span>'
               . '<div style="min-width:0"><a href="' . $dl . '" class="bk-fname">' . $fileE . '</a>'
               . '<div class="mt-1"><span class="bk-tag ' . ($gz ? 't-gz' : 't-sql') . '">' . ($gz ? 'GZIP' : 'SQL') . '</span>'
               . ($i === 0 ? ' <span class="bk-tag t-new"><i class="fa-solid fa-star"></i>latest</span>' : '') . '</div></div></div></td>'
               . '<td class="text-nowrap fw-semibold">' . mksize((float)$b['size']) . '</td>'   // раньше — голое число байт
               . '<td class="text-nowrap bk-muted" title="' . htmlspecialchars(date('Y-m-d H:i:s', $b['time'])) . '">' . ($b['time'] ? my_datee('relative', $b['time']) : '—') . '</td>'
               . '<td class="text-end text-nowrap">'
               . '<a href="' . $dl . '" class="bk-act" title="Download"><i class="fa-solid fa-download"></i></a>'
               // data-атрибуты вместо id с именем файла: точки в «backup_….sql.gz»
               // ломали селектор data-bs-target="#deleteModal…", и модалка не открывалась
               . '<button type="button" class="bk-act danger bk-del" title="Delete" data-file="' . $fileE . '" data-url="' . $_this_script_ . '&amp;action=delete&amp;file=' . rawurlencode($b['file']) . '"><i class="fa-solid fa-trash"></i></button>'
               . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
    echo '</div>';

    // Одна модалка на все строки
    echo '<div class="modal fade bk-modal" id="bkDelModal" tabindex="-1" aria-labelledby="bkDelTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header">
          <span class="bk-mh-icon"><i class="fa-solid fa-trash-can"></i></span>
          <h5 class="modal-title fw-bold" id="bkDelTitle">Delete backup?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="font-monospace p-2 rounded-3 bg-body-tertiary text-break" id="bkDelFile"></div>
          <div class="text-body-secondary small mt-2"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>The file is removed from the server. This can\'t be undone.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal"><i class="fa-solid fa-xmark me-1"></i>Cancel</button>
          <form method="post" action="" id="bkDelForm" class="d-inline">
            <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
            <button type="submit" class="btn btn-danger px-3"><i class="fa-solid fa-trash me-1"></i>Delete</button>
          </form>
        </div>
      </div></div>
    </div>';
    bk_scripts();

    stdfoot();
}