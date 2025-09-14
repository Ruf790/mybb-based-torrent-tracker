<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/* DrNet - wWw.SpecialCoders.CoM               */
/* Vinson - wWw.Decode4u.CoM                   */
/* MrDecoder - wWw.Fearless-Releases.CoM       */
/* Fynnon - wWw.BvList.CoM                     */
/***********************************************/

define('IN_MYBB', 1);
require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_html.php';

// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

// --- Function to send warning emails to selected users ---
function send_warning_emails($selected_users)
{
    global $db, $SITENAME, $body, $subject;
    
    if (empty($selected_users)) {
        return '<tr><td colspan="2" align="left">No users selected for a warning!</td></tr>';
    }

    $response = '';
    $count = 0;
    
    foreach ($selected_users as $user_id) {
        $user_id = intval($user_id);
        $query = $db->sql_query("SELECT id, username, email FROM users WHERE id = " . $user_id);
        $user = $db->fetch_array($query);

        if ($user) {
            $email = htmlspecialchars_uni($user['email']);
            $response .= '<tr><td align="right">Sending email to: ' . htmlspecialchars_uni($user['username']) . ' (' . $email . ')</td>';
            
            $format = "html";
            $text_message = "";
            $sendmail = my_mail($email, $subject['inactive'], sprintf($body['inactive'], $user['username']), "", "", "", false, $format, $text_message);
            
            $response .= '<td align="center">' . ($sendmail ? '<font color="green">Success!</font>' : '<font color="red">Failed!</font>') . '</td></tr>';
            
            if ($sendmail) {
                $db->sql_query('REPLACE INTO ts_inactivity (userid, inactivitytag) VALUES (' . $db->sqlesc($user['id']) . ', ' . $db->sqlesc(TIMENOW) . ')');
                $count++;
            }
        }
    }
    
    return $response . '<tr><td colspan="2" align="left">Sent ' . $count . ' warning emails.</td></tr>';
}

// --- Function to delete selected users ---
function delete_selected_users($selected_users)
{
    global $db, $CURUSER;

    if (empty($selected_users)) {
        return '<tr><td colspan="2" align="left">No users selected for deletion!</td></tr>';
    }

    $deleted_count = 0;
    
    // Убедитесь, что класс существует
    if (!class_exists('UserDataHandler')) {
        require_once INC_PATH . '/datahandlers/user.php';
    }
    
    $userhandler = new UserDataHandler('delete');
    
    foreach ($selected_users as $user_id) {
        $user_id = intval($user_id);
        
        // Проверяем, не пытаемся ли удалить сами себя или важных пользователей
        if ($user_id == $CURUSER['id'] || $user_id == 1) { // Не удаляем себя и пользователя с ID 1
            continue;
        }
        
        $query = $db->sql_query("SELECT id, username FROM users WHERE id = " . $user_id);
        $user = $db->fetch_array($query);
        
        if ($user) {
            try {
                $delete = $userhandler->delete_user(intval($user['id']));
                if ($delete) {
                    $deleted_count++;
                    write_log('Account (' . htmlspecialchars_uni($user['username']) . ') has been deleted due inactivity by ' . $CURUSER['username']);
                } else {
                    write_log('Failed to delete account (' . htmlspecialchars_uni($user['username']) . ')');
                }
            } catch (Exception $e) {
                write_log('Error deleting user ' . $user_id . ': ' . $e->getMessage());
            }
        }
    }
    
    return '<tr><td colspan="2" align="left">' . $deleted_count . ' user accounts have been deleted.</td></tr>';
}

