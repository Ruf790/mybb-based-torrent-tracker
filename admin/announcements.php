<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

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
  
  
  require_once __DIR__ . '/../cache/smilies.php';
  
  
  function show ($aid, $subject, $message, $added, $by, $class)
  {
    global $SITENAME;
    global $BASEURL;
	global $parser;
	global $plugins;
    //$defaulttemplate = ts_template ();
    ob_start ();
    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" />
<head>
<meta http-equiv="Content-Type" content="text/html; charset=';
    echo $charset;
    echo '" />
<link rel="stylesheet" href="';
    echo $BASEURL;
    echo '/include/templates/';
    echo $defaulttemplate;
    echo '/style/style.css" type="text/css" media="screen" />
<title>';
    echo $SITENAME;
    echo ' - Announcement: ';
    echo $subject;
    echo ' - ';
    echo $added;
    echo ' - ';
    echo $by;
    echo '</title>

';
    echo '<s';
    echo 'cript language="JavaScript1.2">

// Drop-in content box- By Dynamic Drive
// For full source code and more DHTML scripts, visit http://www.dynamicdrive.com
// This credit MUST stay intact for use

var ie=document.all
var dom=document.getElementById
var ns4=document.layers
var calunits=document.layers? "" : "px"

var bouncelimit=32 //(must be divisible by 8)
var direction="up"

functi';
    echo 'on initbox(){
if (!dom&&!ie&&!ns4)
return
crossobj=(dom)?document.getElementById("dropin").style : ie? document.all.dropin : document.dropin
scroll_top=(ie)? truebody().scrollTop : window.pageYOffset
crossobj.top=scroll_top-250+calunits
crossobj.visibility=(dom||ie)? "visible" : "show"
dropstart=setInterval("dropin()",50)
}

function dropin(){
scroll_top=(ie)? truebody().scrollTop : win';
    echo 'dow.pageYOffset
if (parseInt(crossobj.top)<100+scroll_top)
crossobj.top=parseInt(crossobj.top)+40+calunits
else{
clearInterval(dropstart)
bouncestart=setInterval("bouncein()",50)
}
}

function bouncein(){
crossobj.top=parseInt(crossobj.top)-bouncelimit+calunits
if (bouncelimit<0)
bouncelimit+=8
bouncelimit=bouncelimit*-1
if (bouncelimit==0){
clearInterval(bouncestart)
}
}

functio';
    echo 'n dismissbox(){
if (window.bouncestart) clearInterval(bouncestart)
crossobj.visibility="hidden"
window.location="';
    echo $BASEURL;
    echo '/admin/announcements.php";
}

function redo(){
bouncelimit=32
direction="up"
initbox()
}

function truebody(){
return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body
}
window.onload=initbox
</script>

</head>

<body>
<!-- announcement start #';
    echo $aid;
    echo ' -->
<div id="dropin" style="position:absolute;visibility:hidden;left:300px;top:100px;width:500px;height:100px;background-color:#F5F5F5">
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tbody><tr><td class="none" style="padding: 2px 0 0 10px; background: red">
<font color=black><b>ANNOUNCEMENT TITLE:</b> ';
    echo $subject;
    echo '</font> -- <b>CREATED ON:</b> ';
    echo $added;
    echo ' -- <b>BY:</b> ';
    echo $by;
    echo '</b> -- <b>TO CLASS:</b> ';
    echo $class;
    echo '</font></td>
<td width="50" align="right" class="none" style="padding: 2px; background: red"><a href="#" onClick="dismissbox();return false"><img src=';
    echo $BASEURL;
    echo '/';
    echo $pic_base_url;
    echo 'close.jpg></a></td></tr>
<tr><td colspan="2" class=none width="650" style="padding: 0 0 0 10px;">
<p>
';
    echo $parser->parse_message($message,$parser_options);
	;
    echo '</p>
</td></tr></tbody></table>
</div>
<!-- announcement end #';
    echo $aid;
    echo '-->
