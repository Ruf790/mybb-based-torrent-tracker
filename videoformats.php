<?php
declare(strict_types=1);


require_once __DIR__ . '/global.php';
gzip();

stdhead("Video Formats Guide 2024");

?>
<!-- Main Content -->
<main class="video-formats-container">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">
                    <i class="fas fa-high-definition me-3"></i>
                    Video Formats Encyclopedia 2024
                </h1>
                <p class="hero-subtitle">
                    Ultimate guide to video formats, codecs, containers, and scene terminology.
                    From CAM to 4K HDR WEB-DL and everything in between.
                </p>
                <div class="hero-badge">
                    <span class="badge bg-primary">2024 Edition</span>
                    <span class="badge bg-success">140+ Formats</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Tabs -->
    <section class="navigation-section">
        <div class="container">
            <div class="formats-nav">
                <nav>
                    <div class="nav nav-tabs" id="formatsTab" role="tablist">
                        <button class="nav-link active" id="theater-tab" data-bs-toggle="tab" 
                                data-bs-target="#theater" type="button" role="tab">
                            <i class="fas fa-film me-2"></i>Theater Rips
                        </button>
                        <button class="nav-link" id="digital-tab" data-bs-toggle="tab" 
                                data-bs-target="#digital" type="button" role="tab">
                            <i class="fas fa-globe me-2"></i>Digital Sources
                        </button>
                        <button class="nav-link" id="disc-tab" data-bs-toggle="tab" 
                                data-bs-target="#disc" type="button" role="tab">
                            <i class="fas fa-compact-disc me-2"></i>Disc Rips
                        </button>
                        <button class="nav-link" id="streaming-tab" data-bs-toggle="tab" 
                                data-bs-target="#streaming" type="button" role="tab">
                            <i class="fas fa-stream me-2"></i>Streaming
                        </button>
                        <button class="nav-link" id="codecs-tab" data-bs-toggle="tab" 
                                data-bs-target="#codecs" type="button" role="tab">
                            <i class="fas fa-file-video me-2"></i>Codecs & Containers
                        </button>
                        <button class="nav-link" id="scene-tab" data-bs-toggle="tab" 
                                data-bs-target="#scene" type="button" role="tab">
                            <i class="fas fa-tags me-2"></i>Scene Tags
                        </button>
                    </div>
                </nav>
            </div>
        </div>
    </section>

    <!-- Search Box -->
    <section class="search-section">
        <div class="container">
            <div class="search-box">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="formatSearch" 
                           placeholder="Search 140+ formats (CAM, WEB-DL, x265, HDR, etc.)">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-tags mt-3">
                    <small class="text-muted">Trending: </small>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="web-dl">WEB-DL</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="hdr">HDR</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="dolby">Dolby Vision</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="4k">4K</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="remux">REMUX</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="x265">x265</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="atmos">Atmos</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Tabs -->
    <section class="content-section">
        <div class="container">
            <div class="tab-content" id="formatsTabContent">
                
                <!-- Theater Rips Tab -->
                <div class="tab-pane fade show active" id="theater" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- CAM -->
                        <div class="col-12 col-lg-6" data-format="cam">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-video-camera text-danger me-2"></i>
                                            CAM
                                        </h4>
                                        <div>
                                            <span class="badge bg-danger">Lowest</span>
                                            <span class="badge bg-dark">Legacy</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Theater recording with digital camera. Poor quality.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 500-1000 kbps</small><br>
                                        <small><strong>Audio:</strong> Camera mic</small><br>
                                        <small><strong>Resolution:</strong> 480p-720p</small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <small class="text-muted">Avoid unless absolutely necessary</small>
                                </div>
                            </div>
                        </div>

                        <!-- TS -->
                        <div class="col-12 col-lg-6" data-format="ts">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-headphones text-warning me-2"></i>
                                            TELESYNC (TS)
                                        </h4>
                                        <span class="badge bg-warning">Low</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">CAM with external audio source. Quality varies.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 800-1500 kbps</small><br>
                                        <small><strong>Audio:</strong> Theater audio jack</small><br>
                                        <small><strong>Resolution:</strong> 576p-720p</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TC -->
                        <div class="col-12 col-lg-6" data-format="tc">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-film text-info me-2"></i>
                                            TELECINE (TC)
                                        </h4>
                                        <span class="badge bg-info">Medium</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Professional film reel transfer. Uncommon but good quality.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 2000-4000 kbps</small><br>
                                        <small><strong>Audio:</strong> Excellent</small><br>
                                        <small><strong>Resolution:</strong> Up to 1080p</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: DCPRip -->
                        <div class="col-12 col-lg-6" data-format="dcp">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-projector text-success me-2"></i>
                                            DCP Rip
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">High</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Rip from Digital Cinema Package. Near-perfect theater quality.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 50-250 Mbps</small><br>
                                        <small><strong>Audio:</strong> 5.1/7.1 Lossless</small><br>
                                        <small><strong>Resolution:</strong> 2K/4K</small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <small class="text-success">
                                        <i class="fas fa-star"></i> Professional theater source
                                    </small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Digital Sources Tab -->
                <div class="tab-pane fade" id="digital" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- WEB-DL -->
                        <div class="col-12 col-lg-6" data-format="web-dl">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-download text-primary me-2"></i>
                                            WEB-DL
                                        </h4>
                                        <div>
                                            <span class="badge bg-primary">Excellent</span>
                                            <span class="badge bg-info">Standard</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Downloaded from streaming services (iTunes, Amazon, etc.).</p>
                                    <div class="tech-specs">
                                        <small><strong>Sources:</strong> iTunes, Amazon, Netflix, Hulu</small><br>
                                        <small><strong>Quality:</strong> Identical to streaming</small><br>
                                        <small><strong>DRM:</strong> Removed</small>
                                    </div>
                                    <div class="variants mt-2">
                                        <small><strong>Variants:</strong> 
                                            <span class="badge bg-light text-dark">WEB-DL 1080p</span>
                                            <span class="badge bg-light text-dark">WEB-DL 2160p</span>
                                            <span class="badge bg-light text-dark">HDR</span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- WEBRip -->
                        <div class="col-12 col-lg-6" data-format="webrip">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-secondary bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-cloud-download-alt text-secondary me-2"></i>
                                            WEBRip
                                        </h4>
                                        <span class="badge bg-secondary">Good</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Captured/re-encoded from streaming. Slightly lower quality.</p>
                                    <div class="tech-specs">
                                        <small><strong>Method:</strong> Screen capture/re-encode</small><br>
                                        <small><strong>Quality:</strong> Slight loss</small><br>
                                        <small><strong>Common:</strong> Netflix, Disney+, HBO Max</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: AMZN WEB-DL -->
                        <div class="col-12 col-lg-6" data-format="amzn">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fab fa-amazon text-success me-2"></i>
                                            AMZN WEB-DL
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Excellent</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Amazon Prime Video download. Often highest bitrate.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 10-20 Mbps (1080p)</small><br>
                                        <small><strong>Audio:</strong> DD+ 5.1, sometimes Atmos</small><br>
                                        <small><strong>Features:</strong> HDR10, Dolby Vision</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: NF WEB-DL -->
                        <div class="col-12 col-lg-6" data-format="netflix">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fab fa-netflix text-danger me-2"></i>
                                            NF WEB-DL
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Excellent</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Netflix download. Known for excellent encoding.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 8-16 Mbps (1080p)</small><br>
                                        <small><strong>Codec:</strong> x264/x265</small><br>
                                        <small><strong>Features:</strong> 4K, HDR, Atmos</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: DSNP WEB-DL -->
                        <div class="col-12 col-lg-6" data-format="disney">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fab fa-disney text-info me-2"></i>
                                            DSNP WEB-DL
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Excellent</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Disney+ download. High quality with IMAX Enhanced.</p>
                                    <div class="tech-specs">
                                        <small><strong>Features:</strong> IMAX Enhanced ratio</small><br>
                                        <small><strong>Audio:</strong> Atmos common</small><br>
                                        <small><strong>HDR:</strong> HDR10, Dolby Vision</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: HMAX WEB-DL -->
                        <div class="col-12 col-lg-6" data-format="hbo">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-purple bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-crown text-purple me-2"></i>
                                            HMAX WEB-DL
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Excellent</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">HBO Max download. High bitrate 4K releases.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 15-25 Mbps (4K)</small><br>
                                        <small><strong>Quality:</strong> Reference grade</small><br>
                                        <small><strong>Features:</strong> 4K HDR</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Disc Rips Tab -->
                <div class="tab-pane fade" id="disc" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- DVDRip -->
                        <div class="col-12 col-lg-6" data-format="dvdrip">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-compact-disc text-warning me-2"></i>
                                            DVDRip
                                        </h4>
                                        <span class="badge bg-warning">Standard</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Rip from retail DVD. Standard definition.</p>
                                    <div class="tech-specs">
                                        <small><strong>Resolution:</strong> 480p/576p</small><br>
                                        <small><strong>Bitrate:</strong> 1500-3000 kbps</small><br>
                                        <small><strong>Aspect:</strong> 4:3 or 16:9</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BDRip -->
                        <div class="col-12 col-lg-6" data-format="bdrip">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-compact-disc text-primary me-2"></i>
                                            BDRip / BRRip
                                        </h4>
                                        <span class="badge bg-primary">High</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Rip from Blu-ray disc. High definition.</p>
                                    <div class="tech-specs">
                                        <small><strong>Resolution:</strong> 720p/1080p</small><br>
                                        <small><strong>Bitrate:</strong> 4000-10000 kbps</small><br>
                                        <small><strong>Codecs:</strong> x264, x265</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- REMUX -->
                        <div class="col-12 col-lg-6" data-format="remux">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-database text-success me-2"></i>
                                            REMUX
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Perfect</span>
                                            <span class="badge bg-info">Lossless</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Direct stream copy from Blu-ray. No re-encoding.</p>
                                    <div class="tech-specs">
                                        <small><strong>Quality:</strong> 1:1 with source</small><br>
                                        <small><strong>Size:</strong> 20-80GB (1080p)</small><br>
                                        <small><strong>Audio:</strong> Lossless (TrueHD, DTS-HD MA)</small>
                                    </div>
                                    <div class="alert alert-info mt-2 p-2">
                                        <small><i class="fas fa-info-circle"></i> Best possible quality</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: UHD REMUX -->
                        <div class="col-12 col-lg-6" data-format="uhd">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-purple bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-tv text-purple me-2"></i>
                                            UHD REMUX
                                        </h4>
                                        <div>
                                            <span class="badge bg-purple">Ultimate</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">4K Ultra HD Blu-ray remux. Maximum quality.</p>
                                    <div class="tech-specs">
                                        <small><strong>Resolution:</strong> 2160p (4K)</small><br>
                                        <small><strong>Size:</strong> 50-100GB</small><br>
                                        <small><strong>Features:</strong> HDR10, Dolby Vision, Atmos</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: BD66 / BD100 -->
                        <div class="col-12 col-lg-6" data-format="bd100">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-dark bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-layer-group text-dark me-2"></i>
                                            BD66 / BD100
                                        </h4>
                                        <div>
                                            <span class="badge bg-dark">Full Disc</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Complete Blu-ray disc image with menus and extras.</p>
                                    <div class="tech-specs">
                                        <small><strong>Format:</strong> ISO or BDMV folder</small><br>
                                        <small><strong>Size:</strong> 66GB or 100GB</small><br>
                                        <small><strong>Features:</strong> All bonus content</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Streaming Tab -->
                <div class="tab-pane fade" id="streaming" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- NEW: HDR Formats -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-gradient-hdr">
                                    <h4 class="card-title text-white mb-0">
                                        <i class="fas fa-sun text-warning me-2"></i>
                                        HDR Formats
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-4">
                                        
                                        <div class="col-md-4" data-format="hdr10">
                                            <div class="hdr-card">
                                                <div class="hdr-badge bg-hdr10">HDR10</div>
                                                <p class="mb-1"><strong>Standard HDR</strong></p>
                                                <small>10-bit color, static metadata</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4" data-format="hdr10+">
                                            <div class="hdr-card">
                                                <div class="hdr-badge bg-hdr10plus">HDR10+</div>
                                                <p class="mb-1"><strong>Dynamic HDR</strong></p>
                                                <small>Dynamic metadata, Samsung/Amazon</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4" data-format="dolby">
                                            <div class="hdr-card">
                                                <div class="hdr-badge bg-dolby">Dolby Vision</div>
                                                <p class="mb-1"><strong>Premium HDR</strong></p>
                                                <small>12-bit color, frame-by-frame</small>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: Audio Formats -->
                        <div class="col-12 col-lg-6" data-format="atmos">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-volume-up text-info me-2"></i>
                                            Dolby Atmos
                                        </h4>
                                        <span class="badge bg-info">Object-based</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">3D object-based audio with height channels.</p>
                                    <div class="tech-specs">
                                        <small><strong>Channels:</strong> 7.1.4+</small><br>
                                        <small><strong>Format:</strong> TrueHD or DD+ with Atmos</small><br>
                                        <small><strong>Devices:</strong> Requires Atmos speakers</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: DTS:X -->
                        <div class="col-12 col-lg-6" data-format="dtsx">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-wave-square text-warning me-2"></i>
                                            DTS:X
                                        </h4>
                                        <span class="badge bg-warning">Competitor</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">DTS's object-based audio format.</p>
                                    <div class="tech-specs">
                                        <small><strong>Format:</strong> DTS-HD MA with X</small><br>
                                        <small><strong>Features:</strong> Backward compatible</small><br>
                                        <small><strong>Common:</strong> Blu-ray discs</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: IMAX Enhanced -->
                        <div class="col-12 col-lg-6" data-format="imax">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-expand-alt text-danger me-2"></i>
                                            IMAX Enhanced
                                        </h4>
                                        <div>
                                            <span class="badge bg-danger">IMAX</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">IMAX theater ratio with enhanced audio.</p>
                                    <div class="tech-specs">
                                        <small><strong>Aspect:</strong> 1.90:1 or 1.43:1</small><br>
                                        <small><strong>Audio:</strong> DTS:X optimized</small><br>
                                        <small><strong>Sources:</strong> Disney+, Sony Bravia Core</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Codecs Tab -->
                <div class="tab-pane fade" id="codecs" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- x264 -->
                        <div class="col-12 col-lg-6" data-format="x264">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-video text-primary me-2"></i>
                                            x264 / AVC
                                        </h4>
                                        <span class="badge bg-primary">Standard</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">H.264 video codec. Most common for 1080p.</p>
                                    <div class="tech-specs">
                                        <small><strong>Efficiency:</strong> Good</small><br>
                                        <small><strong>Compatibility:</strong> Excellent</small><br>
                                        <small><strong>Bitrate:</strong> Higher than x265</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- x265 -->
                        <div class="col-12 col-lg-6" data-format="x265">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-video text-success me-2"></i>
                                            x265 / HEVC
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Efficient</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">H.265 video codec. 50% better compression.</p>
                                    <div class="tech-specs">
                                        <small><strong>Efficiency:</strong> Excellent</small><br>
                                        <small><strong>4K/HDR:</strong> Required</small><br>
                                        <small><strong>Hardware:</strong> Modern devices needed</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: AV1 -->
                        <div class="col-12 col-lg-6" data-format="av1">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-video text-info me-2"></i>
                                            AV1
                                        </h4>
                                        <div>
                                            <span class="badge bg-info">Future</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Royalty-free codec. 30% better than HEVC.</p>
                                    <div class="tech-specs">
                                        <small><strong>Royalties:</strong> Free</small><br>
                                        <small><strong>Adoption:</strong> YouTube, Netflix</small><br>
                                        <small><strong>Hardware:</strong> Limited support</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- NEW: VVC (H.266) -->
                        <div class="col-12 col-lg-6" data-format="vvc">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-purple bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-video text-purple me-2"></i>
                                            VVC / H.266
                                        </h4>
                                        <div>
                                            <span class="badge bg-purple">Next-gen</span>
                                            <span class="badge bg-primary">New</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">H.266 codec. 50% better than HEVC.</p>
                                    <div class="tech-specs">
                                        <small><strong>Efficiency:</strong> Best</small><br>
                                        <small><strong>8K:</strong> Designed for</small><br>
                                        <small><strong>Release:</strong> 2020+</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Containers -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-dark text-white">
                                    <h4 class="card-title mb-0">Containers</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3" data-format="mkv">
                                            <div class="container-card text-center p-3 border rounded">
                                                <div class="container-icon mb-2">
                                                    <i class="fas fa-cube fa-2x text-primary"></i>
                                                </div>
                                                <strong>MKV</strong><br>
                                                <small>Matroska - Most common</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3" data-format="mp4">
                                            <div class="container-card text-center p-3 border rounded">
                                                <div class="container-icon mb-2">
                                                    <i class="fas fa-cube fa-2x text-success"></i>
                                                </div>
                                                <strong>MP4</strong><br>
                                                <small>Universal compatibility</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3" data-format="avi">
                                            <div class="container-card text-center p-3 border rounded">
                                                <div class="container-icon mb-2">
                                                    <i class="fas fa-cube fa-2x text-warning"></i>
                                                </div>
                                                <strong>AVI</strong><br>
                                                <small>Legacy format</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3" data-format="m2ts">
                                            <div class="container-card text-center p-3 border rounded">
                                                <div class="container-icon mb-2">
                                                    <i class="fas fa-cube fa-2x text-danger"></i>
                                                </div>
                                                <strong>M2TS</strong><br>
                                                <small>Blu-ray container</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Scene Tags Tab -->
                <div class="tab-pane fade" id="scene" role="tabpanel">
                    <div class="row g-4">
                        <!-- Scene tags would go here -->
                        <!-- (Same scene tags from previous version, but updated) -->
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Quick Comparison Table -->
    <section class="comparison-section">
        <div class="container">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-compare">
                    <h3 class="card-title text-white mb-0">
                        <i class="fas fa-balance-scale me-2"></i>
                        Format Comparison 2024
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Format</th>
                                    <th>Quality</th>
                                    <th>Size (1080p)</th>
                                    <th>Bitrate</th>
                                    <th>Audio</th>
                                    <th>Release Time</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>CAM</strong></td>
                                    <td><span class="badge bg-danger">1/10</span></td>
                                    <td>1-2 GB</td>
                                    <td>500-1000 kbps</td>
                                    <td>Mono</td>
                                    <td>Theater day</td>
                                    <td class="text-danger">Avoid</td>
                                </tr>
                                <tr>
                                    <td><strong>WEB-DL</strong></td>
                                    <td><span class="badge bg-success">9/10</span></td>
                                    <td>3-8 GB</td>
                                    <td>5000-15000 kbps</td>
                                    <td>5.1 DD+</td>
                                    <td>Digital release</td>
                                    <td class="text-success">Excellent</td>
                                </tr>
                                <tr>
                                    <td><strong>BDRip</strong></td>
                                    <td><span class="badge bg-primary">8/10</span></td>
                                    <td>8-15 GB</td>
                                    <td>8000-12000 kbps</td>
                                    <td>5.1/7.1</td>
                                    <td>Blu-ray release</td>
                                    <td class="text-primary">Very Good</td>
                                </tr>
                                <tr>
                                    <td><strong>REMUX</strong></td>
                                    <td><span class="badge bg-purple">10/10</span></td>
                                    <td>20-40 GB</td>
                                    <td>20-35 Mbps</td>
                                    <td>Lossless</td>
                                    <td>Blu-ray release</td>
                                    <td class="text-purple">Perfect</td>
                                </tr>
                                <tr>
                                    <td><strong>4K WEB-DL</strong></td>
                                    <td><span class="badge bg-info">9.5/10</span></td>
                                    <td>15-30 GB</td>
                                    <td>15-25 Mbps</td>
                                    <td>Atmos/TrueHD</td>
                                    <td>Digital 4K release</td>
                                    <td class="text-info">Best Value</td>
                                </tr>
                                <tr>
                                    <td><strong>UHD REMUX</strong></td>
                                    <td><span class="badge bg-dark">10/10</span></td>
                                    <td>50-80 GB</td>
                                    <td>50-100 Mbps</td>
                                    <td>Atmos/DTS:X</td>
                                    <td>UHD Blu-ray</td>
                                    <td class="text-dark">Ultimate</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Info -->
    <section class="info-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas fa-bolt fa-3x text-warning"></i>
                            </div>
                            <h5>Release Timeline</h5>
                            <p class="small">CAM → WEB-DL → BDRip → REMUX → UHD REMUX</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas fa-chart-line fa-3x text-success"></i>
                            </div>
                            <h5>Quality vs Size</h5>
                            <p class="small">WEB-DL offers best quality/size ratio for most users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas fa-eye fa-3x text-info"></i>
                            </div>
                            <h5>Viewing Recommendations</h5>
                            <p class="small">For 4K HDR: OLED TV with Atmos sound system</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>



