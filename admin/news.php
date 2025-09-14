<?php
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[AJAX News Panel Update]========*/
/***********************************************/

if (!defined('STAFF_PANEL_TSSEv56')) 
{
    exit("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}

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

stdhead('Manage Site News');
define('IN_EDITOR', true);
require_once INC_PATH . '/functions_html.php';



  
  
  
  
require_once $rootpath . 'cache/smilies.php';

require_once INC_PATH . '/editor.php';
	
$editor = insert_bbcode_editor($smilies, $BASEURL, 'newsMessage');




$str = '<div class="container mt-4">';

$str .= '

' . $editor['toolbar'] . '
<form method="post" id="newsAddForm">
  <div class="mb-3">
    <input type="text" class="form-control" name="subject" placeholder="News Title">
  </div>
  
  <div id="fileIdsContainer"></div>
 
   <div class="mb-3">
  <textarea name="newsMessage" id="newsMessage" class="form-control" rows="6" maxlength="500" aria-describedby="charCount4"placeholder="Write news here..."></textarea>
  <div id="charCount4" class="form-text text-end">0 / 500</div>
  </div>
 

 

  <button type="submit" name="submit" class="btn btn-primary">Submit News Item</button>
</form>


' . $editor['modal'] . '





<hr />';


$res = $db->sql_query('SELECT n.*, u.username, u.usergroup, u.donor FROM news n LEFT JOIN users u ON (u.id=n.userid) ORDER BY n.added DESC');
if ($db->num_rows($res) > 0) 
{
    require_once INC_PATH . '/functions_mkprettytime.php';
    while ($arr = $db->fetch_array($res)) 
	{
        $newsid = $arr['id'];
        $body2 = $arr['body']; // сырой текст
        $body = $parser->parse_message($arr['body'], $parser_options); // готовый к отображению

        $title = htmlspecialchars_uni($arr['title']);
        $userid = (int)$arr['userid'];
        $added = my_datee('relative', $arr['added']);
        $postername = format_name($arr['username'], $arr['usergroup']);
        $by = $postername ? '<a href="'.$BASEURL.'/'.get_profile_link($userid).'"><b>' . $postername . '</b></a>' : 'unknown[' . $userid . ']';

        $str .= "<div class='card mb-3' data-newsid='{$newsid}' data-body='".htmlspecialchars(str_replace(array("\r", "\n"), [' ', ' '], $body2), ENT_QUOTES)."'>
          <div class='card-header'>
            {$added} by {$by} - 
            <a href='#' class='btn btn-sm btn-outline-primary news-edit'>Edit</a> 
            <a href='#' class='btn btn-sm btn-outline-danger news-delete'>Delete</a>
          </div>
          <div class='card-body'>
            <h5 class='card-title'>{$title}</h5>
            <div class='news-body'>{$body}</div>
          </div>
        </div>";
    }
} 
else 
{
    //$str .= stdmsg('Sorry', 'No news available!');


 $str .= '


<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">


<div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0"></h2>
          <p class="mb-0 opacity-75"></p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
         <strong>Sorry, No news available!</strong>
        </div>
      </div>
    </div>';





}
$str .= '</div>';
echo $str;

// Modal для редактирования
?>

<div class="modal fade" id="newsEditModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="newsEditForm">
        <div class="modal-header">
          <h5 class="modal-title">Edit News</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="newsid" id="editNewsId">
          <div class="mb-2">
            <input type="text" class="form-control" id="editTitle" name="title" placeholder="News Title">
          </div>
          <div class="mb-2">
            <textarea class="form-control" id="editBody" name="body" rows="6" placeholder="News Body"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>



<script>





document.getElementById('newsAddForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);
  formData.append('action', 'add');

  fetch('news_ajax.php', {
    method: 'POST',
    body: new URLSearchParams(formData)
  }).then(res => res.json()).then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert(data.error || 'Failed to add news');
    }
  });
});





document.querySelectorAll('.news-delete').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const card = this.closest('.card');
    const id = card.dataset.newsid;
    if (confirm('Are you sure you want to delete this news item?')) {
      fetch('news_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&newsid=' + id
      }).then(res => res.json()).then(data => {
        if (data.success) card.remove();
        else alert(data.error || 'Failed to delete news');
      });
    }
  });
});










document.querySelectorAll('.news-edit').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const card = this.closest('.card');
    const id = card.dataset.newsid;
    const title = card.querySelector('h5').innerText;
    const body = card.dataset.body || '';

    document.getElementById('editNewsId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editBody').value = body;

    new bootstrap.Modal(document.getElementById('newsEditModal')).show();
  });
});






document.getElementById('newsEditForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('action', 'edit');

  fetch('news_ajax.php', {
    method: 'POST',
    body: new URLSearchParams(formData)
  }).then(res => res.json()).then(data => {
    if (data.success) location.reload();
    else alert(data.error || 'Edit failed.');
  });
});
</script>





<?php
stdfoot();
exit();
?>
