<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function allowcomments ($torrentid = 0)
  {
    global $is_mod, $db;
    
	$query = $db->simple_select('torrents', 'allowcomments', "id = '{$torrentid}'");
	
    $Result = $db->fetch_array($query);
    return (($Result["allowcomments"] != 'yes' AND !$is_mod) ? false : true);
  }

  
  define("SCRIPTNAME", "comment.php");
  define("IN_MYBB", 1);
  define ('C_VERSION', '1.8.8');
  define("IN_ARCHIVE", true);
  
  require_once 'global.php';
  
  
  require_once 'cache/smilies.php';
  
  
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

  
  gzip ();
  maxsysop ();
  //parked ();
  include_once INC_PATH . '/readconfig.php';
  

  
  
  
  // Include our base data handler class
  require_once INC_PATH . '/datahandler.php';
  
  
  if (!isset($CURUSER)) 
  {
    print_no_permission();
    exit;
  }
  
  
 
  

  
  $query = $db->simple_select('ts_u_perm', 'cancomment', "userid = '{$CURUSER['id']}'");
  
  if (0 < $db->num_rows ($query))
  {
    $commentperm = $db->fetch_array($query);
    if ($commentperm['cancomment'] == '0')
    {
      error_no_permission ();
      exit ();
    }
  }

  $lang->load ('comment');
 
  
 require INC_PATH . '/commenttable.php';
$is_mod = is_mod($usergroups);
$action = isset($_GET['action']) ? htmlspecialchars_uni($_GET['action']) : '';

// Читать тело только при POST и принимать все варианты имени поля
$msgtext = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['message'] ?? $_POST['msgtext'] ?? $_POST['commentText'] ?? '';
    $msgtext = is_string($raw) ? trim($raw) : '';
}
  
  
  
  
   $useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);
   $avatar = '<img src="'.$useravatar['image'].'" alt="" '.$useravatar['width_height'].' />';
  
  


  if ($action == 'close')
  {
    $torrentid = 0 + $_GET['tid'];
    int_check ($torrentid, true);
    $db->sql_query ('' . 'UPDATE torrents SET allowcomments = \'no\' WHERE id = ' . $torrentid);
    redirect ('' . 'details.php?id=' . $torrentid . '&tab=comments');
    exit ();
  }

  if ($action == 'open')
  {
    $torrentid = 0 + $_GET['tid'];
    int_check ($torrentid, true);
    $db->sql_query ('' . 'UPDATE torrents SET allowcomments = \'yes\' WHERE id = ' . $torrentid);
    redirect ('' . 'details.php?id=' . $torrentid . '&tab=comments');
    exit ();
  }










