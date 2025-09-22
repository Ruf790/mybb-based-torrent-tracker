<?php
/*****************************************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet, Vinson, MrDecoder, Fynnon             */
/*****************************************************************/

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<div class="alert alert-danger"><b>Error!</b> Direct access to this file is not allowed.</div>');
}

define('IN_MYBB', 1);
define('MR_VERSION', 'v0.4 by xam');

require_once INC_PATH . '/datahandler.php';

$do = $_GET['do'] ?? $_POST['do'] ?? '';

if ($do === 'request_reseed_final') {
    $requestfrom = $_POST['requestfrom'] ?? '';
    $sender      = (int)($_POST['sender'] ?? 0);
    $postedtorrents = $_POST['torrents'] ?? '';

    if (!empty($postedtorrents)) {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($subject && $message) {

            $queryStr = ($requestfrom === 'owner') ?
                "SELECT t.owner, t.name, t.id, u.username
                 FROM torrents t
                 INNER JOIN users u ON t.owner=u.id
                 WHERE t.id IN ($postedtorrents) AND ts_external != 'yes' AND seeders = 0" :
                "SELECT s.userid as owner, s.torrentid as id, t.name, u.username
                 FROM snatched s
                 INNER JOIN torrents t ON s.torrentid=t.id
                 INNER JOIN users u ON s.userid = u.id
                 WHERE s.finished = 'yes' AND s.torrentid IN ($postedtorrents)";

            $query = $db->sql_query($queryStr);
            require_once INC_PATH . '/functions_pm.php';

            while ($torrent = $db->fetch_array($query)) {
                $torrenturl = '[url=' . $BASEURL . '/' . get_torrent_link($torrent['id']) . ']' . $torrent['name'] . '[/url]';
                $msg = str_replace(['{username}', '{torrentname}'], [$torrent['username'], $torrenturl], $message);

                send_pm([
                    'subject' => $db->escape_string($subject),
                    'message' => $db->escape_string($msg),
                    'touid'   => $torrent['owner']
                ], $sender, true);
            }

            if (($_POST['doubleupload'] ?? '') === 'yes') {
                $db->sql_query("UPDATE torrents SET doubleupload='yes' WHERE id IN ($postedtorrents)");
            }

           echo '<div class="alert alert-success">Reseed requests sent successfully!</div>';
		   
	   
			
        }
    }
}

