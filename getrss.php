<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  define ('R_VERSION', 'v1.7');
  require_once 'global.php';
  
  if (!isset($CURUSER) || isset($CURUSER) && $CURUSER["id"] == 0) 
  {
    print_no_permission();
  }
  
  
  gzip ();

  $lang->load ('getrss');
  $allowed_timezones = array ('-12', '-11', '-10', '-9', '-8', '-7', '-6', '-5', '-4', '-3.5', '-3', '-2', '-1', '0', '1', '2', '3', '3.5', '4', '4.5', '5', '5.5', '6', '7', '8', '9', '9.5', '10', '11', '12');
  $allowed_showrows = array ('5', '10', '20', '30', '40', '50');
  if ($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    $_queries_ = array ();
    $link = $BASEURL . '/rss.php?secret_key=' . $CURUSER['passkey'] . '&';
    if ($_POST['feedtype'] == 'download')
    {
      $_queries_[] = 'feedtype=download';
    }
    else
    {
      $_queries_[] = 'feedtype=details';
    }

    if ((isset ($_POST['timezone']) AND in_array ($_POST['timezone'], $allowed_timezones, 1)))
    {
      $_queries_[] = 'timezone=' . (int)$_POST['timezone'];
    }
    else
    {
      $_queries_[] = 'timezone=1';
    }

    if ((isset ($_POST['showrows']) AND in_array ($_POST['showrows'], $allowed_showrows, 1)))
    {
      $_queries_[] = 'showrows=' . (int)$_POST['showrows'];
    }
    else
    {
      $_queries_[] = 'showrows=20';
    }

    if (isset ($_POST['showall']))
    {
      $_queries_[] = 'categories=all';
    }
    else
    {
      $sqlquery = $db->sql_query ('SELECT id FROM categories WHERE type = \'c\'');
      while ($res = mysqli_fetch_assoc ($sqlquery))
      {
        if ($_POST['cat' . $res['id'] . ''] == 'yes')
        {
          if (!is_array ($_POST['cat']))
          {
            $_POST['cat'] = array ();
          }

          array_push ($_POST['cat'], $res['id']);
          continue;
        }
      }

      if (isset ($_POST['cat']))
      {
        $_queries_[] = 'categories=' . implode (',', (array)$_POST['cat']);
      }
      else
      {
        $_queries_[] = 'categories=all';
      }
    }

    $__queries = implode ('&', $_queries_);
    if ($__queries)
    {
      $link .= $__queries;
    }

    stdhead ($lang->getrss['title']);
    echo '
	
	<div class="container mt-3">
  <div class="card">
    <div class="card-header fw-bold">
     '.$lang->getrss['done2'].'
    </div>
    <div class="card-body">
      <div class="border p-2 bg-light" style="overflow:auto;">
        <strong>'.htmlspecialchars($link) .'</strong>
      </div>
    </div>
  </div>
</div>
	';
	
    stdfoot ();
    exit ();
  }

  stdhead ($lang->getrss['title']);
  include_once INC_PATH . '/functions_category2.php';
  $catoptions = ts_category_list2 (2, 'rss');



 echo '<form method="post" action="/getrss.php" name="rss">
    

      
      '.$catoptions.'


 <div class="container my-4">
      <!-- Feed Type -->
      <div class="card mb-4">
        <div class="card-header fw-bold text-primary">Feed Type:</div>
        <div class="card-body">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="feedtype" value="details" checked>
            <label class="form-check-label">Web Link</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="feedtype" value="download">
            <label class="form-check-label">Download Link</label>
          </div>
        </div>
      </div>

      <!-- Timezone & Rows Per Page -->
      <div class="card mb-4">
        <div class="card-header fw-bold text-primary">Settings</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="timezone" class="form-label"><strong>Select Your TimeZone:</strong></label>
              <select class="form-select" name="timezone" id="timezone">
                <!-- timezone options (unchanged for brevity) -->
                <option value="-12">(GMT -12:00) Eniwetok, Kwajalein</option>
                <option value="-11">(GMT -11:00) Midway Island, Samoa</option>
                <option value="-10">(GMT -10:00) Hawaii</option>
                <option value="-9">(GMT -9:00) Alaska</option>
                <option value="-8">(GMT -8:00) Pacific Time (US & Canada)</option>
                <option value="-7">(GMT -7:00) Mountain Time (US & Canada)</option>
                <option value="-6">(GMT -6:00) Central Time (US & Canada), Mexico City</option>
                <option value="-5">(GMT -5:00) Eastern Time (US & Canada), Bogota, Lima</option>
                <option value="-4">(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
                <option value="-3.5">(GMT -3:30) Newfoundland</option>
                <option value="-3">(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
                <option value="-2">(GMT -2:00) Mid-Atlantic</option>
                <option value="-1">(GMT -1:00 hour) Azores, Cape Verde Islands</option>
                <option value="0">(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
                <option value="1">(GMT +1:00 hour) Brussels, Copenhagen, Madrid, Paris</option>
                <option value="2">(GMT +2:00) Kaliningrad, South Africa</option>
                <option value="3">(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
                <option value="3.5">(GMT +3:30) Tehran</option>
                <option value="4">(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
                <option value="4.5">(GMT +4:30) Kabul</option>
                <option value="5">(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
                <option value="5.5">(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
                <option value="6">(GMT +6:00) Almaty, Dhaka, Colombo</option>
                <option value="7">(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
                <option value="8">(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
                <option value="9">(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
                <option value="9.5">(GMT +9:30) Adelaide, Darwin</option>
                <option value="10">(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
                <option value="11">(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
                <option value="12">(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="showrows" class="form-label"><strong>Rows Per Page:</strong></label>
              <select class="form-select" name="showrows" id="showrows">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="40">40</option>
                <option value="50">50</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="row">
          <div class="col-sm-6"><p class="float-end">
          
		  <button type="submit" class="btn btn-primary">Generate RSS link</button></p>
		  
		  </div>
	  </div>
	  
	  
	  </div>
	  
	  
	  
	  
	  
	  
	  
	  

    
  </form>';




  

  stdfoot ();
?>