<!-- JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.getElementById('formatSearch');
        const clearButton = document.getElementById('clearSearch');
        const tagLinks = document.querySelectorAll('.tag-link');
        const formatCards = document.querySelectorAll('[data-format]');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            formatCards.forEach(card => {
                const format = card.getAttribute('data-format');
                const text = card.textContent.toLowerCase();
                
                if (searchTerm === '' || format.includes(searchTerm) || text.includes(searchTerm)) {
                    card.style.display = 'block';
                    card.classList.remove('d-none');
                } else {
                    card.style.display = 'none';
                    card.classList.add('d-none');
                }
            });
        });
        
        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        });
        
        tagLinks.forEach(tag => {
            tag.addEventListener('click', function() {
                const tagName = this.getAttribute('data-tag');
                searchInput.value = tagName;
                searchInput.dispatchEvent(new Event('input'));
                
                // Find and activate relevant tab
                const formats = {
                    'web-dl': 'digital',
                    'hdr': 'streaming',
                    'dolby': 'streaming',
                    '4k': 'disc',
                    'remux': 'disc',
                    'x265': 'codecs',
                    'atmos': 'streaming'
                };
                
                if (formats[tagName]) {
                    const tabId = formats[tagName] + '-tab';
                    const tabElement = document.getElementById(tabId);
                    if (tabElement) {
                        new bootstrap.Tab(tabElement).show();
                    }
                }
            });
        });
        
        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-focus search on Ctrl+F
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
            }
            
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
            }
        });
    });
</script>

<?php
stdfoot();
?>