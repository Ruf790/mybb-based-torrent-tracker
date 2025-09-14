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

  $ip = (isset ($_POST['ip']) ? htmlspecialchars (trim ($_POST['ip'])) : (isset ($_GET['ip']) ? htmlspecialchars (trim ($_GET['ip'])) : ''));
  if (!empty ($ip))
  {
    $nip = ip2long ($ip);
    if ($nip == 0 - 1)
    {
      $msg = '<font color=\'red\'>Bad IP!</font>';
    }

    require_once INC_PATH . '/functions_isipbanned.php';
    if (isipbanned ($ip))
    {
      $msg = '<font color=\'red\'>The IP address <b>' . $ip . '</b> is banned.</font>';
    }
    else
    {
      $msg = '<font color=\'green\'>The IP address <b>' . $ip . '</b> is not banned.</font>';
    }
  }

  stdhead ('Test IP');
  if (!empty ($msg))
  {
    //_form_header_open_ ('Results: ' . $ip);
	
	
	 
  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Results: ' . $ip.'
	</div>
	 </div>
		</div>';
	
	
	
    echo '<tr><td>' . $msg . '</td></tr>';
    //_form_header_close_ ();
    echo '<br />';
  }

  //_form_header_open_ ('Test IP address');
  
  
   
  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Test IP address
	</div>
	 </div>
		</div>';
  
  
  
  echo '
  
  <div class="container mt-3">
  <div class="card">
  <div class="card-body">
  
<form method=post action="' . $_this_script_ . '">
<tr><td>IP address <label><input type=text name=ip class="form-control"></label> <input type=submit class="btn btn-primary" value=test></td></tr>
</form>

</div>
</div>
</div>
';
  
  stdfoot ();
?>