// --- Core settings and checks ---
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('IUM_VERSION', '0.9 by xam');
if (@file_exists('./include/inactiveusers_config.php')) {
    include_once './include/inactiveusers_config.php';
} else {
    $leechwarn_length2 = '3';
    $deleteafter = TIMENOW + $leechwarn_length2 * 604800;
    $show_per_page = 30;
    $postmaillimit = '20';
    $body = array(
        'inactive' => '<p>Dear %s,</p><p>It has come to our attention that you have registered at <b>' . $SITENAME . '</b> more then <b>' . $maxdays . ' days ago</b>, but didn\'t login again since.</p><p>Did you forget about us?</p><p>We would be happy to see you around again!</p><p>If you don\'t login again within <b>' . $deleteafter . ' days</b> from now, we will <b><font color=red>delete</font></b> your account.</p><p>&nbsp;</p><p>Sincerely,</p><p>' . $SITENAME . ' Team</p><p><a href="' . $BASEURL . '">' . $BASEURL . '</a></p><p>nbsp;</p><p><b>DO NOT REPLY TO THIS EMAIL!</b></p>',
        'deleted' => '<p>Dear %s,</p><p>You have not logged in at <b>' . $SITENAME . '</b> for more then <b>' . $maxdays . ' days</b>.</p><p>You also didn\'t respond to our eMail we sent to you <b>' . $deleteafter . ' days ago</b>.</p><p>Therefor we have decided to <b><font color=red>delete</font></b> your Account, as it seems you are not interested in our site any longer.</p><p>We are sorry to see that you left us, feel free to come back at any time.</p><p>&nbsp;</p><p>Sincerely,</p><p>' . $SITENAME . ' Team</p><p><a href="' . $BASEURL . '">' . $BASEURL . '</a></p><p>nbsp;</p><p><b>DO NOT REPLY TO THIS EMAIL!</b></p>'
    );
    $subject = array(
        'inactive' => $SITENAME . ' - Account Inactive!',
        'deleted' => $SITENAME . ' - Account Deleted!'
    );
}

// Ensure $_this_script_ is defined
if (!isset($_this_script_)) {
    $_this_script_ = $_SERVER['PHP_SELF'];
}

// --- Process form actions ---

$action_result = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Получаем выбранных пользователей из hidden field
    $selected_users = array();
    if (isset($_POST['selected_users_data']) && !empty($_POST['selected_users_data'])) {
        $selected_users = array_map('intval', explode(',', $_POST['selected_users_data']));
    }

    if ($_POST['action'] == 'send_warn_email') {
        if (!empty($selected_users)) {
            $action_result = send_warning_emails($selected_users);
        } else {
            $action_result = '<tr><td colspan="2" align="left">No users selected!</td></tr>';
        }
    } 
    
    elseif ($_POST['action'] == 'delete_selected_users') 
    {
        if (!empty($selected_users)) {
            $action_result = delete_selected_users($selected_users);
        } else {
            $action_result = '<tr><td colspan="2" align="left">No users selected!</td></tr>';
        }
    }
}










