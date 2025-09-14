<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/

  $templatelist = "multipage,multipage_breadcrumb,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

  $rootpath = './../';
  $thispath = './';
  define ('IN_ADMIN_PANEL', true);
  define ('STAFF_PANEL_TSSEv56', true);
  define ('SKIP_CRON_JOBS', true);
  define ('SKIP_LOCATION_SAVE', true);
  define("IN_MYBB", 1);
  //define("IN_ADMINCP", 1);
  
  
  if (session_status() === PHP_SESSION_NONE) 
  {
    session_start();
  }
  
  
  require_once $rootpath . 'global.php';
  gzip ();



  
  
  maxsysop ();
  if (!is_mod ($usergroups))
  {
    print_no_permission (true);
    exit ();
  }

  require_once $thispath . 'include/adminfunctions.php';



  flash_message();
  
 

  $act = (isset ($_POST['act']) ? htmlspecialchars ($_POST['act']) : (isset ($_GET['act']) ? htmlspecialchars ($_GET['act']) : ''));
  $_this_script_ = htmlspecialchars ($_SERVER['SCRIPT_NAME']) . '?act=' . $act;
  $_this_script_no_act = htmlspecialchars ($_SERVER['SCRIPT_NAME']);
  //check_pincode (2);
  define ('WYSIWYG_EDITOR', true);
  define ('USE_BB_CODE', true);
  define ('USE_SMILIES', true);
  require $thispath . 'wysiwyg/wysiwyg.php';
  if (strtoupper (substr (PHP_OS, 0, 3) == 'WIN'))
  {
    $eol = '
';
  }
  else
  {
    if (strtoupper (substr (PHP_OS, 0, 3) == 'MAC'))
    {
      $eol = '
';
    }
    else
    {
      $eol = '
';
    }
  }

  $act_array = array ('securitycheck', 'managestafftools', 'stafftools');
  if (((!empty ($act) AND !in_array ($act, $act_array)) AND @file_exists ($thispath . $act . '.php')))
  {
    _file_access_check_ ($act);
    include $thispath . $act . '.php';
    echo '
	<style type="text/css">
	#topbar
	{
		position:absolute;
		border: 1px solid black;
		padding: 2px;
		background-color: lightyellow;
		width: 150px;
		visibility: hidden;
		z-index: 100;
	}
	</style>

	<script type="text/javascript">
		/***********************************************
		* Floating Top Bar script- � Dynamic Drive (www.dynamicdrive.com)
		* Sliding routine by Roy Whittle (http://www.javascript-fx.com/)
		* This notice must stay intact for legal use.
		* Visit http://www.dynamicdrive.com/ for full source code
		***********************************************/

		var persistclose=0 //set to 0 or 1. 1 means once the bar is manually closed, it will remain closed for browser session
		var startX = 30 //set x offset of bar in pixels
		var startY = 5 //set y offset of bar in pixels
		var verticalpos="fromtop" //enter "fromtop" or "frombottom"

		function iecompattest(){
		return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body
		}

		function get_cookie(Name) {
		var search = Name + "="
		var returnvalue = "";
		if (document.cookie.length > 0) {
		offset = document.cookie.indexOf(search)
		if (offset != -1) {
		offset += search.length
		end = document.cookie.indexOf(";", offset);
		if (end == -1) end = document.cookie.length;
		returnvalue=unescape(document.cookie.substring(offset, end))
		}
		}
		return returnvalue;
		}

		function closebar(){
		if (persistclose)
		document.cookie="remainclosed=1"
		document.getElementById("topbar").style.visibility="hidden"
		}

		function staticbar(){
		barheight=document.getElementById("topbar").offsetHeight
		var ns = (navigator.appName.indexOf("Netscape") != -1) || window.opera;
		var d = document;
		function ml(id){
		var el=d.getElementById(id);
		if (!persistclose || persistclose && get_cookie("remainclosed")=="")
		el.style.visibility="visible"
		if(d.layers)el.style=el;
		el.sP=function(x,y){this.style.left=x+"px";this.style.top=y+"px";};
		el.x = startX;
		if (verticalpos=="fromtop")
		el.y = startY;
		else{
		el.y = ns ? pageYOffset + innerHeight : iecompattest().scrollTop + iecompattest().clientHeight;
		el.y -= startY;
		}
		return el;
		}
		window.stayTopLeft=function(){
		if (verticalpos=="fromtop"){
		var pY = ns ? pageYOffset : iecompattest().scrollTop;
		ftlObj.y += (pY + startY - ftlObj.y)/8;
		}
		else{
		var pY = ns ? pageYOffset + innerHeight - barheight: iecompattest().scrollTop + iecompattest().clientHeight - barheight;
		ftlObj.y += (pY - startY - ftlObj.y)/8;
		}
		ftlObj.sP(ftlObj.x, ftlObj.y);
		setTimeout("stayTopLeft()", 10);
		}
		ftlObj = ml("topbar");
		stayTopLeft();
		}

		if (window.addEventListener)
		window.addEventListener("load", staticbar, false)
		else if (window.attachEvent)
		window.attachEvent("onload", staticbar)
		else if (document.getElementById)
		window.onload=staticbar
	</script>
	<div id="topbar">
		<a href="" onClick="closebar(); return false"><img src="' . $BASEURL . '/' . $pic_base_url . 'close.gif" border="0" alt="" class="inlineimg" /></a>
		<a href="' . $BASEURL . '/admin/index.php">Return to Staff Panel</a>
	</div>
	';
    exit ();
    return 1;
  }

  if ($act == 'stafftools')
  {
    stdhead ('Staff Tools');
    menu ('stafftools');
    echo '
	<div class="container mt-3">
	<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table border="0" cellpadding="6" cellspacing="0" width="100%">
	<tbody><tr><td colspan="4" align="center">Staff Tools</td></tr>';
    echo '<tr><td width="100%" align="center" colspan="4">Tool Name - Description</td></tr>';
    get_list ();
    echo '</table></td></tr></table>';
    close_menu ();
    stdfoot ();
    exit ();
    return 1;
  }

  if ($act == 'managestafftools')
  {
    _access_check_ ();
    if ($_GET['do'] == 'newtool')
    {
      stdhead ('Make a New Tool');
      menu ('managestafftools');
      //_form_header_open_ ('New Tool');
	  
	   echo '
		   </br>
		    
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		New Tool
		</div>
	 </div>
		</div>';
      
	  
	 
	  
	  echo '
	  
	  <form method="post" action="' . $_this_script_ . '&do=savenewtool">';
      echo '
		
		<div class="container mt-3">
		<tr>
		<td align="right">Tool Name:</td>
		<td>
		
		<label>
		<input type="text" name="name" class="form-control">
		</label>
		
		</td>
		</tr>
		<tr>
		<td align="right">Description:</td>
		<td>
		
		<label>
		<input type="text" name="description" class="form-control">
		</label>
		
		</td>
		</tr>';
      echo '
		</br>
		<tr>
		<td align="right" valign="top">Permission:</td>
		<td>';
      echo '
	  
	  <table align="left" border="0" cellpadding="6" cellspacing="0" width="100%">
		<tr>';
      $count = 0;
      $sql = $db->sql_query ('SELECT gid,title,namestyle FROM usergroups WHERE canstaffpanel = 1 ORDER by disporder');
      while ($group = $db->fetch_array ($sql))
      {
        if (($count AND $count % 3 == 0))
        {
          echo '</tr><tr>' . $eol;
        }

        echo '<td align="right" style="border: 0;">' . get_user_color ($group['title'], $group['namestyle']) . '</td><td align="left" style="border: 0;">
		<input style="vertical-align: middle;"  class="form-check-input" type="checkbox" name="gid[]" value="[' . $group['gid'] . ']" ' . ($group['gid'] == UC_SYSOP ? 'checked="checked"' : '') . '></td>';
        ++$count;
      }

      echo '</tr></table></td></div>';
      echo '<tr>
	  
	  <div class="container mt-3">
	  <div class="card">
	  <div class="card-footer text-center">
	  <td colspan=3 align=center>
	  <input type="submit" value="save this tool" class="btn btn-primary"> 
	  <input type="button" value="check all" onClick="this.value=check(form)" class="btn btn-primary">
	  </form>
	  </td>
	  </tr>
	  </div>
	  </div>
	  </div>
	  ';
      //_form_header_close_ ();
      close_menu ();
      stdfoot ();
      exit ();
    }
    else
    {
      if ($_GET['do'] == 'savenewtool')
      {
        $name = htmlspecialchars_uni ($_POST['name']);
        $description = htmlspecialchars_uni ($_POST['description']);
        $filename = $name . '.php';
        $usergroups = (!empty ($_POST['gid']) ? implode (',', $_POST['gid']) : '');
        if (((empty ($name) OR empty ($description)) OR empty ($usergroups)))
        {
          stderr ('Error! Dont leave any fields blank!');
        }
        else
        {
          if (!file_exists ($thispath . $filename))
          {
            stderr ('Error File <b>' . $thispath . 'admin/' . $filename . '</b> does not exists! Please make sure that you have uploaded it correctly!', false);
          }
        }

		
		
		$insert_staffpanel_tool = array(
			"name" => $db->escape_string($name),
			"description" => $db->escape_string($description),
			"filename" => $db->escape_string($filename),
			"usergroups" => $db->escape_string($usergroups)
			
		);


		$db->insert_query("staffpanel", $insert_staffpanel_tool);
		
        
		
		
		redirect ('admin/index.php?act=' . $name, 'The new tool has been added..');
        exit ();
      }
      else
      {
        if (((isset ($_GET['id']) AND is_valid_id ($_GET['id'])) AND $_GET['do'] == 'delete'))
        {
          $id = intval ($_GET['id']);
          if ($_GET['sure'] != 'yes')
          {
            stderr ('Sanity Check, Are you sure to delete the tool?<br /><br /><strong><a href="' . $_this_script_ . '&do=delete&id=' . $id . '&sure=yes"><font color="red">Yes, I am sure</a></font> <a href="' . $_this_script_ . '">No, Go back!</a>', false);
          }

          $db->sql_query ('DELETE FROM staffpanel WHERE id = ' . $db->escape_string ($id));
          redirect ('admin/index.php?act=managestafftools', 'The tool has been deleted..');
          exit ();
        }
        else
        {
          if (((isset ($_GET['id']) AND is_valid_id ($_GET['id'])) AND $_GET['do'] == 'edit'))
          {
            $id = intval ($_GET['id']);
            $sql = $db->sql_query ('SELECT * FROM staffpanel WHERE id = ' . $db->escape_string ($id));
            if ($db->num_rows ($sql) == 0)
            {
              stderr ('Error! Tool not found in database');
            }

            $tool = $db->fetch_array ($sql);
            stdhead ('Edit Tool');
            menu ('managestafftools');
           // _form_header_open_ ('Edit Tool');
		   
		   
		   
		   echo '
		   
		   </br>
		    
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Edit Tool
		</div>
	 </div>
		</div>
	';
		   
		   
		   
		   
		   
		   
            echo '<form method="post" action="' . $_this_script_ . '&do=savetool&id=' . $id . '">';
            echo '
		
		<div class="container mt-3">
		<tr>
		<td align="right">Tool Name:</td>
		<td>
		
		<label>
		<input type="text" name="name" class="form-control" value="' . $tool['name'] . '"></td>
		</label>
		
		</tr>
		<tr>
		<td align="right">Description:</td>
		<td>
		
		<label>
		<input type="text" name="description" class="form-control" value="' . $tool['description'] . '"></td>
		</label>
		
		</tr>';
            echo '
		<tr>
		</br>
		<td align="right" valign="top">Permission:</td>
		
		
		
		
		<td>';
            echo '<table align="left" border="0" cellpadding="6" cellspacing="0" width="100%">
		<tr>';
            $sql = $db->sql_query ('SELECT gid,title,namestyle FROM usergroups WHERE canstaffpanel = \'1\' ORDER BY disporder');
            $usergroups = explode (',', $tool['usergroups']);
            $count = 0;
            while ($group = $db->fetch_array ($sql))
            {
              if (($count AND $count % 3 == 0))
              {
                echo '</tr><tr>' . $eol;
              }

              echo '<td align="right" style="border:0 ;">' . get_user_color ($group['title'], $group['namestyle']) . '</td>
			  <td align="left" style="border:0 ;">
			  <input style="vertical-align: middle;"  class="form-check-input" type="checkbox" name="gid[]" value="[' . $group['gid'] . ']" ' . (in_array ('[' . $group['gid'] . ']', $usergroups) ? 'checked="checked"' : '') . '></td>';
              ++$count;
            }

            echo '</tr></table></td></div>';
           
			echo '<tr>
	  
	        <div class="container mt-3">
	          <div class="card">
	          <div class="card-footer text-center">
	           <td colspan=3 align=center>
	             <input type="submit" value="save this tool" class="btn btn-primary"> 
	             <input type="button" value="check all" onClick="this.value=check(form)" class="btn btn-primary">
	        </form>
	          </td>
	            </tr>
	          </div>
	          </div>
	        </div>';
			
			
			
            
            close_menu ();
            stdfoot ();
            exit ();
          }
          else
          {
            if (((isset ($_GET['id']) AND is_valid_id ($_GET['id'])) AND $_GET['do'] == 'savetool'))
            {
              $id = intval ($_GET['id']);
              $name = htmlspecialchars_uni ($_POST['name']);
              $description = htmlspecialchars_uni ($_POST['description']);
              $filename = $name . '.php';
              $usergroups = (!empty ($_POST['gid']) ? implode (',', $_POST['gid']) : '');
              if (((empty ($name) OR empty ($description)) OR empty ($usergroups)))
              {
                stderr ('Error!', 'Don\'t leave any fields blank!');
              }
              else
              {
                if (!file_exists ($thispath . $filename))
                {
                  stderr ('Error', 'File <b>' . $thispath . 'admin/' . $filename . '</b> does not exists! Please make sure that you have uploaded it correctly!', false);
                }
              }

             
              
			   $update_staffpanel = array(
				  "name" => $db->escape_string ($name), 
			      "description" => $db->escape_string ($description),
			      "filename" => $db->escape_string ($filename), 
			      "usergroups" => $db->escape_string ($usergroups)
		      );
					
		      $db->update_query("staffpanel", $update_staffpanel, "id='".$id."'");
			  
			  
			  redirect ('index.php?act=managestafftools', 'The tool has been updated..');
              exit ();
            }
          }
        }
      }
    }

    stdhead ('Manage Staff Tools');
    menu ('managestafftools');
    echo '<p align="right"><input type="button" class="hoptobutton" value="Add New Tool" onClick="jumpto(\'' . $_this_script_no_act . '?act=managestafftools&do=newtool\')"></p>';
    _form_header_open_ ('Manage Staff Tools', 6);
    get_list2 ();
    _form_header_close_ ();
    close_menu ();
    echo '<br /><p align="right"><input type="button" class="hoptobutton" value="Add New Tool" onClick="jumpto(\'' . $_this_script_no_act . '?act=managestafftools&do=newtool\')"></p>';
    stdfoot ();
    exit ();
    return 1;
  }

  if ($act == 'securitycheck')
  {
    _access_check_ ();
    stdhead ('Security Console');
    menu ('securitycheck');
    function security_check ($query)
    {
      global $BASEURL;
      $url = $BASEURL . '/' . $query;
      $try = @file_get_contents ($url, 'r');
      return $try;
    }

    function securiry_check_results ($text = '', $risk = 2, $passed = true, $notice = '')
    {
      global $BASEURL;
      global $pic_base_url;
      global $risk_levels;
      echo '<tr><td align="left"><strong>' . $text . '</strong></td><td align="left">' . (!empty ($notice) ? '<font color="red">' . $notice . '</font>' : '<font color="green">Passed. No Error Found!</font>') . '</td><td align="center">' . ($passed ? '<img src="' . $BASEURL . '/' . $pic_base_url . 'input_true.gif" border="0" alt="Passed" title="Passed">' : '<img src="' . $BASEURL . '/' . $pic_base_url . 'input_error.gif" border="0" alt="Failed" title="Failed">') . '</td><td align="center"><img src="' . $BASEURL . '/' . $pic_base_url . 'risk_' . $risk . '.gif" border="0" alt="' . $risk_levels[$risk] . '" title="' . $risk_levels[$risk] . '"></td></tr>';
    }

    require INC_PATH . '/readconfig_announce.php';
    $check__1 = security_check ('config/DATABASE');
    $check__2 = security_check ('include/config.php');
    $check__3 = false;
    $check__4 = $iv == 'yes';
    $check__5 = $securelogin == 'yes';
    $check__6 = $bannedclientdetect == 'yes';
    $check__7 = $maxloginattempts <= 7;
    $check__8 = $privatetrackerpatch == 'yes';
    $check__9 = $disablerightclick == 'yes';
    $check__10 = $vkeyword == 'yes';
    $pattern1 = '[A-Z]+';
    $pattern2 = '[0-9]+';
    $pattern3 = '[a-z]+';
    if (
    preg_match($pattern1, $securehash) &&
    preg_match($pattern2, $securehash) &&
    preg_match($pattern3, $securehash) &&
    strlen($securehash) >= 10
) {
    $check__3 = true;
}

    $risk_levels = array ('1' => 'Low Risk', '2' => 'Medium Risk', '3' => 'High Risk');
    echo '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">';
    echo '<tbody><tr><td><table class="tback" border="0" cellpadding="6" cellspacing="0" width="100%"><tbody><tr><td class="colhead" colspan="4" align="center"><strong>The ' . VERSION . ' Security Console by xam v.0.3</strong></td></tr>';
    echo '<tr class="subheader"><td width="40%" align="left"><strong>Type</strong></td><td width="40%" align="left"><strong>Notice</strong></td><td width="10%" align="center"><strong>Passed</strong></td><td width="10%" align="center"><strong>Risk Level</strong></td></tr>';
    if (strstr ($check__1, 'mysql_pass'))
    {
      securiry_check_results ('Directory Protection (Config Folder)', 3, false, 'Please contact the <a href="http://templateshares.net" target="_blank">TS Team</a> regarding the issue.');
    }
    else
    {
      securiry_check_results ('Directory Protection (Config Folder)', 3);
    }

    if ($check__2 != '<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>')
    {
      securiry_check_results ('Directory Protection (Important Files)', 3, false, 'Please contact the <a href="http://templateshares.net" target="_blank">TS Team</a> regarding the issue.');
    }
    else
    {
      securiry_check_results ('Directory Protection (Important Files)', 3);
    }

    if (!$check__3)
    {
      securiry_check_results ('Secure Hash', 3, false, 'This feature will (attempt to) secure tracker cookies from hackers! We recommend that you choose a word only known by you and it contains upper and lowercase letters and alphanumeric values with atleast 10 chars.');
    }
    else
    {
      securiry_check_results ('Secure Hash', 3);
    }

    if (!$check__10)
    {
      securiry_check_results ('Virtual Keyboard (Against Hackers)', 2, false, 'We recommend that you TURN ON Virtual Keyboard for better security against Keyloggers.');
    }
    else
    {
      securiry_check_results ('Virtual Keyboard (Against Hackers)');
    }

    if (!$check__4)
    {
      securiry_check_results ('Image Verification (Against Hackers)', 2, false, 'We recommend that you TURN ON Image Verification for better security.');
    }
    else
    {
      securiry_check_results ('Image Verification (Against Hackers)');
    }

    if (!$check__5)
    {
      securiry_check_results ('Secure Login (Against Hackers)', 2, false, 'We recommend that you TURN ON Secure Login for better security.');
    }
    else
    {
      securiry_check_results ('Secure Login (Against Hackers)');
    }

    if (!$check__6)
    {
      securiry_check_results ('Banned Client Detection', 2, false, 'Once a banned client is detected by the system, this feature will (attempt to) deny seeding&leeching.');
    }
    else
    {
      securiry_check_results ('Banned Client Detection', 2);
    }

    if (!$check__7)
    {
      securiry_check_results ('Failed Login Attempts', 2, false, 'We recommend that you keep this value below 7');
    }
    else
    {
      securiry_check_results ('Failed Login Attempts', 2);
    }

    if (!$check__8)
    {
      securiry_check_results ('Private Tracker Patch', 2, false, 'We recommend that you TURN ON Private Tracker Patch for better security.');
    }
    else
    {
      securiry_check_results ('Private Tracker Patch', 2);
    }

    if (!$check__9)
    {
      securiry_check_results ('Right Mouse Click', 1, false, 'This feature will (attempt to) disable the right click on your page!');
    }
    else
    {
      securiry_check_results ('Right Mouse Click', 1);
    }

    echo '<tr><td colspan="4"><font face="verdana" size="2" color="darkred"><strong>Please Note:</strong> We do not 100% guarantee that above checks will protect your tracker against hackers. We strongly recommend that you use latest version of following applications:<br />TS Special Edition, Apache, PHP, MySQL, Phpmyadmin.<br /><br />
	Always remember, perfect security on the Internet does not exist.</font></td></tr>';
    echo '</table></table>';
    close_menu ();
    stdfoot ();
    exit ();
    return 1;
  }

  stdhead ('Welcome to Staff Panel ' . AP_VERSION . ' of ' . VERSION);
  menu ('welcome');
  function get_count ($name, $where = '', $extra = '')
  {
    global $db;
	$res = $db->sql_query ('SELECT COUNT(*) as ' . $name . ' FROM ' . $where . ' ' . ($extra ? $extra : ''));
    list ($info[$name]) = mysqli_fetch_array ($res);
    return $info[$name];
  }

  $totalusers = get_count ('totalusers', 'users', 'WHERE ustatus=\'confirmed\'');
  $timecut = TIMENOW - 86400;
  $newuserstoday = get_count ('totalnewusers', 'users', 'WHERE added > ' . $db->escape_string ($timecut));
  $pendingusers = get_count ('pendingusers', 'users', 'WHERE ustatus = \'pending\'');
  $todaycomments = get_count ('todaycomments', 'comments', 'WHERE dateline > ' . $db->escape_string ($timecut));
  $todayvisits = get_count ('todayvisits', 'users', 'WHERE lastactive > ' . $db->escape_string ($timecut));
  $peers = get_count ('totalpeers', 'peers');
  $Seeders = get_count ('seeders', 'peers', 'WHERE seeder = \'yes\'');
  $Leechers = get_count ('seeders', 'peers', 'WHERE seeder = \'no\'');
  $result = $db->sql_query ('SELECT SUM(downloaded) AS totaldl, SUM(uploaded) AS totalul, COUNT(id) AS totaluser FROM users');
  $row = $db->fetch_array ($result);
  $totaldownloaded = mksize ($row['totaldl']);
  $totaluploaded = mksize ($row['totalul']);
  
  
  
  echo '
  <br /><br />
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		'.htmlspecialchars_uni ($CURUSER['username']) . ', welcome to '.$SITENAME.' Staff Panel. We hope that you like this new version which will allow you to manage your tracker easly.<br /><br />
	</div>
	 </div>
		</div>
  
  
  
  
  
	
	<div class="container mt-3">
	<table class="table table-hover">
		<tr>
			<td colspan="10" class="thead">Quick ' . $SITENAME . ' Stats</td>
		</tr>

		<tr>
			<td><div align="right" class="subheader"><b>Total Users</b></div></td>
			<td><div align="center">' . ts_nf ($totalusers) . '</div></td>
			<td><div align="right" class="subheader"><b>New Users Today</b></div></td>
			<td><div align="center">' . ts_nf ($newuserstoday) . '</div></td>
			<td><div align="right" class="subheader"><b>Unconfirmed Users</b></div></td>
			<td><div align="center">' . ts_nf ($pendingusers) . '</div></td>	
			<td><div align="right" class="subheader"><b>Active Users Today</b></div>
			<td><div align="center">' . ts_nf ($todayvisits) . '</div></td>
			<td><div align="right" class="subheader"><b>New Comments Today</b></div></td>
			<td><div align="center">' . ts_nf ($todaycomments) . '</div></td>
		</tr>

		<tr>
			<td><div align="right" class="subheader"><b>Active Peers</b></div>
			<td><div align="center">' . ts_nf ($peers) . '</div></td>
			<td><div align="right" class="subheader"><b>Seeders</b></div>
			<td><div align="center">' . ts_nf ($Seeders) . '</div></td>
			<td><div align="right" class="subheader"><b>Leechers</b></div>
			<td><div align="center">' . ts_nf ($Leechers) . '</div></td>
			<td><div align="right" class="subheader"><b>Total Uploaded</b></div>
			<td><div align="center">' . $totaluploaded . '</div></td>
			<td><div align="right" class="subheader"><b>Total Downloaded</b></div>
			<td><div align="center">' . $totaldownloaded . '</div></td>
		</tr>
	</table>
	</div>
	<br />
	<br /><br />
	';
  close_menu ();
  stdfoot ();
  exit ();
?>
