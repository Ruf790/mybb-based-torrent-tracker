<?php

declare(strict_types=1);


  if (!defined ('STAFF_PANEL'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('FD_VERSION', '2.1');


  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';

  $lang->load('delete');

  // Safely get 'id' from POST or GET
  $id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
  $reason = isset($_POST['reason']) ? $_POST['reason'] : (isset($_GET['reason']) ? $_GET['reason'] : '');
  int_check($id, true); // Optional: depends on how strict int_check is defined

  // $row нужен ДО проверки прав - раньше здесь читалось $row['owner']
  // до того, как он вообще был заполнен запросом.
  $res = $db->sql_query_prepared("SELECT name, owner FROM torrents WHERE id = ?", [$id]);
  $row = $res ? $db->fetch_array($res) : null;

  if (!$row)
  {
      stderr($lang->global['error'], $lang->global['notorrentid']);
  }

  $is_mod = is_mod($usergroups);

  if ($is_mod OR ($usergroups['candeletetorrent'] == '1' AND $CURUSER['id'] == $row['owner']))
  {

    if (empty($reason) || strlen($reason) < 3) 
	{
        stdhead('Fast Delete');
        echo '
        <div class="container mt-5">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white fw-bold">Fast Delete</div>
                <div class="card-body">
                    <form method="post" action="' . $_this_script_ . '&id=' . $id . '">
                    <input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
                        <div class="mb-3">
                            <label for="reason" class="form-label">Please enter delete reason:</label>
                            <input type="text" class="form-control" id="reason" name="reason" value="' . htmlspecialchars_uni($reason) . '" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Fast Delete</button>
                    </form>
                </div>
            </div>
        </div>';
        stdfoot();
        exit();
    }

    // Реальное удаление - только POST с валидным CSRF-токеном.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key'))) {
        http_response_code(403);
        die('Invalid security token or request method. Please use the form above.');
    }

    require_once INC_PATH . '/functions_deletetorrent.php';
    deletetorrent($id);

    $logMessage = 'Fast Deleted! Reason: ' . htmlspecialchars_uni($reason);

    if ($CURUSER['anonymous'] == 'yes' && is_mod($usergroups)) 
	{
        write_log(sprintf($lang->delete['logmsg1'], $id, $row['name'], $logMessage));
    } 
	else 
	{
        write_log(sprintf($lang->delete['logmsg2'], $id, $row['name'], $CURUSER['username'], $logMessage));
    }

    if ($row['owner'] != $CURUSER['id'])
    {
      require_once INC_PATH . '/functions_pm.php';
      
	  $pm = array(
			'subject' => $lang->delete['deleted'],
			'message' => sprintf ($lang->delete['logmsg2'], $id, $row['name'], $CURUSER['username'], $reason),
			'touid' => $row['owner']
	  );
			
	  
	  $pm['sender']['uid'] = -1;
	  send_pm($pm, -1, true);
	   
    }

    kps('-', $kpsupload, (int)$row['owner']);
    

    redirect($BASEURL . '/browse.php', $lang->delete['deleted'], '', true, false, false);
    exit();
  }

  

  
  
?>
