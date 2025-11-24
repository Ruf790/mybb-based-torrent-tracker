<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function rebuild_announce_settings ()
  {
    clearstatcache ();
    $var_array = unserialize (file_get_contents (CONFIG_DIR . '/MAIN'));
    extract ($var_array, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array1 = unserialize (file_get_contents (CONFIG_DIR . '/WAITSLOT'));
    extract ($var_array1, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array2 = unserialize (file_get_contents (CONFIG_DIR . '/KPS'));
    extract ($var_array2, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array3 = unserialize (file_get_contents (CONFIG_DIR . '/TWEAK'));
    extract ($var_array3, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array4 = unserialize (file_get_contents (CONFIG_DIR . '/DATABASE'));
    extract ($var_array4, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array5 = unserialize (file_get_contents (CONFIG_DIR . '/EXTRA'));
    extract ($var_array5, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array6 = unserialize (file_get_contents (CONFIG_DIR . '/SECURITY'));
    extract ($var_array6, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array7 = unserialize (file_get_contents (CONFIG_DIR . '/THEME'));
    extract ($var_array7, EXTR_PREFIX_SAME, 'wddx');
    clearstatcache ();
    $var_array8 = unserialize (file_get_contents (CONFIG_DIR . '/ANNOUNCE'));
    extract ($var_array8, EXTR_PREFIX_SAME, 'wddx');
    if (!file_exists (INC_PATH . '/config_announce.php'))
    {
      $mode = 'x';
    }
    else
    {
      $mode = 'w';
    }

    $settings = '';
    $settings .= '$announce_interval = ' . $announce_interval . ';';
    $settings .= '$BASEURL = \'' . $BASEURL . '\';';
    $settings .= '$SITENAME = \'' . $SITENAME . '\';';
    $settings .= '$waitsystem = \'' . $waitsystem . '\';';
    $settings .= '$maxdlsystem = \'' . $maxdlsystem . '\';';
    $settings .= '$mysql_host = \'' . $mysql_host . '\';';
    $settings .= '$mysql_user = \'' . $mysql_user . '\';';
    $settings .= '$mysql_pass = \'' . $mysql_pass . '\';';
    $settings .= '$mysql_db = \'' . $mysql_db . '\';';
    $settings .= '$nc = \'' . $nc . '\';';
    $settings .= '$privatetrackerpatch = \'' . $privatetrackerpatch . '\';';
    $settings .= '$bannedclientdetect = \'' . $bannedclientdetect . '\';';
    $settings .= '$allowed_clients = \'' . $allowed_clients . '\';';
    $settings .= '$snatchmod = \'' . $snatchmod . '\';';
    $settings .= '$announce_actions = \'' . $announce_actions . '\';';
    $settings .= '$max_rate = \'' . $max_rate . '\';';
    $settings .= '$waitsystemtype = \'' . $waitsystemtype . '\';';
    $settings .= '$ratio1 = \'' . $ratio1 . '\';';
    $settings .= '$ratio2 = \'' . $ratio2 . '\';';
    $settings .= '$ratio3 = \'' . $ratio3 . '\';';
    $settings .= '$ratio4 = \'' . $ratio4 . '\';';
    $settings .= '$ratio5 = \'' . $ratio5 . '\';';
    $settings .= '$ratio6 = \'' . $ratio6 . '\';';
    $settings .= '$ratio7 = \'' . $ratio7 . '\';';
    $settings .= '$ratio8 = \'' . $ratio8 . '\';';
    $settings .= '$upload1 = \'' . $upload1 . '\';';
    $settings .= '$upload2 = \'' . $upload2 . '\';';
    $settings .= '$upload3 = \'' . $upload3 . '\';';
    $settings .= '$upload4 = \'' . $upload4 . '\';';
    $settings .= '$upload5 = \'' . $upload5 . '\';';
    $settings .= '$upload6 = \'' . $upload6 . '\';';
    $settings .= '$upload7 = \'' . $upload7 . '\';';
    $settings .= '$upload8 = \'' . $upload8 . '\';';
    $settings .= '$delay1 = \'' . $delay1 . '\';';
    $settings .= '$delay2 = \'' . $delay2 . '\';';
    $settings .= '$delay3 = \'' . $delay3 . '\';';
    $settings .= '$delay4 = \'' . $delay4 . '\';';
    $settings .= '$slot1 = \'' . $slot1 . '\';';
    $settings .= '$slot2 = \'' . $slot2 . '\';';
    $settings .= '$slot3 = \'' . $slot3 . '\';';
    $settings .= '$slot4 = \'' . $slot4 . '\';';
    $settings .= '$announce_wait = \'' . $announce_wait . '\';';
    $settings .= '$gzipcompress = \'' . $gzipcompress . '\';';
    $settings .= '$defaultlanguage = \'' . $defaultlanguage . '\';';
    $settings .= '$charset = \'' . $charset . '\';';
    $settings .= '$aggressivecheat = \'' . $aggressivecheat . '\';';
    $settings .= '$aggressivecheckip = \'' . $aggressivecheckip . '\';';
    $settings .= '$cache = \'' . $cache . '\';';
    $settings .= '$bdayreward = \'' . $bdayreward . '\';';
    $settings .= '$bdayrewardtype = \'' . $bdayrewardtype . '\';';
    $settings .= '$bonus = \'' . $bonus . '\';';
    $settings .= '$kpsseed = \'' . $kpsseed . '\';';
    $settings .= '$detectbrowsercheats = \'' . $detectbrowsercheats . '\';';
    $settings .= '$checkconnectable = \'' . $checkconnectable . '\';';
    $settings .= '$checkip = \'' . $checkip . '\';';
    $settings = '<' . ('' . '?php #DO NOT EDIT THIS FILE, PLEASE USE THE SETTINGS PANEL!!
if(!defined(\'IN_ANNOUNCE\')) die(\'Hacking attempt!\');' . $settings . '?') . '>';
    $file = @fopen (INC_PATH . '/config_announce.php', $mode);
    @fwrite ($file, $settings);
    @fclose ($file);
  }

  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('AA_VERSION', '0.5 by xam');
  $do = $_POST['do'];
  stdhead ('All Clients');
  require INC_PATH . '/readconfig_announce.php';
  if ((($do == 'save' AND !empty ($_POST['client'])) AND is_array ($_POST['client'])))
  {
    $clients = $_POST['client'];
    $allowedclients = explode (',', $allowed_clients);
    $done = array ();
    foreach ($clients as $client)
    {
      if ((!in_array ($client, $allowedclients, true) AND !in_array ($client, $done, true)))
      {
        $add .= '' . ',' . $client;
        $done[] = $client;
        continue;
      }
    }

    if (isset ($add))
    {
      $allowed_clients = $allowed_clients . $add;
      $ANNOUNCE['announce_actions'] = $announce_actions;
      $ANNOUNCE['aggressivecheat'] = $aggressivecheat;
      $ANNOUNCE['nc'] = $nc;
      $ANNOUNCE['announce_wait'] = $announce_wait;
      $ANNOUNCE['announce_interval'] = $announce_interval;
      $ANNOUNCE['max_rate'] = $max_rate;
      $ANNOUNCE['bannedclientdetect'] = $bannedclientdetect;
      $ANNOUNCE['allowed_clients'] = $allowed_clients;
      $ANNOUNCE['detectbrowsercheats'] = $detectbrowsercheats;
      $ANNOUNCE['checkconnectable'] = $checkconnectable;
      $ANNOUNCE['checkip'] = $checkip;
      require_once INC_PATH . '/functions_writeconfig.php';
      writeconfig ('ANNOUNCE', $ANNOUNCE);
      rebuild_announce_settings ();
    }
  }

 
 
 
 
 
 
print '
<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-users me-2"></i>
                All Active Agents' . (isset($add) ? ' <span class="badge bg-danger ms-2">Saved</span>' : '') . '
            </h5>
        </div>
        <div class="card-body p-0">
            <form method="post" action="' . $_this_script_ . '" id="agentsForm">
                <input type="hidden" name="do" value="save">
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-start ps-4">Client</th>
                                <th class="text-start">Peer ID</th>
                                <th class="text-center">Allowed</th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
';

$allowed_clients = explode(',', $allowed_clients);
$res2 = $db->sql_query('SELECT agent, peer_id FROM peers GROUP BY agent, peer_id');
$agents_count = 0;

while ($arr2 = mysqli_fetch_array($res2)) {
    $userclient = substr(str_replace(' ', '', $arr2['peer_id']), 0, 8);
    $allowed = in_array($userclient, $allowed_clients, true) ? 'checked' : '';
    $agents_count++;
    
    $agent_class = $agents_count % 2 == 0 ? 'table-active' : '';
    
    print '
                            <tr class="' . $agent_class . '">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-desktop text-muted me-3"></i>
                                        <span>' . htmlspecialchars_uni($arr2['agent']) . '</span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <code class="bg-light px-2 py-1 rounded">' . htmlspecialchars_uni($arr2['peer_id']) . '</code>
                                </td>
                                <td class="text-center py-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               value="' . htmlspecialchars_uni($userclient) . '" 
                                               name="client[]" 
                                               id="agent_' . $agents_count . '"
                                               ' . $allowed . '
                                               data-client="' . htmlspecialchars_uni($userclient) . '">
                                        <label class="form-check-label" for="agent_' . $agents_count . '">
                                            ' . ($allowed ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>') . '
                                        </label>
                                    </div>
                                </td>
                            </tr>
    ';
}

print '
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer bg-light border-0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Total agents: ' . $agents_count . '
                        </small>
                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function() {
    const checkboxes = document.querySelectorAll("input[name=\'client[]\']");
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener("change", function() {
            const label = this.parentElement.querySelector("label i");
            if (this.checked) {
                label.className = "fas fa-check text-success";
            } else {
                label.className = "fas fa-times text-danger";
            }
        });
    });
    
    // Подтверждение перед сбросом
    document.querySelector("button[type=\'reset\']").addEventListener("click", function(e) {
        if (!confirm("Are you sure you want to reset all changes?")) {
            e.preventDefault();
        }
    });
});
</script>
';
 
 
 
  
  
  stdfoot ();
?>
