<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('IH_VERSION', '1.1 by xam');
  $userid = intval ($_GET['id']);
  if (!is_valid_id ($userid))
  {
    print_no_permission ();
  }

  ($query = $db->sql_query ('SELECT userid FROM iplog WHERE userid = \'' . $userid . '\''));
  $count = $db->num_rows ($query);
  //list ($pagertop, $pagerbottom, $limit) = pager (10, $count, $_this_script_ . '&amp;id=' . $userid . '&amp;' . (isset ($_GET['showhost']) ? 'showhost=true&amp;' : ''));
  ($query = $db->sql_query ('SELECT i.ip, u.username, u.ip as userlastip, g.namestyle 
  FROM iplog i 
  LEFT JOIN users u ON (i.userid=u.id) 
  LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
  WHERE u.id = \'' . $userid . '\' ORDER by i.ip DESC ' . $limit));
  if ($db->num_rows ($query) < 1)
  {
    ($query = $db->sql_query ('SELECT u.ip, u.username, u.ip as userlastip, g.namestyle FROM users u LEFT JOIN usergroups g ON (u.usergroup=g.gid) WHERE u.id = \'' . $userid . '\' ORDER by u.ip DESC ' . $limit));
    $count = $db->num_rows ($query);
  }

  $founds = array ();
  while ($user = mysqli_fetch_assoc ($query))
  {
    if (!$username)
    {
      $username = '<a href="' . ts_seo ($userid, $user['username']) . '" id="panelbutton">' . get_user_color ($user['username'], $user['namestyle']) . '</a>';
    }

    if (!$userlastip)
    {
      $userlastip = htmlspecialchars_uni ($user['userlastip']);
    }

    if ((isset ($_GET['showhost']) AND !$founds[$user['ip']]))
    {
      $founds[$user['ip']] = $host = @gethostbyaddr ($user['ip']);
    }
    else
    {
      $host = $founds[$user['ip']];
    }

    $str .= '
		<tr>
			<td>' . htmlspecialchars_uni ($user['ip']) . '</td>
			<td>' . (3 < strlen ($host) ? htmlspecialchars_uni ($host) : '<span style="color: red;">Not Detected!</span>') . '</td>
		</tr>';
    unset ($host);
  }

  stdhead ('Ip History for ' . $username);
  echo $pagertop . '
<style type="text/css">
/*margin and padding on body element
  can introduce errors in determining
  element position and are not recommended;
  we turn them off as a foundation for YUI
  CSS treatments. */
body {
	margin:0;
	padding:0;
}
</style>

<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.6.0/build/container/assets/skins/sam/container.css" />
<script type="text/javascript" src="http://yui.yahooapis.com/2.6.0/build/utilities/utilities.js"></script>
<script type="text/javascript" src="http://yui.yahooapis.com/2.6.0/build/container/container-min.js"></script>
<script type="text/javascript">

		YAHOO.namespace("example.container");

		function init() {

			if (!YAHOO.example.container.wait) {

				// Initialize the temporary Panel to display while waiting for external content to load

				YAHOO.example.container.wait = 
						new YAHOO.widget.Panel("wait",  
														{ width: "240px", 
														  fixedcenter: true, 
														  close: false, 
														  draggable: false, 
														  zindex:4,
														  modal: true,
														  visible: false
														} 
													);
		
				YAHOO.example.container.wait.setHeader("' . $lang->global['pleasewait'] . '");
				YAHOO.example.container.wait.setBody("<img src=\\"http://us.i1.yimg.com/us.yimg.com/i/us/per/gr/gp/rel_interstitial_loading.gif\\"/>");
				YAHOO.example.container.wait.render(document.body);

			}

			// Define the callback object for Connection Manager that will set the body of our content area when the content has loaded
			var callback = {
				success : function(o) {                
					YAHOO.example.container.wait.hide();
				},
				failure : function(o) {					
					YAHOO.example.container.wait.hide();
				}
			}
		
			// Show the Panel
			YAHOO.example.container.wait.show();
			
			// Connect to our data source and load the data
		   //var conn = YAHOO.util.Connect.asyncRequest("GET", "assets/somedata.php?r=" + new Date().getTime(), callback);
		}
		
		YAHOO.util.Event.on("panelbutton", "click", init);
			
	</script>
	<body class = "yui-skin-sam">
	<table width="100%" border="0" cellpadding="5" cellspacing="0">
		<tr>
			<td class="thead" colspan="2"><span style="float: right;">User Last IP: ' . $userlastip . ' (' . (!isset ($_GET['showhost']) ? '<a href="' . $_this_script_ . '&amp;id=' . $userid . '&amp;showhost=true&amp;page=' . intval ($_GET['page']) . '" id="panelbutton">Detect Host</a>' : '<a href="' . $_this_script_ . '&amp;id=' . $userid . '&amp;page=' . intval ($_GET['page']) . '" id="panelbutton">Hide Host Detection</a>') . ')</span>Historical IP Addresses Used by ' . $username . ' - Total Unique IP Addresses User Has Loged In With ' . ts_nf ($count) . '</td>
		<tr>
		<tr>
			<td class="subheader">IP Address</td>
			<td class="subheader">ISP Host Name</td>
		</tr>
		' . $str . '
	</table>
	</body>
	' . $pagerbottom;
  stdfoot ();
?>