// ---------- Display reseed request form ----------
if ($do === 'request_reseed') {
    $torrents = $_POST['torrents'] ?? [];
    $implode = implode(',', $torrents);

    require 'include/staff_languages.php';
    stdhead('Request Reseed for Weak Torrents');
	
	
	
	
	 echo '
		<script type="text/javascript">
			function TSdoubleupload()
			{
				whatselected = document.forms[\'reseed\'].elements[\'doubleupload\'].value;
				TSnewinput = "\\nPlease Note: Once you start to Re-seed this torrent, you will get Double Upload Credits!";
				if (whatselected == "yes")
				{					
					document.forms[\'reseed\'].elements[\'message\'].focus();
					document.forms[\'reseed\'].elements[\'message\'].value =					
					document.forms[\'reseed\'].elements[\'message\'].value + TSnewinput;
					document.forms[\'reseed\'].elements[\'message\'].focus();
				}
				else
				{
					var str = document.forms[\'reseed\'].elements[\'message\'].value;
					var TSnewtext = str.replace(TSnewinput, "");
					document.forms[\'reseed\'].elements[\'message\'].value = TSnewtext;	
				}
			}
		</script>
		<form method="post" action="' . $_this_script_ . '" name="reseed">
		<input type="hidden" name="do" value="request_reseed_final">
		<input type="hidden" name="torrents" value="' . $implode . '">
		';
      
	  
	  
	
	
	
	
	

    ?>

    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Request Reseed for Weak Torrents</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= $_this_script_ ?>" name="reseed">
                    <input type="hidden" name="do" value="request_reseed_final">
                    <input type="hidden" name="torrents" value="<?= $implode ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= $mass_reseed['message']['subject'] ?? '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Message</label>
                        <textarea name="message" class="form-control" rows="8" required><?= $mass_reseed['message']['body'] ?? '' ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Double Upload</label>
                            <select class="form-select" name="doubleupload" onchange="javascript:TSdoubleupload()">
                                <option value="yes">YES</option>
                                <option value="no" selected>NO</option>
                            </select>
                            <small class="text-muted">Users will receive double upload credits if selected.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Sender</label>
                            <select class="form-select" name="sender">
                                <option value="0">System</option>
                                <option value="<?= $CURUSER['id'] ?>"><?= $CURUSER['username'] ?></option>
                            </select>
                            <small class="text-muted">Do not change {username} or {torrentname} tags.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Request From</label>
                            <select class="form-select" name="requestfrom">
                                <option value="owner">Uploader Only</option>
                                <option value="all">All Snatched Users</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success me-2">Request Reseed</button>
                        <button type="reset" class="btn btn-secondary">Reset Form</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

   

    <?php
    stdfoot();
    exit;
}

// ---------- Show weak torrents ----------
stdhead('Request Reseed for Weak Torrents');

$res = $db->sql_query("SELECT t.id, t.name, t.seeders, t.leechers, t.times_completed, t.added, t.owner, u.username, u.usergroup
                       FROM torrents t
                       LEFT JOIN users u ON t.owner=u.id
                       WHERE t.ts_external!='yes' AND t.seeders=0
                       ORDER BY t.added DESC");

$postedtorrents = !empty($postedtorrents) ? explode(',', $postedtorrents) : [];

?>

<div class="container mt-4">
    <form method="post" action="<?= $_this_script_ ?>" name="reseed">
        <input type="hidden" name="do" value="request_reseed">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Weak Torrents</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Added</th>
                            <th>Owner</th>
                            <th>Seeders</th>
                            <th>Leechers</th>
                            <th>Snatched</th>
                            <th class="text-center"><input name="allbox" type="checkbox" class="form-check-input checkall" value="1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($db->num_rows($res) > 0): ?>
                            <?php while ($torrent = $db->fetch_array($res)): ?>
                                <tr>
                                    <td>
                                        <a href="<?= $BASEURL . '/' . get_torrent_link($torrent['id']) ?>"><?= $torrent['name'] ?></a>
                                        <a href="<?= $BASEURL ?>/upload.php?id=<?= $torrent['id'] ?>" title="Edit Torrent"><i class="fa-solid fa-pen-to-square text-primary ms-2"></i></a>
                                        <a href="<?= $BASEURL ?>/admin/index.php?act=fastdelete&id=<?= $torrent['id'] ?>" title="Delete Torrent"><i class="fa-solid fa-trash-can text-danger ms-2"></i></a>
                                        <?= in_array($torrent['id'], $postedtorrents) ? '<span class="badge bg-success ms-2">Re-seed request sent!</span>' : '' ?>
                                    </td>
                                    <td class="text-center"><?= my_datee($dateformat, $torrent['added']) . ' ' . my_datee($timeformat, $torrent['added']) ?></td>
                                    <td class="text-center"><a href="<?= $BASEURL . '/' . get_profile_link($torrent['owner']) ?>"><?= format_name($torrent['username'], $torrent['usergroup']) ?></a></td>
                                    <td class="text-center"><?= ts_nf($torrent['seeders']) ?></td>
                                    <td class="text-center"><?= ts_nf($torrent['leechers']) ?></td>
                                    <td class="text-center"><a href="<?= $BASEURL ?>/viewsnatches.php?id=<?= $torrent['id'] ?>"><?= ts_nf($torrent['times_completed']) ?></a></td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input"  name="torrents[]" value="<?= $torrent['id'] ?>"></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">No weak torrents found!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="text-end">
                    <button type="submit" class="btn btn-success">Request Re-seed for Selected Torrents</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php
stdfoot();
?>
