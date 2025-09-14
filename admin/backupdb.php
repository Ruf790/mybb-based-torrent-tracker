<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

define("IN_MYBB", 1);

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('TSDIR', dirname(__FILE__));
define('MYBB_ADMIN_DIR', TSDIR.'/admin/');

foreach(array('action', 'do', 'module') as $input)
{
    if(!isset($mybb->input[$input]))
    {
        $mybb->input[$input] = '';
    }
}

function stderr2($error="", $title="")
{
    global $SITENAME, $header, $footer, $theme, $headerinclude, $db, $templates, $lang, $mybb, $plugins;

    $error = $plugins->run_hooks("error", $error);
    if(!$error)
    {
        $error = 'unknown_error';
    }

    if(!$title)
    {
        $title = $SITENAME;
    }

    $errorpage = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>'.$title.'</title>
       
        <style>
            .error-container {
                min-height: 60vh;
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
        </style>
    </head>
    <body>
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
    </body>
    </html>';

    echo $errorpage;
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

    $plugins->run_hooks("admin_tools_backupdb_dlbackup");

    $file = basename($mybb->input['file']);
    $ext = get_extension($file);

    if(file_exists(MYBB_ADMIN_DIR.'backup/'.$file) && filetype(MYBB_ADMIN_DIR.'backup/'.$file) == 'file' && ($ext == 'gz' || $ext == 'sql'))
    {
        $plugins->run_hooks("admin_tools_backupdb_dlbackup_commit");

        // Log admin action
        //log_admin_action($file);

        header('Content-disposition: attachment; filename='.$file);
        header("Content-type: ".$ext);
        header("Content-length: ".filesize(MYBB_ADMIN_DIR.'backup/'.$file));

        $handle = fopen(MYBB_ADMIN_DIR.'backup/'.$file, 'rb');
        while(!feof($handle))
        {
            echo fread($handle, 8192);
        }
        fclose($handle);
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

    if(!trim($mybb->input['file']) || !file_exists(MYBB_ADMIN_DIR.'backup/'.$file) || filetype(MYBB_ADMIN_DIR.'backup/'.$file) != 'file' || ($ext != 'gz' && $ext != 'sql'))
    {
        flash_message('The specified backup does not exist', 'error');
        admin_redirect($_this_script_);
    }

    $plugins->run_hooks("admin_tools_backupdb_delete");

    if($mybb->request_method == "post")
    {
        $delete = @unlink(MYBB_ADMIN_DIR.'backup/'.$file);

        if($delete)
        {
            $plugins->run_hooks("admin_tools_backupdb_delete_commit");

            // Log admin action
            //log_admin_action($file);

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
        // Выводим модалку вместо стандартного подтверждения
        stdhead();
        
        echo '<div class="container mt-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white rounded-top">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deletion
                    </h5>
                </div>
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-trash-alt fa-4x text-danger mb-3"></i>
                        <h4 class="text-danger">Are you sure you wish to delete this backup?</h4>
                        <p class="text-muted">File: <strong>'.$file.'</strong></p>
                        <p class="text-muted">This action cannot be undone.</p>
                    </div>
                    
                    <form action="' . $_this_script_ . '&action=delete&amp;file='.$file.'" method="post">
                        <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                        <button type="submit" class="btn btn-danger btn-lg me-3">
                            <i class="fas fa-trash me-2"></i>Yes, Delete Backup
                        </button>
                        <a href="' . $_this_script_ . '" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </form>
                </div>
            </div>
        </div>';
        
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
        if(empty($mybb->input['tables']) || !is_array($mybb->input['tables']))
        {
            flash_message('You did not select any tables to backup', 'error');
            admin_redirect('' . $_this_script_ . '&action=backup');
        }

        @set_time_limit(0);

        // create an array with table prefix appended for checks, as full table names are accepted
        $binary_fields_prefixed = array();
        foreach($mybb->binary_fields as $table => $fields)
        {
            $binary_fields_prefixed[TABLE_PREFIX.$table] = $fields;
        }

        if($mybb->input['method'] == 'disk')
        {
            $file = MYBB_ADMIN_DIR.'backup/backup_'.date("_Ymd_His_").random_str(16);

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
        $db->set_table_prefix('');

        $time = date('dS F Y \a\t H:i', TIMENOW);
        $header = "-- Ruff Tracker Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";
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
                    $query = mysqli_query($db->read_link, "SELECT * FROM {$db->table_prefix}{$table}", MYSQLI_USE_RESULT);
                }
                else
                {
                    $query = $db->simple_select($table);
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

        $db->set_table_prefix(TABLE_PREFIX);

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

            $file_from_admindir = '' . $_this_script_ . '&action=dlbackup&amp;file='.basename($file).$ext;
            flash_message("<span><em>The backup has been created successfully</em></span><p>The backup was saved to:<br />{$file}{$ext} (<a href=\"{$file_from_admindir}\">Download</a>)</p>", 'success');
			
			
			
			
			
            admin_redirect($_this_script_);
        }
        else
        {
            $plugins->run_hooks("admin_tools_backupdb_backup_download_commit");

            // Log admin action
            //log_admin_action("download");

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

    stdhead();
    
    $ss = "<script type=\"text/javascript\">
    function changeSelection(action, prefix)
    {
        var select_box = document.getElementById('table_select');

        for(var i = 0; i < select_box.length; i++)
        {
            if(action == 'select')
            {
                select_box[i].selected = true;
            }
            else if(action == 'deselect')
            {
                select_box[i].selected = false;
            }
            else if(action == 'forum' && prefix != 0)
            {
                select_box[i].selected = false;
                var row = select_box[i].value;
                var subString = row.substring(prefix.length, 0);
                if(subString == prefix)
                {
                    select_box[i].selected = true;
                }
            }
        }
    }
    </script>\n";
    
    echo $ss;

    echo '<div class="container mt-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white rounded-top">
                <h5 class="mb-0">
                    <i class="fas fa-database me-2"></i>
                    Database Backups
                    <span class="float-end">
                        <a href="' . $_this_script_ . '&action=backup" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>New Backup
                        </a>
                    </span>
                </h5>
            </div>
        </div>
    </div>';

    // Check if file is writable
    if(!is_writable(MYBB_ADMIN_DIR."/backup"))
    {
        stderr2('Your backups directory (within the Admin CP directory) is not writable. You cannot save backups on the server');
        $cannot_write = true;
    }

    $table_selects = array();
    $table_list = $db->list_tables($config['database']['database']);
    foreach($table_list as $id => $table_name)
    {
        $table_selects[$table_name] = $table_name;
    }

    $construct_cell = "\n<br />
    <br />\n<a href=\"javascript:changeSelection('select', 0);\" class=\"btn btn-sm btn-outline-primary me-2\">Select All</a>\n<a href=\"javascript:changeSelection('deselect', 0);\" class=\"btn btn-sm btn-outline-secondary\">Deselect All</a>
    \n\n<br /><br />\n";
    
    $box = generate_select_box("tables[]", $table_selects, false, array('multiple' => true, 'id' => 'table_select', 'size' => 20, 'class' => 'form-select'));
    
    echo '
    <div class="container mt-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Database Backup</h6>
            </div>
            <div class="card-body">
                <form action="' . $_this_script_ . '&action=backup" method="post" name="table_selection" id="table_selection">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Table Selection</label>
                                <p class="text-muted small">You may select the database tables you wish to perform this action on here. Hold down CTRL to select multiple tables</p>
                                '.$construct_cell.'
                                '.$box.'
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-bold">File Type</label>
                                <p class="text-muted small">Select the file type you would like the database backup saved as</p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="filetype" value="gzip" id="gzip" checked>
                                    <label class="form-check-label" for="gzip">GZIP Compressed</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="filetype" value="plain" id="plain">
                                    <label class="form-check-label" for="plain">Plain Text</label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Save Method</label>
                                <p class="text-muted small">Select the method you would like to use to save the backup</p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="method" value="disk" id="disk">
                                    <label class="form-check-label" for="disk">Backup Directory</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="method" value="download" id="download" checked>
                                    <label class="form-check-label" for="download">Download</label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Backup Contents</label>
                                <p class="text-muted small">Select the information that you would like included in the backup</p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="contents" value="both" id="both" checked>
                                    <label class="form-check-label" for="both">Structure and Data</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="contents" value="structure" id="structure">
                                    <label class="form-check-label" for="structure">Structure Only</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="contents" value="data" id="data">
                                    <label class="form-check-label" for="data">Data only</label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Analyze and Optimize Selected Tables</label>
                                <p class="text-muted small">Would you like the selected tables to be analyzed and optimized during the backup?</p>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="analyzeoptimize" value="1" id="optimize_yes" checked>
                                    <label class="form-check-label" for="optimize_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="analyzeoptimize" value="0" id="optimize_no">
                                    <label class="form-check-label" for="optimize_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-download me-2"></i>Perform Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    stdfoot();
}

// Main page - list backups
if(!$mybb->input['action'])
{
    stdhead();
    $plugins->run_hooks("admin_tools_backupdb_start");

    echo '<div class="container mt-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white rounded-top">
                <h5 class="mb-0">
                    <i class="fas fa-database me-2"></i>
                    Database Backups
                    <span class="float-end">
                        <a href="' . $_this_script_ . '&action=backup" class="btn btn-light btn-sm">
                            <i class="fas fa-plus me-1"></i>New Backup
                        </a>
                    </span>
                </h5>
            </div>
        </div>
    </div>';

    echo '<div class="container mt-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Existing Database Backups</h6>
            </div>
        </div>
    </div>';

    $backups = array();
    $dir = MYBB_ADMIN_DIR.'backup/';
    $handle = opendir($dir);

    if($handle !== false)
    {
        while(($file = readdir($handle)) !== false)
        {
            if(filetype(MYBB_ADMIN_DIR.'backup/'.$file) == 'file')
            {
                $ext = get_extension($file);
                if($ext == 'gz' || $ext == 'sql')
                {
                    $backups[@filemtime(MYBB_ADMIN_DIR.'backup/'.$file)] = array(
                        "file" => $file,
                        "time" => @filemtime(MYBB_ADMIN_DIR.'backup/'.$file),
                        "type" => $ext
                    );
                }
            }
        }
        closedir($handle);
    }

    $count = count($backups);
    krsort($backups);

    $show_backup = '';

    foreach($backups as $backup)
    {
        $time = "-";
        if($backup['time'])
        {
            $time = my_datee('relative', $backup['time']);
        }

        $file_size = ts_nf(filesize(MYBB_ADMIN_DIR.'backup/'.$backup['file']));
        
        $show_backup .= '<tr>
            <td>
                <i class="fas fa-file-'.($backup['type'] == 'gz' ? 'archive text-warning' : 'alt text-info').' me-2"></i>
                <a href="' . $_this_script_ . '&action=dlbackup&amp;file='.$backup['file'].'" class="text-decoration-none">
                    '.$backup['file'].'
                </a>
            </td>
            <td>'.$file_size.'</td>
            <td>'.$time.'</td>
            <td class="text-center">
                <a href="' . $_this_script_ . '&action=dlbackup&amp;file='.$backup['file'].'" class="btn btn-sm btn-success me-1" title="Download">
                    <i class="fas fa-download"></i>
                </a>
               
			   
			   
			   <button type="button" 
        class="btn btn-sm btn-danger" 
        title="Delete"
        data-bs-toggle="modal" 
        data-bs-target="#deleteModal'.$backup['file'].'">
    <i class="fas fa-trash"></i>
</button>

<!-- Modal for Delete Confirmation -->
<div class="modal fade" id="deleteModal'.$backup['file'].'" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <i class="fas fa-trash-alt fa-3x text-danger"></i>
                </div>
                <h5 class="text-danger mb-3">Are you sure you want to delete this backup?</h5>
                <p class="text-muted">File: <strong>'.$backup['file'].'</strong></p>
                <p class="text-muted"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <a href="' . $_this_script_ . '&action=delete&amp;file='.$backup['file'].'&amp;my_post_key='.$mybb->post_code.'" 
                   class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Delete Backup
                </a>
            </div>
        </div>
    </div>
</div>
				
				
            </td>
        </tr>';
    }

    if($show_backup)
    {
        echo '<div class="container mt-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-file me-1"></i>Backup Filename</th>
                                    <th><i class="fas fa-weight-hanging me-1"></i>File Size</th>
                                    <th><i class="fas fa-clock me-1"></i>Creation Date</th>
                                    <th class="text-center"><i class="fas fa-cogs me-1"></i>Controls</th>
                                </tr>
                            </thead>
                            <tbody>
                                '.$show_backup.'
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>';
    }

    if($count == 0)
    {
        echo '<div class="container mt-4">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Backups Found</h5>
                    <p class="text-muted">There are currently no backups made yet.</p>
                    <a href="' . $_this_script_ . '&action=backup" class="btn btn-primary mt-2">
                        <i class="fas fa-plus me-2"></i>Create Your First Backup
                    </a>
                </div>
            </div>
        </div>';
    }
	
	
	
	
	
echo "<script>
// Initialize modals
document.addEventListener('DOMContentLoaded', function() {
    var deleteModals = document.querySelectorAll('.modal');
    deleteModals.forEach(function(modal) {
        new bootstrap.Modal(modal);
    });
});
</script>";
	
	
	
	

    stdfoot();
}