<?php


function return_torrent_bookmark_array(int $userid): array
{
    global $db;
    static $ret = [];
    
    if (empty($ret)) {
        $ret = [];
        $res = $db->simple_select("bookmarks", "*", "userid='{$userid}'");
        
        if ($db->num_rows($res) !== 0) {
            while ($row = mysqli_fetch_array($res)) {
                $ret[] = (int)$row['torrentid'];
            }
        } else {
            $ret[] = 0;
        }
    }
    return $ret;
}


function get_torrent_bookmark_state(int $userid, int $torrentid, bool $text = false): string
{
    global $lang, $db, $Torrent;
    
    $ret = return_torrent_bookmark_array($userid);
    $torrent_name = $Torrent['name'] ? htmlspecialchars(cutename($Torrent['name'], 25)) : 'Unknown';
    $bookmarked = count($ret) && in_array($torrentid, $ret, false);
    
    if (!$bookmarked) {
        if ($text) {
            return $lang->browse['title_bookmark_torrent'];
        } else {
            return '<a href="#" class="bookmark-toggle" 
                     data-torrent-id="' . $torrentid . '"
                     data-bs-toggle="popover" data-bs-placement="top" 
                     data-bs-title="⭐ Add to Bookmarks" 
                     data-bs-content="' . htmlspecialchars('
                         <div class="bookmark-popover-content">
                             <div class="mb-2">
                                 <strong>Save for later</strong>
                                 <div class="small text-muted">Quick access to this torrent</div>
                             </div>
                             <div class="torrent-preview small">
                                 <i class="bi bi-link-45deg me-1"></i>
                                 ' . $torrent_name . '
                             </div>
                             <button class="btn btn-warning btn-sm w-100 mt-2 add-bookmark-btn">
                                 <i class="bi bi-star me-1"></i>Add to Bookmarks
                             </button>
                         </div>
                     ', ENT_QUOTES) . '" 
                     data-bs-html="true">
                     <i class="fa-regular fa-star fa-lg bookmark-icon" style="color: #ffc107;"></i>
                   </a>';
        }
    } else {
        if ($text) {
            return $lang->browse['title_delbookmark_torrent'];
        } else {
            return '<a href="#" class="bookmark-toggle" 
                     data-torrent-id="' . $torrentid . '"
                     data-bs-toggle="popover" data-bs-placement="top" 
                     data-bs-title="✅ Bookmarked" 
                     data-bs-content="' . htmlspecialchars('
                         <div class="bookmark-popover-content">
                             <div class="mb-2">
                                 <strong>In Your Bookmarks</strong>
                                 <div class="small text-muted">Easily accessible anytime</div>
                             </div>
                             <div class="bookmarked-info small text-success">
                                 <i class="bi bi-check-circle me-1"></i>
                                 Added to your collection
                             </div>
                             <button class="btn btn-outline-danger btn-sm w-100 mt-2 remove-bookmark-btn">
                                 <i class="bi bi-trash me-1"></i>Remove Bookmark
                             </button>
                         </div>
                     ', ENT_QUOTES) . '" 
                     data-bs-html="true">
                     <i class="fa-solid fa-star fa-lg bookmark-icon bookmarked" style="color: #ffc107;"></i>
                   </a>';
        }
    }
} 