if ($action == 'add') 
{
    $torrentid = 0 + $_GET['tid'];
    int_check($torrentid, true);

    if (!allowcomments($torrentid)) 
    {
        stderr($lang->comment['closed']);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') 
    {
        if (isset($_POST['submit'])) 
        {
            $query = $db->sql_query('SELECT dateline FROM comments WHERE user = ' . $db->escape_string($CURUSER['id']) . ' ORDER BY dateline DESC LIMIT 1');

            if (0 < $db->num_rows($query)) {
                $Result = $db->fetch_array($query);
                $last_comment = $Result["dateline"];
            }

            if ((isset($_POST['ctype']) && $_POST['ctype'] == 'quickcomment')) 
            {
                $rpage = '';
                if ((isset($_POST['page']) && is_valid_id($_POST['page']))) 
                {
                    $rpage = '&page=' . intval($_POST['page']);
                }

                $returnto = $BASEURL . '/details.php?id=' . $torrentid . $rpage;
                $rt = '#startquickcomment';
                $floodmsg = flood_check($lang->comment['floodcomment'], $last_comment, true);
            } 
            else 
            {
                flood_check($lang->comment['floodcomment'], $last_comment);
            }

            $res = $db->simple_select('torrents', 'name, owner', "id = '{$torrentid}'");
            $arr = $db->fetch_array($res);

            if (!empty($floodmsg)) 
            {
                $returnto .= '&cerror=3' . $rt;
                header("Location: $returnto");
                exit();
            }

            if (!$arr) 
            {
                if (isset($returnto)) 
                {
                    $returnto .= '&cerror=1' . $rt;
                    header("Location: $returnto");
                    exit();
                } 
                else 
                {
                    stderr($lang->global['notorrentid']);
                }
            }

            $msgtext = isset($_POST['msgtext']) ? trim($_POST['msgtext']) : '';

            if (!$msgtext) 
            {
                if (isset($returnto)) 
                {
                    $returnto .= '&tab=comments&cerror=2' . $rt;
                    header("Location: $returnto");
                    exit();
                } 
                else 
                {
                    stderr($lang->global['dontleavefieldsblank']);
                }
            }

            $query = $db->sql_query('SELECT id, user, text FROM comments WHERE torrent = ' . $db->escape_string($torrentid) . ' ORDER BY dateline DESC LIMIT 1');
            $lastcommentuserid2 = $db->fetch_array($query);
            $lastcommentuserid = $lastcommentuserid2['user'];

            if ((0 < $db->num_rows($query) && $lastcommentuserid == $CURUSER['id']) && !$is_mod) 
            {
                $text = $lastcommentuserid2['text'];
                $newid = $lastcommentuserid2['id'];

                $update_query['text'] = $text .= "\n[hr]\n" . $msgtext;
                $db->update_query("comments", $update_query, "id='" . $newid . "'");
            } 
            else 
            {
                $comment_insert_data = array(
                    "user" => $db->escape_string($CURUSER['id']),
                    "torrent" => $db->escape_string($torrentid),
                    "dateline" => TIMENOW,
                    "text" => $db->escape_string($msgtext)
                );

                $db->insert_query("comments", $comment_insert_data);
                $newid = $db->insert_id();
				
				// Привязываем загруженные файлы к этому комментарию
                if (!empty($_POST['file_ids'])) 
				{
                   $file_ids = array_map('intval', $_POST['file_ids']); // защита
                   $id_list  = implode(',', $file_ids);

                   if (!empty($id_list)) 
				   {
                     $db->sql_query("
                         UPDATE comment_files 
                         SET comment_id = " . (int)$newid . "
                         WHERE id IN ($id_list)
                        ");
                   }
                }
		





























				
				
				
				

                $update_query222['comments'] = "comments+1";
                $db->update_query("torrents", $update_query222, "id='{$torrentid}'", 1, true);

                $update_query['comms'] = "comms+1";
                $db->update_query("users", $update_query, "id='{$CURUSER['id']}'", 1, true);

                $ras = $db->sql_query('SELECT commentpm FROM users WHERE id = ' . $db->escape_string($arr['owner']));
                $arg = $db->fetch_array($ras);

                if (($arg['commentpm'] == 1 && $CURUSER['id'] != $arr['owner'])) 
                {
                    require_once INC_PATH . '/functions_pm.php';
                    $url2 = get_comment_link($newid, $torrentid) . "#pid{$newid}";

                    $pm = array(
                        'subject' => sprintf($lang->comment['newcommentsub']),
                        'message' => sprintf($lang->comment['newcommenttxt'], '[url=' . $BASEURL . '/' . $url2 . ']' . $arr['name'] . '[/url]'),
                        'touid' => $arr['owner']
                    );

                    $pm['sender']['uid'] = -1;
                    send_pm($pm, -1, true);
                }

                kps('+', $kpscomment, $CURUSER['id']);
            }

            $url = get_comment_link($newid, $torrentid) . "#pid{$newid}";

            // Return JSON success with redirect URL
            header('Content-Type: application/json');
            echo json_encode(['redirect' => $url]);
            exit();
        }
    }

    $res = $db->simple_select('torrents', 'name, owner', "id = '{$torrentid}'");
    $arr = $db->fetch_array($res);
    if (!$arr) 
    {
        stderr($lang->global['notorrentid']);
    }

    
	
	
	stdhead(sprintf($lang->comment['addcomment'], $arr['name']), true, 'supernote');
    
	
	require_once INC_PATH . '/editor.php';
	
	$editor = insert_bbcode_editor($smilies, $BASEURL, 'commentText');
	
	?>

    
	<div class="container mt-4">
  <h3><?= sprintf($lang->comment['addcomment'], htmlspecialchars_uni($arr['name'])); ?></h3>

 <?= $editor['toolbar']?>

  <form id="commentForm" method="post" name="compose" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME']) . '?action=add&tid=' . intval($torrentid); ?>" novalidate>



    <!-- Textarea -->
    <div class="mb-3">
      <label for="commentText" class="form-label"><?= $lang->comment['insertcomment'] ?></label>
      <textarea class="form-control" id="commentText" name="msgtext" rows="6" placeholder="Write your2 comment using BBCode..." maxlength="500" aria-describedby="charCount" required><?= isset($msgtext) ? htmlspecialchars($msgtext) : '' ?></textarea>
      <div id="charCount" class="form-text text-end">0 / 500</div>
    </div>

    <input type="hidden" name="ctype" value="quickcomment">
    <input type="hidden" name="submit" value="1">
    <div id="fileIdsContainer"></div>
	<button type="submit" class="btn btn-primary">Save</button>

    <div id="commentText_preview" class="form-control mt-3 d-none"></div>
  </form>
  
  <?= $editor['modal']?>
  

  
</div>

	
	
	
	
	
<!-- Modal HTML -->
<div id="modalOverlay" style="
    display:none;
    position: fixed; top:0; left:0; width:100vw; height:100vh;
    background: rgba(0,0,0,0.5);
    justify-content: center; align-items: center;
    z-index: 10000;
">
  <div id="modalBox" style="
      background: white; padding: 1.5em; border-radius: 8px;
      max-width: 400px; width: 90%;
      box-shadow: 0 2px 10px rgba(0,0,0,0.3);
      text-align: center;
      position: relative;
  ">
    <div id="modalMessage" style="margin-bottom: 1.5em; font-size: 1.1rem;"></div>
    <button id="modalCloseBtn" style="
      padding: 0.5em 1em;
      border: none;
      background: #007bff;
      color: white;
      border-radius: 4px;
      cursor: pointer;
      font-size: 1rem;
    ">Close</button>
  </div>
</div>

	
	

    <script>
    
	
function showModal(message) {
  const overlay = document.getElementById('modalOverlay');
  const msg = document.getElementById('modalMessage');
  msg.textContent = message;
  overlay.style.display = 'flex';
}

function hideModal() {
  const overlay = document.getElementById('modalOverlay');
  overlay.style.display = 'none';
}

document.getElementById('modalCloseBtn').addEventListener('click', hideModal);
document.getElementById('modalOverlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) hideModal(); // close if clicking outside box
});	
	
	
	
	
  document.getElementById('commentForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);

  fetch(form.action, {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    if (data.error) {
      showModal(data.message || 'Error submitting comment.');
    } else if (data.redirect) {
      window.location.href = data.redirect;
    } else {
      showModal('Unexpected response from server.');
    }
  })
  .catch(error => {
    showModal('Failed to submit comment.');
    console.error('Error:', error);
  });
});
	
	
	
    </script>

    <?php


// Подготовленный SQL для последних 5 комментариев
$sql = "
    SELECT c.id, c.torrent AS torrentid, c.text, c.dateline, c.editreason, c.editedby, c.editedat,
           u.id AS user, u.username, u.usergroup, u.displaygroup, u.usertitle, u.signature,
           u.lastactive, u.lastvisit, u.invisible, u.postnum, u.threadnum, u.added, u.comms,
           u.avatar AS useravatar, u.avatardimensions,
           g.title AS grouptitle, g.namestyle,
           uu.username AS editedbyuname, gg.namestyle AS editbynamestyle
    FROM comments c
    LEFT JOIN users u  ON u.id = c.user
    LEFT JOIN usergroups g  ON g.gid = u.usergroup
    LEFT JOIN users uu ON uu.id = c.editedby
    LEFT JOIN usergroups gg ON gg.gid = uu.usergroup
    WHERE c.torrent = ?
    ORDER BY c.id DESC
    LIMIT 0,5
";

// Выполнение подготовленного запроса - ПРАВИЛЬНЫЙ ФОРМАТ
$res = $db->sql_query_prepared($sql, [$torrentid]);

$allrows = [];
if ($res && $db->num_rows($res->result) > 0) {
    while ($row = $db->fetch_array($res->result)) {
        $allrows[] = $row;
    }
}

// Вывод комментариев
if (count($allrows)) {
    echo '
    <div class="container-md">
        <div class="card border-0 mb-4">
            <div class="card-header rounded-bottom text-19 fw-bold">
                ' . $lang->comment['order'] . '
            </div>
        </div>
    </div>';
    
    commenttable($allrows);
}


    stdfoot();
    exit();
}














