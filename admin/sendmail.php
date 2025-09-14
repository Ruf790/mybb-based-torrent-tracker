<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function strip_selected_tags ($text, $tags = array ())
  {
    $args = func_get_args ();
    $text = array_shift ($args);
    $tags = (2 < func_num_args () ? array_diff ($args, array ($text)) : (array)$tags);
    foreach ($tags as $tag)
    {
      while (preg_match ('/<' . $tag . '(|\\W[^>]*)>(.*)<\\/' . $tag . '>/iusU', $text, $found))
      {
        $text = str_replace ($found[0], $found[2], $text);
      }
    }

    return preg_replace ('/(<(' . join ('|', $tags) . ')(|\\W.*)\\/>)/iusU', '', $text);
  }

  function html2txt ($document)
  {
    $search = array ('@<script[^>]*?>.*?</script>@si', '@<style[^>]*?>.*?</style>@siU', '@<![\\s\\S]*?--[ \\t\\n\\r]*>@');
    $text = preg_replace ($search, '', $document);
    return $text;
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('SM_VERSION', '0.4 by xam');
  define("IN_MYBB", 1);
  $error = '';
  $msgtext = trim ($_POST['message']);
  $subject = trim ($_POST['subject']);
  //$avatar = get_user_avatar ($CURUSER['avatar']);
  $email = (isset ($_GET['email']) ? htmlspecialchars_uni ($_GET['email']) : (isset ($_POST['email']) ? htmlspecialchars_uni ($_POST['email']) : ''));
  $externalpreview = '<div id=\'loading-layer\' style=\'position: absolute; display:none; left:500px; width:200px;height:50px;background:#FFF;padding:10px;text-align:center;border:1px solid #000\'><div style=\'font-weight:bold\' id=\'loading-layer-text\' class=\'small\'>Sending... Please wait...</div><br /><img src=\'' . $BASEURL . '/' . $pic_base_url . 'await.gif\' border=\'0\' /></div>';
  if ($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    $sender_name = ($_POST['sender'] == 'system' ? $SITENAME : $CURUSER['username']);
    if (((empty ($msgtext) OR empty ($subject)) OR strlen ($msgtext) <= 5))
    {
      $error = 'Don\'t leave any fields blank.';
    }

    if (empty ($error))
    {
      $to = $email['email'];
      //$msendmail = sent_mail ($email, $subject, $msgtext, 'sendmail', FALSE);
	  
	  $format = "html";
	  $text_message = "";
	  $msendmail = my_mail($email, $subject, $msgtext, "", "", "", false, $format, $text_message);
	  
	  
	  
      if ($msendmail)
      {
        $error = '<font color="darkgreen" size="2"><b>The message has been sent.</b></font>';
      }
      else
      {
        $error = '<font color="red" size="2"><b>Unable send mail!</b></font>';
      }
    }
  }

  stdhead ('Send Mail', true, '', '');
  if (!empty ($error))
  {
    echo '
		<table border="0" cellspacing="0" cellpadding="4" class="" width="100%">
		<tr><td class="thead">Status</td></tr>
		<tr><td>' . $error . '</tr></td>
		</table><br />';
  }

  if (isset ($prvp))
  {
    echo $prvp;
  }

  echo '
<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
<input type="hidden" name="act" value="sendmail">';
  //_form_header_open_ ('Send Mail');
  
  
   echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Send Mail
	</div>
	 </div>
		</div>';
  
  
  
  echo '
  
  <div class="container mt-3">
  <div class="card">
  <div class="card-body">
   
  
  
  <tr><td>Email</td><td>
  
 
  <input type="text" name="email" value="' . $email . '" class="form-control" />
  
  
  </td></tr>';
  echo '</br><tr><td>Subject</td><td>
  
  
  <input type="text" name="subject" value="' . htmlspecialchars_uni ($_POST['subject']) . '" class="form-control" />
  
  
  
  </td></tr>';
  echo '</br><tr><td valign="top">Message</td><td>
  
  
  <div class="mb-3">
  <textarea class="form-control" name="message" rows="10" id="message">' . htmlspecialchars_uni ($_POST['message']) . '</textarea>
</div>
  
  
  
  </td></tr>';
  echo '<tr>
  <td colspan="2" align="center">
  <input name="submit" class="btn btn-primary" value="Send" tabindex="3" accesskey="s" type="submit" onclick="ts_show(\'loading-layer\')"> <input type="reset" class="btn btn-primary" value="reset"> ' . $externalpreview . '</td>
  </tr>
  

 </div>
 </div>
 </div>
 
  </form>

';
  
  stdfoot ();
?>
