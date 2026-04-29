<?php


define("IN_MYBB", 1);


 
require_once(INC_PATH.'/class_parser.php');
$parser = new postParser;

$parser_options = array(
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

require_once INC_PATH . '/functions_multipage.php';
require_once INC_PATH . '/functions_mkprettytime.php';


// Include our base data handler class
require_once INC_PATH . '/datahandler.php';

if (!defined ('STAFF_PANEL_TSSEv56')) 
{
    exit ('<div class="alert alert-danger" role="alert"><b>Error!</b> Direct initialization of this file is not allowed.</div>');
}

define ('SB_VERSION', '0.7');

$action = htmlspecialchars($_GET['action'] ?? '');
$url = $_this_script_ . '&';



if (($_SERVER['REQUEST_METHOD'] == 'POST' && (!empty($_POST['setanswered']) || !empty($_POST['delete'])))) {
    if (!empty($_POST['setanswered'])) {
        foreach ($_POST['setanswered'] as $set) {
            $db->sql_query('UPDATE staffmessages SET answered = 1, answeredby = ' . $CURUSER['id'] . ' WHERE id = ' . $db->sqlesc($set));
        }
        unset($action);
    }

    if (!empty($_POST['delete'])) {
        foreach ($_POST['delete'] as $del) {
            $db->sql_query('DELETE FROM staffmessages WHERE id = ' . $db->sqlesc($del));
        }
        unset($action);
    }
}

if (!$action) {
    stdhead('Staff PM\'s');
    ($res = $db->sql_query('SELECT count(id) FROM staffmessages'));
    $row = mysqli_fetch_array($res);
    $count = $row[0];
    
    $threadcount = $count;
    $torrentsperpage = $torrentsperpage ?? 20;
    $perpage = $torrentsperpage;
    
    if(isset($mybb->input['page']) && $mybb->input['page'] > 0) {
        $page = $mybb->input['page'];
        $start = ($page-1) * $perpage;
        $pages = $threadcount/ $perpage;
        $pages = ceil($pages);
        if($page > $pages || $page <= 0) {
            $start = 0;
            $page = 1;
        }
    } else {
        $start = 0;
        $page = 1;
    }

    $end = $start + $perpage;
    $lower = $start + 1;
    $upper = $end;

    if($upper > $threadcount) {
        $upper = $threadcount;
    }

    $page_url = str_replace("{fid}", $fid, $url);
    $multipage = multipage($threadcount, $perpage, $page, $page_url);
    
    echo '
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Staff PM\'s</h5>
                    </div>
                    <div class="card-body p-0">';
    
    if ($count == 0) {
        echo '
                        <div class="p-4 text-center">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No messages yet!</p>
                        </div>';
    } else {
        echo '
                        <form method="post" action="' . $url . '">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                            <th>Subject</th>
                                            <th>Sender</th>
                                            <th>Added</th>
                                            <th>Answered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
        
        $res = $db->sql_query('SELECT 
            s.*, u.username, u.usergroup, g.namestyle,
            uu.username as username2, gg.namestyle as namestyle2
            FROM staffmessages s 
            LEFT JOIN users u ON (s.sender=u.id) 
            LEFT JOIN usergroups g ON (u.usergroup=g.gid)
            LEFT JOIN users uu ON (uu.id=s.answeredby)
            LEFT JOIN usergroups gg ON (gg.gid=uu.usergroup)
            ORDER BY s.id desc LIMIT '.$start.', ' . $perpage);
        
        while ($arr = $db->fetch_array($res)) {
            if ($arr['answered']) {
                $answered = '<span class="badge bg-success" data-bs-toggle="tooltip" title="Answered by ' . htmlspecialchars($arr['username2']) . '">Yes</span>';
            } else {
                $answered = '<span class="badge bg-danger">No</span>';
            }

            $pmid = $arr['id'];
            $ads = my_datee('relative', $arr['added']);
            
            echo '
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input message-checkbox" name="setanswered[]" value="' . $arr['id'] . '">
                                                <input type="checkbox" class="form-check-input message-checkbox" name="delete[]" value="' . $arr['id'] . '">
                                            </td>
                                            <td>
                                                <a href="' . $url . 'action=viewpm&pmid=' . $pmid . '" class="text-decoration-none">
                                                    ' . htmlspecialchars_uni($arr['subject']) . '
                                                </a>
                                            </td>
                                            <td>
                                                <a href="' . $BASEURL . '/'.get_profile_link($arr['sender']) . '" class="text-decoration-none">
                                                    ' . format_name($arr['username'], $arr['usergroup']) . '
                                                </a>
                                            </td>
                                            <td>' . $ads . '</td>
                                            <td>' . $answered . '</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="' . $url . 'action=viewpm&pmid=' . $pmid . '" class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="' . $url . 'action=answermessage&receiver=' . $arr['sender'] . '&answeringto=' . $arr['id'] . '" class="btn btn-outline-success" data-bs-toggle="tooltip" title="Reply">
                                                        <i class="fas fa-reply"></i>
                                                    </a>
                                                    <a href="' . $url . 'action=deletestaffmessage&id=' . $arr['id'] . '" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Delete" onclick="return confirm(\'Are you sure you want to delete this message?\')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>';
        }
        
        echo '
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="submit" name="mark_answered" class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-1"></i> Mark as Answered
                                    </button>
                                    <button type="submit" name="delete_selected" class="btn btn-danger btn-sm ms-2">
                                        <i class="fas fa-trash me-1"></i> Delete Selected
                                    </button>
                                </div>
                                ' . $multipage . '
                            </div>
                        </form>';
    }
    
    echo '
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    stdfoot();
}

if ($action == 'viewpm') {
    $pmid = (int)($_GET['pmid'] ?? 0);
    int_check($pmid, true);
    
    $ress4 = $db->sql_query('SELECT s.*, u.username, g.namestyle 
        FROM staffmessages s 
        LEFT JOIN users u ON (s.answeredby=u.id) 
        LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
        WHERE s.id=' . $db->sqlesc($pmid));
    
    $arr4 = $db->fetch_array($ress4);
    $answeredby = $arr4['answeredby'];
    $senderr = $arr4['sender'] ?? '';
    
    if (is_valid_id($arr4['sender'])) {
        $res2 = $db->sql_query('SELECT u.username,g.namestyle FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.id=' . $arr4['sender']);
        $arr2 = $db->fetch_array($res2);
        $sender = '<a href="' . $BASEURL . '/'.get_profile_link($senderr) . '" class="text-decoration-none">' . ($arr2['username'] ? get_user_color($arr2['username'], $arr2['namestyle']) : '[Deleted]') . '</a>';
    } else {
        $sender = 'System';
    }

    $subject = htmlspecialchars_uni($arr4['subject']);
    
    if ($arr4['answered'] == '0') {
        $answered = '<span class="badge bg-danger">No</span>';
        $setanswered = '<a href="' . $url . 'action=setanswered&id=' . $arr4['id'] . '" class="btn btn-success btn-sm ms-2">Mark Answered</a>';
    } else {
        $answered = '<span class="badge bg-success">Yes</span> by <a href="' . $BASEURL . '/'.get_profile_link($answeredby) . '" class="text-decoration-none">' . get_user_color($arr4['username'], $arr4['namestyle']) . '</a> (<a href="' . $url . 'action=viewanswer&pmid=' . $pmid . '">Show Answer</a>)';
        $setanswered = '';
    }

    $iidee = $arr4['id'];
    stdhead('Staff PM\'s');
    
    
	require_once INC_PATH . '/functions_mkprettytime.php';
	
	$elapsed = mkprettytime(TIMENOW - $arr4['added']);
    $addded = my_datee('relative', $arr4['added']);

    echo '
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Message Details</h5>
                        <div>
                            ' . ($arr4['sender'] ? '<a href="' . $url . 'action=answermessage&receiver=' . $arr4['sender'] . '&answeringto=' . $iidee . '" class="btn btn-light btn-sm me-2"><i class="fas fa-reply me-1"></i> Reply</a>' : '') . '
                            <a href="' . $url . 'action=deletestaffmessage&id=' . $arr4['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this message?\')"><i class="fas fa-trash me-1"></i> Delete</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="fw-semibold me-2">From:</span>
                                    ' . $sender . '
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="fw-semibold me-2">Date:</span>
                                    ' . $addded . ' (' . $elapsed . ' ago)
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="fw-semibold me-2">Status:</span>
                                    ' . $answered . '
                                    ' . $setanswered . '
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="fw-semibold me-2">Subject:</span>
                                    <span class="text-dark fw-bold">' . $subject . '</span>
                                </div>
                            </div>
                        </div>
                        <div class="border-top pt-3">
                            <h6 class="mb-3">Message Content:</h6>
                            <div class="bg-light p-4 rounded">
                                ' . $parser->parse_message($arr4['msg'], $parser_options) . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    stdfoot();
}

// Остальной код остается без изменений, но можно аналогично улучшить другие разделы
// (viewanswer, answermessage, deletestaffmessage, setanswered, takecontactanswered)







if ($action == 'viewanswer')
{
    $pmid = 0 + $_GET['pmid'];
    int_check ($pmid, true);
    
	($ress4 = $db->sql_query ('SELECT s.*, u.username, g.namestyle FROM staffmessages s LEFT JOIN users u ON (s.answeredby=u.id) LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE s.id=' . $db->sqlesc ($pmid)));
    
	$arr4 = $db->fetch_array ($ress4);
    $answeredby = $arr4['answeredby'];
    if (is_valid_id ($arr4['sender']))
    {
      ($res2 = $db->sql_query ('SELECT u.username,g.namestyle FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.id=' . $arr4['sender']));
      $arr2 = $db->fetch_array ($res2);
      $sender = '' . '<a href=' . $BASEURL . '/userdetails.php?id=' . $arr4['sender'] . '>' . ($arr2['username'] ? get_user_color ($arr2['username'], $arr2['namestyle']) : '[Deleted]') . '</a>';
    }
    else
    {
      $sender = 'System';
    }

    if ($arr4['subject'] == '')
    {
      $subject = 'No subject';
    }
    else
    {
      $subject = '<a style=\'color: darkred\' href=' . $url . ('' . 'action=viewpm&pmid=' . $pmid . '>') . htmlspecialchars_uni ($arr4['subject']) . '</a>';
    }

    $iidee = $arr4['id'];
    if ($arr4['answer'] == '')
    {
      $answer = '[b][i]This message has not been answered yet or message is empty or marked as answered![/b][/i]';
    }
    else
    {
      $answer = $arr4['answer'];
    }

    stdhead ('Staff PM\'s');
   
 
	
	print '
	 <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold" align="center">
		Viewing Answer
	</div>
	 </div>
		</div>';
	
	
    require_once INC_PATH . '/functions_mkprettytime.php';
    $elapsed = mkprettytime (time () - strtotime ($arr4['added']));
    print '<div class="container mt-3">';
    print '

  <div class="card">
	
	<div class="card-header">
	<a href=' . $BASEURL . '/userdetails.php?id=' . $answeredby . '>' . get_user_color ($arr4['username'], $arr4['namestyle']) . ('' . '</a></b> answered this message sent by ' . $sender).'
	</div>
	
	
	<div class="card-body">
	<b>Subject: ' . $subject . '</b>
	</br>
	<b>Answer:</b>
	'.$parser->parse_message ($answer,$parser_options).'
	
	</div>';
	
    
    print '</div></div>';
    stdfoot ();
}





if ($action == 'answermessage')
{
    $returnto = (isset($_GET['returnto']) ? fix_url($_GET['returnto']) : (isset($_POST['returnto']) ? fix_url($_POST['returnto']) : fix_url($_SERVER['HTTP_REFERER'])));
    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
        if (($_POST['previewpost'] && !empty($_POST['message'])))
        {
            $useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
            $avatar = '<img src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';

            $prvp = '<table border="0" cellspacing="0" cellpadding="4" class="tborder" width="100%">
                <tr>
                    <td class="thead" colspan="2"><strong><h2>' . $lang->global['buttonpreview'] . '</h2></strong></td>
                </tr>
                <tr>
                    <td class="tcat" width="20%" align="center" valign="middle">' . $avatar . '</td>
                    <td class="tcat" width="80%" align="left" valign="top">' . $parser->parse_message($_POST['message'], $parser_options) . '</td>
                </tr>
            </table><br />';
        }

        if (isset($_POST['submit']))
        {
            $receiver = 0 + $_POST['receiver'];
            $answeringto = (int)$_POST['answeringto'];
            int_check($receiver, true);
            $userid = (int)$CURUSER['id'];
            $msg = trim($_POST['message']);
            $message = $db->sqlesc($msg);

            if (!$msg)
            {
                stderr('Error', 'Please enter something!');
            }

            require_once INC_PATH . '/functions_pm.php';

            $pm = array(
                'subject' => $db->escape_string($_POST['subject']),
                'message' => $msg,
                'touid'   => $receiver
            );

            send_pm($pm, $userid, true);

            $db->sql_query('UPDATE staffmessages SET answer='.$message.' WHERE id='.$db->sqlesc($answeringto));
            $db->sql_query('UPDATE staffmessages SET answered=\'1\', answeredby='.$db->sqlesc($userid).' WHERE id='.$db->sqlesc($answeringto));

            header('Location: ' . $url . 'action=viewpm&pmid=' . $answeringto);
            exit();
        }
    }

    $answeringto = 0 + $_GET['answeringto'];
    $receiver = 0 + $_GET['receiver'];
    int_check(array($receiver, $answeringto), true);

    $res = $db->sql_query('SELECT u.username, g.namestyle FROM users u 
                           LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
                           WHERE u.id=' . $db->sqlesc($receiver));
    $user = $db->fetch_array($res);

    if (!$user)
    {
        stderr('Error', 'No user with that ID.');
    }

    $res2 = $db->sql_query('SELECT * FROM staffmessages WHERE id=' . $db->sqlesc($answeringto));
    $array = $db->fetch_array($res2);

    stdhead('Answer to Staff PM', false);
   
    include_once INC_PATH . '/editor.php';

    $str = '
    <div class="container mt-3">
	
	
	<form method="post" name="compose" action="index.php?act=staffbox&action=answermessage&answeringto=' . $answeringto . '&receiver=' . $receiver . '">

	
	
        <input type="hidden" name="returnto" value="' . $returnto . '">
        <input type="hidden" name="receiver" value="' . $receiver . '">
        <input type="hidden" name="answeringto" value="' . $answeringto . '">';

    if (!empty($prvp)) {
        $str .= $prvp;
    }

    $body = '[quote=' . $user['username'] . ']' . htmlspecialchars_uni($array['msg']) . '[/quote]' . $eol;
    $subject_val = ($_POST['subject'] ? $_POST['subject'] : $array['subject']);
    $message_val = (!empty($_POST['message']) ? $_POST['message'] : $body);

    // подключаем новый редактор
    $editor = insert_bbcode_editor($smilies, $BASEURL, 'staffMessage');

    $str .= '
        ' . $editor['toolbar'] . '

        <div class="mb-3">
            <label class="form-label"><strong>Subject:</strong></label>
            <input type="text" name="subject" value="' . htmlspecialchars_uni($subject_val) . '" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label"><strong>Message:</strong></label>
            <textarea name="message" id="staffMessage" rows="12" class="form-control">' . htmlspecialchars_uni($message_val) . '</textarea>
        </div>

        <div class="d-flex justify-content-between">
            <button type="submit" name="previewpost" class="btn btn-secondary">Preview</button>
            <button type="submit" name="submit" class="btn btn-primary">Send</button>
        </div>
    </form>
	</div>

    ' . $editor['modal'];

    echo $str;
    stdfoot();
    exit();
}



if ($action == 'deletestaffmessage')
{
    $id = 0 + $_GET['id'];
    int_check ($id, true);
    $db->sql_query ('DELETE FROM staffmessages WHERE id=' . $db->sqlesc ($id));
    header ('Location: ' . $url);
}

if ($action == 'setanswered')
{
    $id = 0 + $_GET['id'];
    int_check ($id, true);
    ($db->sql_query ('' . 'UPDATE staffmessages SET answered=1, answeredby = ' . $CURUSER['id'] . ' WHERE id = ' . $db->sqlesc ($id)));
    header ('Refresh: 0; url=' . $url . ('' . 'action=viewpm&pmid=' . $id));
}

if ($action == 'takecontactanswered')
{
    foreach ($_POST['setanswered'] as $id)
    {
      if (is_valid_id ($id))
      {
        $db->sql_query ('UPDATE staffmessages SET answered = 1, answeredby = ' . $CURUSER['id'] . ' WHERE id = ' . $db->sqlesc ($id));
        continue;
      }
    }

    header ('Refresh: 0; url=' . $url);
}









// Добавляем JavaScript для улучшения взаимодействия
echo '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Инициализация tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Select all checkbox
    document.getElementById("selectAll").addEventListener("change", function() {
        var checkboxes = document.querySelectorAll(".message-checkbox");
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
        }
    });
});
</script>';

