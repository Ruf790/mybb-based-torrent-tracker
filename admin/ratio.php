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

  $action = htmlspecialchars ($_POST['action']);
  if ($action == 'update')
  {
    if ((($_POST['username'] == '' OR $_POST['uploaded'] == '') OR $_POST['downloaded'] == ''))
    {
      stderr ('Error, Missing form data.');
    }

    $username = $db->sqlesc ($_POST['username']);
    $uploaded = $db->sqlesc ($_POST['uploaded']);
    $downloaded = $db->sqlesc ($_POST['downloaded']);
    ($db->sql_query ('' . 'UPDATE users SET uploaded=' . $uploaded . ', downloaded=' . $downloaded . ' WHERE username=' . $username));
    $res = $db->sql_query ('' . 'SELECT id FROM users WHERE username=' . $username);
    $arr = mysqli_fetch_row ($res);
    if (!$arr)
    {
      stderr ('Error,Unable to update account.');
    }

    header ('' . 'Location: ' . $BASEURL . '/userdetails.php?id=' . $arr['0']);
    exit ();
  }
  else
  {
    if ($action == 'calculate')
    {
      $value = _calculate_ ($_POST['value']);
    }
  }

  stdhead ('Update Users Ratio');
 // _form_header_open_ ('Update Users Ratio');
 
 
  echo '
 
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Update Users Ratio
	</div>
	 </div>
		</div>';
 
 
 
  echo '
  
   <div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
  
  
<form method="post" action="' . $_this_script_ . '">
<input type=hidden name=action value=update>
<table border=0 class=tborder cellspacing=0 cellpadding=5 width=100%>
<tr><td class=rowhead>User name</td><td>

<label>
<input type=text class="form-control" name=username>
</label>

</td></tr>
<tr><td class=rowhead>Uploaded</td><td>

<label>
<input type=uploaded class="form-control" name=uploaded>
</label>

</td></tr>
<tr><td class=rowhead>Downloaded</td><td>

<label>
<input type=downloaded class="form-control" name=downloaded> 
</label>

<input type=submit value="Update" class="btn btn-primary"></td></tr>


</div>
 </div>
 </div>

</form>';
  
  
  
  echo '<br />';
 // _form_header_open_ ('Calculate');
 
 

 
 
    echo '<form method="post" action="' . $_this_script_ . '">
<input type=hidden name=action value=calculate>';
  echo '
  
 
  <div class="card">
 
  
   
  
  

<tr><td class=rowhead>Value</td><td>

<label>
<input type=text name=value class="form-control">
</label>

<input type=submit value="Calculate" class="btn btn-primary">
</td></tr>' . (isset ($value) ? '<tr><td class=rowhead>Result:</td><td>' . $value . '</tr></td>' : '') . '

</div>





';
  
  
  
  stdfoot ();
  unset ($value);
?>
