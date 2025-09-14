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

  stdhead ('Mass invite');
  $selectbox = _selectbox_ (NULL, 'usergroup');
  if ($_POST['doit'] == 'yes')
  {
    $amount = 0 + $_POST['amount'];
    $type = $_POST['type'];
    $allowed_type = array ('-', '+');
    if (!in_array ($type, $allowed_type))
    {
      $type = '+';
    }

    $query = 'enabled=\'yes\' AND status=\'confirmed\'';
    $usergroup = $_POST['usergroup'];
    if (($usergroup == '-' OR !is_valid_id ($usergroup)))
    {
      $usergroup = '';
    }

    if ($usergroup)
    {
      $query .= '' . ' AND usergroup=' . $usergroup;
    }

    ($db->sql_query ('' . 'UPDATE users SET invites = invites ' . $type . ' ' . $amount . ' WHERE ' . $query));
    stdmsg ('Message', 'Done...', true, 'success');
    stdfoot ();
    exit ();
  }

 
  echo '
  
  <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Mass Invite
	</div>
	 </div>
		</div>';
  
  
  
  
  
  echo '
  
  <div class="container mt-3">
   
  <div class="card">
            
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Amount</th>
        <th>Usergroup</th>
        <th>Type</th>
      </tr>
    </thead>
  
  
  
  ';
  echo '<form action="';
  echo $_this_script_;
  echo '" method="post">
<tr>
<td align="left">
<input type = "hidden" name = "doit" value = "yes" />
<input type="text" name="amount" value="5" size="5" class="form-control">
</td>
<td align="left">
';
  echo $selectbox;
  echo '</td>
<td align="left">
';
  echo '<s';
  echo 'elect class="form-select form-select-sm border pe-5 w-auto" name=type>
<option value="0" style="color: gray;">(Select invite type - / +)</option>
<option value="+">+</option>
<option value="-">-</option>
</select> 
</br>
<input type="submit" value="Update Users" class="btn btn-primary" /></td>
</tr>
</table></div></div>
</form>
';
  stdfoot ();
?>
