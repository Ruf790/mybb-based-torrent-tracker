<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

const AA_VERSION   = '0.8';
const AA_ASSET_VER = 1;

global $db, $mybb, $BASEURL, $allowed_clients, $_this_script_;

$aaEsc = static fn(string $s): string => htmlspecialchars_uni($s);

// Post/Redirect/Get: every POST ends with a redirect, so F5 never re-submits
$aaRedirect = static function (string $status) use (&$_this_script_): never {
    $url = html_entity_decode((string)$_this_script_, ENT_QUOTES);
    $sep = str_contains($url, '?') ? '&' : '?';
    header('Location: ' . $url . $sep . 'aa=' . rawurlencode($status), true, 303);
    exit;
};

// Current whitelist as a clean list of prefixes
$aaCurrent = array_values(array_unique(array_filter(
    array_map('trim', explode(',', (string)$allowed_clients)),
    static fn(string $v): bool => $v !== ''
)));

/* ------------------------------------------------------------------ *
 *  Save
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mybb->get_input('do') === 'save') {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        $aaRedirect('csrf');
    }

    $posted = $_POST['client'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }

    $aaNew = [];
    foreach ($posted as $c) {
        if (!is_string($c)) {
            continue;
        }
        // Same normalisation the list uses: no spaces, no commas, max 8 chars
        $c = substr(str_replace([' ', ','], '', $c), 0, 8);
        if ($c !== '' && !in_array($c, $aaNew, true)) {
            $aaNew[] = $c;
        }
    }

    $aaAdded   = array_values(array_diff($aaNew, $aaCurrent));
    $aaRemoved = array_values(array_diff($aaCurrent, $aaNew));

    if ($aaAdded === [] && $aaRemoved === []) {
        $aaRedirect('nochange');
    }

    $result = $db->sql_query_prepared(
        "UPDATE settings SET value = ? WHERE name = 'allowed_clients'",
        [implode(',', $aaNew)]
    );

    if ($result === false) {
        $aaRedirect('dberror');
    }

    $parts = [];
    if ($aaAdded !== [])   { $parts[] = 'allowed: ' . implode(', ', $aaAdded); }
    if ($aaRemoved !== []) { $parts[] = 'blocked: ' . implode(', ', $aaRemoved); }
    write_log('Allowed clients changed by ' . (string)($mybb->user['username'] ?? 'unknown') . ' (' . implode('; ', $parts) . ')');

    $aaRedirect('saved');
}

/* ------------------------------------------------------------------ *
 *  Data: one row per peer ID prefix, with every agent string seen
 * ------------------------------------------------------------------ */
$aaKnown = [
    'qB' => 'qBittorrent',  'UT' => 'µTorrent',     'UM' => 'µTorrent Mac', 'UW' => 'µTorrent Web',
    'TR' => 'Transmission', 'DE' => 'Deluge',       'lt' => 'rTorrent',     'LT' => 'libtorrent',
    'BI' => 'BiglyBT',      'AZ' => 'Vuze',         'BT' => 'BitTorrent',   'BW' => 'BitTorrent Web',
    'TX' => 'Tixati',       'KT' => 'KTorrent',     'WW' => 'WebTorrent',   'WD' => 'WebTorrent Desktop',
    'PI' => 'PicoTorrent',  'BC' => 'BitComet',     'FD' => 'Free Download Manager', 'FL' => 'Folx',
    'XL' => 'Xunlei',       'SD' => 'Thunder',      'TL' => 'Tribler',      'FW' => 'FrostWire',
    'LW' => 'LimeWire',     'MG' => 'MediaGet',     'HL' => 'Halite',       'AG' => 'Ares',
    'A~' => 'Ares',         'BL' => 'BitLord',      'ZT' => 'ZipTorrent',   'TT' => 'TuoTu',
];
$aaMainline = ['M' => 'BitTorrent Mainline', 'T' => 'BitTornado', 'S' => 'Shadow', 'O' => 'Osprey', 'Q' => 'BTQueue', 'A' => 'ABC'];

$aaClientInfo = static function (string $prefix, array $agents) use ($aaKnown, $aaMainline): array {
    $fromAgent = static function (array $agents): string {
        $first = $agents !== [] ? strtok((string)$agents[0], '/ ') : false;
        return ($first !== false && $first !== '') ? $first : 'Unknown client';
    };

    if (preg_match('/^-([A-Za-z~]{2})/', $prefix, $m)) {
        return [$aaKnown[$m[1]] ?? $fromAgent($agents), $m[1]];
    }
    if (preg_match('/^([A-Z])\d/', $prefix, $m) && isset($aaMainline[$m[1]])) {
        return [$aaMainline[$m[1]], $m[1]];
    }
    $name     = $fromAgent($agents);
    $initials = substr((string)preg_replace('/[^A-Za-z]/', '', $name), 0, 2);
    return [$name, $initials !== '' ? $initials : '?'];
};