</body>
</html>
';
    ob_end_flush ();
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }


  define ('B_VERSION', 'v.0.6');
  unset ($action);
  unset ($do);
  $action = (isset ($_POST['action']) ? htmlspecialchars ($_POST['action']) : (isset ($_GET['action']) ? htmlspecialchars ($_GET['action']) : 'show'));
  $do = (isset ($_POST['do']) ? htmlspecialchars ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars ($_GET['do']) : ''));
 
 
 

  if ($action == 'show')
  {
    $countrows = number_format (tsrowcount ('id', 'announcements'));
    $page = 0 + $_GET['page'];
    $perpage = 5;
    //list ($pagertop, $pagerbottom, $limit) = pager ($ts_perpage, $countrows, $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=show&');
    stdhead ('Announcements ' . B_VERSION);
    ($res = $db->sql_query ('SELECT * FROM announcements ORDER by added DESC ' . $limit));
    $where = array ('New Announcement' => $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=add');
    echo '
	
	<div class="container mt-3">
	<div class="float-end">
	' . jumpbutton ($where) . '
	</div>
	</div>
	</br>
	</br>
	';
   
   
   echo '
   
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Announcements
	</div>
	 </div>
		</div>';
   
   
   
    
	
	
	echo '
	<div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>ID</th>
        <th>SUBJECT</th>
        <th>MESSAGE</th>
		<th>ADDED</th>
        <th>MIN.CLASS</th>
        <th>ACTION</th>
		
      </tr>
    </thead>';
	
	
	
	
    if (1 <= $db->num_rows ($res))
    {
      require_once INC_PATH . '/functions_mkprettytime.php';
      while ($arr = mysqli_fetch_array ($res))
      {
        
		
		echo '<tr>
		
		<td class="text-center fw-bold">' . $arr['id'] . '</td>
		<td class="text-start">' . htmlspecialchars($arr['subject']) . '</td>
		
		<td align=left>
		<textarea class="form-control form-control-sm border" style="width: 100%; height: 150px;" READONLY>' . $arr['message'] . '</textarea>
		</td>
		
		
		
		<td class="text-center small text-muted">
    ' . my_datee('relative', $arr['added']) . '<br>
    <span class="badge bg-secondary">' . mkprettytime(TIMENOW - $arr['added']) . '</span><br>
    <span class="text-dark">by <strong>' . htmlspecialchars($arr['by']) . '</strong></span>
  </td>
		
		
		
		<td class="text-center">' . get_user_class_name($arr['minclassread']) . '</td>
		
		
		<td align=center><a href=' . $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=edit&id=' . $arr['id'] . '>
		
		<i class="fa-solid fa-pen-to-square fa-xl" style="color: #0658e5;" alt="Edit" title="Edit"></i>
		
		</a><a href=' . $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=delete&id=' . $arr['id'] . '>
		
		<i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;" alt="Delete" title="Delete"></i></a>
		
		</a><a href=' . $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=see&id=' . $arr['id'] . '>
		
		<i class="fa-solid fa-eye fa-xl" style="color: #13a479;" alt="Show" title="Show"></i>
		</a></td></tr>';

      }
    }
    else
    {
      echo '<tr><td colspan=6>Nothing Found..</td></tr>';
    }

    echo $pagerbottom;
    
  }
  else
  {
    if ($action == 'see')
    {
      $id = (isset ($_GET['id']) ? (int)$_GET['id'] : (int)$_POST['id']);
      int_check ($id, true);
      ($res = $db->sql_query ('SELECT * FROM announcements WHERE id = ' . $db->sqlesc ($id)));
      $arr = $db->fetch_array ($res);
      show ($arr['id'], $arr['subject'], $arr['message'], $arr['added'], $arr['by'], get_user_class_name ($arr['minclassread']));
      exit ();
    }
    else
    {
      if ($action == 'add')
      {
        if (($do == 'save' AND empty ($prvp)))
        {
          $added = TIMENOW;
          $subject = htmlspecialchars_uni ($_POST['subject']);
          $message = trim ($_POST['message']);
          $minclassread = $_POST['minclassread'];
          if (((empty ($subject) OR empty ($message)) OR ($minclassread != '-' AND !is_valid_id ($minclassread))))
          {
            redirect ('admin/index.php?act=announcements&action=add', 'Don\'t leave any fields blank..');
          }

          if ($minclassread == '-')
          {
            $query = 'UPDATE users SET announce_read = \'no\' WHERE enabled = \'yes\' AND ustatus = \'confirmed\'';
            $insert = 'INSERT INTO announcements (subject, message, added, minclassread) VALUES (' . $db->sqlesc ($subject) . ', ' . $db->sqlesc ($message) . ', ' . $db->sqlesc ($added) . ', 0)';
          }
          else
          {
            $query = 'UPDATE users SET announce_read = \'no\' WHERE enabled = \'yes\' AND ustatus = \'confirmed\' AND usergroup = ' . $minclassread;
            $insert = 'INSERT INTO announcements (subject, message, added, minclassread) VALUES (' . $db->sqlesc ($subject) . ', ' . $db->sqlesc ($message) . ', ' . $db->sqlesc ($added) . ', ' . $db->sqlesc ($minclassread) . ')';
          }

          ($db->sql_query ($query));
          ($db->sql_query ($insert));
          redirect ('admin/index.php?act=announcements', 'The announcement has been added..');
          exit ();
        }

        $selectbox = _selectbox_ (NULL, 'minclassread', true, 'any usergroup (all)', $_POST['minclassread']);
        stdhead ('Announcements ' . B_VERSION);
        
		
		
		  ?>
			<script>
    const smilies = <?= json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bbcode.css" type="text/css">
  <script src="<?= $BASEURL ?>/scripts/bbcode_tools.js"></script>


<div class="container my-4">


 <form id="commentForm" method="post" name="compose" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) ?>">
 
  <div id="fileIdsContainer"></div>
 
<input type="hidden" name="act" value="announcements">
		<input type="hidden" name="action" value="add">
		<input type="hidden" name="do" value="save">

	
	
	
	<?php echo $selectbox ?>
	</br>
	</br>
	

	   
	   
	   
	 <input type="text" class="form-control form-control border mb-3" name="subject" maxlength="85" placeholder="Announcement Subject" value="" tabindex="1" />  
	 </br>
	   
	   
	
  

    <!-- BBCode Toolbar -->
    <div class="mb-2 d-flex flex-wrap gap-1">

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[b]', '[/b]', 'commentText')"><strong>B</strong></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[i]', '[/i]', 'commentText')"><em>I</em></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[u]', '[/u]', 'commentText')"><u>U</u></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[s]', '[/s]', 'commentText')">S</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[url]', '[/url]', 'commentText')">URL</button>
      
	  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[img]', '[/img]', 'commentText')">IMG</button>
	   
	

      <div class="btn-group position-relative">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn" data-textarea="commentText">🎨 Color</button>
        <div class="color-palette d-none"></div>
      </div>

      <div class="btn-group position-relative">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="smileyBtn">😊</button>
        <div class="smiley-panel d-none border p-2 bg-white shadow-sm position-absolute" id="smileyPanel" style="z-index:1000;"></div>
      </div>

      
	  
	   <!-- Size Picker -->
	<div class="btn-group position-relative">
  <button type="button" class="btn btn-sm btn-outline-secondary size-picker-btn" 
          id="sizeBtn-commentText" data-textarea="commentText">Size</button>
  <div class="size-menu dropdown-menu p-2" id="sizeMenu-commentText"></div>
</div>

	
	
	
	 <!-- Font Picker -->
    <div class="btn-group position-relative">
  <button type="button" class="btn btn-sm btn-outline-secondary font-picker-btn" 
          id="fontBtn-commentText" data-textarea="commentText">Font</button>
  <div class="font-menu dropdown-menu p-2 shadow" id="fontMenu-commentText"></div>
</div>
	  
	  
	  
	  

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[center]', '[/center]', 'commentText')">Center</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[left]', '[/left]', 'commentText')">Left</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[right]', '[/right]', 'commentText')">Right</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]', '[/quote]', 'commentText')">Quote</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]', '[/code]', 'commentText')">Code</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[list]\\n[*]Item 1\\n[*]Item 2\\n[/list]', '', 'commentText')">List</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]', '[/spoiler]', 'commentText')">Spoiler</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[video=youtube]', '[/video]', 'commentText')">YouTube</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreviewBtn">Preview</button>
    </div>

    <!-- Textarea -->
    <div class="mb-3">
     
      
	  <!-- было name="msgtext" -->
