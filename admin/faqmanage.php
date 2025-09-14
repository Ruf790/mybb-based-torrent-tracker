<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function show_faq_errors ()
  {
    global $faq_errors;
    global $lang;
    if (0 < count ($faq_errors))
    {
      $errors = implode ('<br />', $faq_errors);
      echo '
			<table class="main" border="0" cellspacing="0" cellpadding="5" width="100%">
			<tr>
				<td class="thead">
					' . $lang->global['error'] . '
				</td>
			</tr>
			<tr>
				<td>
					<font color="red">
						<strong>
							' . $errors . '
						</strong>
					</font>
				</td>
			</tr>
			</table>
			<br />
		';
    }

  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('TSFAQMANAGE_VERSION', '1.3.2 by xam');
  $lang->load ('faq');
  $do = (isset ($_GET['do']) ? htmlspecialchars_uni ($_GET['do']) : (isset ($_POST['do']) ? htmlspecialchars_uni ($_POST['do']) : ''));
  $subdo = (isset ($_GET['subdo']) ? htmlspecialchars_uni ($_GET['subdo']) : (isset ($_POST['subdo']) ? htmlspecialchars_uni ($_POST['subdo']) : ''));
  $id = (isset ($_GET['id']) ? intval ($_GET['id']) : (isset ($_POST['id']) ? intval ($_POST['id']) : ''));
  $faq_errors = array ();
  stdhead ($lang->faq['faqtitle'], true, '', '');
  
  
  echo  '<script type="text/javascript" src="'.$BASEURL.'/scripts/collapse222222222.js"></script>';
  
  
  
  if ($do == 'view')
  {
    if (!is_valid_id ($id))
    {
      $faq_errors[] = $lang->faq['faqerror'];
    }
    else
    {
      $query = $db->sql_query ('' . 'SELECT a.id,a.name,a.description,b.name as title FROM ts_faq a 
	  LEFT JOIN ts_faq b ON (a.pid=b.id) WHERE a.type = \'2\' AND a.pid = \'' . $id . '\' ORDER By a.disporder ASC');
      if ($db->num_rows ($query) == 0)
      {
        $faq_errors[] = $lang->faq['faqerror'];
      }
      else
      {
        echo '
		
		       <div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->faq['faqtitle'] . '
	</div>
	 </div>
		</div>
		
		
				
				<div class="container mt-3">
                <div class="card">
  
				<table class="tborder" border="0" cellspacing="0" cellpadding="5" width="100%">
					<tr>
						<td>
							';
        $uldone = false;
        while ($faq = $db->fetch_array ($query))
        {
          if (!$uldone)
          {
            echo '
						<ul><strong>' . $faq['title'] . '</strong>';
          }

          echo '
						<li><a href="javascript:collapse' . $faq['id'] . '.slideit()"><font color="red">' . $faq['name'] . '</font></a> 
						<a href="' . $_this_script_ . '&amp;do=edit&amp;id=' . $faq['id'] . '">
						
						
						
						<i class="fa-solid fa-pen-to-square fa-xl" style="color: #0658e5;" alt="Edit" title="Edit"></i>
						
						</a> 
						<a href="' . $_this_script_ . '&amp;do=delete&amp;id=' . $faq['id'] . '" onclick="return confirmdelete()">
						
						<i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;" alt="Delete" title="Delete"></i>
						
						</a></li>
						<div id="faq' . $faq['id'] . '" style="padding: 0px 0px 0px 0px; margin: 0px 0px 0px 15px;">' . $faq['description'] . '<br /></div>
						<script type="text/javascript">				
							var collapse' . $faq['id'] . '=new animatedcollapse("faq' . $faq['id'] . '", 850, true)
						</script>';
          $uldone = true;
        }

        echo '
						</ul>
					</td>
				</tr>
			</table>
			</div>
</div>
			
			<br />';
      }
    }
  }

  if ($do == 'savedisplayorder')
  {
    $orders = $_POST['disporder'];
    if (!is_array ($orders))
    {
      $faq_errors[] = 'Empty FAQ order(s)!';
    }
    else
    {
      foreach ($orders as $id => $order)
      {
        $db->sql_query ('UPDATE ts_faq SET disporder = ' . $db->sqlesc ($order) . ' WHERE id = ' . $db->sqlesc ($id));
      }
    }
  }

  if ($do == 'delete')
  {
    if (!is_valid_id ($id))
    {
      $faq_errors[] = $lang->faq['faqerror'];
    }
    else
    {
      $db->sql_query ('DELETE FROM ts_faq WHERE id = ' . $db->sqlesc ($id));
      $db->sql_query ('DELETE FROM ts_faq WHERE pid = ' . $db->sqlesc ($id));
    }
  }

  if ($do == 'new')
  {
    if ($subdo == 'save')
    {
      print_r ($_POST);
      exit ();
      $name = trim ($_POST['name']);
      $description = trim ($_POST['description']);
      $disporder = intval ($_POST['disporder']);
      if (empty ($name))
      {
        $faq_errors[] = 'Please fill all fields!';
      }
      else
      {
        $db->sql_query ('INSERT INTO ts_faq (type,name,description,disporder) VALUES (\'1\',' . $db->sqlesc ($name) . ',' . $db->sqlesc ($description) . ',' . $db->sqlesc ($disporder) . ')');
        header ('Location: ' . $_this_script_);
        exit ();
      }

      show_faq_errors ();
    }

    $where = array ('Cancel' => $_this_script_);
    echo '
	<form method="post" action="' . $_this_script_ . '">
	<input type="hidden" name="do" value="new">
	<input type="hidden" name="subdo" value="save">
	
	
	<div class="container mt-3">
	<div class="float-end">
			
			' . jumpbutton ($where) . '
			
			</div>
			</div>
			</br>
			</br>
	
	
	<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
			Add New FAQ Item
	</div>
	 </div>
		</div>
		
	
	<div class="container mt-3">
			

			
            <div class="card">
            <div class="card-body">
	
	
	

		
		
		
		<tr>
			<td align="right" valign="top">
				Title
			</td>
			<td align="left">
				<input type="text" class="form-control" name="name" value="' . htmlspecialchars_uni ($name) . '" style="width: 745px;">
			</td>
		</tr>

		
		</br>
		<tr>
			<td align="right" valign="top">
				Description
			</td>
			<td align="left">
				<textarea name="description" class="form-control form-control-sm border" style="height: 300px" id="description">' . htmlspecialchars_uni ($description) . '</textarea>				
			</td>
		</tr>

		
		</br>
		<tr>
			<td align="right" valign="top">
				Display Order
			</td>
			<td align="left">
				<label>
				<input type="text" class="form-control form-control-sm" name="disporder" value="' . $disporder . '" size="4">
				</label>
			</td>
		</tr>

		<tr>
			<td align="center" colspan="3">
				<input type="submit" class="btn btn-primary" value="save"> <input type="reset" class="btn btn-primary" value="reset">
			</td>
		</tr>
	
	</div>
    </div>
    </div>
	
	
	</form>
	
	';
    stdfoot ();
    exit ();
  }

  if ($do == 'add')
  {
    if ($subdo == 'save')
    {
      $name = trim ($_POST['name']);
      $description = trim ($_POST['description']);
      $disporder = intval ($_POST['disporder']);
      $pid = intval ($_POST['pid']);
      if (empty ($name))
      {
        $faq_errors[] = 'Please fill all fields!';
      }
      else
      {
        $db->sql_query ('INSERT INTO ts_faq (type,name,description,disporder,pid) VALUES (\'2\',' . $db->sqlesc ($name) . ',' . $db->sqlesc ($description) . ',' . $db->sqlesc ($disporder) . ',' . $db->sqlesc ($pid) . ')');
        header ('Location: ' . $_this_script_);
        exit ();
      }
    }

    if (!is_valid_id ($id))
    {
      $faq_errors[] = $lang->faq['faqerror'];
      show_faq_errors ();
    }
    else
    {
      ($query = $db->sql_query ('SELECT * FROM ts_faq WHERE type = \'1\''));
      if ($db->num_rows ($query) == 0)
      {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors ();
      }
      else
      {
        show_faq_errors ();
        $categories = '<select name="pid" class="form-select form-select-sm border pe-5 w-auto">';
        while ($faq = $db->fetch_array ($query))
        {
          $categories .= '<option value="' . $faq['id'] . ($id == $faq['id'] ? ' selected="selected"' : '') . '">' . $faq['name'] . '</option>';
        }

        $categories .= '</select>';
        $where = array ('Cancel' => $_this_script_);
        echo '
			<form method="post" action="' . $_this_script_ . '">
			<input type="hidden" name="do" value="add">
			<input type="hidden" name="subdo" value="save">
			<input type="hidden" name="id" value="' . $id . '">
			
			
			
			<div class="container mt-3">
  <div class="float-end">
  ' . jumpbutton ($where) . '
  </div></div>
  </br>
  </br>
			
			
			
			
			
			
			<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		Add Child FAQ Item
	</div>
	 </div>
		</div>
		
		
			
			
			<div class="container mt-3">
            <div class="card">
			
			<table class="tborder" border="0" cellspacing="0" cellpadding="5" width="100%">
				

				<tr>
					<td align="right" valign="top">
						Category
					</td>
					<td align="left">
						' . $categories . '
					</td>
				</tr>

				<tr>
					<td align="right" valign="top">
						Title
					</td>
					<td align="left">
						<input type="text" class="form-control" name="name" value="' . htmlspecialchars_uni ($name) . '" style="width: 745px;">
					</td>
				</tr>

				<tr>
					<td align="right" valign="top">
						Description
					</td>
					<td align="left">
						<textarea class="form-control" style="height: 250px; width: 750px;" name="description" id="description">' . htmlspecialchars_uni ($description) . '</textarea>						
					</td>
				</tr>

				<tr>
					<td align="right" valign="top">
						Display Order
					</td>
					<td align="left">
					    <label>
						<input type="text" class="form-control" name="disporder" value="' . $disporder . '">
						</label>
					</td>
				</tr>

				<tr>
					<td align="center" colspan="3">
						<input type="submit" class="btn btn-primary" value="save"> <input type="reset" class="btn btn-primary" value="reset">
					</td>
				</tr>
			</table>
			
			</div>
            </div>
			
			</form>
			';
      }
    }

    stdfoot ();
    exit ();
  }

  if ($do == 'edit')
  {
    if (($subdo == 'save' AND is_valid_id ($id)))
    {
      $type = intval ($_POST['type']);
      $name = trim ($_POST['name']);
      $description = trim ($_POST['description']);
      $disporder = intval ($_POST['disporder']);
      $pid = intval ($_POST['pid']);
      if ((empty ($name) OR ($type == 2 AND empty ($description))))
      {
        $faq_errors[] = 'Please fill all fields!';
      }
      else
      {
        ($db->sql_query ('UPDATE ts_faq SET type = ' . $db->sqlesc ($type) . ', name = ' . $db->sqlesc ($name) . ', description = ' . $db->sqlesc ($description) . ', disporder=' . $db->sqlesc ($disporder) . ', pid = ' . $db->sqlesc ($pid) . ' WHERE id = ' . $db->sqlesc ($id)));
        header ('Location: ' . $_this_script_);
        exit ();
      }
    }

    if (!is_valid_id ($id))
    {
      $faq_errors[] = $lang->faq['faqerror'];
      show_faq_errors ();
    }
    else
    {
      ($firstquery = $db->sql_query ('SELECT * FROM ts_faq WHERE id = ' . $db->sqlesc ($id)));
      if ($db->num_rows ($firstquery) == 0)
      {
        $faq_errors[] = $lang->faq['faqerror'];
        show_faq_errors ();
      }
      else
      {
        $editfaq = $db->fetch_array ($firstquery);
        show_faq_errors ();
        if ($editfaq['type'] == 2)
        {
          ($query2 = $db->sql_query ('SELECT * FROM ts_faq WHERE type = \'1\' ORDER By disporder ASC'));
          $categories = '				
				<tr>
					<td align="right" valign="top">
						Category
					</td>
					<td align="left">
					<select name="pid" class="form-select form-select-sm border pe-5 w-auto">';
          while ($cat = $db->fetch_array ($query2))
          {
            $categories .= '<option value="' . $cat['id'] . ($editfaq['pid'] == $cat['id'] ? ' selected="selected"' : '') . '">' . $cat['name'] . '</option>';
          }

          $categories .= '
				</select>
				</td>
				</tr>';
        }
        else
        {
          $categories = '<input type="hidden" name="pid" value="' . $editfaq['pid'] . '">';
        }

        $where = array ('Cancel' => $_this_script_);
        echo '
			<form method="post" action="' . $_this_script_ . '">
			<input type="hidden" name="do" value="edit">
			<input type="hidden" name="subdo" value="save">
			<input type="hidden" name="id" value="' . $id . '">
			<input type="hidden" name="type" value="' . $editfaq['type'] . '">
			
			
			
			
            <div class="container mt-3">
			
			<div class="float-end">
			
			' . jumpbutton ($where) . '
			
			</div>
			</br>
			</br>

			
			
			
            <div class="card">
            <div class="card-body">
			
			
			


			
				<tr>
					<td class="thead" colspan="2">
						Edit FAQ Item: ' . htmlspecialchars_uni ($editfaq['name']) . '
					</td>
				</tr>				
				' . $categories . '
				
				
				</br>
				<tr>
					<td align="right" valign="top">
						Title
					</td>
					<td align="left">
						<input  style="width: 745px;"  type="text" class="form-control" name="name" value="' . (!empty ($name) ? htmlspecialchars_uni ($name) : htmlspecialchars_uni ($editfaq['name'])) . '">
					</td>
				</tr>

                </br>
				<tr>
					<td align="right" valign="top">
						Description
					</td>
					<td align="left">						
						<textarea class="form-control form-control-sm border" style="height: 300px" name="description" id="description">' . (!empty ($description) ? htmlspecialchars_uni ($description) : $editfaq['description']) . '</textarea>
					</td>
				</tr>

				</br>
				<tr>
					<td align="right" valign="top">
						Display Order
					</td>
					<td align="left">
						<input type="text" name="disporder" value="' . (!empty ($disporder) ? htmlspecialchars_uni ($disporder) : $editfaq['disporder']) . '" size="4">
					</td>
				</tr>

				<tr>
					<td align="center" colspan="3">
						<input type="submit" class="btn btn-primary" value="save"> <input type="reset" class="btn btn-primary" value="reset">
					</td>
				</tr>
			</div>
            </div>
            </div>
			
			</form>
			
			';
      }
    }

    stdfoot ();
    exit ();
  }

  show_faq_errors ();
  $where = array ('Add New FAQ Item' => $_this_script_ . '&amp;do=new');
  ($query = $db->sql_query ('SELECT disporder, id, name FROM ts_faq WHERE type = \'1\' ORDER By disporder ASC'));
  if (0 < $db->num_rows ($query))
  {
    echo '
	<script type="text/javascript">
		function confirmdelete()
		{
			ht = document.getElementsByTagName("html");
			ht[0].style.filter = "progid:DXImageTransform.Microsoft.BasicImage(grayscale=1)";
			if (confirm("Are you sure you want to delete this FAQ item?"))
			{
				return true;
			}
			else
			{
				ht[0].style.filter = "";
				return false;
			}
		};
	</script>
	<form method="post" action="' . $_this_script_ . '">
	<input type="hidden" name="do" value="savedisplayorder">
	
	
	<div class="container mt-3">
	<div class="float-end">
		' . jumpbutton ($where) . '
	</div>
	</div>
	</br>
	</br>
			
	
		
		
	<div class="container-md">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->faq['faqtitle'] . '
	</div>
	 </div>
		</div>	
		
		
		
		
		
  <div class="container mt-3">
  <div class="card">       
  <table class="table table-hover">
    <thead>
      <tr>
        <th>Title</th>
        <th>Display Order</th>
        <th>Action</th>
      </tr>
    </thead>
		
		
		
		
	';
    while ($faq = $db->fetch_array ($query))
    {
      echo '
		<tr>
			<td>
				<a href="' . $_this_script_ . '&amp;do=view&amp;id=' . $faq['id'] . '">' . $faq['name'] . '</a>
			</td>
			<td align="center">
				<input type="text" class="form-control form-control-sm" name="disporder[' . $faq['id'] . ']" value="' . $faq['disporder'] . '" size="3">
			</td>
			<td align="center">
		<a href="' . $_this_script_ . '&amp;do=edit&amp;id=' . $faq['id'] . '">
		
		<i class="fa-solid fa-pen-to-square fa-xl" style="color: #0658e5;" alt="Edit" title="Edit"></i>
		
		</a> 
		<a href="' . $_this_script_ . '&amp;do=add&amp;id=' . $faq['id'] . '">
		
		
		
		<i class="fa-solid fa-plus fa-xl" style="color: #22ce9a;" alt="Add Child FAQ Item" title="Add Child FAQ Item"></i>
		
		</a>
		<a href="' . $_this_script_ . '&amp;do=delete&amp;id=' . $faq['id'] . '" onclick="return confirmdelete()">
		
		<i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;" alt="Delete" title="Delete"></i>
		
		
		</a>
			</td>
		</tr>';
    }

    echo '
		<tr>
  <td colspan="3" align="center"><input type="submit" class="btn btn-primary btn-sm" value="Save Display Order">
		</tr>
	</table>
	</div>
    </div>
	
	</form>';
  }
  else
  {
    stdmsg ('Error', 'There is no FAQ items yet. Click <a href="' . $_this_script_ . '&amp;do=new">here</a> to create one', false);
  }

  stdfoot ();
?>