$aaClients = [];
$res = $db->sql_query_prepared(
    "SELECT agent, LEFT(REPLACE(peer_id, ' ', ''), 8) AS prefix, COUNT(*) AS peers
       FROM peers
      GROUP BY agent, prefix"
);
if ($res !== false) {
    while ($r = $db->fetch_array($res)) {
        $p = (string)$r['prefix'];
        if ($p === '') {
            continue;
        }
        $aaClients[$p] ??= ['agents' => [], 'peers' => 0];
        $agent = trim((string)$r['agent']);
        if ($agent !== '' && !in_array($agent, $aaClients[$p]['agents'], true)) {
            $aaClients[$p]['agents'][] = $agent;
        }
        $aaClients[$p]['peers'] += (int)$r['peers'];
    }
}

// Keep whitelisted clients that have no active peers right now,
// otherwise saving the form would silently drop them from the list
foreach ($aaCurrent as $p) {
    $aaClients[$p] ??= ['agents' => [], 'peers' => 0];
}

uksort($aaClients, static function ($a, $b) use ($aaClients): int {
    return [$aaClients[$b]['peers'], (string)$a] <=> [$aaClients[$a]['peers'], (string)$b];
});

$aaTotal   = count($aaClients);
$aaAllowed = 0;
$aaPeers   = 0;
foreach ($aaClients as $p => $c) {
    if (in_array((string)$p, $aaCurrent, true)) {
        $aaAllowed++;
    }
    $aaPeers += $c['peers'];
}
$aaBlocked = $aaTotal - $aaAllowed;

/* ------------------------------------------------------------------ *
 *  Output
 * ------------------------------------------------------------------ */
stdhead('Allowed Clients');

$aaFlash = match ($mybb->get_input('aa')) {
    'saved'    => ['success', 'fa-circle-check',         'Client list saved.'],
    'nochange' => ['info',    'fa-circle-info',          'Nothing changed, so there was nothing to save.'],
    'csrf'     => ['danger',  'fa-shield-halved',        'The security token expired. Reload the page and save again.'],
    'dberror'  => ['danger',  'fa-triangle-exclamation', 'The database rejected the update. The client list was not saved.'],
    default    => null,
};
?>
<link rel="stylesheet" href="<?= $BASEURL ?>/include/templates/default/style/sweetalert2.min.css">
<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/allagents.css?ver=<?= AA_ASSET_VER ?>">