// --- Pagination and database queries ---
$dt = TIMENOW - ($maxdays * 86400);
$query = $db->sql_query("
    SELECT COUNT(id) as uid
    FROM users u
    WHERE lastactive <{$dt} AND ustatus='confirmed' AND enabled='yes' ORDER BY lastactive DESC
");

$threadcount = $db->fetch_field($query, "uid");

$f_threadsperpage = "20";
if (!$f_threadsperpage || (int)$f_threadsperpage < 1) {
    $f_threadsperpage = 20;
}

$perpage = $f_threadsperpage;
$page = $mybb->get_input('page', MyBB::INPUT_INT);
if ($page > 0) {
    $start = ($page - 1) * $perpage;
    $pages = ceil($threadcount / $perpage);
    if ($page > $pages || $page <= 0) {
        $start = 0;
        $page = 1;
    }
} else {
    $start = 0;
    $page = 1;
}

$page_url = str_replace("{fid}", $fid, $_this_script_ . '');
$multipage = multipage($threadcount, $perpage, $page, $page_url);

$query_inactive = $db->sql_query('SELECT u.id,u.username,u.usergroup,u.email,u.uploaded,u.downloaded,u.lastactive,u.lastvisit,u.added,i.inactivitytag FROM users u LEFT JOIN ts_inactivity i ON (u.id=i.userid) WHERE u.enabled = \'yes\' AND u.ustatus = \'confirmed\' AND u.lastactive < ' . $dt . ('' . '  ORDER BY i.inactivitytag DESC, u.lastactive DESC LIMIT ' . $start . ', ' . $perpage . ''));

// --- HTML and user interface ---


stdhead('Inactive Users more than ' . $maxdays . ' days! (Total ' . $threadcount . ' users found, Showing ' . $perpage . ' per page!)');
?>

<div class="container mt-3">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title"><i class="fas fa-users-slash"></i> Inactive Users (<?php echo $maxdays; ?>+ Days)</h5>
            <p class="card-subtitle mb-2 text-muted">Total: <?php echo $threadcount; ?> users found</p>
        </div>
        <div class="card-body">
            <?php if (!empty($action_result)) { ?>
                <div class="alert alert-info" role="alert">
                    <table class="table mb-0">
                        <?php echo $action_result; ?>
                    </table>
                </div>
            <?php } ?>
            
            <!-- Форма без чекбоксов -->
            <form method="post" action="<?php echo htmlspecialchars($_this_script_); ?>" id="mainForm">
                <input type="hidden" name="selected_users_data" id="selectedUsersData" value="">
                
                
				
				<!-- Кнопки ДО таблицы -->
<div class="d-flex justify-content-between mb-3">
    <div class="btn-group">
        <button type="button" onclick="submitForm('send_warn_email')" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i> Send Warning Emails
        </button>
        <button type="button" onclick="submitForm('delete_selected_users')" class="btn btn-danger">
            <i class="fas fa-user-times me-2"></i> Delete Selected
        </button>
    </div>
    <div class="pagination-container">
        <?php echo $multipage; ?>
    </div>
</div>
				
				
				
            </form>

            
            <!-- Таблица вне формы -->
<table class="table table-hover table-striped">
    <thead>
        <tr>
            <th>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="checkAll">
                    <label class="form-check-label" for="checkAll"></label>
                </div>
            </th>
            <th><i class="fas fa-user me-2"></i> User</th>
            <th><i class="fas fa-envelope me-2"></i> Email</th>
            <th><i class="fas fa-chart-pie me-2"></i> Ratio</th>
            <th><i class="fas fa-calendar-plus me-2"></i> Joined</th>
            <th><i class="fas fa-clock me-2"></i> Last Access</th>
            <th><i class="fas fa-info-circle me-2"></i> Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        include_once INC_PATH . '/functions_ratio.php';
        require_once INC_PATH . '/functions_mkprettytime.php';
        $count = 0;
        if ($db->num_rows($query_inactive) > 0) {
            while ($user = $db->fetch_array($query_inactive)) {
                $last_seen = max(array($user['lastactive'], $user['lastvisit']));
                $last_active = !empty($last_seen) ? my_datee('relative', $last_seen) : 'Never';

                $secs = $deleteafter * 86400;
                $dt_delete = get_date_time($user['inactivitytag'] + $secs);
                $left = ($user['inactivitytag'] + $secs) - TIMENOW;
                $status_text = '';
                if ($user['inactivitytag'] != 0) {
                    $status_text = 'Warned ' . mkprettytime(TIMENOW - $user['inactivitytag']) . ' ago.<br/>Will be deleted in ' . mkprettytime($left) . ' (on ' . $dt_delete . ')';
                } else {
                    $status_text = 'Inactive. No action taken yet.';
                }
                ?>
                <tr>
                    
					
					
					
					
					<td>
    <div class="form-check form-switch">
        <input class="form-check-input user-checkbox" type="checkbox" role="switch" 
               name="selected_users[]" value="<?php echo $user['id']; ?>" id="user_<?php echo $user['id']; ?>">
        <label class="form-check-label" for="user_<?php echo $user['id']; ?>"></label>
    </div>
</td>
					
					
					
					
					
					
					
					
					
					
					
					
                    <td><a href="<?php echo $BASEURL . '/' . get_profile_link($user['id']); ?>"><?php echo format_name($user['username'], $user['usergroup']); ?></a></td>
                    <td><a href="mailto:<?php echo $user['email']; ?>"><?php echo $user['email']; ?></a></td>
                    <td class="text-center"><?php echo get_user_ratio($user['uploaded'], $user['downloaded']); ?></td>
                    <td><?php echo my_datee($dateformat, $user['added']); ?><br/>(<?php echo mkprettytime(TIMENOW - $user['added']); ?>)</td>
                    <td><?php echo $last_active; ?></td>
                    <td><?php echo $status_text; ?></td>
                </tr>
                <?php
                $count++;
            }
        } else {
            ?>
            <tr>
                <td colspan="7" class="text-center">No inactive users found.</td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
            
           
            <!-- Кнопки под таблицей -->
<div class="d-flex justify-content-between mt-3">
    <div class="btn-group">
        <button type="button" onclick="submitForm('send_warn_email')" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i> Send Warning Emails
        </button>
        <button type="button" onclick="submitForm('delete_selected_users')" class="btn btn-danger">
            <i class="fas fa-user-times me-2"></i> Delete Selected
        </button>
    </div>
</div>
			
			
			
			
			
			
			
            
            <div class="d-flex justify-content-center mt-3">
                <div class="pagination-container">
                    <?php echo $multipage; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.1) !important;
    cursor: pointer;
}
.table-hover tbody tr:hover .form-check-input {
    border-color: #0d6efd;
}
</style>


