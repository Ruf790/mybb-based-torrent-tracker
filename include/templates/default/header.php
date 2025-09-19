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
if(!defined('IN_TRACKER')) die('Hacking attempt!');
/* TS Special Edition Default Template by xam - v5.6 */
?>
<!doctype html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset; ?>" />
    <meta name="generator" content="<?php echo $title; ?>" />
    <meta name="revisit-after" content="3 days" />
    <meta name="robots" content="index, follow" />
    <meta name="description" content="<?php echo $metadesc; ?>" />
    <meta name="keywords" content="<?php echo $metakeywords; ?>" />
    <meta http-equiv="Content-Script-Type" content="text/javascript" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	
	<script type="text/javascript" src="<?php echo $BASEURL; ?>/scripts/jquery.js?ver=1823"></script>
    <script type="text/javascript" src="<?php echo $BASEURL; ?>/scripts/jquery.plugins.min.js?ver=1821"></script>
    <script type="text/javascript" src="<?php echo $BASEURL; ?>/scripts/general.js?ver=1827"></script>
	<script type="text/javascript" src="<?php echo $BASEURL; ?>/scripts/bootstrap.bundle.min.js"></script>
	
	
	
<?php echo (isset($includeCSS) ? $includeCSS : ''); ?>
<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php echo $BASEURL; ?>/rss.php" />
<link rel="alternate" type="text/xml" title="RSS .92" href="<?php echo $BASEURL; ?>/rss.php" />
<link rel="shortcut icon" href="<?php echo $BASEURL; ?>/favicon.ico" type="image/x-icon" />
<script type="text/javascript">
	//<![CDATA[
	var baseurl="<?php echo htmlspecialchars_uni($BASEURL); ?>";
	var dimagedir="<?php echo $BASEURL; ?>/<?php echo $pic_base_url; ?>";
	var charset="<?php echo $charset; ?>";
	var userid="<?php echo (isset($CURUSER['id']) ? (int)$CURUSER['id'] : 0); ?>";
	//]]>
</script>

<script type="text/javascript">
<!--
	
	lang.expcol_collapse = "[-]";
	lang.expcol_expand = "[+]";
	lang.select2_match = "One result is available, press enter to select it.";
	lang.select2_matches = "{1} results are available, use up and down arrow keys to navigate.";
	lang.select2_nomatches = "No matches found";
	lang.select2_inputtooshort_single = "Please enter one or more character";
	lang.select2_inputtooshort_plural = "Please enter {1} or more characters";
	lang.select2_inputtoolong_single = "Please delete one character";
	lang.select2_inputtoolong_plural = "Please delete {1} characters";
	lang.select2_selectiontoobig_single = "You can only select one item";
	lang.select2_selectiontoobig_plural = "You can only select {1} items";
	lang.select2_loadmore = "Loading more results&hellip;";
	lang.select2_searching = "Searching&hellip;";
	

	var use_xmlhttprequest = "<?php echo  $use_xmlhttprequest; ?>";
	
	var my_post_key = "<?php echo $mybb->post_code; ?>";
	

	var cookieDomain = "<?php echo $cookiedomain; ?>";
	var cookiePath = "<?php echo $cookiepath; ?>";
	var cookiePrefix = "<?php echo $cookieprefix; ?>";
	var cookieSecureFlag = "<?php echo $cookiesecureflag; ?>";
	
	
	var MyBBEditor = null;
	
	var spinner_image = "https://ruff-tracker.eu/pic/spinner.gif";
	var spinner = "<img src='" + spinner_image +"' alt='' />";
	
	
	var loading_text = 'Loading. <br />Please Wait&hellip;';
	var saving_changes = 'Saving changes&hellip;';
	
		
	
// -->
</script>

