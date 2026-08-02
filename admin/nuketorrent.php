<?php

  declare(strict_types=1);


  if (!defined ('STAFF_PANEL'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('NT_VERSION', '0.2');
  if (!is_mod ($usergroups))
  {
    print_no_permission (true);
  }

  $do = (isset ($_POST['do']) ? htmlspecialchars_uni ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars_uni ($_GET['do']) : ''));
  $id = (isset ($_POST['id']) ? intval ($_POST['id']) : (isset ($_GET['id']) ? intval ($_GET['id']) : ''));
  int_check ($id, true);
  if (empty ($_SESSION['returnto']))
  {
    $returnto = fix_url (($_SERVER['HTTP_REFERRER'] ? $_SERVER['HTTP_REFERRER'] : '' . $BASEURL . '/browse.php'));
    $_SESSION['returnto'] = $returnto;
  }

  if (empty ($do))
  {
    $autoreasons = array ('quality' => 'Bad Quality', 'rip' => 'Bad Rip', 'dupe' => 'Duplicate Torrent', 'fake' => 'Fake Content.', 'other' => '');
    foreach ($autoreasons as $value => $reason)
    {
      $options .= '<option value="' . $reason . '">' . $value . '</option>';
    }

    stdhead ('' . 'Nuke Torrent: ' . $id);
    _form_header_open_ ('' . 'Nuke Torrent: ' . $id);
    echo '
	<script type="text/javascript">
	function ChangeReason(sel) {			
		document.forms[0].WhyNuked.value = "" + sel.options[sel.selectedIndex].value;
		document.forms[0].WhyNuked.focus();
	}
	</script>
	';
    echo '
	<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
	<input type="hidden" name="act" value="nuketorrent">
	<input type="hidden" name="do" value="nuke_torrent">
	<input type="hidden" name="id" value="' . $id . '">
	<input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
	<table border="1" cellspacing="0" cellpadding="5" width="100%">
	<tr><td class=rowhead>Reason</td><td><input type="text" name="WhyNuked" size="100" id="specialboxg" value="">
	<select name="selbox" onChange="ChangeReason(this)">' . $options . '</select>
	 <input type="submit" value="Nuke" class=button></td></tr>
	</table>
	</form>';
    _form_header_close_ ();
    stdfoot ();
    return 1;
  }

  // Реальные изменяющие действия — только POST с валидным CSRF-токеном.
  // Раньше $do читался и из $_GET, и из этой проверки не было вовсе —
  // "nuke_torrent"/"unnuke_torrent" можно было запустить одной ссылкой.
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_post_check($mybb->get_input('my_post_key')))
  {
    http_response_code(403);
    stderr('Error', 'Invalid security token or request method. Please use the form.');
  }

  if ($do == 'nuke_torrent')
  {
    $reason = htmlspecialchars_uni ($_POST['WhyNuked']);
    $query = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$id]);
    if (!$query || $db->num_rows ($query) == 0)
    {
      stderr ('Error', 'Invalid Torrent!');
    }
    else
    {
      if ((empty ($reason) OR strlen ($reason) < 6))
      {
        stderr ('Error', 'Reason too short! Min. 6 chars!');
      }
    }

    $db->sql_query_prepared('UPDATE torrents SET isnuked = \'yes\', WhyNuked = ? WHERE id = ?', [$reason, $id]);
    redirect ('' . 'details.php?id=' . $id, 'The torrent has ben nuked!');
    return 1;
  }

  if ($do == 'unnuke_torrent')
  {
    $query = $db->sql_query_prepared('SELECT id FROM torrents WHERE id = ?', [$id]);
    if (!$query || $db->num_rows ($query) == 0)
    {
      stderr ('Error', 'Invalid Torrent!');
    }

    $db->sql_query_prepared('UPDATE torrents SET isnuked = \'no\', WhyNuked = \'\' WHERE id = ?', [$id]);
    redirect ('' . 'details.php?id=' . $id, 'The torrent has ben unnuked!');
    return 1;
  }

  print_no_permission (true);
?>