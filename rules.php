<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  require_once 'global.php';
  gzip ();

  define ('R_VERSION', '0.6 ');
  
  include_once INC_PATH . '/functions_security.php';
  
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
  
  stdhead ();
  $res = $db->sql_query ('SELECT title,text,usergroups FROM rules ORDER BY id');
  while ($rules = $db->fetch_array ($res))
  {
    if (((($rules['usergroups'] == '[0]' OR $rules['usergroups'] == '0') OR ($CURUSER AND ($CURUSER['usergroup'] === $rules['usergroups'] OR '[' . $CURUSER['usergroup'] . '].' === $rules['usergroups']))) OR ($CURUSER['usergroup'] AND preg_match ('#\\[' . $CURUSER['usergroup'] . '\\]#U', $rules['usergroups']))))
    {
      echo '
		
		
		
		<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $rules['title'] . '
	</div>
	 </div>
		</div>
		
		
		
		
		
		
		<div class="container mt-3">
        <div class="card">
		
		<table width="100%" border="0" class="tborder" cellspacing="0" cellpadding="5">
			
			<tr>
				<td align="left">' . $parser->parse_message($rules['text'],$parser_options) . '</td>
			</tr>
		</table>
		
		</div>
		</div>
		</br>
		
		';
      continue;
    }
    else
    {
      continue;
    }
  }

  stdfoot ();
?>