<textarea class="form-control" id="commentText" name="message" rows="11"
          placeholder="Write your comment using BBCode..."
          maxlength="500" aria-describedby="charCount" required>
</textarea>
	
	  
      <div id="charCount" class="form-text text-end">0 / 500</div>
    </div>

   
    <div id="fileIdsContainer"></div>
	
	
	
	
	
	<div class="d-flex justify-content-center">
  <input type="submit" class="btn btn-primary" name="submit" value="Save" tabindex="3" accesskey="s">
</div>
	
	
	

    
  </form>
  
 
  
  
  </div>
 <?
		
		
	
		
      }
      else
      {
        if ($action == 'delete')
        {
          $id = (isset ($_GET['id']) ? (int)$_GET['id'] : (int)$_POST['id']);
          int_check ($id, true);
          $sure = (string)$_GET['sure'];
          if (!$sure)
          {
            stderr ('Delete Announcement!, Sanity check: You are about to delete an Announcement. Click <a href=' . $_SERVER['SCRIPT_NAME'] . '?act=announcements&action=delete&id=' . $id . '&sure=yes>here</a> if you are sure. (<a href="' . $_SERVER['SCRIPT_NAME'] . '?act=announcements">cancel</a>)', false);
          }
          else
          {
            ($db->sql_query ('DELETE FROM announcements WHERE id = ' . $db->sqlesc ($id)));
          }

          redirect ('admin/index.php?act=announcements', 'announcement has been deleted..');
        }
        else
        {
          if ($action == 'edit')
          {
            $id = (isset ($_GET['id']) ? (int)$_GET['id'] : (int)$_POST['id']);
            int_check ($id, true);
            if (($do == 'save' AND empty ($prvp)))
            {
              $by = htmlspecialchars_uni ($_POST['by']);
              $subject = htmlspecialchars_uni ($_POST['subject']);
              $message = trim ($_POST['message']);
              $minclassread = $_POST['minclassread'];
              if (((empty ($subject) OR empty ($message)) OR ($minclassread != '-' AND !is_valid_id ($minclassread))))
              {
                redirect ('admin/index.php?act=announcements&action=edit&id=' . $id, 'Don\'t leave any fields blank..');
              }

              ($db->sql_query ('UPDATE announcements SET `by` = ' . $db->sqlesc ($by) . ', subject = ' . $db->sqlesc ($subject) . ', message = ' . $db->sqlesc ($message) . ', minclassread = ' . $db->sqlesc (($minclassread == '-' ? '0' : $minclassread)) . ' WHERE id = ' . $db->sqlesc ($id)) OR sqlerr (__FILE__, 229));
              if ($_POST['reset'] == 'yes')
              {
                if ($minclassread == '-')
                {
                  $query = 'UPDATE users SET announce_read = \'no\' WHERE enabled = \'yes\' AND ustatus = \'confirmed\'';
                }
                else
                {
                  $query = 'UPDATE users SET announce_read = \'no\' WHERE enabled = \'yes\' AND ustatus = \'confirmed\' AND usergroup = ' . $minclassread;
                }

                ($db->sql_query ($query));
              }

              redirect ('index.php?act=announcements', 'Update successfull..');
              exit ();
            }

            ($res = $db->sql_query ('SELECT * FROM announcements WHERE id = ' . $db->sqlesc ($id)));
            if ($db->num_rows ($res) == 0)
            {
              stderr ('Error', 'Invalid Link!');
            }
            else
            {
              $arr = $db->fetch_array ($res);
            }

            $selectbox = '<table border="0" width="100%" cellspacing="0" cellpadding="3">';
            $selectbox .= '<tr><td>Select Usergroup:</td><td>' . _selectbox_ (NULL, 'minclassread', true, 'any usergroup (all)', (empty ($_POST['minclassread']) ? $arr['minclassread'] : $_POST['minclassread'])) . '</td></tr>';
            $selectbox .= '<tr><td>Creator:</td><td>
			
			<label>
			<input type="text" name="by" class="form-control" maxlength="64" value="' . $arr['by'] . '"></label></td></tr>';
            $selectbox .= '<tr><td>Mark Unread:</td><td><input type="checkbox" class="form-check-input" name="reset" value="yes"> check this to mark all users as unread.</td></tr>';
            $selectbox .= '</table>';
            stdhead ('Announcements ' . B_VERSION);
            
			
			
            ?>
			<script>
    const smilies = <?= json_encode($smilies, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/bbcode.css" type="text/css">
  <script src="<?= $BASEURL ?>/scripts/bbcode_tools.js"></script>


<div class="container my-4">


 <form id="commentForm" method="post" name="compose" action="<?= htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) ?>">
 
  <div id="fileIdsContainer"></div>
 
<input type="hidden" name="id" value="<?= (int)$id ?>">

  <input type="hidden" name="act" value="announcements">
	<input type="hidden" name="action" value="edit">
	<input type="hidden" name="do" value="save">

	
	
	
	<?php echo $selectbox ?>
	
	</br>
	
	
	<input class="form-control"
       type="text"
       id="formName"
       name="subject"
       placeholder="Enter form name"
       required minlength="3" maxlength="255"
       value="<?= isset($arr['subject']) ? htmlspecialchars($arr['subject'], ENT_QUOTES) : '' ?>">
	
	</br>
  

    <!-- BBCode Toolbar -->
    <div class="mb-2 d-flex flex-wrap gap-1">

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[b]', '[/b]', 'commentText')"><strong>B</strong></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[i]', '[/i]', 'commentText')"><em>I</em></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[u]', '[/u]', 'commentText')"><u>U</u></button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[s]', '[/s]', 'commentText')">S</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[url]', '[/url]', 'commentText')">URL</button>
      
	  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[img]', '[/img]', 'commentText')">IMG</button>
	   
	

      <div class="btn-group position-relative">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle bbcode-color-btn" data-textarea="commentText">🎨 Color</button>
        <div class="color-palette d-none"></div>
      </div>

      <div class="btn-group position-relative">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="smileyBtn">😊</button>
        <div class="smiley-panel d-none border p-2 bg-white shadow-sm position-absolute" id="smileyPanel" style="z-index:1000;"></div>
      </div>

      
	  
	   <!-- Size Picker -->
	<div class="btn-group position-relative">
  <button type="button" class="btn btn-sm btn-outline-secondary size-picker-btn" 
          id="sizeBtn-commentText" data-textarea="commentText">Size</button>
  <div class="size-menu dropdown-menu p-2" id="sizeMenu-commentText"></div>
</div>

	
	
	
	 <!-- Font Picker -->
    <div class="btn-group position-relative">
  <button type="button" class="btn btn-sm btn-outline-secondary font-picker-btn" 
          id="fontBtn-commentText" data-textarea="commentText">Font</button>
  <div class="font-menu dropdown-menu p-2 shadow" id="fontMenu-commentText"></div>
</div>
	  
	  
	  
	  

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[center]', '[/center]', 'commentText')">Center</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[left]', '[/left]', 'commentText')">Left</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[right]', '[/right]', 'commentText')">Right</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[quote]', '[/quote]', 'commentText')">Quote</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[code]', '[/code]', 'commentText')">Code</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[list]\\n[*]Item 1\\n[*]Item 2\\n[/list]', '', 'commentText')">List</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[spoiler]', '[/spoiler]', 'commentText')">Spoiler</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertBBCode('[video=youtube]', '[/video]', 'commentText')">YouTube</button>

      <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreviewBtn">Preview</button>
    </div>

    <!-- Textarea -->
    <div class="mb-3">
      </br>
      
	  <!-- было name="msgtext" -->
<textarea class="form-control" id="commentText" name="message" rows="11"
          placeholder="Write your comment using BBCode..."
          maxlength="500" aria-describedby="charCount" required>
<?= isset($arr['message']) ? htmlspecialchars($arr['message'], ENT_QUOTES) : '' ?>
</textarea>
	  
	  
      <div id="charCount" class="form-text text-end">0 / 500</div>
    </div>

   
    <div id="fileIdsContainer"></div>
	
	
	<div class="d-flex justify-content-center">
  <input type="submit" class="btn btn-primary" name="submit" value="Save" tabindex="3" accesskey="s">
</div>
	

    
  </form>
  
 
  
  
  </div>
 <?
            
			
			
			//echo $str;
          }
        }
      }
    }
  }

  echo '</table>';
  stdfoot ();
?>
