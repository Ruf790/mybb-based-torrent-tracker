<?


  if (!defined ('STAFF_PANEL_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

  define ('AA_VERSION', '0.5 by xam');
  $do = $_POST['do'];
  stdhead ('All Clients');

 
 
 
 
 
 
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