if ($action == 'edit') 
{
    $commentid = 0 + $_GET['pid'];
    int_check($commentid, true);
    // Fetch comment and torrent info
    $res = $db->sql_query('SELECT c.*, t.name, t.id as torrentid FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id= ' . $db->escape_string($commentid));
    $arr = mysqli_fetch_assoc($res);
    if (!$arr) 
	{
        stderr($lang->global['notorrentid']);
    }

    if (($arr['user'] != $CURUSER['id'] && !$is_mod)) 
	{
        print_no_permission(true);
    }

    if (allowcomments($arr['torrentid']) == false) 
	{
        stderr($lang->comment['closed']);
    }

    // POST handling
    if ($_SERVER['REQUEST_METHOD'] == 'POST') 
	{
        $returnto = get_comment_link($commentid, $arr['torrentid']) . "#pid{$commentid}";

        if (isset($_POST['submit'])) 
		{
            $msgtext = trim($_POST['msgtext'] ?? '');
            if ($msgtext == '') 
			{
                stderr($lang->global['error'], $lang->global['dontleavefieldsblank']);
            }

            $msgtext = $db->escape_string($msgtext);
            $editedat = TIMENOW;
            $updateedit = true;

        

            // Update comment
            $update_comment = array(
                "text" => $msgtext,
                "editedat" => $editedat,
                "editedby" => $db->escape_string($CURUSER["id"])
            );

            $db->update_query("comments", $update_comment, "id='" . $commentid . "'");
			
			
			if (!empty($_POST['file_ids'])) 
			{
               $file_ids = array_map('intval', $_POST['file_ids']);
               $id_list = implode(',', $file_ids);

               if (!empty($id_list)) 
			   {
                 // Привязываем файлы к новости в базе данных
                  $db->sql_query("
                    UPDATE comment_files 
                    SET comment_id = " . (int)$commentid . " 
                    WHERE id IN ($id_list)
                 ");
               }
           }
			
			
			

            if ($returnto) 
			{
                header('Location: ' . $returnto);
            } 
			else 
			{
                header('Location: ' . $BASEURL . '/');
            }
            exit();
        }
    }

    
	
	
	
	
// Prepare return URL (safe)
$page = (int)($_GET['page'] ?? 0);
$ref  = $_SERVER['HTTP_REFERER'] ?? '';

// если реферер есть — используем его, иначе ссылка на сам комментарий
$base = $ref !== '' ? $ref : get_comment_link($commentid, $arr['torrentid']);

// корректный разделитель параметров
$sep = (strpos($base, '?') === false) ? '?' : '&';

// собираем URL; параметр page добавляем только если он есть
$returnto = $base . $sep . 'viewcomm=' . (int)$commentid . ($page ? '&page='.$page : '') . '#pid' . (int)$commentid;

// (если у тебя есть helper)
$returnto = fix_url($returnto);
	
	
	
	
	
    
    // Output form
    stdhead(sprintf($lang->comment['adit'], $arr['name']));
    
	require_once INC_PATH . '/editor.php';
	
	$editor = insert_bbcode_editor($smilies, $BASEURL, 'commentText');
	
	
	?>
	


<div class="container my-4">
  <h2>Edit Comment for: <strong><?php echo htmlspecialchars_uni($arr['name']); ?></strong></h2>
  
  <?= $editor['toolbar']?>
  
  
  <?php
$page = (int)($_GET['page'] ?? 0);
$actionUrl = htmlspecialchars($_SERVER['SCRIPT_NAME']) . '?action=edit&pid=' . $commentid . ($page ? '&page='.$page : '');
?>
<form method="post" name="compose" action="<?php echo $actionUrl; ?>">
  
  
  
    <input type="hidden" name="returnto" value="<?php echo htmlspecialchars($returnto); ?>">


     <div class="mb-3">
      <label for="commentText" class="form-label">Comment Text</label>
      <textarea class="form-control" id="commentText" name="msgtext" rows="8"><?php echo htmlspecialchars(!empty($prvp) ? $msgtext : $arr['text']); ?></textarea>
    </div>
	
	<div id="fileIdsContainer"></div>

    <button type="submit" name="submit" class="btn btn-primary">Save Changes22</button>
  </form>
  
  <?= $editor['modal']?>
  
  <hr>

  
</div>










     
    </div>

    



    <?php
    stdfoot();
    exit();
}




   
  
  
  
  
  
  	
  
  
  
  
  
  
  
  
  
  
  
  
  else
  {
	  
	  
	  

if ($action === 'edit2' && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    $input = json_decode(file_get_contents('php://input'), true);

    $commentid = (int)($input['pid'] ?? 0);
    $torrentid = (int)($input['tid'] ?? 0);
    $msgtext   = is_string($input['text'] ?? null) ? trim($input['text']) : '';

    if (!$commentid || !$torrentid || $msgtext === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Missing or invalid data.']);
        exit;
    }

    int_check($commentid, true);

    $res = $db->sql_query('
        SELECT c.*, t.name, t.id AS torrentid
        FROM comments AS c
        JOIN torrents AS t ON c.torrent = t.id
        WHERE c.id = ' . $db->escape_string($commentid) . ' LIMIT 1');
    $arr = mysqli_fetch_assoc($res);

    if (!$arr) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $lang->global['notorrentid']]);
        exit;
    }

    if ($arr['user'] != $CURUSER['id'] && !$is_mod) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'You don\'t have permission to edit this comment.']);
        exit;
    }

    if (!allowcomments((int)$arr['torrentid'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $lang->comment['closed']]);
        exit;
    }

    $editedat = TIMENOW;
    $update_comment = [
        "text"     => $db->escape_string($msgtext),
        "editedat" => $editedat,
        "editedby" => (int)$CURUSER["id"]
    ];
    $db->update_query("comments", $update_comment, "id = " . $commentid);