<script type="text/javascript">
window.onscroll = () => {
  toggleTopButton();
}
function scrollToTop(){
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function toggleTopButton() {
  if (document.body.scrollTop > 20 ||
      document.documentElement.scrollTop > 20) {
    document.getElementById('back-to-up').classList.remove('d-none');
  } else {
    document.getElementById('back-to-up').classList.add('d-none');
  }
}
</script>


<script>
     document.addEventListener('DOMContentLoaded', (event) => {
                const htmlElement = document.documentElement;
                const switchElement = document.getElementById('darkModeSwitch');
                const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
                const currentTheme = localStorage.getItem('bsTheme') || (prefersDarkScheme ? 'dark' : 'light');

                htmlElement.setAttribute('data-bs-theme', currentTheme);
                switchElement.checked = currentTheme === 'dark';

                switchElement.addEventListener('change', function () {
                    const newTheme = this.checked ? 'dark' : 'light';
                    htmlElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('bsTheme', newTheme);
                });

                // Tooltip aktivieren
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
 </script>



	
	
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="<?php echo $BASEURL; ?>/include/templates/default/style/bootstrap.min.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="<?php echo $BASEURL; ?>/include/templates/default/style/all.min.css" type="text/css" media="screen" />


<style>	
header.sticky-top {
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1020;
}
</style>	
	
	
	
	
  </head>
  <body>
  <div class="container md">
  
 



<button class="btn btn-primary position-fixed bottom-0 end-0 translate-middle d-none" onclick="scrollToTop()" id="back-to-up">
  <i class="fa fa-arrow-up" aria-hidden="true"></i>
</button>


<?php
////////////////////////////////////////////////$lang->load('scripts');






//echo '
//<script type="text/javascript" src="'.$BASEURL.'/scripts/main.js?v='.O_SCRIPT_VERSION.'"></script>
//'.(isset($includescripts) ? $includescripts : '').'';





// Prepare the main templates for use
$admincplink = $modcplink = $usercplink = '';





$pms_unread = isset($CURUSER['pms_unread']) ? ts_nf($CURUSER['pms_unread']) : ts_nf(0);
$pms_total = isset($CURUSER['pms_total']) ? ts_nf($CURUSER['pms_total']) : ts_nf(0);
$welcome_pms_usage = sprintf($lang->global['welcome_pms_usage'], $pms_unread, $pms_total);



//$welcome_pms_usage = sprintf($lang->global['welcome_pms_usage'], ts_nf($CURUSER['pms_unread']), ts_nf($CURUSER['pms_total']));

$pmslink = '
<div class="d-none d-sm-none d-md-none d-lg-block d-xl-block d-xxl-block">
<a href="'.$BASEURL.'/private.php" title="'.$welcome_pms_usage.'"><i class="fa-solid fa-envelope"></i> &nbsp;'.$lang->global['welcome_pms'].'</a>
</div>

<div class="d-block d-sm-block d-md-block d-lg-none d-xl-none d-xxl-none">
<a href="'.$BASEURL.'/private.php"><i class="fa-solid fa-envelope"></i></a>
</div>';




$usercplink ='
<a href="'.$BASEURL.'/usercp.php" class="dropdown-item pb-2 ms-0 ps-0" style="border-bottom: 1px solid #b3b3b3"><i class="fa-solid fa-user-gear"></i> &nbsp;User CP</a>
';








if (
    is_array($usergroups) && 
    isset($usergroups['canstaffpanel'], $usergroups['cansettingspanel'], $usergroups['issupermod']) &&
    $usergroups['canstaffpanel'] == 1 &&
    $usergroups['cansettingspanel'] == 1 &&
    $usergroups['issupermod'] == 1
) {
    $admincplink = '
    <a href="' . $BASEURL . '/admin/index.php" class="dropdown-item ms-0 ps-0">
        <i class="fa-solid fa-gears"></i> &nbsp;Admin CP
    </a>
    ';

    $modcplink = '
    <a href="' . $BASEURL . '/modcp.php" class="dropdown-item ms-0 ps-0">
        <i class="fa-solid fa-screwdriver-wrench"></i> &nbsp;&nbsp;Mod CP
    </a>
    ';
}
















// Set the logout key for this user
if (isset($CURUSER) && is_array($CURUSER) && isset($CURUSER['loginkey'])) 
{
    $logoutkey = md5($CURUSER['loginkey']);
} 
else 
{
    // Handle error or assign a default
    $logoutkey = ''; // or null or some fallback value
    // Maybe log error or redirect
}




if (!empty($CURUSER) && isset($CURUSER['id'])) 
{
    $profilelink_link = get_profile_link($CURUSER['id']);
} 
else 
{
    // Handle the case where $CURUSER is null or doesn't have 'id'
    $profilelink_link = '';  // or a default value
}





	
echo '</br>
</br>';





// Получаем информацию о раздачах и скачиваниях текущего пользователя
$uid = (int)$CURUSER['id'];


// Количество сидов и личей
$seedtorrentscount   = tsrowcount('id', 'peers', "seeder='yes' AND userid={$uid}");
$leechingtorrentscount = tsrowcount('id', 'peers', "seeder='no' AND userid={$uid}");

// Определяем тип пользователя
if ($seedtorrentscount > 0) 
{
    $seederOrLeecher = '<span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i> Seeder</span>';
} 

elseif ($leechingtorrentscount > 0) 
{
    $seederOrLeecher = '<span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i> Leecher</span>';
} 

else 
{
    $seederOrLeecher = '<span class="badge bg-secondary"><i class="fas fa-minus me-1"></i> Idle</span>';
}













$useravatar = format_avatar($CURUSER['avatar'], $CURUSER['avatardimensions']);


if (strpos($useravatar['image'], '<') === 0) 
{
       $avatar_mini = '
    <svg class="nav-avatar rounded border avatar-ring2" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
        <text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">No Avatar</text>
    </svg>';
} 
else 
{
       $avatar_mini = '<img class="nav-avatar" src="' . $useravatar['image'] . '" alt="" ' . $useravatar['width_height'] . ' />';
}


 
if (strpos($useravatar['image'], '<') === 0) 
{
       $avataar = '
    <svg class="nav-avatar rounded border avatar-ring2" width="50" height="50" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="45" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
        <text x="50" y="55" text-anchor="middle" font-size="12" fill="#666">No Avatar</text>
    </svg>';
} 
else 
{
       $avataar = '<img src="' . $useravatar['image'] . '" style="width: 80px;" class="rounded border" />';
	   
} 





	


?>



<!-- Блок информации пользователя -->
<style>
.navbar-fixed {
    max-width: 1100px;
    margin: 0 auto; /* центрирование */
    width: 100%;
}
.header-fixed {
    max-width: 1100px;
    margin: 0 auto;
    width: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 0 15px;
}


.nav-link.dropdown-toggle::after {
  display: none; /* скрываем стандартную стрелку Bootstrap */
}
.nav-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 6px;
  border: 1px solid #ccc;
}




</style>




<?php if (isset($CURUSER)) { ?>	
<!-- Header with card-style navbar -->










<header class="sticky-top header-fixed">





<div class="container-fluid py-1 bg-light border-bottom">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">
    
    <!-- Профиль с аватаром -->
    <div class="dropdown">
      <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
        <!-- Mini Avatar -->
        <?= $avatar_mini ?>
        <!-- Username -->
		<div class="text-dark fw-semibold">
      <?=$lang->global['welcomeback'];?> 
      
      <span class="text-primary"><?= format_name($CURUSER['username'], $CURUSER['usergroup']);?></span>
	  <?= $warn ?> 
	  <?= $lwarn ?> 
	  <?= $medaldon ?> 
    </div>
		
		
		
		
		
        <!-- Arrow -->
        <i class="fa-solid fa-angle-down small ms-1"></i>
      </a>

      <!-- Dropdown Menu -->
      <div class="dropdown-menu dropdown-menu-end border rounded" style="width: 250px">
        <div class="row p-2">
          <div class="col align-self-center">
            <?php echo $usercplink; ?>
            <?php echo $modcplink; ?>
            <?php echo $admincplink; ?>
            
			<button type="button" class="dropdown-item ms-0 ps-0"
                data-bs-toggle="modal" data-bs-target="#logoutModal">
                <i class="fa-solid fa-right-from-bracket"></i> &nbsp;&nbsp;Log Out
            </button>

			
          </div>
         
		 <div class="col-auto align-self-center">
            <?= $avataar ?>
          </div>


        </div>
      </div>
    </div>

    <!-- Статистика -->
    <small class="text-dark d-flex flex-wrap justify-content-center gap-2 align-items-center">
    <span><i class="fa-solid fa-upload me-1"></i>Uploaded: <strong><?= mksize($uploaded) ?></strong></span>
    <span><i class="fa-solid fa-download me-1"></i>Downloaded: <strong><?= mksize($downloaded) ?></strong></span>
    <span><i class="fa-solid fa-chart-simple me-1"></i>Ratio: <strong><?= $ratio ?></strong></span>
    <span><i class="fa-solid fa-coins me-1"></i>Bonus: <strong><?= number_format($CURUSER['seedbonus'], 1) ?></strong></span>
    <span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i><?= $seedtorrentscount ?></span>
    <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i><?= $leechingtorrentscount ?></span>
</small>
    
  </div>
</div>



	



    <nav class="navbar navbar-expand-lg bg-body-tertiary">
	
	
        <div class="container-fluid">
           
		   <a class="navbar-brand" href="<?php echo $BASEURL?>/index.php"><?php echo $lang->global['home']; ?></a>
		    <!-- Исправленная кнопка (добавлен открывающий тег button) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
		   
		   
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- левая часть меню -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-list me-1"></i> Browse
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/browse.php"><i class="fas fa-table-list me-2"></i> Browse Torrents</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/upload.php"><i class="fas fa-upload me-2"></i> Upload</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/browse.php?special_search=mybookmarks"><i class="fas fa-bookmark me-2"></i> My Bookmarks</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/browse.php?special_search=myreseeds"><i class="fas fa-seedling me-2"></i> My Reseeds</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/browse.php?special_search=weaktorrents"><i class="fas fa-heart-crack me-2"></i> Weak Torrents</a></li>
                        </ul>
                    </li>
                    
                    <!-- остальные пункты меню -->
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-comments me-1"></i> Forums
                        </a>
                        <ul class="dropdown-menu dropdown-menu-card">
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/index2.php"><i class="fas fa-forumbee me-2"></i> Forums</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/search.php?action=getnew"><i class="fas fa-star me-2"></i> New Posts</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/search.php?action=getdaily"><i class="fas fa-calendar-day me-2"></i> Today's Posts</a></li>
                            <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/search.php"><i class="fas fa-search me-2"></i> Search</a></li>
                        </ul>
                    </li>
                    
                    <!-- остальные пункты меню продолжаются... -->
					
					
					
					 <li class="nav-item dropdown">
                            <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i> User CP
                            </a>
                            <ul class="dropdown-menu dropdown-menu-card">
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/usercp.php"><i class="fas fa-cog me-2"></i> User CP Home</a></li>
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/private.php"><i class="fas fa-envelope me-2"></i> Private Messages</a></li>
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/browse.php?special_search=mytorrents"><i class="fas fa-download me-2"></i> Your Torrents</a></li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link nav-link-card dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-chart-line me-1"></i> Top 10
                            </a>
                            <ul class="dropdown-menu dropdown-menu-card">
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/topten.php?type=1"><i class="fas fa-users me-2"></i> Users</a></li>
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/topten.php?type=2"><i class="fas fa-download me-2"></i> Torrents</a></li>
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/topten.php?type=3"><i class="fas fa-globe me-2"></i> Countries</a></li>
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/topten.php?type=4"><i class="fas fa-network-wired me-2"></i> Peers</a></li>
                                <li><a class="dropdown-item dropdown-item-card" href="<?php echo $BASEURL; ?>/topten.php?type=5"><i class="fas fa-comments me-2"></i> Forums</a></li>
                            </ul>
                        </li>
					
					
					
					
					
					
					
					<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
        <i class="fas fa-ellipsis-h me-1"></i> <?php echo $lang->global['extra']; ?>
    </a>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item" href="<?php echo $profilelink_link?>">
                <i class="fas fa-user-circle me-2"></i> Your Profile
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?php echo $BASEURL?>/memberlist.php">
                <i class="fas fa-users me-2"></i> Members List
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?php echo $BASEURL?>/getrss.php">
                <i class="fas fa-rss me-2"></i> RSS Feeds
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?php echo $BASEURL?>/invite.php">
                <i class="fas fa-user-plus me-2"></i> Invite Friend
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?php echo $BASEURL?>/mybonus.php">
                <i class="fas fa-coins me-2"></i> Bonus Points
            </a>
        </li>
    </ul>
</li>
					
					
					
					<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
        <i class="fas fa-shield-alt me-1"></i> <?php echo $lang->global['staff']; ?>
    </a>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item" href="<?php echo $BASEURL?>/showteam.php">
                <i class="fas fa-users-cog me-2"></i> Staff Team
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="<?php echo $BASEURL?>/contactstaff.php">
                <i class="fas fa-envelope me-2"></i> Contact Staff
            </a>
        </li>
    </ul>
</li>
                    
                
               

                
				
				

<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
        <i class="fas fa-search"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 300px;">
        <form class="d-flex" role="search" method="post" action="<?php echo $BASEURL?>/browse.php?do=search&amp;search_type=t_both">
            <input type="hidden" name="search_type" value="t_both" />
            <input type="hidden" name="do" value="search" />
            <div class="input-group">
                <input class="form-control" type="text" value="" name="keywords" placeholder="Search torrents..." aria-label="Search">
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</li>





				
                
                <a class="btn btn-search pt-2 border-0 btn-sm text-muted ms-2" href="<?php echo $BASEURL?>/search.php" style="border-left: 0px!important">
                    <i class="fa-solid fa-gear"></i>
                </a>
                
                <!-- Bootstrap 5 switch -->
                <div class="form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" id="darkModeSwitch" checked aria-label="Switch between light and dark mode" data-bs-toggle="tooltip" data-bs-placement="top" title="Switch between light and dark mode">
                    <label class="form-check-label" for="darkModeSwitch"></label>
                </div>
            </div>
        </div>	
		
    </nav>
	
	

	

	
	
	
</header>


<!-- Modal Logout -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content shadow-lg border-0 rounded-3">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="fa-solid fa-right-from-bracket me-2"></i> Confirm Logout
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p class="mb-0 fs-6">Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="fa-solid fa-xmark me-1"></i> Cancel
        </button>
        <a href="<?= $BASEURL ?>/member.php?action=logout&amp;logoutkey=<?= $logoutkey; ?>" 
           class="btn btn-danger px-4">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Log Out
        </a>
      </div>
    </div>
  </div>
</div>


	
</br>
</br>
</br>

	
	
<?php 
	
}
else
{
	


echo '



<nav class="navbar navbar-expand-lg bg-white shadow-sm border-bottom py-3">
  <div class="container">
    

    <!-- Бургер меню -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#guestNavbar" aria-controls="guestNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Навигация -->
    <div class="collapse navbar-collapse" id="guestNavbar">
      <ul class="navbar-nav ms-auto align-items-center">
        <!-- Инфо -->
        <li class="nav-item me-3">
          <a class="nav-link text-secondary fw-semibold" href="<?=$BASEURL;?>/faq.php">
            <i class="fa-solid fa-circle-question me-1"></i> FAQ
          </a>
        </li>
        <li class="nav-item me-3">
          <a class="nav-link text-secondary fw-semibold" href="'.$BASEURL.'/contact.php">
            <i class="fa-solid fa-envelope me-1"></i> Contact
          </a>
        </li>
        <!-- Кнопки -->
        <li class="nav-item me-2">
          <a class="btn btn-outline-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="'.$BASEURL.'/member.php?action=login">
            <i class="fa-solid fa-right-to-bracket me-1"></i> Login
          </a>
        </li>
        <li class="nav-item">
          <a class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm hover-shadow" href="'.$BASEURL.'/member.php?action=register">
            <i class="fa-solid fa-user-plus me-1"></i> Register
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>




</br>

</br>


';


    

 









}








$awaitingusers = '';

if (
    is_array($usergroups) &&
    isset($usergroups['canstaffpanel'], $usergroups['cansettingspanel'], $usergroups['issupermod']) &&
    $usergroups['canstaffpanel'] == '1' &&
    $usergroups['cansettingspanel'] == '1' &&
    $usergroups['issupermod'] == '1'
) {
    $awaitingusers = $cache->read('awaitingactivation');

    if (isset($awaitingusers['time']) && $awaitingusers['time'] + 86400 < TIMENOW) 
	{
        $cache->update_awaitingactivation();
        $awaitingusers = $cache->read('awaitingactivation');
    }

    if (!empty($awaitingusers['users'])) 
	{
        $awaitingusers = (int)$awaitingusers['users'];
    } 
	else 
	{
        $awaitingusers = 0;
    }

    if ($awaitingusers < 1) 
	{
        $awaitingusers = 0;
    } 
	else 
	{
        $awaitingusers = ts_nf($awaitingusers);
    }

    if ($awaitingusers > 0) 
	{
        if ($awaitingusers == 1) 
		{
            $awaiting_message = $lang->global['awaiting_message_single'];
        } 
		else 
		{
            $awaiting_message = sprintf($lang->global['awaiting_message_plural'], $awaitingusers);
        }

        if ($admincplink) 
		{
            $awaiting_message .= sprintf($lang->global['awaiting_message_link'], $BASEURL);
        }

        //$awaitingusers = '<div class="red_alert">' . $awaiting_message . '</div>';
		
		$awaitingusers = '
		
  <link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
  <link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">
    <div class="container mt-3">
	
	<div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">Attention</h2>
          <p class="mb-0 opacity-75">Awaiting Activation</p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
          '.$awaiting_message.'
        </div>
      </div>
    </div>
  </div>';


        echo $awaitingusers;
    } 
	else 
	{
        $awaitingusers = '';
    }
}





// Is this user apart of a banned group?
$bannedwarning = '';
if (is_array($usergroups) && isset($usergroups['isbannedgroup']) && $usergroups['isbannedgroup'] == 1) 
{
	// Format their ban lift date and reason appropriately
	if(!empty($mybb->user['banned']))
	{
		if(!empty($mybb->user['banlifted']))
		{
			$banlift = my_datee('normal', $mybb->user['banlifted']);
		}
		else
		{
			$banlift = $lang->global['banned_lifted_never'];
		}
	}
	else
	{
		$banlift = $lang->global['unknown2'];
	}

	if(!empty($mybb->user['banreason']))
	{
		$reason = htmlspecialchars_uni($mybb->user['banreason']);
	}
	else
	{
		$reason = $lang->global['unknown2'];
	}

	// Display a nice warning to the user
	eval('$bannedwarning = "'.$templates->get('global_bannedwarning').'";');
}

echo $bannedwarning;






$output = '';
$notallowed = false;
if($mybb->usergroup['canview'] != 1)
{
	// Check pages allowable even when not allowed to view board
	if(defined('ALLOWABLE_PAGE'))
	{
		if(is_string(ALLOWABLE_PAGE))
		{
			$allowable_actions = explode(',', ALLOWABLE_PAGE);
			if(!in_array($mybb->get_input('action'), $allowable_actions))
			{
				$notallowed = true;
			}

			unset($allowable_actions);
		}
		else if(ALLOWABLE_PAGE !== 1)
		{
			$notallowed = true;
		}
	}
	else
	{
		$notallowed = true;
	}

	if($notallowed == true)
	{
		if(!$mybb->get_input('modal'))
		{
			error_no_permission();
		}
		else
		{
			eval('$output = "'.$templates->get('global_no_permission_modal', 1, 0).'";');
			echo($output);
			exit;
		}
	}
}








// Check banned ip addresses
if(is_banned_ip($session->ipaddress, true))
{
	if($CURUSER['id'])
	{
		$db->delete_query('sessions', "ip = ".$db->escape_binary($session->packedip)." OR uid='{$CURUSER['id']}'");
	}
	else
	{
		$db->delete_query('sessions', "ip = ".$db->escape_binary($session->packedip));
	}
	error('Im sorry, but you are banned.  You may not post, read threads, or access the Tracker.  Please contact your forum administrator should you have any questions');
}








   



if ($offlinemsg)
$warnmessages[] = '


<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">


<div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">The tracker is currently offline! </h2>
          <p class="mb-0 opacity-75"></p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
         <strong>Danger!</strong> '.sprintf($lang->header['trackeroffline'], $BASEURL).'
        </div>

        
		
		
      </div>
    </div>';




if((isset($CURUSER) && $CURUSER['id'] > 0 && $CURUSER['downloaded'] > 0 && $CURUSER['leechwarn'] == 'yes' AND $CURUSER['leechwarnuntil'] > TIMENOW))
{
	include_once(INC_PATH.'/readconfig_cleanup.php');
	require_once(INC_PATH.'/functions_mkprettytime.php');
	
	


$warnmessages[] = '


<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/errorss.css" rel="stylesheet">



<div class="card error-card">
      <div class="card-header22">
        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
        <div>
          <h2 class="mb-0">You are now warned for having a low ratio! </h2>
          <p class="mb-0 opacity-75"></p>
        </div>
      </div>
      <div class="card-body">
        <div class="alert alert-danger" role="alert">
         <strong>Danger!</strong> '.sprintf($lang->header['warned'], $leechwarn_remove_ratio, mkprettytime($CURUSER['leechwarnuntil'] - TIMENOW)).'
        </div>

        
		
		
      </div>
    </div>';




	
}




if (isset($CURUSER) AND $CURUSER['announce_read'] == 'no')
{
    $res = $db->sql_query ('SELECT subject,message,added,`by` FROM announcements WHERE minclassread IN (0,' . $CURUSER['usergroup'] . ') ORDER by added DESC LIMIT 1');
    if (0 < $db->num_rows ($res))
    {
      $arr = $db->fetch_array ($res);
     
	 

    require_once(INC_PATH.'/class_parser.php');
    $parser = new postParser;
  


    $parser_options = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"allow_videocode" => 1,
		"filter_badwords" => 1
    );
	  
	  
	  
	  $zz = '<!-- Button trigger modal -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
  '.$lang->header['newann'].'
</button>




<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">
		
		' . str_replace ('&amp;', '&', 
	   htmlspecialchars_uni ($arr['subject'])) . ' - ' . htmlspecialchars_uni ($arr['by']) . ' - ' . my_datee ($dateformat, $arr['added']) . ', ' . my_datee ($timeformat, $arr['added']) . '
	   

		</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
	  ' . $parser->parse_message($arr['message'],$parser_options).'
	  
	 
	  
      </div>
      <div class="modal-footer">
        
		<a href="'.$BASEURL.'/clear_ann.php">
		<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
		</a>
		
      </div>
    </div>
  </div>
</div>
';

    $infomessages[] = $zz.'</br></br>';
 
	  
	}      
	
}





