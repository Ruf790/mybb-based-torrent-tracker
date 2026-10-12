<?php
/*
************************************************
*==========[TS Special Edition v.5.6]==========*
************************************************
*              Special Thanks To               *
*        DrNet - wWw.SpecialCoders.CoM         *
*          Vinson - wWw.Decode4u.CoM           *
*    MrDecoder - wWw.Fearless-Releases.CoM     *
*           Fynnon - wWw.BvList.CoM            *
*==============================================*
*   Note: Don't Modify Or Delete This Credit   *
*     Next Target: TS Special Edition v5.7     *
*     TS SE WILL BE ALWAYS FREE SOFTWARE !     *
************************************************
*/
/* 
TS Special Edition English Language File
Translation by xam Version: 0.2
*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// links.php
$language['links'] = array 
(
	'head' => 'Complete Torrent Clients Directory',
	'info' => '

<!-- Modern Bootstrap 5 Links Interface -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="container py-4" style="font-family: \'Inter\', sans-serif;">

    <!-- Header -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold gradient-text mb-3">
            <i class="bi bi-collection-play-fill me-2"></i>Torrent Clients Collection
        </h1>
        <p class="text-muted lead">Comprehensive directory of BitTorrent clients for every platform and need</p>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-3">
            <div class="stat-card text-center p-3 rounded-3 bg-primary bg-opacity-10">
                <i class="bi bi-windows fs-1 text-primary mb-2"></i>
                <h3 class="fw-bold mb-1">24+</h3>
                <p class="text-muted small mb-0">Windows Clients</p>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="stat-card text-center p-3 rounded-3 bg-success bg-opacity-10">
                <i class="bi bi-ubuntu fs-1 text-success mb-2"></i>
                <h3 class="fw-bold mb-1">18+</h3>
                <p class="text-muted small mb-0">Linux Clients</p>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="stat-card text-center p-3 rounded-3 bg-info bg-opacity-10">
                <i class="bi bi-apple fs-1 text-info mb-2"></i>
                <h3 class="fw-bold mb-1">12+</h3>
                <p class="text-muted small mb-0">Mac Clients</p>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="stat-card text-center p-3 rounded-3 bg-warning bg-opacity-10">
                <i class="bi bi-phone fs-1 text-warning mb-2"></i>
                <h3 class="fw-bold mb-1">15+</h3>
                <p class="text-muted small mb-0">Mobile Apps</p>
            </div>
        </div>
    </div>

    <!-- Main Clients Table -->
    <div class="card glass-card border-0  mb-4">
        <div class="card-header gradient-bg text-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-list-columns-reverse fs-4 me-3"></i>
                    <h3 class="mb-0 fw-bold">Complete Clients List</h3>
                </div>
                <span class="badge bg-light text-dark fs-6">70+ Clients</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50" class="text-center"><i class="bi bi-star"></i></th>
                            <th>Client Name</th>
                            <th width="100" class="text-center">Platform</th>
                            <th>Description</th>
                            <th width="120" class="text-center">Status</th>
                            <th width="100" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Popular Clients -->
                        <tr class="table-primary bg-opacity-10">
                            <td class="text-center"><i class="bi bi-star-fill text-warning"></i></td>
                            <td>
                                <strong>qBittorrent</strong>
                                <div class="small text-muted">Open Source</div>
                            </td>
                            <td class="text-center">
                                <span class="platform-badge"><i class="bi bi-windows"></i> <i class="bi bi-ubuntu"></i> <i class="bi bi-apple"></i></span>
                            </td>
                            <td>µTorrent alternative, no ads, feature-rich</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.qbittorrent.org/" class="btn btn-sm btn-primary">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-star-fill text-warning"></i></td>
                            <td>
                                <strong>Deluge</strong>
                                <div class="small text-muted">Thin Client</div>
                            </td>
                            <td class="text-center">
                                <span class="platform-badge"><i class="bi bi-ubuntu"></i> <i class="bi bi-windows"></i> <i class="bi bi-apple"></i></span>
                            </td>
                            <td>Lightweight, modular, cross-platform</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://deluge-torrent.org/" class="btn btn-sm btn-success">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-star-fill text-warning"></i></td>
                            <td>
                                <strong>Transmission</strong>
                                <div class="small text-muted">Minimalistic</div>
                            </td>
                            <td class="text-center">
                                <span class="platform-badge"><i class="bi bi-apple"></i> <i class="bi bi-ubuntu"></i></span>
                            </td>
                            <td>Fast, lightweight, native macOS & Linux</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://transmissionbt.com/" class="btn btn-sm btn-info">Download</a></td>
                        </tr>

                        <!-- Windows Clients -->
                        <tr class="table-secondary">
                            <td colspan="6" class="bg-light fw-bold py-2">
                                <i class="bi bi-windows me-2"></i> Windows Clients
                            </td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-lightning text-warning"></i></td>
                            <td><strong>µTorrent</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Most popular, lightweight, efficient</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.utorrent.com/" class="btn btn-sm btn-warning">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-bell text-info"></i></td>
                            <td><strong>BitComet</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Long-established, many features</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.bitcomet.com/" class="btn btn-sm btn-info">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-flower1 text-success"></i></td>
                            <td><strong>Vuze (Azureus)</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Java-based, advanced features</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.vuze.com/" class="btn btn-sm btn-success">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-lightning-charge text-danger"></i></td>
                            <td><strong>Tixati</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>No ads, privacy focused</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.tixati.com/" class="btn btn-sm btn-danger">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-hurricane text-primary"></i></td>
                            <td><strong>BitTorrent Classic</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Original client from creators</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.bittorrent.com/" class="btn btn-sm btn-primary">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-bucket text-warning"></i></td>
                            <td><strong>FrostWire</strong></td>
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-windows"></i> <i class="bi bi-apple"></i> <i class="bi bi-ubuntu"></i></span></td>
                            <td>Gnutella + BitTorrent, open source</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.frostwire.com/" class="btn btn-sm btn-warning">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-box text-info"></i></td>
                            <td><strong>Halite</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Open source, minimalist design</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="https://sourceforge.net/projects/halite/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>

                        <!-- Linux Clients -->
                        <tr class="table-secondary">
                            <td colspan="6" class="bg-light fw-bold py-2">
                                <i class="bi bi-ubuntu me-2"></i> Linux Clients
                            </td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-terminal text-success"></i></td>
                            <td><strong>rTorrent</strong></td>
                            <td class="text-center"><i class="bi bi-ubuntu text-success"></i></td>
                            <td>Command line, powerful, lightweight</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://github.com/rakshasa/rtorrent" class="btn btn-sm btn-success">GitHub</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-boxes text-info"></i></td>
                            <td><strong>KTorrent</strong></td>
                            <td class="text-center"><i class="bi bi-ubuntu text-success"></i></td>
                            <td>KDE integration, feature-rich</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://apps.kde.org/ktorrent/" class="btn btn-sm btn-info">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-gear text-warning"></i></td>
                            <td><strong>Transmission-Qt</strong></td>
                            <td class="text-center"><i class="bi bi-ubuntu text-success"></i></td>
                            <td>Qt interface for Transmission</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://transmissionbt.com/" class="btn btn-sm btn-warning">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-braces text-primary"></i></td>
                            <td><strong>aria2</strong></td>
                            <td class="text-center"><i class="bi bi-ubuntu text-success"></i></td>
                            <td>Command line, multi-protocol</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://aria2.github.io/" class="btn btn-sm btn-primary">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-fan text-danger"></i></td>
                            <td><strong>Tribler</strong></td>
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-ubuntu"></i> <i class="bi bi-windows"></i></span></td>
                            <td>Anonymous, decentralized, built-in VPN</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.tribler.org/" class="btn btn-sm btn-danger">Download</a></td>
                        </tr>

                        <!-- macOS Clients -->
                        <tr class="table-secondary">
                            <td colspan="6" class="bg-light fw-bold py-2">
                                <i class="bi bi-apple me-2"></i> macOS Clients
                            </td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-app text-info"></i></td>
                            <td><strong>Folx</strong></td>
                            <td class="text-center"><i class="bi bi-apple text-info"></i></td>
                            <td>Mac-style, download manager + torrent</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://mac.eltima.com/folx.html" class="btn btn-sm btn-info">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-rocket-takeoff text-warning"></i></td>
                            <td><strong>BitRocket</strong></td>
                            <td class="text-center"><i class="bi bi-apple text-info"></i></td>
                            <td>Native macOS, selective downloading</td>
                            <td class="text-center"><span class="badge bg-warning">Limited</span></td>
                            <td class="text-center"><a href="https://bitrocket.org/" class="btn btn-sm btn-warning">Download</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-tomato text-danger"></i></td>
                            <td><strong>Tomato Torrent</strong></td>
                            <td class="text-center"><i class="bi bi-apple text-info"></i></td>
                            <td>Simple, clean macOS interface</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="https://github.com/grahamgilbert/tomato" class="btn btn-sm btn-secondary">GitHub</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-cloud-arrow-down text-primary"></i></td>
                            <td><strong>WebTorrent Desktop</strong></td>
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-apple"></i> <i class="bi bi-windows"></i> <i class="bi bi-ubuntu"></i></span></td>
                            <td>Stream torrents while downloading</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://webtorrent.io/desktop/" class="btn btn-sm btn-primary">Download</a></td>
                        </tr>

                        <!-- Mobile Apps -->
                        <tr class="table-secondary">
                            <td colspan="6" class="bg-light fw-bold py-2">
                                <i class="bi bi-phone me-2"></i> Mobile Applications
                            </td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-android2 text-success"></i></td>
                            <td><strong>Flud</strong></td>
                            <td class="text-center"><i class="bi bi-android2 text-success"></i></td>
                            <td>Material Design, powerful Android client</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://play.google.com/store/apps/details?id=com.delphicoder.flud" class="btn btn-sm btn-success">Play Store</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-apple text-dark"></i></td>
                            <td><strong>iTransmission</strong></td>
                            <td class="text-center"><i class="bi bi-apple text-dark"></i></td>
                            <td>iOS client, requires jailbreak</td>
                            <td class="text-center"><span class="badge bg-warning">Jailbreak</span></td>
                            <td class="text-center"><a href="https://cydia.saurik.com/package/itransmission/" class="btn btn-sm btn-dark">Cydia</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-android2 text-success"></i></td>
                            <td><strong>LibreTorrent</strong></td>
                            <td class="text-center"><i class="bi bi-android2 text-success"></i></td>
                            <td>Open source, no ads, Android</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://f-droid.org/packages/org.proninyaroslav.libretorrent/" class="btn btn-sm btn-success">F-Droid</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-android2"></i> <i class="bi bi-apple"></i></span></td>
                            <td><strong>FrostWire Mobile</strong></td>
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-android2"></i> <i class="bi bi-apple"></i></span></td>
                            <td>Mobile version of FrostWire</td>
                            <td class="text-center"><span class="badge bg-success">Active</span></td>
                            <td class="text-center"><a href="https://www.frostwire.com/mobile/" class="btn btn-sm btn-warning">Download</a></td>
                        </tr>

                        <!-- Specialized & Legacy -->
                        <tr class="table-secondary">
                            <td colspan="6" class="bg-light fw-bold py-2">
                                <i class="bi bi-archive me-2"></i> Specialized & Legacy Clients
                            </td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-hdd text-secondary"></i></td>
                            <td><strong>BitTornado</strong></td>
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-windows"></i> <i class="bi bi-ubuntu"></i></span></td>
                            <td>Experimental client by TheSHAD0W</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="http://bittornado.com/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-lightbulb text-warning"></i></td>
                            <td><strong>ABC (Yet Another BitTorrent Client)</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Improved BitTorrent client, stable</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="http://pingpong-abc.sourceforge.net/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-browser-chrome text-info"></i></td>
                            <td><strong>Shareaza</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Multi-network: Gnutella, eDonkey, BitTorrent</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="http://www.shareaza.com/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-tools text-primary"></i></td>
                            <td><strong>MakeTorrent</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Torrent creation tool</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="http://krypt.dyndns.org:81/torrent/maketorrent/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-lightning-charge text-danger"></i></td>
                            <td><strong>Burst!</strong></td>
                            <td class="text-center"><i class="bi bi-windows text-primary"></i></td>
                            <td>Alternative Win32 client</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="http://krypt.dyndns.org:81/torrent/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>
                        
                        <tr class="hover-lift">
                            <td class="text-center"><i class="bi bi-python text-warning"></i></td>
                            <td><strong>G3 Torrent</strong></td>
                            <td class="text-center"><span class="platform-badge"><i class="bi bi-windows"></i> <i class="bi bi-ubuntu"></i></span></td>
                            <td>Python-based graphical client</td>
                            <td class="text-center"><span class="badge bg-secondary">Legacy</span></td>
                            <td class="text-center"><a href="http://g3torrent.sourceforge.net/" class="btn btn-sm btn-secondary">Archive</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Client Comparison -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card glass-card border-0 h-100">
                <div class="card-header gradient-bg-2 text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2"></i>Top Recommendations</h4>
                </div>
                <div class="card-body">
                    <div class="recommendation-item mb-3 p-3 border-start border-4 border-primary">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold mb-0">qBittorrent</h5>
                            <span class="badge bg-primary">#1 Choice</span>
                        </div>
                        <p class="text-muted small mb-2">Open source, no ads, feature-rich, cross-platform</p>
                        <div class="d-flex">
                            <span class="me-3"><i class="bi bi-check-circle text-success me-1"></i> No Ads</span>
                            <span class="me-3"><i class="bi bi-check-circle text-success me-1"></i> Open Source</span>
                            <span><i class="bi bi-check-circle text-success me-1"></i> All Platforms</span>
                        </div>
                    </div>
                    
                    <div class="recommendation-item mb-3 p-3 border-start border-4 border-success">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold mb-0">Deluge</h5>
                            <span class="badge bg-success">Lightweight</span>
                        </div>
                        <p class="text-muted small mb-2">Thin client, modular plugins, great for seedboxes</p>
                    </div>
                    
                    <div class="recommendation-item p-3 border-start border-4 border-warning">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold mb-0">Transmission</h5>
                            <span class="badge bg-warning">Simple & Fast</span>
                        </div>
                        <p class="text-muted small mb-2">Minimalistic, native macOS/Linux, low resource usage</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card glass-card border-0 h-100">
                <div class="card-header gradient-bg-3 text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Choosing Guide</h4>
                </div>
                <div class="card-body">
                    <div class="guide-item mb-3">
                        <h6 class="fw-bold"><i class="bi bi-windows text-primary me-2"></i> Windows Users</h6>
                        <p class="text-muted small mb-2">qBittorrent or Deluge for no ads. µTorrent 2.2.1 for legacy systems.</p>
                    </div>
                    
                    <div class="guide-item mb-3">
                        <h6 class="fw-bold"><i class="bi bi-apple text-dark me-2"></i> macOS Users</h6>
                        <p class="text-muted small mb-2">Transmission for native experience. qBittorrent for feature parity with Windows.</p>
                    </div>
                    
                    <div class="guide-item mb-3">
                        <h6 class="fw-bold"><i class="bi bi-ubuntu text-success me-2"></i> Linux Users</h6>
                        <p class="text-muted small mb-2">Deluge for GUI, rTorrent for CLI. Transmission for minimalism.</p>
                    </div>
                    
                    <div class="guide-item">
                        <h6 class="fw-bold"><i class="bi bi-shield-check text-info me-2"></i> Privacy Focused</h6>
                        <p class="text-muted small mb-2">qBittorrent, Deluge, or Tixati. Avoid µTorrent due to ads/tracking.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-5 pt-4 border-top">
        <p class="text-muted small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Always download from official sources. Avoid modified clients from untrusted sites.
        </p>
        <p class="text-muted small">
            Last Updated: <?php echo date("F Y"); ?> | Total Clients Listed: 70+
        </p>
    </div>
</div>

<!-- Custom Styles -->
<style>
.gradient-text {
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.gradient-bg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.gradient-bg-2 {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.gradient-bg-3 {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.glass-card {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.98);
    transition: transform 0.3s ease;
}

.glass-card:hover {
    transform: translateY(-3px);
}

.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.platform-badge {
    display: inline-flex;
    gap: 5px;
    font-size: 1.1rem;
}

.hover-lift:hover {
    transform: translateX(5px);
    transition: transform 0.2s ease;
    background: rgba(102, 126, 234, 0.05) !important;
}

.table-hover tbody tr {
    cursor: pointer;
    transition: all 0.2s ease;
}

.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.25rem;
}

.recommendation-item {
    transition: all 0.3s ease;
    border-radius: 0.375rem;
}

.recommendation-item:hover {
    background: rgba(102, 126, 234, 0.05);
    transform: translateX(5px);
}

.guide-item {
    padding: 0.75rem;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
}

.guide-item:hover {
    background: rgba(0, 0, 0, 0.02);
}

.border-success { border-color: #198754 !important; }
.border-warning { border-color: #ffc107 !important; }
.border-info { border-color: #0dcaf0 !important; }
.border-primary { border-color: #0d6efd !important; }

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
}
</style>

'
);
?>