<script>
// Глобальные переменные для модалки
let currentAction = '';
let selectedUsers = [];

document.addEventListener('DOMContentLoaded', function() {
    // Select all checkboxes functionality
    document.getElementById('checkAll').addEventListener('click', function() {
        let checkboxes = document.querySelectorAll('.user-checkbox');
        const isChecked = this.checked;
        for (let checkbox of checkboxes) {
            checkbox.checked = isChecked;
        }
        // Обновляем список при изменении выбора
        updateSelectedUsers();
    });

    // Обработчик изменения чекбоксов
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedUsers);
    });

    // Обработчик подтверждения удаления в модалке
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (selectedUsers.length > 0) {
            proceedWithAction();
        }
    });

    // Стилизация для Bootstrap чекбоксов
    const style = document.createElement('style');
    style.textContent = `
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .form-check-input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .form-check {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: auto;
        }
        .form-check-input {
            margin: 0;
            width: 1.2em;
            height: 1.2em;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.1) !important;
            cursor: pointer;
        }
        .user-item {
            padding: 2px 5px;
            margin: 2px 0;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 3px;
            font-size: 0.9em;
        }
    `;
    document.head.appendChild(style);
});

// Функция обновления списка выбранных пользователей
function updateSelectedUsers() {
    selectedUsers = [];
    const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
    
    selectedCheckboxes.forEach(checkbox => {
        const userId = checkbox.value;
        const userName = checkbox.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
        selectedUsers.push({ id: userId, name: userName });
    });
}

// Функция показа модалки
function showDeleteModal(action) {
    currentAction = action;
    
    // Обновляем информацию в модалке
    document.getElementById('selectedUsersCount').textContent = selectedUsers.length;
    
    const usersListContainer = document.getElementById('selectedUsersList');
    if (selectedUsers.length > 0) {
        usersListContainer.innerHTML = '';
        selectedUsers.forEach(user => {
            const userElement = document.createElement('div');
            userElement.className = 'user-item';
            userElement.innerHTML = `<i class="fas fa-user me-1"></i> ${user.name} (ID: ${user.id})`;
            usersListContainer.appendChild(userElement);
        });
    } else {
        usersListContainer.innerHTML = '<small>No users selected</small>';
    }
    
    // Показываем модалку
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// Функция отправки формы
function proceedWithAction() {
    const userIds = selectedUsers.map(user => user.id);
    
    // Записываем выбранных пользователей в hidden field
    document.getElementById('selectedUsersData').value = userIds.join(',');
    
    // Добавляем скрытое поле для action
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = currentAction;
    document.getElementById('mainForm').appendChild(actionInput);
    
    // Отправляем форму
    document.getElementById('mainForm').submit();
}

// Функция отправки формы
function submitForm(action) {
    if (selectedUsers.length === 0) {
        alert('Please select at least one user.');
        return false;
    }
    
    if (action === 'delete_selected_users') {
        showDeleteModal(action);
    } else if (action === 'send_warn_email') {
        if (confirm(`Send warning emails to ${selectedUsers.length} user(s)?`)) {
            proceedWithAction();
        }
    }
}
</script>






<!-- Modal для подтверждения удаления -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning!</strong> This action cannot be undone!
                </div>
                <p>Are you sure you want to delete <span id="selectedUsersCount" class="fw-bold">0</span> user(s)?</p>
                <div id="selectedUsersList" class="mt-3 p-2 bg-light rounded" style="max-height: 150px; overflow-y: auto;">
                    <small>No users selected</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-2"></i>Delete Users
                </button>
            </div>
        </div>
    </div>
</div>





<?php
stdfoot();
?>