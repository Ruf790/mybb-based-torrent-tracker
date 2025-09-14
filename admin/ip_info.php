<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  class get_by_ip
  {
    var $ip = null;
    var $host = null;
    var $netname = null;
    var $country = null;
    var $person = null;
    var $address = null;
    var $phone = null;
    var $email = null;
    var $msg = null;
    var $server = null;
    function get_by_ip ($addr = '194.44.39.135')
    {
      $this->ip = $addr;
      $this->host = gethostbyaddr ($this->ip);
      if (!$this->server)
      {
        $this->server = 'whois.arin.net';
      }

      if (!$this->ip == gethostbyname ($this->host))
      {
        $msg .= 'Can\'t IP Whois without an IP address.';
      }
      else
      {
        if (!$sock = fsockopen ($this->server, 43, $num, $error, 20))
        {
          unset ($sock);
          $msg .= 'Timed-out connecting to ' . $this->server . ' (port 43)';
        }
        else
        {
          fputs ($sock, $this->ip . '
');
          while (!feof ($sock))
          {
            $buffer .= fgets ($sock, 10240);
          }

          fclose ($sock);
        }

        if (preg_match ('RIPE.NET', $buffer))
        {
          $nextServer = 'whois.ripe.net';
        }
        else
        {
          if (preg_match ('whois.apnic.net', $buffer))
          {
            $nextServer = 'whois.apnic.net';
          }
          else
          {
            if (preg_match ('nic.ad.jp', $buffer))
            {
              $nextServer = 'whois.nic.ad.jp';
              $extra = '/e';
            }
            else
            {
              if (preg_match ('whois.registro.br', $buffer))
              {
                $nextServer = 'whois.registro.br';
              }
            }
          }
        }

        if ($nextServer)
        {
          $buffer = '';
          if (!$sock = fsockopen ($nextServer, 43, $num, $error, 10))
          {
            unset ($sock);
            $msg .= '' . 'Timed-out connecting to ' . $nextServer . ' (port 43)';
          }
          else
          {
            fputs ($sock, $this->ip . $extra . '
');
            while (!feof ($sock))
            {
              $buffer .= fgets ($sock, 10240);
            }

            fclose ($sock);
          }
        }

        $msg .= nl2br ($buffer);
      }

      $msg .= '</blockquote></p>';
      $this->msg = str_replace (' ', '&nbsp;', $msg);
      $tmparr = explode ('<br />', $msg);
      foreach ($tmparr as $value)
      {
        $tmpvalue = explode (':', $value);
        $key = trim ($tmpvalue[0]);
        $znach = trim ($tmpvalue[1]);
        if ($key == 'country')
        {
          $this->country = $znach;
          continue;
        }
        else
        {
          if ($key == 'netname')
          {
            $this->netname = $znach;
            continue;
          }
          else
          {
            if ($key == 'person')
            {
              $this->person .= $znach . ' ';
              continue;
            }
            else
            {
              if ($key == 'address')
              {
                $this->address .= $znach . ' ';
                continue;
              }
              else
              {
                if ($key == 'phone')
                {
                  $this->phone = $znach;
                  continue;
                }
                else
                {
                  if ($key == 'e-mail')
                  {
                    $this->email = $znach;
                    continue;
                  }

                  continue;
                }

                continue;
              }

              continue;
            }

            continue;
          }

          continue;
        }
      }

    }

    function reset ()
    {
      $this->ip = '';
      $this->host = '';
      $this->netname = '';
      $this->country = '';
      $this->person = '';
      $this->address = '';
      $this->phone = '';
      $this->email = '';
      $this->msg = '';
      $this->server = '';
    }
  }

  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  gzip ();
  
  loggedinorreturn ();
  maxsysop ();
  define ('IP_VERSION', 'v.0.1 by xam');
  if (!is_valid_id ($_GET['userid']))
  {
    print_no_permission ();
  }

  $query = $db->sql_query ('SELECT u.ip,u.id,u.username,u.country,c.name as countryname, c.flagpic FROM users u LEFT JOIN countries c ON (u.country=c.id) WHERE u.id = ' . $db->sqlesc ($_GET['userid']));
  if ($db->num_rows ($query) == 0)
  {
    stderr ('Error', 'No User with this ID!');
  }

  $user = mysqli_fetch_assoc ($query);
  stdhead ('IP INFO - ' . IP_VERSION);
  _form_header_open_ ('IP INFO - ' . IP_VERSION);
  echo '<tr><td class="subheader" width="15%" align="center">Username</td><td class="subheader" width="15%" align="center">Selected Country</td><td class="subheader" width="70%" align="left">IP Info</td></tr>';
  $ipdata = new get_by_ip ($user['ip']);
  echo '<tr><td  align="center">' . $user['username'] . '</td><td  align="center">' . $user['countryname'] . '</td><td  align="left">';
  echo $ipdata->ip . '<br />';
  echo $ipdata->host . '<br />';
  echo $ipdata->netname . '<br />';
  echo $ipdata->country . '<br />';
  echo $ipdata->person . '<br />';
  echo $ipdata->address . '<br />';
  echo $ipdata->phone . '<br />';
  echo $ipdata->email . '<br />';
  echo '</td></tr>';
  _form_header_close_ ();
  stdfoot ();
?>
