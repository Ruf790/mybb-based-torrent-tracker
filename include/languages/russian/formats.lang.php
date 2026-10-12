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
Translation by xam Version: 0.1

*/

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// formats.php
$language['formats'] = array 
(
	'head'					=>'Downloaded Files',
	'info'					=>'<main class="file-formats-container">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">
                    <i class="fas fa-file-alt me-3"></i>
                    File Formats Encyclopedia 2026
                </h1>
                <p class="hero-subtitle">
                    Ultimate guide to digital file formats, compression types, multimedia containers, 
                    disc images, and scene terminology. From archives to verification files and everything in between.
                </p>
                <div class="hero-badge">
                    <span class="badge bg-primary">2026 Edition</span>
                    <span class="badge bg-success">50+ Formats</span>
                    <span class="badge bg-info">Updated Daily</span>
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
                        <button class="nav-link active" id="compression-tab" data-bs-toggle="tab" 
                                data-bs-target="#compression" type="button" role="tab">
                            <i class="fas fa-file-archive me-2"></i>Compression
                        </button>
                        <button class="nav-link" id="multimedia-tab" data-bs-toggle="tab" 
                                data-bs-target="#multimedia" type="button" role="tab">
                            <i class="fas fa-film me-2"></i>Multimedia
                        </button>
                        <button class="nav-link" id="disc-tab" data-bs-toggle="tab" 
                                data-bs-target="#disc" type="button" role="tab">
                            <i class="fas fa-compact-disc me-2"></i>Disc Images
                        </button>
                        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" 
                                data-bs-target="#documents" type="button" role="tab">
                            <i class="fas fa-file-lines me-2"></i>Documents
                        </button>
                        <button class="nav-link" id="images-tab" data-bs-toggle="tab" 
                                data-bs-target="#images" type="button" role="tab">
                            <i class="fas fa-image me-2"></i>Images
                        </button>
                        <button class="nav-link" id="verification-tab" data-bs-toggle="tab" 
                                data-bs-target="#verification" type="button" role="tab">
                            <i class="fas fa-shield-alt me-2"></i>Verification
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
                           placeholder="Search 50+ formats (ZIP, RAR, MP3, ISO, PDF, etc.)">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-tags mt-3">
                    <small class="text-muted">Trending: </small>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="rar">RAR</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="7z">7Z</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="mkv">MKV</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="iso">ISO</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="pdf">PDF</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="flac">FLAC</span>
                    <span class="badge bg-light text-dark me-1 mb-1 tag-link" data-tag="nfo">NFO</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Tabs -->
    <section class="content-section">
        <div class="container">
            <div class="tab-content" id="formatsTabContent">
                
                <!-- Compression Tab -->
                <div class="tab-pane fade show active" id="compression" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- RAR -->
                        <div class="col-12 col-lg-6" data-format="rar">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-archive text-danger me-2"></i>
                                            RAR
                                        </h4>
                                        <div>
                                            <span class="badge bg-danger">Popular</span>
                                            <span class="badge bg-dark">Proprietary</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Proprietary archive format with excellent compression. Supports multi-volume archives.</p>
                                    <div class="tech-specs">
                                        <small><strong>Compression:</strong> Very Good</small><br>
                                        <small><strong>Features:</strong> Multi-volume, Recovery, Encryption</small><br>
                                        <small><strong>Software:</strong> WinRAR, 7-Zip, PeaZip</small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <small class="text-muted">Most common for scene releases</small>
                                </div>
                            </div>
                        </div>

                        <!-- ZIP -->
                        <div class="col-12 col-lg-6" data-format="zip">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-zipper text-warning me-2"></i>
                                            ZIP
                                        </h4>
                                        <span class="badge bg-warning">Universal</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Most widely supported archive format. Native support in Windows/macOS.</p>
                                    <div class="tech-specs">
                                        <small><strong>Compression:</strong> Good</small><br>
                                        <small><strong>Features:</strong> Simple, Universal</small><br>
                                        <small><strong>Software:</strong> Built-in, 7-Zip, WinZip</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7Z -->
                        <div class="col-12 col-lg-6" data-format="7z">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-archive text-info me-2"></i>
                                            7Z
                                        </h4>
                                        <span class="badge bg-info">Best Compression</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Open-source format with highest compression ratio. Uses LZMA algorithm.</p>
                                    <div class="tech-specs">
                                        <small><strong>Compression:</strong> Excellent (better than RAR)</small><br>
                                        <small><strong>Features:</strong> AES-256, Solid archives</small><br>
                                        <small><strong>Software:</strong> 7-Zip, PeaZip</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACE -->
                        <div class="col-12 col-lg-6" data-format="ace">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-secondary bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file-archive text-secondary me-2"></i>
                                            ACE
                                        </h4>
                                        <span class="badge bg-secondary">Legacy</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Older format, once popular for multi-part archives. Less common now.</p>
                                    <div class="tech-specs">
                                        <small><strong>Compression:</strong> Good for its time</small><br>
                                        <small><strong>Software:</strong> WinAce, 7-Zip (limited)</small><br>
                                        <small><strong>Note:</strong> Discontinued software</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CBR/CBZ -->
                        <div class="col-12 col-lg-6" data-format="cbr">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-book-open text-success me-2"></i>
                                            CBR / CBZ
                                        </h4>
                                        <div>
                                            <span class="badge bg-success">Comics</span>
                                            <span class="badge bg-primary">RAR/ZIP</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Comic book archives. CBR = RAR, CBZ = ZIP with images.</p>
                                    <div class="tech-specs">
                                        <small><strong>Structure:</strong> Images + metadata</small><br>
                                        <small><strong>Software:</strong> YACReader, ComicRack</small><br>
                                        <small><strong>Pages:</strong> Preserves reading order</small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <small class="text-success">Optimized for comic reading</small>
                                </div>
                            </div>
                        </div>

                        <!-- 001 Split -->
                        <div class="col-12 col-lg-6" data-format="001">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-dark bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-cut text-dark me-2"></i>
                                            .001, .r01
                                        </h4>
                                        <span class="badge bg-dark">Split Parts</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Multi-part archive segments. Need all parts to extract.</p>
                                    <div class="tech-specs">
                                        <small><strong>Format:</strong> RAR/7Z split volumes</small><br>
                                        <small><strong>Usage:</strong> Large file distribution</small><br>
                                        <small><strong>Software:</strong> Same as parent format</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Multimedia Tab -->
                <div class="tab-pane fade" id="multimedia" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- Audio Section -->
                        <div class="col-12">
                            <h4 class="section-subtitle mb-3">
                                <i class="fas fa-music me-2 text-primary"></i>
                                Audio Formats
                            </h4>
                        </div>

                        <!-- MP3 -->
                        <div class="col-12 col-lg-4" data-format="mp3">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-music text-primary me-2"></i>
                                        MP3
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Most common lossy audio format. Good quality/size ratio.</p>
                                    <div class="tech-specs">
                                        <small><strong>Bitrate:</strong> 128-320 kbps</small><br>
                                        <small><strong>Use:</strong> Music, Podcasts</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FLAC -->
                        <div class="col-12 col-lg-4" data-format="flac">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-wave-square text-success me-2"></i>
                                        FLAC
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Lossless audio compression. Perfect for audiophiles.</p>
                                    <div class="tech-specs">
                                        <small><strong>Quality:</strong> Lossless</small><br>
                                        <small><strong>Size:</strong> 50-70% of WAV</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OGG -->
                        <div class="col-12 col-lg-4" data-format="ogg">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-music text-info me-2"></i>
                                        OGG
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Open-source container for Vorbis audio.</p>
                                    <div class="tech-specs">
                                        <small><strong>Quality:</strong> Comparable to MP3</small><br>
                                        <small><strong>Free:</strong> No patents</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Video Section -->
                        <div class="col-12 mt-4">
                            <h4 class="section-subtitle mb-3">
                                <i class="fas fa-video me-2 text-danger"></i>
                                Video Containers
                            </h4>
                        </div>

                        <!-- MKV -->
                        <div class="col-12 col-lg-3" data-format="mkv">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <h6 class="mb-0">MKV</h6>
                                </div>
                                <div class="card-body p-2">
                                    <small>Matroska - Most flexible container</small>
                                </div>
                            </div>
                        </div>

                        <!-- AVI -->
                        <div class="col-12 col-lg-3" data-format="avi">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <h6 class="mb-0">AVI</h6>
                                </div>
                                <div class="card-body p-2">
                                    <small>Legacy Microsoft format</small>
                                </div>
                            </div>
                        </div>

                        <!-- MP4 -->
                        <div class="col-12 col-lg-3" data-format="mp4">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <h6 class="mb-0">MP4</h6>
                                </div>
                                <div class="card-body p-2">
                                    <small>Universal container</small>
                                </div>
                            </div>
                        </div>

                        <!-- MOV -->
                        <div class="col-12 col-lg-3" data-format="mov">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <h6 class="mb-0">MOV</h6>
                                </div>
                                <div class="card-body p-2">
                                    <small>QuickTime format</small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Disc Images Tab -->
                <div class="tab-pane fade" id="disc" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- ISO -->
                        <div class="col-12 col-lg-6" data-format="iso">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-circle text-primary me-2"></i>
                                            ISO
                                        </h4>
                                        <span class="badge bg-primary">Universal</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Standard disc image format. Contains exact copy of optical media.</p>
                                    <div class="tech-specs">
                                        <small><strong>Type:</strong> Single file image</small><br>
                                        <small><strong>Software:</strong> Daemon Tools, PowerISO</small><br>
                                        <small><strong>Use:</strong> OS, Software, Games</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BIN/CUE -->
                        <div class="col-12 col-lg-6" data-format="bin">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-file text-warning me-2"></i>
                                            BIN / CUE
                                        </h4>
                                        <span class="badge bg-warning">Raw + Metadata</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Raw disc image (BIN) with cue sheet for track info.</p>
                                    <div class="tech-specs">
                                        <small><strong>BIN:</strong> Raw data</small><br>
                                        <small><strong>CUE:</strong> Track layout</small><br>
                                        <small><strong>Use:</strong> CD/DVD/Blu-ray backups</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CCD/IMG/SUB -->
                        <div class="col-12 col-lg-6" data-format="ccd">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-layer-group text-info me-2"></i>
                                            CCD / IMG / SUB
                                        </h4>
                                        <span class="badge bg-info">CloneCD</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">CloneCD format with subchannel data for copy protection.</p>
                                    <div class="tech-specs">
                                        <small><strong>CCD:</strong> Control data</small><br>
                                        <small><strong>IMG:</strong> Image data</small><br>
                                        <small><strong>SUB:</strong> Subchannel</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MDF/MDS -->
                        <div class="col-12 col-lg-6" data-format="mdf">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-database text-danger me-2"></i>
                                            MDF / MDS
                                        </h4>
                                        <span class="badge bg-danger">Alcohol 120%</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Alcohol 120% format with Media Descriptor File.</p>
                                    <div class="tech-specs">
                                        <small><strong>MDF:</strong> Main image</small><br>
                                        <small><strong>MDS:</strong> Metadata</small><br>
                                        <small><strong>Features:</strong> DPM, SafeDisc</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Documents Tab -->
                <div class="tab-pane fade" id="documents" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- PDF -->
                        <div class="col-12 col-lg-4" data-format="pdf">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-danger bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-pdf text-danger me-2"></i>
                                        PDF
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>Portable Document Format - Universal document standard</p>
                                </div>
                            </div>
                        </div>

                        <!-- DOC -->
                        <div class="col-12 col-lg-4" data-format="doc">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-word text-primary me-2"></i>
                                        DOC/DOCX
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>Microsoft Word documents</p>
                                </div>
                            </div>
                        </div>

                        <!-- TXT -->
                        <div class="col-12 col-lg-4" data-format="txt">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-secondary bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-lines text-secondary me-2"></i>
                                        TXT
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>Plain text - Universal readability</p>
                                </div>
                            </div>
                        </div>

                        <!-- NFO -->
                        <div class="col-12 col-lg-4" data-format="nfo">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        NFO
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>Release information with ASCII art</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Images Tab -->
                <div class="tab-pane fade" id="images" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- JPG -->
                        <div class="col-12 col-lg-3" data-format="jpg">
                            <div class="format-card card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-image fa-2x text-primary mb-2"></i>
                                    <h6>JPG/JPEG</h6>
                                    <small>Lossy, small files</small>
                                </div>
                            </div>
                        </div>

                        <!-- PNG -->
                        <div class="col-12 col-lg-3" data-format="png">
                            <div class="format-card card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-image fa-2x text-success mb-2"></i>
                                    <h6>PNG</h6>
                                    <small>Lossless, transparency</small>
                                </div>
                            </div>
                        </div>

                        <!-- GIF -->
                        <div class="col-12 col-lg-3" data-format="gif">
                            <div class="format-card card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-image fa-2x text-warning mb-2"></i>
                                    <h6>GIF</h6>
                                    <small>Animation, 256 colors</small>
                                </div>
                            </div>
                        </div>

                        <!-- WebP -->
                        <div class="col-12 col-lg-3" data-format="webp">
                            <div class="format-card card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-image fa-2x text-info mb-2"></i>
                                    <h6>WebP</h6>
                                    <small>Modern, superior</small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Verification Tab -->
                <div class="tab-pane fade" id="verification" role="tabpanel">
                    <div class="row g-4">
                        
                        <!-- SFV -->
                        <div class="col-12 col-lg-4" data-format="sfv">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-check-circle text-primary me-2"></i>
                                        SFV
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>Simple File Verification - CRC32 checksums</p>
                                </div>
                            </div>
                        </div>

                        <!-- MD5 -->
                        <div class="col-12 col-lg-4" data-format="md5">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-info bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-fingerprint text-info me-2"></i>
                                        MD5
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>MD5 hash - File integrity verification</p>
                                </div>
                            </div>
                        </div>

                        <!-- SHA -->
                        <div class="col-12 col-lg-4" data-format="sha">
                            <div class="format-card card border-0 shadow-sm h-100">
                                <div class="card-header bg-success bg-opacity-10">
                                    <h5 class="mb-0">
                                        <i class="fas fa-shield-alt text-success me-2"></i>
                                        SHA-1/256
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p>Secure Hash Algorithm - Stronger verification</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Quick Reference Table -->
    <section class="comparison-section mt-5">
        <div class="container">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient-compare">
                    <h3 class="card-title text-white mb-0">
                        <i class="fas fa-table me-2"></i>
                        File Format Reference 2026
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Format</th>
                                    <th>Type</th>
                                    <th>Best For</th>
                                    <th>Software</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td><strong>RAR</strong></td><td>Archive</td><td>Scene releases</td><td>WinRAR</td><td>Multi-volume</td></tr>
                                <tr><td><strong>ZIP</strong></td><td>Archive</td><td>General use</td><td>Built-in</td><td>Universal</td></tr>
                                <tr><td><strong>7Z</strong></td><td>Archive</td><td>Best compression</td><td>7-Zip</td><td>Open source</td></tr>
                                <tr><td><strong>CBR</strong></td><td>Comics</td><td>Comic books</td><td>YACReader</td><td>RAR-based</td></tr>
                                <tr><td><strong>ISO</strong></td><td>Disc image</td><td>OS/Software</td><td>Daemon Tools</td><td>Universal</td></tr>
                                <tr><td><strong>PDF</strong></td><td>Document</td><td>Documents</td><td>Adobe Reader</td><td>Standard</td></tr>
                                <tr><td><strong>NFO</strong></td><td>Text</td><td>Release info</td><td>Notepad</td><td>ASCII art</td></tr>
                                <tr><td><strong>SFV</strong></td><td>Checksum</td><td>Verification</td><td>QuickSFV</td><td>CRC32</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Info -->
    <section class="info-section mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas fa-bolt fa-3x text-warning"></i>
                            </div>
                            <h5>Pro Tips</h5>
                            <p class="small">Always verify SFV/MD5 files after download</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas fa-shield-alt fa-3x text-success"></i>
                            </div>
                            <h5>Security</h5>
                            <p class="small">Scan archives before extracting</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="info-icon mb-3">
                                <i class="fas fa-sync fa-3x text-info"></i>
                            </div>
                            <h5>Compatibility</h5>
                            <p class="small">7-Zip opens almost all archive formats</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>'
);
?>