<div class="aa-page container mt-4 mb-5">

    <div class="aa-header card">
        <div class="card-body">
            <div class="aa-header-icon"><i class="fa-solid fa-plug-circle-check"></i></div>
            <div class="aa-header-text">
                <h1 class="aa-title">Allowed clients <span class="aa-version">v<?= AA_VERSION ?></span></h1>
                <p class="aa-subtitle">Choose which BitTorrent clients the tracker accepts. Each row groups every peer that announces with the same peer ID prefix.</p>
            </div>
        </div>
    </div>

    <?php if ($aaFlash !== null): ?>
    <div class="aa-flash aa-flash-<?= $aaFlash[0] ?>" role="status">
        <i class="fa-solid <?= $aaFlash[1] ?>"></i>
        <span><?= $aaFlash[2] ?></span>
    </div>
    <?php endif; ?>

    <div class="aa-kpis">
        <div class="aa-kpi aa-tone-primary">
            <div class="aa-kpi-icon"><i class="fa-solid fa-plug"></i></div>
            <div><div class="aa-kpi-value" data-kpi="total"><?= $aaTotal ?></div><div class="aa-kpi-label">Clients</div></div>
        </div>
        <div class="aa-kpi aa-tone-success">
            <div class="aa-kpi-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="aa-kpi-value" data-kpi="allowed"><?= $aaAllowed ?></div><div class="aa-kpi-label">Allowed</div></div>
        </div>
        <div class="aa-kpi aa-tone-danger">
            <div class="aa-kpi-icon"><i class="fa-solid fa-ban"></i></div>
            <div><div class="aa-kpi-value" data-kpi="blocked"><?= $aaBlocked ?></div><div class="aa-kpi-label">Blocked</div></div>
        </div>
        <div class="aa-kpi aa-tone-info">
            <div class="aa-kpi-icon"><i class="fa-solid fa-users"></i></div>
            <div><div class="aa-kpi-value"><?= number_format($aaPeers) ?></div><div class="aa-kpi-label">Active peers</div></div>
        </div>
    </div>

    <form method="post" action="<?= $_this_script_ ?>" id="agentsForm">
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="my_post_key" value="<?= $mybb->post_code ?>">

        <div class="aa-list card">
            <?php if ($aaTotal > 0): ?>
            <div class="aa-toolbar">
                <div class="aa-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="aaSearch" class="form-control" placeholder="Search by client, version or prefix" autocomplete="off">
                </div>
                <div class="aa-filters" role="group" aria-label="Show">
                    <button type="button" class="aa-pill is-active" data-aa-filter="all"><i class="fa-solid fa-layer-group"></i>All</button>
                    <button type="button" class="aa-pill" data-aa-filter="allowed"><i class="fa-solid fa-circle-check"></i>Allowed</button>
                    <button type="button" class="aa-pill" data-aa-filter="blocked"><i class="fa-solid fa-ban"></i>Blocked</button>
                </div>
                <div class="aa-bulk">
                    <button type="button" class="aa-pill aa-pill-soft-success" data-aa-bulk="allow" title="Allow every client shown below"><i class="fa-solid fa-check-double"></i>Allow shown</button>
                    <button type="button" class="aa-pill aa-pill-soft-danger" data-aa-bulk="block" title="Block every client shown below"><i class="fa-solid fa-xmark"></i>Block shown</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="aa-table">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-desktop"></i>Client</th>
                            <th><i class="fa-solid fa-fingerprint"></i>Peer ID prefix</th>
                            <th class="text-end"><i class="fa-solid fa-users"></i>Peers</th>
                            <th class="text-center"><i class="fa-solid fa-toggle-on"></i>Access</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $aaTones = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                    $i = 0;
                    foreach ($aaClients as $prefix => $c):
                        $prefix    = (string)$prefix;
                        $i++;
                        $isAllowed = in_array($prefix, $aaCurrent, true);
                        [$name, $initials] = $aaClientInfo($prefix, $c['agents']);
                        $display   = (string)preg_replace('/[^\x20-\x7E]/', '·', $prefix);
                        $tone      = $aaTones[crc32($prefix) % count($aaTones)];
                        $search    = mb_strtolower($name . ' ' . $display . ' ' . implode(' ', $c['agents']));
                        $shown     = array_slice($c['agents'], 0, 3);
                        $more      = count($c['agents']) - count($shown);
                    ?>
                        <tr class="aa-row" data-state="<?= $isAllowed ? 'allowed' : 'blocked' ?>" data-search="<?= $aaEsc($search) ?>">
                            <td>
                                <div class="aa-client">
                                    <span class="aa-avatar aa-tone-<?= $tone ?>"><?= $aaEsc($initials) ?></span>
                                    <div class="aa-client-text">
                                        <div class="aa-name"><?= $aaEsc($name) ?></div>
                                        <?php if ($shown !== []): ?>
                                        <div class="aa-agents">
                                            <?php foreach ($shown as $a): ?><span class="aa-chip"><?= $aaEsc($a) ?></span><?php endforeach; ?>
                                            <?php if ($more > 0): ?><span class="aa-chip aa-chip-more" title="<?= $aaEsc(implode("\n", array_slice($c['agents'], 3))) ?>">+<?= $more ?> more</span><?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><code class="aa-prefix"><?= $aaEsc($display) ?></code></td>
                            <td class="text-end">
                                <?php if ($c['peers'] > 0): ?>
                                    <span class="aa-peers"><?= number_format($c['peers']) ?></span>
                                <?php else: ?>
                                    <span class="aa-offline" title="Whitelisted, but no peers are connected with this client right now"><i class="fa-solid fa-moon"></i>No peers</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch aa-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           value="<?= $aaEsc($prefix) ?>"
                                           name="client[]"
                                           id="agent_<?= $i ?>"
                                           data-client="<?= $aaEsc($prefix) ?>"
                                           <?= $isAllowed ? 'checked' : '' ?>>
                                    <label class="form-check-label aa-state" for="agent_<?= $i ?>">
                                        <?= $isAllowed
                                            ? '<i class="fa-solid fa-circle-check"></i><span>Allowed</span>'
                                            : '<i class="fa-solid fa-ban"></i><span>Blocked</span>' ?>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="aa-empty" id="aaNoResults" hidden>
                <i class="fa-solid fa-filter-circle-xmark"></i>
                <p>No clients match this search. Clear the search or pick another filter.</p>
            </div>
            <?php else: ?>
            <div class="aa-empty">
                <i class="fa-solid fa-satellite-dish"></i>
                <p>No clients yet. They appear here as soon as peers announce to the tracker.</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($aaTotal > 0): ?>
        <div class="aa-savebar">
            <div class="aa-savebar-status" id="aaDirty">
                <i class="fa-solid fa-circle-check"></i><span>No unsaved changes</span>
            </div>
            <div class="aa-savebar-actions">
                <button type="reset" class="aa-pill aa-pill-outline"><i class="fa-solid fa-rotate-left"></i>Reset</button>
                <button type="submit" class="aa-pill aa-pill-primary"><i class="fa-solid fa-floppy-disk"></i>Save changes</button>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<script src="<?= $BASEURL ?>/scripts/sweetalert2.min.js"></script>
<script src="<?= $BASEURL ?>/admin/scripts/allagents.js?ver=<?= AA_ASSET_VER ?>"></script>
<?php
stdfoot();