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

  define ('IT_VERSION', '0.3 by xam');
  include_once INC_PATH . '/functions_ratio.php';
  $tree = (isset ($_POST['tree']) ? $_POST['tree'] : (isset ($_GET['tree']) ? $_GET['tree'] : ''));
  $error = false;
  stdhead ('Invite Tree', false);
  //_form_header_open_ ('Invite Tree');
  
  
  
  echo '
  
   <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Invite Tree
	</div>
	 </div>
		</div>';
  
  
  if (empty ($tree))
  {
    $str = '	
	
	<div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
	
	<form method="post" action="' . $_this_script_ . '">
	<tr><td>User ID or Username: <label><input type="text" name="tree" class="form-control"></label>
	<input type="submit" value="Get Invite Tree" class="btn btn-primary"></td></tr>
	</form>
	
	</div>
 </div>
 </div>';
 
 
  }
  else
  {
    if (preg_match ('' . '^[0-9]+$', $tree))
    {
      $id = intval ($tree);
    }
    else
    {
      $sql = $db->sql_query ('SELECT id FROM users WHERE username = ' . $db->sqlesc ($tree));
      if ($db->num_rows ($sql) == 0)
      {
        stdmsg ('Error', 'No user found with this name.');
        $error = true;
      }

      $user = mysqli_fetch_assoc ($sql);
      $id = intval ($user['id']);
    }

    if (!$error)
    {
      $firstquery = $db->sql_query ('SELECT username, id, uploaded, downloaded, invited_by FROM users WHERE status = \'confirmed\' AND invited_by = ' . $db->sqlesc ($id) . ' AND invited_by > 0 ORDER BY username');
      $str = '';
      if ($db->num_rows ($firstquery) == 0)
      {
        $str .= '<tr><td>There is no invite tree for this user.</td></tr>';
      }
      else
      {
        while ($arr = mysqli_fetch_assoc ($firstquery))
        {
          if (0 < $arr['downloaded'])
          {
            $ratio = $arr['uploaded'] / $arr['downloaded'];
            $ratio = number_format ($ratio, 2);
            $color = get_ratio_color ($ratio);
            if ($color)
            {
              $ratio = '' . '(<font color=' . $color . '>' . $ratio . '</font>)';
            }
          }
          else
          {
            if (0 < $arr['uploaded'])
            {
              $ratio = '(Inf.)';
            }
            else
            {
              $ratio = '(---)';
            }
          }

          $str .= '' . '
		  
		  
		   <div class="container mt-3">
 
  <div class="card">
   
  <div class="card-body">
		  
		  <tr>
		  <td>&diams;<a href=' . $BASEURL . '/userdetails.php?id=' . $arr['id'] . '><b><u>' . $arr['username'] . '</u></b></a> ' . $ratio;
          $secondquery = $db->sql_query ('SELECT DISTINCT username, id, invited_by  FROM users WHERE invited_by = ' . $db->sqlesc ($arr['id']) . ' AND status=\'confirmed\'');
          while ($arr2 = mysqli_fetch_assoc ($secondquery))
          {
            if (0 < $arr2['invited_by'])
            {
              $user1 = '' . '<br />&nbsp;&nbsp;&nbsp;-<a href=' . $BASEURL . '/userdetails.php?id=' . $arr2['id'] . '>' . $arr2['username'] . '</a>';
            }
            else
            {
              $user1 = '';
            }

            $str .= $user1;
            $thirdquery = $db->sql_query ('SELECT DISTINCT username, id, invited_by FROM users WHERE invited_by = ' . $db->sqlesc ($arr2['id']) . ' AND status=\'confirmed\'');
            while ($arr3 = mysqli_fetch_assoc ($thirdquery))
            {
              if (0 < $arr3['invited_by'])
              {
                $user2 = '' . '<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-<a href=' . $BASEURL . '/userdetails.php?id=' . $arr3['id'] . '>' . $arr3['username'] . '</a>';
              }
              else
              {
                $user2 = '';
              }

              $str .= $user2;
            }
          }

          $str .= '</td></tr>';
        }
      }

      $str .= '</div>
 </div>
 </div>';
    }
  }

  echo $str;
  unset ($str);
  //_form_header_close_ ();
  stdfoot ();
?>
