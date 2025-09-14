<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  
  $rootpath = './../';
  $thispath = './';
  define ('SETTING_PANEL_TSSEv56', true);
  define ('STAFF_PANEL_TSSEv56', true);
  define ('TEMPLATESHARES', 'OOps');
  define ('IN_SETTING_PANEL', true);
  define ('SKIP_LOCATION_SAVE', true);
  require_once $rootpath . 'global.php';
  gzip ();

 
  maxsysop ();
  if (($usergroups['cansettingspanel'] == '0' OR $usergroups['cansettingspanel'] != '1'))
  {
    print_no_permission (true);
    exit ();
  }

  require_once $thispath . 'include/adminfunctions.php';
  if (!defined ('ADMIN_FUNCTIONS_TSSEv56'))
  {
    @stop_script (@base64_decode ('VGhlIGF1dGhlbnRpY2F0aW9uIGhhcyBiZWVuIGJsb2NrZWQgYmVjYXVzZSBvZiBpbnZhbGlkIGZpbGUgZGV0ZWN0ZWQh'));
  }

  $do = (isset ($_POST['do']) ? htmlspecialchars ($_POST['do']) : (isset ($_GET['do']) ? htmlspecialchars ($_GET['do']) : ''));
  //check_pincode ();
  require_once $thispath . 'include/settingpanelfunctions.php';
  admin_cp_header ('
<script src="' . $BASEURL . '/scripts/collapse.js?v=' . O_SCRIPT_VERSION . '" type="text/javascript"></script>');
  echo '<script src="' . $BASEURL . '/admin/templates/wz_tooltip.js?v=' . O_SCRIPT_VERSION . '" type="text/javascript"></script>';
  
  
  //if (((strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST' AND (0 < count ($_POST['configoption']) OR strtoupper ($_POST['configname']) == 'STAFFTEAM')) AND !empty ($do)))
  //{
   // save_settings ($do);
  //}
  
  
  
  if (
    strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'
    && (
        (isset($_POST['configoption']) && count($_POST['configoption']) > 0)
        || strtoupper($_POST['configname']) == 'STAFFTEAM'
    )
    && !empty($do)
)
{
    save_settings($do);
}
  
  
  
  
  
  

  define ('WYSIWYG_EDITOR', true);
  define ('USE_BB_CODE', true);
  define ('USE_SMILIES', true);
  define ('USE_HTML', false);
  require $thispath . 'wysiwyg/wysiwyg.php';
  if ((preg_match ('#forum(.*)#', $do) OR preg_match ('#forum(.*)#', ($_GET['action'] ? $_GET['action'] : $_POST['action']))))
  {
    table_open (admin_collapse ($do) . ' TS Manage Forums ' . admin_collapse ($do, 2));
    echo '
	<tr>
		<td class="tdclass1">
			<script type="text/javascript">
				function jumpto(url,message)
				{
					if (typeof message != "undefined")
					{
						document.getElementById("jumpto").style.display = "block"; 
					}
				window.location = url;
				};
			</script>
	';
    require $rootpath . '/admin/forumcp.php';
    echo '
		</td>
	</tr>';
    table_close (false);
    admin_cp_footer (true);
    exit ();
  }
  else
  {
    if (preg_match ('#ts_update_cache(.*)#', $do))
    {
      table_open (admin_collapse ($do) . ' TS Update Cache ' . admin_collapse ($do, 2));
      echo '
	<tr>
		<td class="tdclass1">
	';
      require $rootpath . '/admin/ts_update_cache.php';
      echo '
		</td>
	</tr>';
      table_close (false);
      admin_cp_footer (true);
      exit ();
    }
    else
    {
      
     // else
     // {
      
       
          if (preg_match ('#group(.*)#', $do))
          {
            table_open (admin_collapse ($do) . ' TS Manage Usergroups ' . admin_collapse ($do, 2));
            echo '
	<tr>
		<td class="tdclass1">
			<script type="text/javascript">
				function jumpto(url,message)
				{
					if (typeof message != "undefined")
					{
						document.getElementById("jumpto").style.display = "block"; 
					}
				window.location = url;
				};
			</script>
	';
            require $rootpath . '/admin/usergroups.php';
            echo '
		</td>
	</tr>';
            table_close (false);
            admin_cp_footer (true);
            exit ();
          }
          else
          {
            if ($do == 'chmod')
            {
              $str = $str2 = '';
              $addfiles = $addfiles2 = $addfiles3 = array ();
              table_open (admin_collapse ($do) . ' TS Check Folder & File Permissions ' . admin_collapse ($do, 2));
              $folders = array ('admin/backup', 'cache', 'config', 'error_logs', 'include/avatars', 'torrents', 'torrents/images', 'tsf_forums/uploads');
              $files = array ('admin/adminnotes.txt', 'admin/ads.txt', 'admin/quicklinks.txt', 'include/config_announce.php', 'shoutcast/cache.xml', 'shoutcast/lps.dat');
              if ($handle = opendir ($rootpath . '/cache'))
              {
                while (false !== $file = readdir ($handle))
                {
                  if ((($file != '.' AND $file != '..') AND get_extension ($file) == 'php'))
                  {
                    $addfiles[] = 'cache/' . $file;
                    continue;
                  }
                }

                $files = array_merge ($files, $addfiles);
                closedir ($handle);
              }

              if ($handle = opendir ($rootpath . '/config'))
              {
                while (false !== $file = readdir ($handle))
                {
                  if ((($file != '.' AND $file != '..') AND get_extension ($file) == ''))
                  {
                    $addfiles2[] = 'config/' . $file;
                    continue;
                  }
                }

                $files = array_merge ($files, $addfiles2);
                closedir ($handle);
              }


              sort ($folders);
              sort ($files);
              foreach ($folders as $folder)
              {
                $str .= '
				<tr>
					<td align="left">' . $rootpath . $folder . '</td>
					<td align="center"><span style="color: ' . ((is_dir ($rootpath . $folder) AND is_writable ($rootpath . $folder)) ? 'green;">Writable' : 'red;">Not Writable') . '</span></td>
				</tr>
				';
              }

              unset ($folders);
              unset ($folder);
              foreach ($files as $file)
              {
                $str2 .= '
				<tr>
					<td align="left">' . $rootpath . $file . '</td>
					<td align="center"><span style="color: ' . ((is_file ($rootpath . $file) AND is_writable ($rootpath . $file)) ? 'green;">Writable' : 'red;">Not Writable') . '</span></td>
				</tr>
				';
              }

              unset ($files);
              unset ($file);
              echo '
	<tr>
		<td class="none" align="center" valign="top">
			<table width="600" cellpadding="3" cellspacing="0" border="1">
				<tr>
					<td class="subheader" width="70%" align="left">File Name</td>
					<td class="subheader" width="30%" align="center">Is Writable?</td>
				</tr>
				' . $str2 . '
			</table>	
		</td>
		<td class="none" align="center" valign="top">
			<table width="600" cellpadding="3" cellspacing="0" border="1">
				<tr>
					<td class="subheader" width="70%" align="left">Folder Name</td>
					<td class="subheader" width="30%" align="center">Is Writable?</td>
				</tr>
				' . $str . '
			</table>	
		</td>		
	</tr>
	';
              table_close (false);
              admin_cp_footer (true);
              exit ();
            }
            else
            {
              if ($do == 'ts_execute_sql_query')
              {
                table_open (admin_collapse ($do) . ' TS Run SQL Query Tool ' . admin_collapse ($do, 2));
                echo '
	<tr>
		<td class="tdclass1">';
                require $rootpath . '/admin/ts_execute_sql_query.php';
                echo '
		</td>
	</tr>';
                table_close (false);
                admin_cp_footer (true);
                exit ();
              }
             
                
                else
                {
                  if ($do == 'filemanagement')
                  {
                    include 'tssebeditor.php';
                    admin_cp_footer (true);
                    exit ();
                  }
                  else
                  {
                    if ($do == 'image_test')
                    {
                      table_open (admin_collapse ($do) . ' Image Verification Test Script ' . admin_collapse ($do, 2), 2);
                      echo '<tr><td>';
                      include 'image_test.php';
                      echo '</td></tr>';
                      echo '<tr><td>
	GD2<br />
	<img src="' . $BASEURL . '/include/class_tscaptcha.php?width=100&height=30" id="regimage" border="0" alt="" /><br />GD<br /><img src="' . $BASEURL . '/include/class_tscaptcha.php?width=100&height=30&type=2" id="regimage" border="0" alt="" /></td></tr>';
                      table_close (false);
                      admin_cp_footer (true);
                      exit ();
                    }
                    else
                    {
                      if ($do == 'dboptimize')
                      {
                        table_open (admin_collapse ($do) . ' Database Optimization ' . admin_collapse ($do, 2));
                        echo '<tr><td>';
                        include 'optimizetables.php';
                        echo '</td></tr>';
                        table_close (false);
                        admin_cp_footer (true);
                        exit ();
                      }
                      else
                      {
                       
                       
                              
                                  if ($do == 'smilies')
                                  {
                                    $SmilieDir = $rootpath . $pic_base_url . 'smilies';
                                    if ($_GET['action'] == 'new')
                                    {
                                      if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
                                      {
                                        $stitle = htmlspecialchars_uni ($_POST['stitle']);
                                        $stext = htmlspecialchars_uni ($_POST['stext']);
                                        $spath = htmlspecialchars_uni ($_POST['spath']);
                                        $sorder = 0 + $_POST['sorder'];
                                        if (((!$stitle OR !$stext) OR !$spath))
                                        {
                                          $error = 'Please fill required details!';
                                        }
                                        else
                                        {
                                          if (!file_exists ($SmilieDir . '/' . $spath))
                                          {
                                            $error = 'This smilie does not exists!';
                                          }
                                          else
                                          {
                                            $db->sql_query ('INSERT INTO ts_smilies (stitle, stext, spath, sorder) VALUES (' . $db->sqlesc ($stitle) . ', ' . $db->sqlesc ($stext) . ', ' . $db->sqlesc ($spath) . ('' . ', \'' . $sorder . '\')'));
                                            $cache->update_smilies();
                                            $message = 'Smilie has been added!';
                                            $do = 'smilies';
                                            admin_cp_redirect ($do, $message);
                                            exit ();
                                          }
                                        }
                                      }

                                      table_open (admin_collapse ($do) . ' Manage Smilies - Add New Smilie ' . admin_collapse ($do, 2), true, 1);
                                      if ($error)
                                      {
                                        echo '<td class="subheader"><font color="red">' . $error . '</font></td></tr><tr>';
                                      }

                                      echo '
		<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '?do=smilies&action=new">
		<td>
			<fieldset>
				<legend>Title:</legend>
				<input type="text" size="20" value="' . ($stitle ? $stitle : '') . '" name="stitle" />
			</fieldset>
			<fieldset>
				<legend>Text to Replace:</legend>
				<input type="text" size="20" value="' . ($stext ? $stext : '') . '" name="stext" />
			</fieldset>
			<fieldset>
				<legend>Image:</legend>
				<input type="text" size="20" value="' . ($spath ? $spath : '') . '" name="spath" /> <img src="' . $SmilieDir . '/' . ($spath ? $spath : '') . '" alt="' . ($stitle ? $stitle : '') . '" class="inlineimg" border="0">
			</fieldset>
			<fieldset>
				<legend>Display Order:</legend>
				<input type="text" size="5" value="' . ($sorder ? $sorder : 1) . '" name="sorder" />
			</fieldset>
			<input type="submit" value="save" />
		</td>			
		</form>
		';
                                      table_close (false);
                                      admin_cp_footer (true);
                                      exit ();
                                    }

                                    if ($_GET['action'] == 'edit')
                                    {
                                      $sid = intval ($_GET['sid']);
                                      $query = $db->sql_query ('' . 'SELECT stitle, stext, spath, sorder FROM ts_smilies WHERE sid = \'' . $sid . '\'');
                                      if ($db->num_rows ($query) == 0)
                                      {
                                        exit ('Invalid Smilie ID!');
                                      }

                                      $sarray = mysqli_fetch_assoc ($query);
                                      if (strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST')
                                      {
                                        $stitle = htmlspecialchars_uni ($_POST['stitle']);
                                        $stext = htmlspecialchars_uni ($_POST['stext']);
                                        $spath = htmlspecialchars_uni ($_POST['spath']);
                                        $sorder = 0 + $_POST['sorder'];
                                        if (((!$stitle OR !$stext) OR !$spath))
                                        {
                                          $error = 'Please fill required details!';
                                        }
                                        else
                                        {
                                          if (!file_exists ($SmilieDir . '/' . $spath))
                                          {
                                            $error = 'This smilie does not exists!';
                                          }
                                          else
                                          {
                                            $db->sql_query ('UPDATE ts_smilies SET stitle = ' . $db->sqlesc ($stitle) . ', stext = ' . $db->sqlesc ($stext) . ', spath = ' . $db->sqlesc ($spath) . ('' . ', sorder = \'' . $sorder . '\' WHERE sid = \'' . $sid . '\''));
                                            $cache->update_smilies();
                                            $message = 'Smilie has been updated!';
                                            $do = 'smilies';
                                            admin_cp_redirect ($do, $message);
                                            exit ();
                                          }
                                        }
                                      }

                                      table_open (admin_collapse ($do) . ' Manage Smilies - Edit Smilie ' . admin_collapse ($do, 2), true, 1);
                                      if ($error)
                                      {
                                        echo '<td class="subheader"><font color="red">' . $error . '</font></td></tr><tr>';
                                      }

                                      echo '
		<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '?do=smilies&action=edit&sid=' . intval ($_GET['sid']) . '">
		<td>
			<fieldset>
				<legend>Title:</legend>
				<input type="text" size="20" value="' . ($stitle ? $stitle : $sarray['stitle']) . '" name="stitle" />
			</fieldset>
			<fieldset>
				<legend>Text to Replace:</legend>
				<input type="text" size="20" value="' . ($stext ? $stext : $sarray['stext']) . '" name="stext" />
			</fieldset>
			<fieldset>
				<legend>Image:</legend>
				<input type="text" size="20" value="' . ($spath ? $spath : $sarray['spath']) . '" name="spath" /> <img src="' . $SmilieDir . '/' . ($spath ? $spath : $sarray['spath']) . '" alt="' . ($stitle ? $stitle : $sarray['stitle']) . '" class="inlineimg" border="0">
			</fieldset>
			<fieldset>
				<legend>Display Order:</legend>
				<input type="text" size="5" value="' . ($sorder ? $sorder : $sarray['sorder']) . '" name="sorder" />
			</fieldset>
			<input type="submit" value="save" />
		</td>			
		</form>
		';
                                      table_close (false);
                                      admin_cp_footer (true);
                                      exit ();
                                    }

                                    if (($_GET['action'] == 'delete' AND is_valid_id ($_GET['sid'])))
                                    {
                                      $db->sql_query ('DELETE FROM ts_smilies WHERE sid = ' . intval ($_GET['sid']));
                                      $cache->update_smilies();
                                    }

                                    if ($_GET['action'] == 'update_sorder')
                                    {
                                      if (is_array ($_POST['sorder']))
                                      {
                                        foreach ($_POST['sorder'] as $sid => $sorder)
                                        {
                                          if (is_valid_id ($sid))
                                          {
                                            $sorder = 0 + $sorder;
                                            $db->sql_query ('' . 'UPDATE ts_smilies SET sorder = \'' . $sorder . '\' WHERE sid = \'' . $sid . '\'');
                                            continue;
                                          }
                                        }

                                        $cache->update_smilies();
                                      }
                                    }

                                    $query = $db->sql_query ('SELECT sid, stitle, stext, spath, sorder FROM ts_smilies ORDER BY sorder, stitle');
                                    while ($Sa = mysqli_fetch_assoc ($query))
                                    {
                                      $SimilieArray[] = '<b>' . $Sa['stitle'] . '</b><br><br><img src="' . $SmilieDir . '/' . $Sa['spath'] . '" alt="' . $Sa['stitle'] . '" class="inlineimg" border="0"><br> <span class="smallfont">' . $Sa['stext'] . '</span><br> <a href="' . $_SERVER['SCRIPT_NAME'] . '?do=smilies&action=edit&sid=' . $Sa['sid'] . '">[Edit]</a>  <a href="' . $_SERVER['SCRIPT_NAME'] . '?do=smilies&action=delete&sid=' . $Sa['sid'] . '">[Delete]</a> <input type="text" size="2" name="sorder[' . $Sa['sid'] . ']" value="' . $Sa['sorder'] . '" />';
                                    }

                                    table_open (admin_collapse ($do) . ' Manage Smilies ' . admin_collapse ($do, 2), true, 6);
                                    echo '
	<form method="POST" action="' . $_SERVER['SCRIPT_NAME'] . '?do=smilies&action=update_sorder">
	<td class="subheader" colspan="6" align="center"><a href="' . $_SERVER['SCRIPT_NAME'] . '?do=smilies&action=new">[Add a New Smilie]</a> - <a href="' . $_SERVER['SCRIPT_NAME'] . '?do=ts_update_cache">[Update Smilies Cache]</a></td>';
                                    $count = 0;
                                    foreach ($SimilieArray as $showsmilie)
                                    {
                                      if ($count % 6 == 0)
                                      {
                                        echo '</tr><tr>';
                                      }

                                      echo '
		<td>' . $showsmilie . '</td>
		';
                                      ++$count;
                                    }

                                    echo '	
	</tr>
	<tr>
		<td colspan="6" class="subheader" align="center"><input type="submit" value="Save Display Order" /></td>
	</tr>
	</form>';
                                    table_close (false);
                                    admin_cp_footer (true);
                                    exit ();
                                  }
                                  
                                
                              
                            
                          
                        
                      }
                    }
                  }
                }
              //}
            }
          }
        
      //}
    }
  }

  open_form ($do);
  table_open (admin_collapse ($do) . ' ' . strtoupper ($do) . ' Settings ' . admin_collapse ($do, 2), 2);
  switch ($do)
  {
    case 'main':
    {
      
	    print_rows ('Tracker Name', show_helptip ('Enter your Tracker name here.') . '
		<input type="text" size="50" name="configoption[SITENAME]" value="' . $SITENAME . '" class="bginput">');
		
	   print_rows ('BASE URL', show_helptip ('Enter your tracker URL here. ie: http://yourwebsiteurl.com<br />Note: NO a trailing slash (/) at the end!') . '
		<input type="text" size="50" name="configoption[BASEURL]" value="' . $BASEURL . '" class="bginput">');
      
	  print_rows ('Site E-MAIL', show_helptip ('Enter your tracker contact email here. ie: contact@sitename.com') . '
		<input type="text" size="50" name="configoption[SITEEMAIL]" value="' . $SITEEMAIL . '" class="bginput">');
      
	  print_rows ('Report E-MAIL', show_helptip ('Enter your tracker report email here. ie: report@sitename.com') . '
		<input type="text" size="50" name="configoption[REPORTMAIL]" value="' . $REPORTMAIL . '" class="bginput">');
      
	  
	  print_rows ('Contact E-MAIL(s)', show_helptip ('Enter your tracker contact E-mail addresses here. They will be used on Contact Us Page. Saperate multiple E-mails with ,<br /><br />Example: contacus@sitename.com,john@hotmail.com') . '
		<input type="text" size="50" name="configoption[contactemail]" value="' . $contactemail . '" class="bginput">');
	  
	  print_rows ('Site Online?', show_helptip ('From time to time, you may want to turn your tracker off to the public while you perform maintenance, update versions, etc. When you turn your forum off, visitors will receive a message that states that the tracker is temporarily unavailable. Administrators will still be able to see the tracker.') . ' 
		<select class="bginput" name="configoption[SITEONLINE]">
						<option value="yes"' . iif ($SITEONLINE == 'yes', ' selected="selected"') . '>Online</option>
						<option value="no"' . iif ($SITEONLINE == 'no', ' selected="selected"') . '>Offline</option>
					</select>');
      print_rows ('Active Ajax Features?', show_helptip ('AJAX uses javascript and features of recent browsers to allow additional data to be retrieved without doing a page refresh, such as posting with quick reply or editing a thread title inline.<br /><br />These features may cause problems with tracker not running in English. You may use this setting to disable same AJAX features.') . ' 
		<select class="bginput" name="configoption[useajax]">
						<option value="yes"' . iif ($useajax == 'yes', ' selected="selected"') . '>Active</option>
						<option value="no"' . iif ($useajax == 'no', ' selected="selected"') . '>Disable</option>
					</select>');
      print_rows ('Active External Scrape?', show_helptip ('Users can also upload torrents tracked by other public trackers!') . ' 
		<select class="bginput" name="configoption[externalscrape]">
						<option value="yes"' . iif ($externalscrape == 'yes', ' selected="selected"') . '>Active</option>
						<option value="no"' . iif ($externalscrape == 'no', ' selected="selected"') . '>Disable</option>
					</select>');
      print_rows ('Include External Peers?', show_helptip ('Include External Peers to Internal Peers which will update stats automaticly.') . ' 
		<select class="bginput" name="configoption[includeexpeers]">
						<option value="yes"' . iif ($includeexpeers == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($includeexpeers == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Registered Members Only?', show_helptip ('If you set this to \\\'NO\\\', guests can acces to some pages such as index, browse, forums etc..') . ' 
		<select class="bginput" name="configoption[MEMBERSONLY]">
						<option value="yes"' . iif ($MEMBERSONLY == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($MEMBERSONLY == 'no', ' selected="selected"') . '>No</option>
					</select>');
					
	  print_rows ('Aggressive IP Ban?', show_helptip ('Check banned IP\\\'s in Aggressive mode. This might decrease server load.') . '
		<select class="bginput" name="configoption[aggressivecheckip]">
						<option value="yes"' . iif ($aggressivecheckip == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($aggressivecheckip == 'no', ' selected="selected"') . '>No</option>
					</select>');
			
      print_rows ('Aggressive EMAIL Ban?', show_helptip ('Check banned EMAIL\\\'s in Aggressive mode.') . '
		<select class="bginput" name="configoption[aggressivecheckemail]">
						<option value="yes"' . iif ($aggressivecheckemail == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($aggressivecheckemail == 'no', ' selected="selected"') . '>No</option>
					</select>');
					
	   print_rows ('Cut-off Time (mins)', show_helptip ('The number of minutes before a user is marked offline. Recommended: 15') . '
		<input type="text" class="bginput" size="3" name="configoption[wolcutoffmins]" value="' . $wolcutoffmins . '">');			
					
      print_rows ('Max. Login Attempts?', show_helptip ('Disable/Ban IP address who exceed this limit.') . '
		<input type="text" class="bginput" size="3" name="configoption[maxloginattempts]" value="' . $maxloginattempts . '">');
      print_rows ('Secure Hash?', show_helptip ('Please enter a secure word that only known by you. Whenever you change this, all users must re-login.') . '
		<input type="text" class="bginput" size="30" name="configoption[securehash]" value="' . $securehash . '">');
		
		print_rows ('Default Character Set?', show_helptip ('Enter the charset for the language you are exporting.') . '
		<input type="text" size="20" name="configoption[charset]" value="' . $charset . '" class="bginput">');
      print_rows ('Character Set for Ajax Scripts?', show_helptip ('Leave this default (utf-8) if you have any problem on ajax scripts such as shoutbox, poll etc..') . '
		<input type="text" size="20" name="configoption[shoutboxcharset]" value="' . $shoutboxcharset . '" class="bginput">');
      print_rows ('Meta Keywords?', show_helptip ('Type in keywords separated by commas that describe your website. These keywords will help your site be listed in search engines.') . '
		<textarea name="configoption[metakeywords]" style="width: 600px; height: 100px;">' . $metakeywords . '</textarea>');
      print_rows ('Meta Description?', show_helptip ('Description of your website: Helps your website\\\'s position in search engines.') . '
		<textarea name="configoption[metadesc]" style="width: 600px; height: 100px;">' . $metadesc . '</textarea>');
      print_rows ('Tracker Slogan?', show_helptip ('Set your tracker slogan.') . '
		<input type="text" style="width: 600px;" name="configoption[slogan]" value="' . $slogan . '" class="bginput">');
		
		
		
		print_rows ('Active SEO?', show_helptip ('Check banned IP\\\'s in Aggressive mode. This might decrease server load.') . '
		<select class="bginput" name="configoption[seourls]">
						<option value="yes"' . iif ($seourls == 'yes', ' selected="selected"') . '>Active</option>
						<option value="no"' . iif ($seourls == 'no', ' selected="selected"') . '>Disable</option>
					</select>');
					
	 print_rows ('Active ZIP Feature?', show_helptip ('Download .zip instead of .torrent files and put a description file inside the .zip arcive. (e.g.: This file downloaded from X)') . ' 
		<select class="bginput" name="configoption[usezip]">
						<option value="yes"' . iif ($usezip == 'yes', ' selected="selected"') . '>Active</option>
						<option value="no"' . iif ($usezip == 'no', ' selected="selected"') . '>Disable</option>
					</select>');
							
      print_rows ('Save User IP?', show_helptip ('When user login with a different IP, save it into database.') . '
		<select class="bginput" name="configoption[iplog1]">
						<option value="yes"' . iif ($iplog1 == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($iplog1 == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Cracker Tracker Protection Enabled?', show_helptip ('Extra Protection against Hacker Attacks. (URL Protection)') . '
		<select class="bginput" name="configoption[ctracker]">
						<option value="yes"' . iif ($ctracker == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($ctracker == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('GZIP Compression Enabled?', show_helptip ('If your server is running off of apache, then you can turn this setting on to compress your pages potentially making your site much quicker.') . '
		<select class="bginput" name="configoption[gzipcompress]">
						<option value="yes"' . iif ($gzipcompress == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($gzipcompress == 'no', ' selected="selected"') . '>No</option>
					</select>');
      
      print_rows ('Snatch Mod Enabled?', show_helptip ('Turn off this mod for better performance..') . '
		<select class="bginput" name="configoption[snatchmod]">
						<option value="yes"' . iif ($snatchmod == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($snatchmod == 'no', ' selected="selected"') . '>No</option>
					</select>');
					
					
      
      
      print_rows ('Perpage Limit?', show_helptip ('Perpage for all pages which using pager function.') . '
		<input type="text" size="3" name="configoption[ts_perpage]" value="' . $ts_perpage . '" class="bginput">');
      print_rows ('*NIX Server Load Limit?', show_helptip ('TS SE can read the overall load of the server on certain *NIX setups (including Linux). On certain *NIX setups, including Linux, TS SE can read the server\\\'s load as reported by the operating system. TS SE can then use this information turn away users if the server load passes this threshold. Load on *NIX systems is measured in numbers. Usually load should stay below 1, however spikes can occasionally occur, so you should not set this number too low. A setting of 10 to 20 would be a reasonable threshold.<br /><br />If you do not want to use this option, set it to 0.') . '
		<input type="text" size="3" name="configoption[loadlimit]" value="' . $loadlimit . '" class="bginput">');
		
		

		print_rows ('Use Redirectsystem?', show_helptip ('From time to time, you may want to turn your tracker off to the public while you perform maintenance, update versions, etc. When you turn your forum off, visitors will receive a message that states that the tracker is temporarily unavailable. Administrators will still be able to see the tracker.') . ' 
		<select class="bginput" name="configoption[useredirectsystem]">
						<option value="yes"' . iif ($useredirectsystem == 'yes', ' selected="selected"') . '>Online</option>
						<option value="no"' . iif ($useredirectsystem == 'no', ' selected="selected"') . '>Offline</option>
					</select>');
					
					
					
		
		print_rows ('Virtual Keyboard Enabled?', show_helptip ('Enable this feature to prevent Keylogger hacks.') . '
		<select class="bginput" name="configoption[vkeyword]">
						<option value="yes"' . iif ($vkeyword == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($vkeyword == 'no', ' selected="selected"') . '>No</option>
					</select>');
					
					
      print_rows ('Private Tracker Patch Enabled?', show_helptip ('Re-download is necessary for seed after upload a torrent.') . '
		<select class="bginput" name="configoption[privatetrackerpatch]">
						<option value="yes"' . iif ($privatetrackerpatch == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($privatetrackerpatch == 'no', ' selected="selected"') . '>No</option>
					</select>');
     
	 print_rows ('Image Verification Enabled?', show_helptip ('Require users to enter a Visual Verify Code to register/login/recover? (GD OR GD2 Library required) If you see the image on this page, this feature should be work on your server. Click <a href=?do=image_test><font color=red>here</font></a> to check your server.') . '
		<select class="bginput" name="configoption[iv]">
						<option value="yes"' . iif ($iv == 'yes', ' selected="selected"') . '>Yes, use Image Verification (GD/GD2).</option>
						<option value="reCAPTCHA"' . iif ($iv == 'reCAPTCHA', ' selected="selected"') . '>Yes, use reCAPTCHA™</option>
						<option value="no"' . iif ($iv == 'no', ' selected="selected"') . '>No, disabled.</option>
					</select>');
					
      if ($iv == 'reCAPTCHA')
      {
        print_rows ('reCAPTCHA™ Public Key?', show_helptip ('Public key provided to you by <a href=http://recaptcha.net/api/getkey target=_blank><font color=red>reCAPTCHA™</font></a>') . '
			<input type="text" class="bginput" name="configoption[reCAPTCHAPublickey]" size="35" value="' . $reCAPTCHAPublickey . '">
			');
        print_rows ('reCAPTCHA™ Private Key?', show_helptip ('Private key provided to you by <a href=http://recaptcha.net/api/getkey target=_blank><font color=red>reCAPTCHA™</font></a>') . '
			<input type="text" class="bginput" name="configoption[reCAPTCHAPrivatekey]" size="35" value="' . $reCAPTCHAPrivatekey . '">
			');
        print_rows ('reCAPTCHA™ Theme?', show_helptip ('reCAPTCHA™ provides different themes for their CAPTCHA\\\'s. This option allows you to select the theme to use within your website.') . '
			<select class="bginput" name="configoption[reCAPTCHATheme]">
						<option value="red"' . iif ($reCAPTCHATheme == 'red', ' selected="selected"') . '>Red</option>
						<option value="white"' . iif ($reCAPTCHATheme == 'white', ' selected="selected"') . '>White</option>
						<option value="blackglass"' . iif ($reCAPTCHATheme == 'blackglass', ' selected="selected"') . '>Black Glass</option>
					</select>');
        print_rows ('reCAPTCHA™ Language?', show_helptip ('reCAPTCHA™ provides different languages for their CAPTCHA\\\'s. This option allows you to select the default language of reCAPTCHA™') . '
			<select class="bginput" name="configoption[reCAPTCHALanguage]">
						<option value="en"' . iif ($reCAPTCHALanguage == 'en', ' selected="selected"') . '>English</option>
						<option value="nl"' . iif ($reCAPTCHALanguage == 'nl', ' selected="selected"') . '>Dutch</option>
						<option value="fr"' . iif ($reCAPTCHALanguage == 'fr', ' selected="selected"') . '>French</option>
						<option value="de"' . iif ($reCAPTCHALanguage == 'de', ' selected="selected"') . '>German</option>
						<option value="pt"' . iif ($reCAPTCHALanguage == 'pt', ' selected="selected"') . '>Portuguese</option>
						<option value="ru"' . iif ($reCAPTCHALanguage == 'ru', ' selected="selected"') . '>Russian</option>
						<option value="es"' . iif ($reCAPTCHALanguage == 'es', ' selected="selected"') . '>Spanish</option>
						<option value="tr"' . iif ($reCAPTCHALanguage == 'tr', ' selected="selected"') . '>Turkish</option>
					</select>');
      }
	  
	  
	  
	  $timezoneoffset = str_replace ('n', '-', $timezoneoffset);
      $selzonetime = abs (intval ($timezoneoffset) * 10);
      if (my_substrr ($timezoneoffset, 0, 1) == '-')
      {
        $selzoneway = 'n';
      }
      else
      {
        $selzoneway = '';
      }

      $selzone = $selzoneway . $selzonetime;
      $timezoneselect[$selzone] = 'selected="selected"';
      $timenow = my_datee ($timeformat, time (), '-');
      $lang->time_offset_desc = sprintf ($lang->time_offset_desc, $timenow);
      $i = 0 - 12;
      while ($i <= 12)
      {
        if ($i == 0)
        {
          $i2 = '-';
        }
        else
        {
          $i2 = $i;
        }

        $temptime = my_datee ($timeformat, time (), $i2);
        $zone = $i * 10;
        $zone = str_replace ('-', 'n', $zone);
        $timein[$zone] = $temptime;
        ++$i;
      }

      $timein['n35'] = my_datee ($timeformat, time (), 0 - 3.5);
      $timein['35'] = my_datee ($timeformat, time (), 3.5);
      $timein['45'] = my_datee ($timeformat, time (), 4.5);
      $timein['55'] = my_datee ($timeformat, time (), 5.5);
      $timein['575'] = my_datee ($timeformat, time (), 5.75);
      $timein['95'] = my_datee ($timeformat, time (), 9.5);
      $timein['105'] = my_datee ($timeformat, time (), 10.5);
      $str2 .= '<select name="configoption[timezoneoffset]" class="bginput">';
      $str2 .= '<option value="-12" ' . $timezoneselect['n120'] . '>' . $lang->global['GMT'] . ' -12:00 ' . $lang->global['hours'] . ' (' . $timein['n120'] . ')</option>';
      $str2 .= '<option value="-11" ' . $timezoneselect['n110'] . '>' . $lang->global['GMT'] . ' -11:00 ' . $lang->global['hours'] . ' (' . $timein['n110'] . ')</option>';
      $str2 .= '<option value="-10" ' . $timezoneselect['n100'] . '>' . $lang->global['GMT'] . ' -10:00 ' . $lang->global['hours'] . ' (' . $timein['n100'] . ')</option>';
      $str2 .= '<option value="-9" ' . $timezoneselect['n90'] . '>' . $lang->global['GMT'] . ' -9:00 ' . $lang->global['hours'] . ' (' . $timein['n90'] . ')</option>';
      $str2 .= '<option value="-8" ' . $timezoneselect['n80'] . '>' . $lang->global['GMT'] . ' -8:00 ' . $lang->global['hours'] . ' (' . $timein['n80'] . ')</option>';
      $str2 .= '<option value="-7" ' . $timezoneselect['n70'] . '>' . $lang->global['GMT'] . ' -7:00 ' . $lang->global['hours'] . ' (' . $timein['n70'] . ')</option>';
      $str2 .= '<option value="-6" ' . $timezoneselect['n60'] . '>' . $lang->global['GMT'] . ' -6:00 ' . $lang->global['hours'] . ' (' . $timein['n60'] . ')</option>';
      $str2 .= '<option value="-5" ' . $timezoneselect['n50'] . '>' . $lang->global['GMT'] . ' -5:00 ' . $lang->global['hours'] . ' (' . $timein['n50'] . ')</option>';
      $str2 .= '<option value="-4" ' . $timezoneselect['n40'] . '>' . $lang->global['GMT'] . ' -4:00 ' . $lang->global['hours'] . ' (' . $timein['n40'] . ')</option>';
      $str2 .= '<option value="-3.5" ' . $timezoneselect['n35'] . '>' . $lang->global['GMT'] . ' -3:30 ' . $lang->global['hours'] . ' (' . $timein['n35'] . ')</option>';
      $str2 .= '<option value="-3" ' . $timezoneselect['n30'] . '>' . $lang->global['GMT'] . ' -3:00 ' . $lang->global['hours'] . ' (' . $timein['n30'] . ')</option>';
      $str2 .= '<option value="-2" ' . $timezoneselect['n20'] . '>' . $lang->global['GMT'] . ' -2:00 ' . $lang->global['hours'] . ' (' . $timein['n20'] . ')</option>';
      $str2 .= '<option value="-1" ' . $timezoneselect['n10'] . '>' . $lang->global['GMT'] . ' -1:00 ' . $lang->global['hours'] . ' (' . $timein['n10'] . ')</option>';
      $str2 .= '<option value="0" ' . $timezoneselect['0'] . '>' . $lang->global['GMT'] . ' (' . $timein['0'] . ')</option>';
      $str2 .= '<option value="+1" ' . $timezoneselect['10'] . '>' . $lang->global['GMT'] . ' +1:00 ' . $lang->global['hours'] . ' (' . $timein['10'] . ')</option>';
      $str2 .= '<option value="+2" ' . $timezoneselect['20'] . '>' . $lang->global['GMT'] . ' +2:00 ' . $lang->global['hours'] . ' (' . $timein['20'] . ')</option>';
      $str2 .= '<option value="+3" ' . $timezoneselect['30'] . '>' . $lang->global['GMT'] . ' +3:00 ' . $lang->global['hours'] . ' (' . $timein['30'] . ')</option>';
      $str2 .= '<option value="+3.5" ' . $timezoneselect['35'] . '>' . $lang->global['GMT'] . ' +3:30 ' . $lang->global['hours'] . ' (' . $timein['35'] . ')</option>';
      $str2 .= '<option value="+4" ' . $timezoneselect['40'] . '>' . $lang->global['GMT'] . ' +4:00 ' . $lang->global['hours'] . ' (' . $timein['40'] . ')</option>';
      $str2 .= '<option value="+4.5" ' . $timezoneselect['45'] . '>' . $lang->global['GMT'] . ' +4:30 ' . $lang->global['hours'] . ' (' . $timein['45'] . ')</option>';
      $str2 .= '<option value="+5" ' . $timezoneselect['50'] . '>' . $lang->global['GMT'] . ' +5:00 ' . $lang->global['hours'] . ' (' . $timein['50'] . ')</option>';
      $str2 .= '<option value="+5.5" ' . $timezoneselect['55'] . '>' . $lang->global['GMT'] . ' +5:30 ' . $lang->global['hours'] . ' (' . $timein['55'] . ')</option>';
      $str2 .= '<option value="+5.75" ' . $timezoneselect['575'] . '>' . $lang->global['GMT'] . ' +5:45 ' . $lang->global['hours'] . ' (' . $timein['575'] . ')</option>';
      $str2 .= '<option value="+6" ' . $timezoneselect['60'] . '>' . $lang->global['GMT'] . ' +6:00 ' . $lang->global['hours'] . ' (' . $timein['60'] . ')</option>';
      $str2 .= '<option value="+7" ' . $timezoneselect['70'] . '>' . $lang->global['GMT'] . ' +7:00 ' . $lang->global['hours'] . ' (' . $timein['70'] . ')</option>';
      $str2 .= '<option value="+8" ' . $timezoneselect['80'] . '>' . $lang->global['GMT'] . ' +8:00 ' . $lang->global['hours'] . ' (' . $timein['80'] . ')</option>';
      $str2 .= '<option value="+9" ' . $timezoneselect['90'] . '>' . $lang->global['GMT'] . ' +9:00 ' . $lang->global['hours'] . ' (' . $timein['90'] . ')</option>';
      $str2 .= '<option value="+9.5" ' . $timezoneselect['95'] . '>' . $lang->global['GMT'] . ' +9:30 ' . $lang->global['hours'] . ' (' . $timein['95'] . ')</option>';
      $str2 .= '<option value="+10" ' . $timezoneselect['100'] . '>' . $lang->global['GMT'] . ' +10:00 ' . $lang->global['hours'] . ' (' . $timein['100'] . ')</option>';
      $str2 .= '<option value="+10.5" ' . $timezoneselect['105'] . '>' . $lang->global['GMT'] . ' +10:30 ' . $lang->global['hours'] . ' (' . $timein['105'] . ')</option>';
      $str2 .= '<option value="+11" ' . $timezoneselect['110'] . '>' . $lang->global['GMT'] . ' +11:00 ' . $lang->global['hours'] . ' (' . $timein['110'] . ')</option>';
      $str2 .= '<option value="+12" ' . $timezoneselect['120'] . '>' . $lang->global['GMT'] . ' +12:00 ' . $lang->global['hours'] . ' (' . $timein['120'] . ')</option>';
      $str2 .= '</select></td></tr>';
      print_rows ('Date Format?', show_helptip ('The format of the dates used on the tracker/forum. This format uses the PHP date() function. We recommend not changing this unless you know what you are doing.') . '
		<input type="text" size="30" name="configoption[dateformat]" value="' . $dateformat . '" class="bginput">');
      print_rows ('Time Format?', show_helptip ('The format of the dates used on the tracker/forum. This format uses the PHP date() function. We recommend not changing this unless you know what you are doing.') . '
		<input type="text" size="30" name="configoption[timeformat]" value="' . $timeformat . '" class="bginput">');
      print_rows ('Registered Date Format?', show_helptip ('The format used on showthread/userdetails etc.. where it shows when the user registered.') . '
		<input type="text" size="30" name="configoption[regdateformat]" value="' . $regdateformat . '" class="bginput">');
      print_rows ('Default Timezone Offset?', show_helptip ('Here you can set the default timezone offset for guests and members using the default offset.') . '
		' . $str2);
      print_rows ('Day Light Savings Time?', show_helptip ('If times are an hour out above and your timezone is selected correctly, enable day light savings time correction.') . '
		<select class="bginput" name="configoption[dstcorrection]">
						<option value="1"' . iif ($dstcorrection == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($dstcorrection == '0', ' selected="selected"') . '>No</option>
					</select>');
					
		
		
     
     
		
	 print_rows ('Cookie Domain', show_helptip ('The domain which cookies should be set to. This can remain blank. It should also start with a . so it covers all subdomains..') . '
		<input type="text" size="50" name="configoption[cookiedomain]" value="' . $cookiedomain . '" class="bginput">');
		
		print_rows ('Cookie Path', show_helptip ('The path which cookies are set to. We recommend setting this to the full directory path to your forums with a trailing slash.') . '
		<input type="text" size="50" name="configoption[cookiepath]" value="' . $cookiepath . '" class="bginput">');
		
			print_rows ('Cookie Prefix', show_helptip ('A prefix to append to all cookies set by MyBB. This is useful if you wish to install multiple copies of MyBB on the one domain or have other software installed which conflicts with the names of the cookies in MyBB. If not specified, no prefix will be used.') . '
		<input type="text" size="50" name="configoption[cookieprefix]" value="' . $cookieprefix . '" class="bginput">');
		
		
		
		 print_rows ('Secure Cookie Flag', show_helptip ('Cookies can be set with the Secure flag to prevent them from being sent over unencrypted connections. You should enable this setting only if your forum works correctly under HTTPS.') . '
		<select class="bginput" name="configoption[cookiesecureflag]">
						<option value="1"' . iif ($cookiesecureflag == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($cookiesecureflag == '0', ' selected="selected"') . '>No</option>
					</select>');
					
		print_rows ('SameSite Cookie Flag', show_helptip ('Authentication cookies will carry the SameSite flag to prevent CSRF attacks. Keep this disabled if you expect cross-origin POST requests') . '
		<select class="bginput" name="configoption[cookiesamesiteflag]">
						<option value="1"' . iif ($cookiesamesiteflag == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($cookiesamesiteflag == '0', ' selected="selected"') . '>No</option>
					</select>');
		
		
	 print_rows ('Maximum Page Links in Pagination', show_helptip ('Here you can set the number of next and previous page links to show in the pagination for threads or forums with more than one page of results. Set to 0 to disable the limitation.') . '
		<input type="text" size="50" name="configoption[maxmultipagelinks]" value="' . $maxmultipagelinks . '" class="bginput">');
		
		
		print_rows ('Show Jump To Page form in Pagination', show_helptip ('Do you want to show a "jump to page" form in pagination if number of pages exceeds "Maximum Page Links in Pagination"?') . '
		<select class="bginput" name="configoption[jumptopagemultipage]">
						<option value="1"' . iif ($jumptopagemultipage == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($jumptopagemultipage == '0', ' selected="selected"') . '>No</option>
					</select>');
		
		
		print_rows ('Default User Avatar', show_helptip ('If the user does not set a custom avatar this image will be used instead. If you want to use a different image for different themes, please use theme to represent the image directory of each theme') . '
		<input type="text" size="50" name="configoption[useravatar]" value="' . $useravatar . '" class="bginput">');
		
		
		print_rows ('Default Avatar Dimensions', show_helptip ('The dimensions of the default avatar; width and height separated by  or  (e.g. 40|40 or 40x40).') . '
		<input type="text" size="50" name="configoption[useravatardims]" value="' . $useravatardims . '" class="bginput">');
		
		
		
		print_rows ('Maximum Avatar Dimensions', show_helptip ('The maximum dimensions that an avatar can be, width and height separated by  or  If this is left blank then there will be no dimension restriction.') . '
		<input type="text" size="50" name="configoption[maxavatardims]" value="' . $maxavatardims . '" class="bginput">');
		
		
		
		print_rows ('Allow Remote Avatars', show_helptip ('Whether to allow the usage of avatars from remote servers. Having this enabled can expose your servers IP address.') . '
		<select class="bginput" name="configoption[allowremoteavatars]">
						<option value="1"' . iif ($allowremoteavatars == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($allowremoteavatars == '0', ' selected="selected"') . '>No</option>
					</select>');
					
					
		print_rows ('Delayed Thread View Updates', show_helptip ('If this setting is enabled, the number of thread views for threads will be updated in the background by the task schedule system. If not enabled, thread view counters are incremented instantly.') . '
		<select class="bginput" name="configoption[delayedthreadviews]">
						<option value="1"' . iif ($delayedthreadviews == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($delayedthreadviews == '0', ' selected="selected"') . '>No</option>
					</select>');
			
			
		print_rows ('Date/Time Separator', show_helptip ('Where MyBB joins date and time formats this setting is used to separate them (typically a space or comma).') . '
		<input type="text" size="50" name="configoption[datetimesep]" value="' . $datetimesep . '" class="bginput">');
					
		
		
		
      print_rows ('Announce URL', show_helptip ('Enter your announce URL here. ie: http://yourwebsiteurl.com/announce.php') . '
		<input type="text" size="50" name="configoption[announce_urls]" value="' . $announce_urls[0] . '" class="bginput">');
      
	 
      
	  print_rows ('Torrent Directory Path?', show_helptip ('Enter Tracker Torrent Directory Path.<br />Note: NO a trailing slash (/) at the end!') . '
		<input type="text" class="textbox" name="configoption[torrent_dir]" value="' . $torrent_dir . '" class="bginput">');
      print_rows ('Image Directory Path?', show_helptip ('Enter Tracker Image Directory Path.<br />Note: ADD a trailing slash (/) at the end!') . '
		<input type="text" class="textbox" name="configoption[pic_base_url]" value="' . $pic_base_url . '" class="bginput">');
      print_rows ('Category Directory Path?', show_helptip ('Enter Tracker Category Directory Path.<br />Note: NO a trailing slash (/) at the end!') . '
		<input type="text" class="textbox" name="configoption[table_cat]" value="' . $table_cat . '" class="bginput">');
     
      print_rows ('Max. characters Limit?', show_helptip ('Max. characters limit for User Signatures and Info.') . '
		<input type="text" class="textbox" name="configoption[maxchar]" value="' . $maxchar . '" class="bginput">');
      print_rows ('Max. Torrent Size?', show_helptip ('Max. Torrent Limit for Upload. ' . 10 * 1024 * 1024 . ' (10 gb)') . '
		<input type="text" class="textbox" name="configoption[max_torrent_size]" value="' . $max_torrent_size . '" class="bginput">');
      print_submit_rows ();
      break;
    }

    

    case 'smtp':
    {
      
	  
	  
	    print_rows ('Mail handler', show_helptip ('The medium through which MyBB will send outgoing emails.') . '
		<select class="bginput" name="configoption[mail_handler]">
						<option value="mail"' . iif ($mail_handler == 'mail', ' selected="selected"') . '>PHP mail</option>
						<option value="smtp"' . iif ($mail_handler == 'smtp', ' selected="selected"') . '>SMTP mail</option>
						
					</select>');
					
					
		print_rows ('SMTP hostname', show_helptip ('The hostname of the SMTP server through which mail should be sent.Only required if SMTP Mail is selected as the Mail Handler.') . '
		<input type="text" class="bginput" size="25" name="configoption[smtp_host]" value="' . $smtp_host . '">');	


       print_rows ('SMTP port', show_helptip ('The port number of the SMTP server through which mail should be sent.Only required if SMTP Mail is selected as the Mail Handler') . '
		<input type="text" class="bginput" size="25" name="configoption[smtp_port]" value="' . $smtp_port . '">');


      	print_rows ('SMTP username', show_helptip ('The username used to authenticate with the SMTP server.Only required if SMTP Mail is selected as the Mail Handler, and the SMTP server requires authentication.') . '
		<input type="text" class="bginput" size="25" name="configoption[smtp_user]" value="' . $smtp_user . '">');	


       	print_rows ('SMTP password', show_helptip ('The corresponding password used to authenticate with the SMTP server.Only required if SMTP Mail is selected as the Mail Handler, and the SMTP server requires authentication.') . '
		<input type="text" class="bginput" size="25" name="configoption[smtp_pass]" value="' . $smtp_pass . '">');					
		
		
		
		 print_rows ('Mail Logging', show_helptip ('This setting allows you to set how to log outgoing emails sent via the Send Thread to a Friend feature. In some countries it is illegal to log all content.') . '
		<select class="bginput" name="configoption[mail_logging]">
						<option value="0"' . iif ($mail_logging == '0', ' selected="selected"') . '>Disable email logging</option>
						<option value="1"' . iif ($mail_logging == '1', ' selected="selected"') . '>Log emails without content</option>
						<option value="2"' . iif ($mail_logging == '2', ' selected="selected"') . '>Log everything</option>
					</select>');
		
		
		
		
		
	  
	  
	  
	  print_rows ('SMTP Encryption Mode', show_helptip ('Select the encryption required to communicate with the SMTP server.<br />Only required if SMTP Mail is selected as the Mail Handler..') . '
		<select class="bginput" name="configoption[secure_smtp]">
						<option value="0"' . iif ($secure_smtp == '0', ' selected="selected"') . '>No encryption</option>
						<option value="1"' . iif ($secure_smtp == '1', ' selected="selected"') . '>SSL encryption</option>
						<option value="2"' . iif ($secure_smtp == '2', ' selected="selected"') . '>TLS encryption</option>
					</select>');
					
					
		
		print_rows ('Add message ID in mail headers', show_helptip ('Disabling this option on some shared hosts resolves issues with forum emails being marked as spam.') . '
		<select class="bginput" name="configoption[mail_message_id]">
						<option value="1"' . iif ($mail_message_id == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($mail_message_id == '0', ' selected="selected"') . '>No</option>
					</select>');
					
					
					
		print_rows ('Messages to send from the mail queue', show_helptip ('The number of messages to send from the mail queue every time the Send Mail Queue task is ran.') . '
		<input type="text" class="bginput" size="5" name="configoption[mail_queue_limit]" value="' . $mail_queue_limit . '">');			
					
					
					
					
					
					
     
     
      print_submit_rows ();
      break;
	  
	  
	  
	  
	  
    }


    case 'cronjobs':
    {
      if ((((strtoupper ($_SERVER['REQUEST_METHOD']) == 'POST' AND $_GET['act'] == 'save') AND is_valid_id ($_GET['cronid'])) AND $cronid = intval ($_GET['cronid'])))
      {
        $IsNew = ($_POST['save_new'] ? true : false);
        $mosecs = 31 * 24 * 60 * 60;
        $wsecs = 7 * 24 * 60 * 60;
        $dsecs = 24 * 60 * 60;
        $hsecs = 60 * 60;
        $msecs = 60;
        $minutes = 0;
        if (0 < $_POST['months'])
        {
          $minutes += $mosecs * $_POST['months'];
        }

        if (0 < $_POST['weeks'])
        {
          $minutes += $wsecs * $_POST['weeks'];
        }

        if (0 < $_POST['days'])
        {
          $minutes += $dsecs * $_POST['days'];
        }

        if (0 < $_POST['hours'])
        {
          $minutes += $hsecs * $_POST['hours'];
        }

        if (0 < $_POST['minutes'])
        {
          $minutes += $msecs * $_POST['minutes'];
        }

        $Filename = trim ($_POST['filename']);
        $Description = trim ($_POST['description']);
        $Active = iif ($_POST['active'] == 'yes', '1', '0');
        $Loglevel = iif ($_POST['loglevel'] == 'yes', '1', '0');
        if (!$IsNew)
        {
          $db->sql_query ('UPDATE ts_cron SET filename = ' . $db->sqlesc ($Filename) . ', description = ' . $db->sqlesc ($Description) . ('' . ', minutes = \'' . $minutes . '\', active = \'' . $Active . '\', loglevel = \'' . $Loglevel . '\' WHERE cronid = \'' . $cronid . '\''));
        }
        else
        {
          $db->sql_query ('' . 'INSERT INTO ts_cron (minutes, filename, description, active, loglevel) VALUES (\'' . $minutes . '\', ' . $db->sqlesc ($Filename) . ', ' . $db->sqlesc ($Description) . ('' . ', \'' . $Active . '\', \'' . $Loglevel . '\')'));
        }

        unset ($_GET['act']);
        unset ($_POST);
        unset ($_GET);
        unset ($_GET['cronid']);
        unset ($minutes);
        unset ($Filename);
        unset ($Description);
        unset ($Active);
        unset ($Loglevel);
      }

      require_once INC_PATH . '/functions_mkprettytime.php';
      $showcrons = '';
      if ((((isset ($_GET['act']) AND $_GET['act'] == 'run') AND is_valid_id ($_GET['cronid'])) AND $cronid = intval ($_GET['cronid'])))
      {
        $db->sql_query ('' . 'UPDATE ts_cron SET nextrun=\'0\' WHERE cronid = \'' . $cronid . '\'');
        echo '<img src="' . $BASEURL . '/ts_cron.php?rand=' . TIMENOW . '" alt="" title="" width="1" height="1" border="0" />';
      }
      else
      {
        if ((((isset ($_GET['act']) AND $_GET['act'] == 'delete') AND is_valid_id ($_GET['cronid'])) AND $cronid = intval ($_GET['cronid'])))
        {
          $db->sql_query ('' . 'DELETE FROM ts_cron WHERE cronid = \'' . $cronid . '\'');
        }
        else
        {
          if ((((isset ($_GET['act']) AND ($_GET['act'] == 'disable' OR $_GET['act'] == 'active')) AND is_valid_id ($_GET['cronid'])) AND $cronid = intval ($_GET['cronid'])))
          {
            $db->sql_query ('' . 'UPDATE ts_cron SET active = IF(active=1, 0, 1) WHERE cronid = \'' . $cronid . '\'');
          }
          else
          {
            if ((((isset ($_GET['act']) AND ($_GET['act'] == 'edit' OR $_GET['act'] == 'save_new')) AND is_valid_id ($_GET['cronid'])) AND $cronid = intval ($_GET['cronid'])))
            {
              $numrows = 0;
              $IsNew = ($_GET['act'] == 'save_new' ? true : false);
              if (!$IsNew)
              {
                $query = $db->sql_query ('' . 'SELECT * FROM ts_cron WHERE cronid = \'' . $cronid . '\'');
                $numrows = $db->num_rows ($query);
              }

              if ((0 < $numrows OR $IsNew))
              {
                if (!$IsNew)
                {
                  $Cron = mysqli_fetch_assoc ($query);
                  $TArray = calc_cron_time ($Cron['minutes']);
                }
                else
                {
                  $Cron['cronid'] = '999';
                  $Cron['filename'] = '';
                  $Cron['description'] = '';
                  $TArray = array ();
                }

                $i = 0;
                while ($i <= 12)
                {
                  $months .= '<option value="' . $i . '"' . iif ($TArray['months'] == $i, ' selected="selected"') . '>' . $i . ' Month' . iif (1 < $i, 's') . '</option>';
                  ++$i;
                }

                $i = 0;
                while ($i <= 4)
                {
                  $weeks .= '<option value="' . $i . '"' . iif ($TArray['weeks'] == $i, ' selected="selected"') . '>' . $i . ' Week' . iif (1 < $i, 's') . '</option>';
                  ++$i;
                }

                $i = 0;
                while ($i <= 31)
                {
                  $days .= '<option value="' . $i . '"' . iif ($TArray['days'] == $i, ' selected="selected"') . '>' . $i . ' Day' . iif (1 < $i, 's') . '</option>';
                  ++$i;
                }

                $i = 0;
                while ($i <= 24)
                {
                  $hours .= '<option value="' . $i . '"' . iif ($TArray['hours'] == $i, ' selected="selected"') . '>' . $i . ' Hour' . iif (1 < $i, 's') . '</option>';
                  ++$i;
                }

                $i = 0;
                while ($i <= 60)
                {
                  $minutes .= '<option value="' . $i . '"' . iif ($TArray['minutes'] == $i, ' selected="selected"') . '>' . $i . ' Minute' . iif (1 < $i, 's') . '</option>';
                  ++$i;
                }

                $showcrons .= '
				</form>
				<form method="POST" action="managesettings.php?do=cronjobs&act=save&cronid=' . $Cron['cronid'] . '">
				' . iif ($IsNew, '<input type="hidden" name="save_new" value="true" />') . '
				<input type="hidden" name="do" value="cronjobs" />
				<table width="100%" border="0" class="tborder" cellpadding="3" cellspacing="0">
					<tr>
						<td class="subheader">' . iif ($IsNew, 'New', 'Edit') . ' Cron</td>
					</tr>
					<tr>
						<td>
							<fieldset>
								<legend>Filename</legend>
								<input type="text" size="90" name="filename" value="' . iif ($Filename, htmlspecialchars_uni ($Filename), $Cron['filename']) . '" class="bginput" />
							</fieldset>						
							<fieldset>
								<legend>Description</legend>
								<input type="text" size="90" name="description" value="' . iif ($Description, htmlspecialchars_uni ($Description), $Cron['description']) . '" class="bginput" />
							</fieldset>
							<fieldset>
								<legend>Run Period</legend>
								<table border="0" cellpadding="2" cellspacing="0">
									<tr>
										<td>
											Month<br />
											<select name="months" class="bginput">
												' . $months . '
											</select>
										</td>
										<td>
											Week<br />
											<select name="weeks" class="bginput">
												' . $weeks . '
											</select>
										</td>
										<td>
											Day<br />
											<select name="days" class="bginput">
												' . $days . '
											</select>
										</td>
										<td>
											Hour<br />
											<select name="hours" class="bginput">
												' . $hours . '
											</select>
										</td>
										<td>
											Minute<br />
											<select name="minutes" class="bginput">
												' . $minutes . '
											</select>
										</td>
									</tr>					
								</table>
							</fieldset>
							<fieldset>
								<legend>Cron Settings</legend>
								<input class="inlineimg" type="checkbox" name="active" value="yes"' . iif ($Cron['active'], ' checked="checked"') . ' class="bginput" /> Check this box to active this cron. Uncheck to disable this cron.<br />
								<input class="inlineimg" type="checkbox" name="loglevel" value="yes"' . iif ($Cron['loglevel'], ' checked="checked"') . ' class="bginput" /> Check this box to log cron action into database. Uncheck to disable this feature.
							</fieldset>
							<fieldset>
								<legend>Save Cron</legend>
								<input type="submit" value="Save Cron" /> <input type="reset" value="Reset Cron" />
							</fieldset>
						</td>
					</tr>
				</table>
				</form>
				<br />
				';
              }
            }
          }
        }
      }

      $showcrons .= '
		<table width="100%" class="trow2" cellpadding="5" cellspacing="0">
			<tr>
				<td class="subheader" align="left">Filename</td>
				<td class="subheader" align="left">Description</td>
				<td class="subheader" align="center">Run Period</td>
				<td class="subheader" align="center">Next Run</td>
				<td class="subheader" align="center">Log Action</td>
				<td class="subheader" align="center">Active</td>
				<td class="subheader" align="center">Action</td>
			</tr>';
      $query = $db->sql_query ('SELECT * FROM ts_cron ORDER BY cronid');
      while ($crons = mysqli_fetch_assoc ($query))
      {
        $showcrons .= '
			<tr>
				<td align="left">' . $crons['filename'] . '</td>
				<td align="left">' . $crons['description'] . '</td>
				<td align="center">' . mkprettytime ($crons['minutes']) . '</td>
				<td align="center">' . my_datee ($dateformat, $crons['nextrun']) . ' ' . my_datee ($timeformat, $crons['nextrun']) . '</td>
				<td align="center"><font color="' . iif ($crons['loglevel'] == '1', 'green">YES', 'red">NO') . '</font></td>
				<td align="center"><font color="' . iif ($crons['active'] == '1', 'green">YES', 'red">NO') . '</font></td>
				<td align="center">' . frame_link ('cronjobs&amp;act=run&amp;cronid=' . $crons['cronid'], '[run]') . ' ' . frame_link ('cronjobs&amp;act=edit&amp;cronid=' . $crons['cronid'], '[edit]') . ' ' . frame_link ('cronjobs&amp;act=' . iif ($crons['active'] == '1', 'disable', 'active') . '&amp;cronid=' . $crons['cronid'], '[' . iif ($crons['active'] == '1', 'disable', 'active') . ']') . ' ' . frame_link ('cronjobs&amp;act=delete&amp;cronid=' . $crons['cronid'], '[delete]') . '</td>
			</tr>
			';
      }

      $showcrons .= '</table>';
      echo $showcrons;
      $showlogs = '
		<br />
		<table border="0" cellpadding="5" class="trow2" cellspacing="0" align="center" width="800">
			<tr>
				<td colspan="4" class="thead">Show Cron Logs</td>
			</tr>
			<tr>
				<td class="subheader" align="left">Filename</td>
				<td class="subheader" align="center">Query Count</td>
				<td class="subheader" align="center">Execute Time</td>
				<td class="subheader" align="center">Last Run</td>
			</tr>';
      $query = $db->sql_query ('SELECT * FROM ts_cron_log ORDER BY querycount DESC, executetime ASC');
      while ($logs = mysqli_fetch_assoc ($query))
      {
        $showlogs .= '
			<tr>
				<td align="left">' . $logs['filename'] . '</td>
				<td align="center">' . ts_nf ($logs['querycount']) . '</td>
				<td align="center">' . $logs['executetime'] . '</td>
				<td align="center">' . my_datee ($dateformat, $logs['runtime']) . ' ' . my_datee ($timeformat, $logs['runtime']) . '</td>				
			</tr>
			';
      }

      $showlogs .= '</table>';
      echo $showlogs . '<p align="center">' . frame_link ('cronjobs&amp;act=save_new&cronid=999', 'Create New Cronjob') . '</p>';
      break;
    }

    case 'cleanup':
    {
      require INC_PATH . '/readconfig_cleanup.php';
      print_rows ('Automatic Invite?', show_helptip ('' . 'Give x Invites every ' . $autoinvitetime . ' days if usergroup have this feature.') . '
		<select class="bginput" name="configoption[ai]">
						<option value="yes"' . iif ($ai == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($ai == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Automatic Invite Time?', show_helptip ('Give x Invites every X days if usergroup have this feature.') . '
		<input type="text" size="2" name="configoption[autoinvitetime]" value="' . $autoinvitetime . '" class="bginput">');
      print_rows ('Mark Torrents Invisible?', show_helptip ('Mark torrents as invisible after X days. (Torrent Last Action < X days)') . '
		<input type="text" size="2" name="configoption[max_dead_torrent_time]" value="' . $max_dead_torrent_time . '" class="bginput">');
      print_rows ('Promote Users GB Limit?', show_helptip ('Once Regular User reach this Limit, his/her account will be promoted automaticly to Power User. Leave 0 to disable this feature.') . '
		<input type="text" size="2" name="configoption[promote_gig_limit]" value="' . $promote_gig_limit . '" class="bginput">');
      print_rows ('Promote Users RATIO Limit?', show_helptip ('Once Regular User reach this Limit, his/her account will be promoted automaticly to Power User.') . '
		<input type="text" size="2" name="configoption[promote_min_ratio]" value="' . $promote_min_ratio . '" class="bginput">');
      print_rows ('Promote Users DAYS Limit?', show_helptip ('Min. DAYS Limit for promote.') . '
		<input type="text" size="2" name="configoption[promote_min_reg_days]" value="' . $promote_min_reg_days . '" class="bginput">');
      print_rows ('Demote Users RATIO Limit?', show_helptip ('Whenever user have below ratio from this limit, his/her account will be demoted automaticly to User.') . '
		<input type="text" size="2" name="configoption[demote_min_ratio]" value="' . $demote_min_ratio . '" class="bginput">');
      print_rows ('Referrer Gift?', show_helptip ('Referrer will receive X GB Upload when his referred users reach Power User Level.') . '
		<input type="text" size="2" name="configoption[referrergift]" value="' . $referrergift . '" class="bginput">');
      print_rows ('Warn User MIN. Ratio?', show_helptip ('Min. Ratio for LeechWarning.') . '
		<input type="text" size="2" name="configoption[leechwarn_min_ratio]" value="' . $leechwarn_min_ratio . '" class="bginput">');
      print_rows ('Warn User GB Limit?', show_helptip ('Min. GB Limit for LeechWarning.') . '
		<input type="text" size="2" name="configoption[leechwarn_gig_limit]" value="' . $leechwarn_gig_limit . '" class="bginput">');
      print_rows ('Warning Length?', show_helptip ('LeechWarning Length (weeks).') . '
		<input type="text" size="2" name="configoption[leechwarn_length]" value="' . $leechwarn_length . '" class="bginput">');
      print_rows ('Remove Warning Min. Ratio?', show_helptip ('Min. Ratio Limit to Remove LeechWarning.') . '
		<input type="text" size="2" name="configoption[leechwarn_remove_ratio]" value="' . $leechwarn_remove_ratio . '" class="bginput">');
      print_rows ('Ban Warned Users?', show_helptip ('Once an user has reached this limit, he will be automaticly banned.') . '
		<input type="text" size="2" name="configoption[ban_user_limit]" value="' . $ban_user_limit . '" class="bginput">');
      print_submit_rows ();
      break;
    }

    

    case 'announce':
    {
      require INC_PATH . '/readconfig_announce.php';
      print_rows ('Log Cheat Attempts?', show_helptip ('Disable this for better performance.') . '
		<select class="bginput" name="configoption[announce_actions]">
						<option value="yes"' . iif ($announce_actions == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($announce_actions == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Aggressive Cheat Detection?', show_helptip ('Aggressive Cheat Detection system which will allow you to detect cheat programs such as Ratio Master, Ratio Faker etc..') . '
		<select class="bginput" name="configoption[aggressivecheat]">
						<option value="yes"' . iif ($aggressivecheat == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($aggressivecheat == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Disable DL/UL?', show_helptip ('Disable download/upload of not connectable users. Please note: This feature may help you to stop/detect cheaters.') . '
		<select class="bginput" name="configoption[nc]">
						<option value="yes"' . iif ($nc == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($nc == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Min. Announce Refresh Time?', show_helptip ('Minimum announce refresh time (floot limit). Leave 0 to disable this feature.') . '
		<input type="text" size="10" name="configoption[announce_wait]" value="' . $announce_wait . '" class="bginput">');
      print_rows ('Announce Interval?', show_helptip ('Announce Update Time in Seconds. Leave this high to better performance.') . '
		<input type="text" size="10" name="configoption[announce_interval]" value="' . $announce_interval . '" class="bginput">');
      print_rows ('Max. Transfer Rate?', show_helptip ('Once user has reached this transfer rate, we will try to detect his upload speed..') . '
		<input type="text" class="bginput" size="10" name="configoption[max_rate]" value="' . $max_rate . '">');
      print_rows ('Banned Client Detection Enabled?', show_helptip ('Disable downloads if a banned client detected.<br /><br />If \\\'Banned Client Detection Enabled\\\', only \\\'Allowed Client\\\' list allowed.<br />Separated by , ie: -UT1610-,-AZ2504-,-AZ3012- To catch peer ids, click <a href=index.php?act=allagents><font color=red>here</font></a>.') . '
		<select class="bginput" name="configoption[bannedclientdetect]">
						<option value="yes"' . iif ($bannedclientdetect == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($bannedclientdetect == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Allowed Clients', show_helptip ('This is a white list of clients.') . '
		<textarea name="configoption[allowed_clients]" rows="4" cols="150">' . $allowed_clients . '</textarea>
		');
      print_rows ('Detect Browser Cheats?', show_helptip ('Enable this feature to detect Browser Cheat Attempts.') . '
		<select class="bginput" name="configoption[detectbrowsercheats]">
						<option value="yes"' . iif ($detectbrowsercheats == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($detectbrowsercheats == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Detect Connectable?', show_helptip ('Enable this feature to detect user connectable status.<br />Note: This will decrease system performance.<br /> Note2: If you disable this feature, system will show all users as connectable.') . '
		<select class="bginput" name="configoption[checkconnectable]">
						<option value="yes"' . iif ($checkconnectable == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($checkconnectable == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Check IP?', show_helptip ('Check User IP Before Send Peer List. If you enable this feature, system will check user last ip in users table, user last ip must be equal to client ip.') . '
		<select class="bginput" name="configoption[checkip]">
						<option value="yes"' . iif ($checkip == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($checkip == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_submit_rows ();
      break;
    }

    case 'signup':
    {
      require INC_PATH . '/readconfig.php';
  
	  $squery = $db->sql_query ('SELECT gid, title, namestyle FROM usergroups');
      $sgids = '
		<fieldset>
			<legend>Select Usergroup</legend>
				<table border="0" cellspacing="0" cellpadding="2">
					<tr>
						<td>
							<select name="configoption[_d_usergroup]" class="bginput">';
      while ($gid = mysqli_fetch_assoc ($squery))
      {
        $sgids .= '	
								<option value="' . $gid['gid'] . '"' . ($_d_usergroup == $gid['gid'] ? ' selected="selected"' : '') . '>' . get_user_color ($gid['title'], $gid['namestyle']) . '</option>';
      }

      $sgids .= '
							</select>
						</td>
					</tr>
				</table>
		</fieldset>';
      
	  
					
					
      print_rows ('Active Invite System?', show_helptip ('Enable this feature to Allow Registrations via Invite System.') . ' 
		<select class="bginput" name="configoption[invitesystem]">
						<option value="on"' . iif ($invitesystem == 'on', ' selected="selected"') . '>Active</option>
					<option value="off"' . iif ($invitesystem == 'off', ' selected="selected"') . '>Disable</option>
					</select>');
     
	
					
					
					
	
	print_rows ('Registration Method?', show_helptip ('Please select the method of registration to use when users register.') . ' 
		<select class="bginput" name="configoption[regtype]">
						<option value="invite"' . iif ($regtype == 'invite', ' selected="selected"') . '>Invite System</option>
						<option value="instant"' . iif ($regtype == 'instant', ' selected="selected"') . '>Instant Activation</option>
						<option value="verify"' . iif ($regtype == 'verify', ' selected="selected"') . '>Send Email Verification</option>
						<option value="randompass"' . iif ($regtype == 'randompass', ' selected="selected"') . '>Send Random Password</option>
						<option value="admin"' . iif ($regtype == 'admin', ' selected="selected"') . '>Administrator Activation</option>
						<option value="both"' . iif ($regtype == 'both', ' selected="selected"') . '>Email Verification &amp; Administrator Activation</option>
					</select>');

					

	print_rows ('Require a complex password?', show_helptip ('Do you want users to use complex passwords? Complex passwords require an upper case letter, lower case letter and a number..') . '
		<select class="bginput" name="configoption[requirecomplexpasswords]">
						<option value="1"' . iif ($requirecomplexpasswords == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($requirecomplexpasswords == '0', ' selected="selected"') . '>No</option>
					</select>');
					
					
					
	print_rows ('Minimum Username Length', show_helptip ('The minimum number of characters a username can be when a user registers.') . '
		<input type="text" size="10" name="configoption[minnamelength]" value="' . $minnamelength . '" class="bginput">');	
		
	
	print_rows ('Maximum Username Length', show_helptip ('The maximum number of characters a username can be when a user registers') . '
		<input type="text" size="10" name="configoption[maxnamelength]" value="' . $maxnamelength . '" class="bginput">');	
		
		
		


    print_rows ('Minimum Password Length', show_helptip ('The minimum number of characters a password should contain.') . '
		<input type="text" size="10" name="configoption[minpasswordlength]" value="' . $minpasswordlength . '" class="bginput">');					
					
	
	print_rows ('Maximum Password Length', show_helptip ('The maximum number of characters a password should contain.') . '
		<input type="text" size="10" name="configoption[maxpasswordlength]" value="' . $maxpasswordlength . '" class="bginput">');	
		
		
		
	print_rows ('Number of times to allow failed logins', show_helptip ('The number of times to allow someone to attempt to login. Set to 0 to disable.') . '
		<input type="text" size="10" name="configoption[failedlogincount]" value="' . $failedlogincount . '" class="bginput">');
		
		
	

    print_rows ('Display number of failed logins', show_helptip ('Do you wish to display a line of text telling the user how many more login attempts they have?') . '
		<select class="bginput" name="configoption[failedlogintext]">
						<option value="1"' . iif ($failedlogintext == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($failedlogintext == '0', ' selected="selected"') . '>No</option>
					</select>');	
		


	
	

		
	


		
		



print_rows ('Allowed Login Methods', show_helptip ('The login methods you wish to allow for the username field. Username only, Email only, or allow both.To allow email as a valid login method, no users are allowed to share email addresses.') . '
		<select class="bginput" name="configoption[username_method]">
						<option value="0"' . iif ($username_method == '0', ' selected="selected"') . '>Username Only</option>
						<option value="1"' . iif ($username_method == '1', ' selected="selected"') . '>Email Only</option>
						<option value="2"' . iif ($username_method == '2', ' selected="selected"') . '>Both Username and Email</option>
					</select>');
	



	print_rows ('Disable Registrations', show_helptip ('Allows you to turn off the capability for users to register with one click') . '
		<select class="bginput" name="configoption[disableregs]">
						<option value="1"' . iif ($disableregs == '1', ' selected="selected"') . '>Yes</option>
						<option value="0"' . iif ($disableregs == '0', ' selected="selected"') . '>No</option>
					</select>');
					
					
		

		
	print_rows ('COPPA Compliance', show_helptip ('If you wish to enable COPPA support on your forums, please select the registration allowance below.') . '
		<select class="bginput" name="configoption[coppa]">
						<option value="enabled"' . iif ($coppa == 'enabled', ' selected="selected"') . '>Enabled</option>
						<option value="deny"' . iif ($coppa == 'deny', ' selected="selected"') . '>Deny users under the age of 13</option>
						<option value="disabled"' . iif ($coppa == 'disabled', ' selected="selected"') . '>Disable this feature</option>
					</select>');				
					
					
								
					
					
    	
					
					
					
      print_rows ('Max. IP\'s?', show_helptip ('Disable registration with same IP Address. Leave it \\\'disable\\\' to disable this feature.') . '
		<input type="text" class="bginput" size="3" name="configoption[maxip]" value="' . $maxip . '">');
      print_rows ('Max. Users?', show_helptip ('Disable registration whenever this limit will be reached.') . '
		<input type="text" size="10" name="configoption[maxusers]" value="' . $maxusers . '" class="bginput">');
      print_rows ('Default Usergroup?', show_helptip ('Once user confirm his/her account, he/she will be promoted to this usergroup.') . $sgids);
      print_rows ('Initial Number of Invites?', show_helptip ('How many invites should each user be given upon registration? Leave 0 to disable this.') . '
		<input type="text" size="10" name="configoption[invite_count]" value="' . $invite_count . '" class="bginput">');
      print_rows ('Auto GB on Signup?', show_helptip ('How much GB should each user be given upon registration? Leave 0 to disable this.') . '
		<input type="text" size="10" name="configoption[autogigsignup]" value="' . $autogigsignup . '" class="bginput">');
      print_rows ('Auto SeedBonus on Signup?', show_helptip ('How much Seedbonus should each user be given upon registration? Leave 0 to disable this.') . '
		<input type="text" size="10" name="configoption[autosbsignup]" value="' . $autosbsignup . '" class="bginput">');
      
	    
	  
	  print_rows ('Illegal User Names?', show_helptip ('Enter names in here that you do not want people to be able to register. If any of the names here are included within the username, the user will told that there is an error. For example, if you make the name John illegal, the name Johnathan will also be disallowed. Separate names by spaces.') . '
		<textarea name="configoption[illegalusernames]" rows="5" cols="75" class="bginput">' . $illegalusernames . '</textarea>');
      print_rows ('Verification Fields?', show_helptip ('User must agree rules before registering.') . '
		<select class="bginput" name="configoption[r_verification]">
						<option value="yes"' . iif ($r_verification == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($r_verification == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Referrer?', show_helptip ('User can enter Referrer username while registering.') . '
		<select class="bginput" name="configoption[r_referrer]">
						<option value="yes"' . iif ($r_referrer == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($r_referrer == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_submit_rows ();
      break;
    }

    case 'extra':
    {
      print_rows ('Check & Show Connectable?', show_helptip ('Show a warning message to user if his/her connectable status is NO.') . '
		<select class="bginput" name="configoption[checkconnectable]">
						<option value="yes"' . iif ($checkconnectable == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($checkconnectable == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Save Referrers?', show_helptip ('Detect referrers and save into database.') . '
		<select class="bginput" name="configoption[ref]">
						<option value="yes"' . iif ($ref == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($ref == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('HIT & RUN System Enabled?', show_helptip ('Check user ratio before download torrent.') . '
		<select class="bginput" name="configoption[hitrun]">
						<option value="yes"' . iif ($hitrun == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($hitrun == 'no', ' selected="selected"') . '>No</option>
					</select> Min. Ratio for HIT & RUN: <input type="text" size="3" name="configoption[hitrun_ratio]" value="' . $hitrun_ratio . '" class="bginput"> Min. GB limit for HIT & RUN: <input type="text" size="3" name="configoption[hitrun_gig]" value="' . $hitrun_gig . '" class="bginput">');
      print_rows ('Request Section Enabled?', show_helptip ('Turn OFF Request section by selecting \\\'NO\\\'.') . '
		<select class="bginput" name="configoption[rqs]">
						<option value="yes"' . iif ($rqs == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($rqs == 'no', ' selected="selected"') . '>No</option>
					</select>');
     
      print_submit_rows ();
      break;
    }

    

    case 'pincode':
    {
      print_rows ('Enter your new pincode (For settings panel)', show_helptip ('Leave blank if you want to keep this.') . '
		<input type="password" class="bginput" size="15" name="configoption[pincode1]" value="">', '30%', '70%');
      print_rows ('Re-enter your new pincode (For settings panel)', show_helptip ('Re-enter pincode, same as above pincode.') . '
		<input type="password" class="bginput" size="15" name="configoption[re_pincode1]" value="">', '30%', '70%');
      print_rows ('Enter your new pincode (For staff panel)', show_helptip ('Leave blank if you want to keep this.') . '
		<input type="password" class="bginput" size="15" name="configoption[pincode2]" value="">', '30%', '70%');
      print_rows ('Re-enter your new pincode (For staff panel)', show_helptip ('Re-enter pincode, same as above pincode.') . '
		<input type="password" class="bginput" size="15" name="configoption[re_pincode2]" value="">', '30%', '70%');
      print_submit_rows ();
      break;
    }

    case 'staffteam':
    {
      $filename = CONFIG_DIR . '/STAFFTEAM';
      $handle = fopen ($filename, 'r');
      $staffteam = fread ($handle, filesize ($filename));
      $staffteam = explode (',', $staffteam);
      $staffarray = array ();
      foreach ($staffteam as $staff)
      {
        $staff = explode (':', $staff);
        $staffarray[] = array ('name' => '<input type="text" name="staffnames[]" value="' . $staff[0] . '" class="bginput" />', 'id' => '<input type="text" name="staffids[]" value="' . $staff[1] . '" class="bginput" />');
      }

      $printrows = '
		<table align="left" border="1" cellpadding="3" cellspacing="0">
			<tr>
				<td class="subheader">Username</td>
				<td class="subheader">Userid</td>
			</tr>';
      foreach ($staffarray as $array)
      {
        $printrows .= '
			<tr>
				<td>' . $array['name'] . '</td>
				<td>' . $array['id'] . '</td>
			</tr>';
      }

      $printrows .= '
			<tr>
				<td><input type="text" name="staffnames[]" value="" class="bginput" /></td>
				<td><input type="text" name="staffids[]" value="" class="bginput" /></td>
			</tr>
			<tr>
				<td><input type="text" name="staffnames[]" value="" class="bginput" /></td>
				<td><input type="text" name="staffids[]" value="" class="bginput" /></td>
			</tr>
			<tr>
				<td><input type="text" name="staffnames[]" value="" class="bginput" /></td>
				<td><input type="text" name="staffids[]" value="" class="bginput" /></td>
			</tr>';
      $printrows .= '		
		</table>		
		';
      print_rows ('Manage Staff Team<br /><br /><input type="button" onclick="window.open(\'' . $BASEURL . '/users.php#searchuser\',\'finduser\',\'toolbar=no, scrollbars=yes, resizable=no, width=800, height=300, top=50, left=50\'); return false;" value="' . $lang->global['finduser'] . '" class="button" />', show_helptip ('To insert a new staff member please enter username, userid and click on save button.<br />To remove a staff member delete username and userid from the input field and click on save button.', 'Quick Help', 600) . $printrows);
      print_submit_rows ();
      break;
    }

   

  
    case 'kps':
    {
      require INC_PATH . '/readconfig.php';

      print_rows ('KPS System Enabled?', show_helptip ('Once user earn points by seeding a torrent, posting a comment etc.. they can trade this points on KPS page.') . '
		<select class="bginput" name="configoption[bonus]">
						<option value="enable"' . iif ($bonus == 'enable', ' selected="selected"') . '>Yes, Enabled.</option>
						<option value="disablesave"' . iif ($bonus == 'disablesave', ' selected="selected"') . '>No, But Save Points.</option>
						<option value="disable"' . iif ($bonus == 'disable', ' selected="selected"') . '>No, Disabled.</option>
					</select>');
      print_rows ('Seed Point?', show_helptip ('This points depending on your Announce Interval value.<br /><br />Example: Set this to 0.5 and set Announce Interval to 1800 so seeders will get 1 point per hour.') . '
		<input type="text" class="bginput" size="5" name="configoption[kpsseed]" value="' . $kpsseed . '">');
      print_rows ('Upload Point?', show_helptip ('Give seeding bonus when user upload a torrent.') . '
		<input type="text" class="bginput" size="5" name="configoption[kpsupload]" value="' . $kpsupload . '">');
      print_rows ('Post/Comment/Thread Point?', show_helptip ('Give seeding bonus when user submit a comment/post/thread.') . '
		<input type="text" class="bginput" size="5" name="configoption[kpscomment]" value="' . $kpscomment . '">');
      print_rows ('Thanks Point?', show_helptip ('Give seeding bonus when user say thanks.') . '
		<input type="text" class="bginput" size="5" name="configoption[kpsthanks]" value="' . $kpsthanks . '">');
      print_rows ('Rating Point?', show_helptip ('Give seeding bonus when user rate a torrent.') . '
		<input type="text" class="bginput" size="5" name="configoption[kpsrate]" value="' . $kpsrate . '">');
      print_rows ('Poll Point?', show_helptip ('Give seeding bonus when user vote a poll.') . '
		<input type="text" class="bginput" size="5" name="configoption[kpspoll]" value="' . $kpspoll . '">');
      print_rows ('Max. Bonus Point?', show_helptip ('Once user reach this limit he can only use trade for GIFT!') . '
		<input type="text" class="bginput" size="5" name="configoption[kpsmaxpoint]" value="' . $kpsmaxpoint . '">');
      print_rows ('Enable Invite Usage?', show_helptip ('Enable/Disable Invite Gift for KPS Page.') . '
		<select class="bginput" name="configoption[kpsinvite]">
						<option value="yes"' . iif ($kpsinvite == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($kpsinvite == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Enable Custom Title Usage?', show_helptip ('Enable/Disable Custom Title Gift for KPS Page.') . '
		<select class="bginput" name="configoption[kpstitle]">
						<option value="yes"' . iif ($kpstitle == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($kpstitle == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Enable VIP Status Usage?', show_helptip ('Enable/Disable VIP Gift for KPS Page.') . '
		<select class="bginput" name="configoption[kpsvip]">
						<option value="yes"' . iif ($kpsvip == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($kpsvip == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Enable Give A Karma Gift Usage?', show_helptip ('Enable/Disable Give A Karma Gift Usage for KPS Page.') . '
		<select class="bginput" name="configoption[kpsgift]">
						<option value="yes"' . iif ($kpsgift == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($kpsgift == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Enable Remove Warning Usage?', show_helptip ('Enable/Disable Remove Warning Usage for KPS Page.') . '
		<select class="bginput" name="configoption[kpswarning]">
						<option value="yes"' . iif ($kpswarning == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($kpswarning == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Enable Fix Torrent Ratio Usage?', show_helptip ('Enable/Disable Fix Torrent Ratio Usage for KPS Page.') . '
		<select class="bginput" name="configoption[kpsratiofix]">
						<option value="yes"' . iif ($kpsratiofix == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($kpsratiofix == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_row_header ('Birthday Reward Settings');
      print_rows ('Birthday Reward System Enabled?', show_helptip ('Free/Silver/Double leech/seed on user\\\'s birthday!') . '
		<select class="bginput" name="configoption[bdayreward]">
						<option value="yes"' . iif ($bdayreward == 'yes', ' selected="selected"') . '>Yes</option>
						<option value="no"' . iif ($bdayreward == 'no', ' selected="selected"') . '>No</option>
					</select>');
      print_rows ('Birthday Reward TYpe?', show_helptip ('Select Birthday Reward Type:<br /><br />Free Leech: Free Torrents download when set gives the users upload credit only and no download credit is posted to the users stats.<br /><br />Silver Leech: Silver Torrents when set only record 50% of the users download credit on that file.<br /><br />x2 Upload: x2 Double Upload Credit for seeding back files.') . '
		<select class="bginput" name="configoption[bdayrewardtype]">
						<option value="freeleech"' . iif ($bdayrewardtype == 'freeleech', ' selected="selected"') . '>Free Leech</option>
						<option value="silverleech"' . iif ($bdayrewardtype == 'silverleech', ' selected="selected"') . '>Silver Leech</option>
						<option value="doubleupload"' . iif ($bdayrewardtype == 'doubleupload', ' selected="selected"') . '>x2 Upload</option>
					</select>');
      print_submit_rows ();
      break;
    }


    case 'freeleech':
    {
      require TSDIR . '/cache/freeleech.php';
      print_rows ('Select System Type?', show_helptip ('Please select system type. Free Leech, Silver or DoubleUpload..') . '
		<select class="bginput" name="configoption[system]">
			<option value="freeleech"' . iif ($__FLSTYPE == 'freeleech', ' selected="selected"') . '>Free Leech</option>
			<option value="silverleech"' . iif ($__FLSTYPE == 'silverleech', ' selected="selected"') . '>Silver Leech</option>
			<option value="doubleupload"' . iif ($__FLSTYPE == 'doubleupload', ' selected="selected"') . '>Double Upload</option>
		</select>
		');
      print_rows ('Begin Date?', show_helptip ('Enter Free Days Begin Date. ALL torrents Will be free, silver or doubleupload...') . '
		<script language="JavaScript" src="scripts/calendar3.js"></script>
		 <input type="text" class="bginput" size="25" name="configoption[start]" value="' . ($__F_START != '0000-00-00 00:00:00' ? $__F_START : '') . '">
		 <a href="javascript:FLbegindate.popup();"><img src="scripts/img/cal.gif" width="16" height="16" border="0" alt="Click Here to Pick up the date" title="Click Here to Pick up the date"></a>
		 <script type="text/javascript">
			var siteadrr = "' . $BASEURL . '/admin/scripts/";
			var FLbegindate = new calendar3(document.forms[\'freeleech\'].elements[\'configoption[start]\']);
			FLbegindate.year_scroll = true;
			FLbegindate.time_comp = true;
		</script>
		 ');
      print_rows ('End Date?', show_helptip ('Enter Free Days End Date. Automatic System will be stoped at this date.') . '		
		 <input type="text" class="bginput" size="25" name="configoption[end]" value="' . ($__F_END != '0000-00-00 00:00:00' ? $__F_END : '') . '">
		 <a href="javascript:FLenddate.popup();"><img src="scripts/img/cal.gif" width="16" height="16" border="0" alt="Click Here to Pick up the date" title="Click Here to Pick up the date"></a>
		 <script type="text/javascript">			
			var FLenddate = new calendar3(document.forms[\'freeleech\'].elements[\'configoption[end]\']);
			FLenddate.year_scroll = true;
			FLenddate.time_comp = true;
		</script>
		 ');
      print_submit_rows ();
      break;
    }


    case 'hitrun':
    {
      readconfig ('HITRUN');
      if ($HRSkipUsergroups = explode (',', $HITRUN['HRSkipUsergroups']))
      {
      }
      else
      {
        $HRSkipUsergroups = array ();
      }

      $squery = $db->sql_query ('SELECT gid, title, namestyle FROM usergroups');
      $scount = 1;
      $sgids = '
		<fieldset>
			<legend>Select Usergroup(s)</legend>
				<table border="0" cellspacing="0" cellpadding="2" width="100%">
					<tr>';
      while ($gid = mysqli_fetch_assoc ($squery))
      {
        if ($scount % 4 == 1)
        {
          $sgids .= '</tr><tr>';
        }

        $sgids .= '	
			<td class="none"><input type="checkbox" name="usergroup[]" value="' . $gid['gid'] . '"' . (in_array ($gid['gid'], $HRSkipUsergroups, true) ? ' checked="checked"' : '') . '></td>
			<td class="none">' . get_user_color ($gid['title'], $gid['namestyle']) . '</td>';
        ++$scount;
      }

      $sgids .= '
					</tr>
				</table>
		</fieldset>';
      print_rows ('Enable Auto Hit-RUN Feature?', show_helptip ('You might want to enable/disable this feature.') . '	
			<select name="configoption[Enabled]" class="bginput">
				<option value="yes"' . ($HITRUN['Enabled'] == 'yes' ? ' selected="selected"' : '') . '>Yes</option>
				<option value="no"' . ($HITRUN['Enabled'] == 'no' ? ' selected="selected"' : '') . '>No</option>
			</select>
		 ');
      print_rows ('Minimum Ratio Per Torrent?', show_helptip ('Please Enter Minimum Ratio. User must get X ratio after finished. Default: 0.5 (Leave 0 to disable this system)') . ' <input type="text" name="configoption[MinRatio]" value="' . (isset ($HITRUN['MinRatio']) ? $HITRUN['MinRatio'] : '0.5') . '" class="bginput" />');
      print_rows ('Minimum Seed Time Per Torrent?', show_helptip ('Please Enter Minimum Seed Time In Hours. User must seed a torrent at least X hours after finished. Default: 24 (Leave 0 to disable this system)') . ' <input type="text" name="configoption[MinSeedTime]" value="' . (isset ($HITRUN['MinSeedTime']) ? $HITRUN['MinSeedTime'] : 24) . '" class="bginput" />');
      print_rows ('Minimum Finish Date?', show_helptip ('Check date of finished torrents (Do not count warns for old torrents), this option will allow system to save queries. Set this 0 to count ALL torrents (not recommend)') . '
		<script language="JavaScript" src="scripts/calendar3.js"></script>
		 <input type="text" class="bginput" size="25" name="configoption[MinFinishDate]" value="' . date ('Y-m-d H:i:s', ($HITRUN['MinFinishDate'] ? $HITRUN['MinFinishDate'] : time ())) . '">
		 <a href="javascript:FinishDate.popup();"><img src="scripts/img/cal.gif" width="16" height="16" border="0" alt="Click Here to Pick up the date" title="Click Here to Pick up the date"></a>
		 <script type="text/javascript">
			var siteadrr = "' . $BASEURL . '/admin/scripts/";
			var FinishDate = new calendar3(document.forms[\'hitrun\'].elements[\'configoption[MinFinishDate]\']);
			FinishDate.year_scroll = true;
			FinishDate.time_comp = true;
		</script>
		 ');
      print_rows ('Skip (Protect) Usergroups?', show_helptip ('Select usergroups to skip from Automatic HIT & RUN System. Do not warn them.') . '
		 ' . $sgids);
      print_submit_rows ();
    }
  }

  table_close (false);
  close_form ();
  admin_cp_footer (true);
  exit ();
?>