// перечитываем одну запись для рендера
$sql = "
    SELECT
        c.id, c.torrent AS torrentid, c.text, c.dateline, c.editreason, c.editedby, c.editedat,
        u.id AS user, u.username, u.usergroup, u.displaygroup, u.usertitle, u.signature,
        u.lastactive, u.lastvisit, u.invisible, u.avatar AS useravatar, u.avatardimensions,
        g.title AS grouptitle, g.namestyle,
        uu.username AS editedbyuname, gg.namestyle AS editbynamestyle,
        t.name AS torrent_name
    FROM comments c
    LEFT JOIN users       u  ON u.id  = c.user
    LEFT JOIN usergroups  g  ON g.gid = u.usergroup
    LEFT JOIN users       uu ON uu.id = c.editedby
    LEFT JOIN usergroups  gg ON gg.gid= uu.usergroup
    LEFT JOIN torrents    t  ON t.id  = c.torrent
    WHERE c.id = ?
    LIMIT 1
";

// Выполнение подготовленного запроса
$q = $db->sql_query_prepared($sql, [(int)$commentid]);
$row = $db->fetch_array($q->result);



    if (!$row) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Updated row not found']);
        exit;
    }

    // Готовим окружение для commenttable()
    global $torrent, $postcounter;
    $torrent = ['name' => $row['torrent_name'] ?? ($arr['name'] ?? '')];

    // Чтобы commenttable не рисовал "шапку списка"
    $postcounter = 1; // внутри станет 2

    require_once INC_PATH . '/commenttable.php';

    // ⛑️ ГЛАВНОЕ: глушим любой вывод (модалки и т.п.), который делает commenttable
    $level = ob_get_level();
    ob_start();
    $html = commenttable([$row], '', '', false, false, true); // $return = true → вернёт строку
    // выбрасываем всё, что было выведено внутри, кроме $html:
    while (ob_get_level() > $level) { ob_end_clean(); }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'pid' => (int)$commentid, 'html' => $html]);
    exit;
}



	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

	
	
	
	

    
	  

	
    else
    {
 


if ($action == 'delete') 
{
    if (!$is_mod) 
	{
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    // Expect JSON POST data
    $input = json_decode(file_get_contents('php://input'), true);

    $commentid = isset($input['pid']) ? (int)$input['pid'] : 0;
    $torrentid = isset($input['tid']) ? (int)$input['tid'] : 0;

    int_check(array($commentid, $torrentid), true);

    if ($commentid <= 0 || $torrentid <= 0) 
	{
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $res = $db->sql_query('SELECT torrent, user FROM comments WHERE id= ' . $db->escape_string($commentid));
    $arr = $db->fetch_array($res);

    if (!$arr) 
	{
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }

    $torrentid = $arr['torrent'];
    $userpostid = $arr['user'];
	
	
	
	// === Удаляем картинки, привязанные к комментарию ===
    $files = $db->simple_select("comment_files", "*", "comment_id = " . (int)$commentid);
    while ($file = $db->fetch_array($files)) 
	{
        if (is_file($file['file_path'])) 
		{
            @unlink($file['file_path']); // удаляем с диска
        }
    }
    // Удаляем записи из таблицы
    $db->delete_query("comment_files", "comment_id = " . (int)$commentid);
	
	
	

    // Delete the comment
    $db->delete_query("comments", "id='$commentid'");

    if ($torrentid && $db->affected_rows() > 0) 
	{
        $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrentid));
        $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $db->escape_string($userpostid));
    }

    kps('-', $kpscomment, $userpostid);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}