function GetTorrentTags(array $t): string
{
    global $lang, $BASEURL, $pic_base_url, $is_mod, $CURUSER;
    
    $ShowImage = (TIMENOW - $t['ts_external_lastupdate'] < 3600) ? (!$is_mod ? false : true) : true;
    $I = [];
    
    // New torrent badge
    if ($t['added'] > $CURUSER['last_login']) {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="🆕 New Torrent" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>Fresh Content</strong>
                            <p class="mb-2 small">Recently added to the site</p>
                        </div>
                        <div class="feature-benefits">
                            <div class="benefit-item">
                                <i class="bi bi-clock text-success me-1"></i>
                                <span>Added: '.my_datee('relative', $t['added']).'</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-eye text-success me-1"></i>
                                <span>Be the first to download</span>
                            </div>
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                    New
                </span>
            </a>';
    }
    
    // Free leech badge
    if ($t['free'] === 'yes') {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="🎁 Free Leech" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>Download for Free</strong>
                            <p class="mb-2 small">No download cost - keeps your ratio safe!</p>
                        </div>
                        <div class="feature-benefits">
                            <div class="benefit-item">
                                <i class="bi bi-arrow-down-circle text-success me-1"></i>
                                <span>Zero download counted</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-shield-check text-success me-1"></i>
                                <span>Ratio protection</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-download text-success me-1"></i>
                                <span>Risk-free downloading</span>
                            </div>
                        </div>
                        <div class="feature-footer text-muted small mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Upload still counts normally
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                    <i class="bi bi-gift me-1"></i>Free
                </span>
            </a>';
    }
    
    // Silver leech badge
    if ($t['silver'] === 'yes') {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="🥈 Silver Leech" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>Reduced Download Cost</strong>
                            <p class="mb-2 small">Download at 50% of normal cost</p>
                        </div>
                        <div class="feature-benefits">
                            <div class="benefit-item">
                                <i class="bi bi-arrow-down-circle text-secondary me-1"></i>
                                <span>50% download counted</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-percent text-secondary me-1"></i>
                                <span>Half the normal cost</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-shield text-secondary me-1"></i>
                                <span>Better ratio protection</span>
                            </div>
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <span class="badge-silver" title="silverdownload">
            <i class="fas fa-star"></i>
        </span>
            </a>';
    }
    
    // Nuked torrent
    if ($t['isnuked'] === 'yes') {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="⚠️ Nuked Torrent" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>Content Issues</strong>
                            <p class="mb-2 small text-danger">This torrent has been marked as problematic</p>
                        </div>
                        <div class="feature-warnings">
                            <div class="warning-item">
                                <i class="bi bi-exclamation-triangle text-danger me-1"></i>
                                <span><strong>Reason:</strong> '.htmlspecialchars($t['WhyNuked']).'</span>
                            </div>
                            <div class="warning-item">
                                <i class="bi bi-info-circle text-warning me-1"></i>
                                <span>Download at your own risk</span>
                            </div>
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <i class="fa-solid fa-circle-radiation fa-lg" style="color: #e70808;"></i>
            </a>';
    }
    
    // Request filled
    if ($t['isrequest'] === 'yes') {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="✅ Request Filled" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>Community Request</strong>
                            <p class="mb-2 small">This torrent was uploaded to fulfill a user request</p>
                        </div>
                        <div class="feature-benefits">
                            <div class="benefit-item">
                                <i class="bi bi-people text-primary me-1"></i>
                                <span>Requested by community</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-check-circle text-primary me-1"></i>
                                <span>Successfully filled</span>
                            </div>
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                    <i class="bi bi-check-lg me-1"></i>Request
                </span>
            </a>';
    }
    
    // Double upload
    if ($t['doubleupload'] === 'yes') {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="⚡ Double Upload" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>🚀 Boosted Upload</strong>
                            <p class="mb-2 small">Earn double credit for your uploads!</p>
                        </div>
                        <div class="feature-benefits">
                            <div class="benefit-item">
                                <i class="bi bi-lightning-charge text-primary me-1"></i>
                                <span>2x upload multiplier</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-lightning-charge text-primary me-1"></i>
                                <span>Faster ratio building</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-lightning-charge text-primary me-1"></i>
                                <span>Great for seeders</span>
                            </div>
                        </div>
                        <div class="feature-footer text-muted small mt-2">
                            <i class="bi bi-graph-up-arrow me-1"></i>
                            Applies to all upload data
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                    <i class="bi bi-lightning-charge me-1"></i>2x Upload
                </span>
            </a>';
    }
    
    // External torrent update
    $canUpdateExternal = 'yes';
    if ($t["ts_external"] === "yes" && $ShowImage === true) {
        $I[] = "<span id=\"isexternal_" . $t["id"] . "\">" . 
            ($canUpdateExternal ? 
                '<a href="javascript:void(0)" onclick="UpdateExternalTorrent(\'include/ts_external_scrape/ts_update.php\', \'id=' . $t["id"] . '&ajax_update=true\', ' . $t["id"] . ')" 
                   class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                   data-bs-title="🌐 External Tracker" 
                   data-bs-content="'.htmlspecialchars('
                       <div class="torrent-feature-popover">
                           <div class="feature-info">
                               <strong>External Source</strong>
                               <p class="mb-2 small">This torrent is tracked from external source</p>
                           </div>
                           <div class="feature-actions">
                               <button class="btn btn-outline-primary btn-sm w-100" onclick="UpdateExternalTorrent(\'include/ts_external_scrape/ts_update.php\', \'id=' . $t["id"] . '&ajax_update=true\', ' . $t["id"] . ')">
                                   <i class="bi bi-arrow-clockwise me-1"></i>Update Stats
                               </button>
                           </div>
                       </div>
                   ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                   <i class="fa-solid fa-circle-notch" style="color: #0b59e0;"></i>
                </a>' : 
                "<img src=\"" . $Imagedir . "external.gif\" border=\"0\" alt=\"" . $lang->browse["sortby15"] . "\" title=\"" . $lang->browse["sortby15"] . "\" class=\"inlineimg\" />") . 
            "</span>";
    }
    
    // Sticky torrent
    if ($t['sticky'] === 'yes') {
        $I[] = '<a href="#" class="badge-popover" data-bs-toggle="popover" data-bs-placement="top"
                data-bs-title="📌 Sticky Torrent" 
                data-bs-content="'.htmlspecialchars('
                    <div class="torrent-feature-popover">
                        <div class="feature-info">
                            <strong>Pinned Content</strong>
                            <p class="mb-2 small">This torrent is pinned to the top of the list</p>
                        </div>
                        <div class="feature-benefits">
                            <div class="benefit-item">
                                <i class="bi bi-pin-angle text-info me-1"></i>
                                <span>Always visible</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-star text-info me-1"></i>
                                <span>Featured content</span>
                            </div>
                        </div>
                    </div>
                ', ENT_QUOTES).'" data-bs-html="true" data-bs-trigger="hover focus">
                <i class="fa-solid fa-bolt fa-lg" style="color: #0e5ce1;"></i>
            </a>';
    }
    
    return count($I) ? implode(' ', $I) : '';
}