$current_page = my_strtolower(basename(SCRIPTNAME));

// Check if this user has a new private message.
$pm_notice = '';
//if(isset($CURUSER['pmnotice']) && $CURUSER['pmnotice'] == 2 && $CURUSER['unreadpms'] > 0 && ($current_page != "private.php" || $mybb->get_input('action') != "read"))
	
if(isset($CURUSER['pmnotice']) && $CURUSER['pmnotice'] == 2 && $CURUSER['pms_unread'] > 0 && ($current_page != "private.php" || $mybb->get_input('action') != "read"))

{
	//if(!isset($parser))
	//{
		//require_once MYBB_ROOT.'inc/class_parser.php';
		//$parser = new postParser;
	//}

	$query = $db->sql_query("
		SELECT pm.subject, pm.pmid, fu.username AS fromusername, fu.id AS fromuid
		FROM privatemessages pm
		LEFT JOIN users fu on (fu.id=pm.fromid)
		WHERE pm.folder = '1' AND pm.uid = '{$CURUSER['id']}' AND pm.status = '0'
		ORDER BY pm.dateline DESC
		LIMIT 1
	");

	$pm = $db->fetch_array($query);
	$pm['subject'] = htmlspecialchars_uni($pm['subject']);

	if($pm['fromuid'] == 0)
	{
		$pm['fromusername'] = 'Ruff Tracker Engine';
		$user_text = $pm['fromusername'];
	}
	else
	{
		$pm['fromusername'] = htmlspecialchars_uni($pm['fromusername']);
		$user_text = build_profile_link($pm['fromusername'], $pm['fromuid']);
	}

	if($CURUSER['pms_unread'] == 1)
	{
		$privatemessage_text = sprintf($lang->global['newpm_notice_one'], $user_text, $BASEURL, $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	else
	{
		$privatemessage_text = sprintf($lang->global['newpm_notice_multiple'], $CURUSER['pms_unread'], $user_text, $BASEURL, $pm['pmid'], htmlspecialchars_uni($pm['subject']));
	}
	

$pm_notice = '
<script type="text/javascript" src="'.$BASEURL.'/scripts/dismisspm.js"></script>
<link href="'.$BASEURL.'/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">
<link href="'.$BASEURL.'/include/templates/default/style/messagess.css" rel="stylesheet">

<div class="card error-card4 fade show" id="pm_notice">
  <div class="card-header4">
    <i class="bi bi-exclamation-triangle-fill error-icon4"></i>
    <div><h2 class="mb-0">You have a private Message</h2></div>

    <div class="float-end ms-auto">
      <a href="'.$BASEURL.'/private.php?action=dismiss_notice&amp;my_post_key='.$mybb->post_code.'"
         title="'.$lang->header['dismiss_notice'].'"
         onclick="return dismissPMNotice(\''.$BASEURL.'/\')">
        <i class="btn-close"></i>
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="alert4 alert-info" role="alert">
      '.$privatemessage_text.'
    </div>
  </div>
</div>
';

	
	$infomessages[] = $pm_notice;
}





if (isset($nummessages) AND $nummessages > 0)
	$infomessages[] = '

<div class="container mt-3">
  <div class="alert alert-primary alert-dismissible fade show">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <strong>Primary!</strong><a href="'.$BASEURL.'/admin/index.php?act=staffbox">'.sprintf($lang->header['staffmess'], $nummessages).'</a>
  </div>
</div>';



if (isset($numreports) AND $numreports > 0)
	$infomessages[] = '<a href="'.$BASEURL.'/admin/index.php?act=reports">'.sprintf($lang->header['newreport'], $numreports).'</a>';

if (isset($warnmessages))
{
	echo implode('<br />',$warnmessages);
	unset($warnmessages);
}


if (isset($infomessages))
{
	echo implode('<br />',$infomessages);
	unset($infomessages);
}

?>


</div>


<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 2000;"></div>


  </body>
</html>