// Mass delete comments
if ($action == 'massdelete') 
{
    if (!$is_mod) 
    {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }

    // Get comment IDs and torrent IDs from POST
    $comment_ids = isset($_POST['comment_ids']) ? explode(',', $_POST['comment_ids']) : [];
    $torrent_ids = isset($_POST['torrent_ids']) ? explode(',', $_POST['torrent_ids']) : [];

    if (empty($comment_ids) || empty($torrent_ids) || count($comment_ids) !== count($torrent_ids)) 
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    $deleted_count = 0;
    $errors = [];

    // Process each comment
    for ($i = 0; $i < count($comment_ids); $i++) 
    {
        $comment_id = (int)$comment_ids[$i];
        $torrent_id = (int)$torrent_ids[$i];

        if ($comment_id <= 0 || $torrent_id <= 0) 
        {
            $errors[] = "Invalid ID pair: comment=$comment_id, torrent=$torrent_id";
            continue;
        }

        // Verify comment exists and belongs to the specified torrent
        $res = $db->sql_query('SELECT id, user FROM comments WHERE id = ' . $db->escape_string($comment_id) . ' AND torrent = ' . $db->escape_string($torrent_id));
        $comment = $db->fetch_array($res);

        if (!$comment) 
        {
            $errors[] = "Comment #$comment_id not found or doesn't belong to torrent #$torrent_id";
            continue;
        }

        $user_id = $comment['user'];

        // Delete associated files
        $files = $db->simple_select("comment_files", "*", "comment_id = " . (int)$comment_id);
        while ($file = $db->fetch_array($files)) 
        {
            if (is_file($file['file_path'])) 
            {
                @unlink($file['file_path']);
            }
        }
        $db->delete_query("comment_files", "comment_id = " . (int)$comment_id);

        // Delete the comment
        $db->delete_query("comments", "id = '$comment_id'");

        if ($db->affected_rows() > 0) 
        {
            $deleted_count++;

            // Update counters
            $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrent_id));
            $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $db->escape_string($user_id));

            // Remove KPS points
            kps('-', $kpscomment, $user_id);
        }
    }

    // Return result
    if ($deleted_count > 0 && empty($errors)) 
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'deleted' => $deleted_count]);
    } 
    else 
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Partial success: ' . implode(', ', $errors),
            'deleted' => $deleted_count
        ]);
    }
    exit;
}










	  
	  
	  
      else
      {
        stderr ($lang->global['error'], $lang->global['invalidaction']);
      }
    }
  }

  exit ();
?>
