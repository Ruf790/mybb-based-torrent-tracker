<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// header.php
$language['header'] = array(

    // ── System Messages ───────────────────────────────────────────────────────
    'trackeroffline'             => '<b>WARNING</b>: The tracker is currently offline! Click <a href="{1}/admin/settings.php?action=mainsettings">here</a> to change settings.',
    'newann'                     => 'There is a new announcement since your last visit. Click here to read the Latest Annoucement.',
    'newreport'                  => 'There is {1} new report(s)!',
    'staffmess'                  => 'There is {1} new staff message(s)!',
    'warndonor'                  => 'Your access to the paid subscription "VIP" is about to expire. To renew this subscription you must visit <a href="{1}/donate.php">donate page.</a> If you do not extend your subscription, your "VIP" access will be removed! ({2} left)',
    'warned'                     => 'You are now warned for having a low ratio. You need to get a {1} ratio for your warning be removed.<br />If you don\'t get it in {2}, your account will be banned.',
    'dismiss_notice'             => 'Dismiss this notice',

    // ── Welcome Bar ───────────────────────────────────────────────────────────
    'welcome_newposts'           => 'View New Posts',
    'welcome_todaysposts'        => "View Today's Posts",
    'welcome_pms'                => 'Private Messages',
    'welcome_pms_usage'          => '(Unread {1}, Total {2})',
    'welcome_usercp'             => 'User CP',
    'welcome_modcp'              => 'Mod CP',
    'welcome_admin'              => 'Admin CP',
    'welcome_logout'             => 'Log Out',
    'newmessage'                 => 'You have {1} new private message(s), click here to read.',

    // ── Private Messages ──────────────────────────────────────────────────────
    'newpm_notice_one'           => '<strong>You have one unread private message</strong> from {1} titled <a href="{2}/messages.php?userid={5}&do=showpm&pmid={3}" style="font-weight: bold;">{4}</a>',
    'newpm_notice_multiple'      => '<strong>You have {1} unread private messages.</strong> The most recent is from {2} titled <a href="{3}/messages.php?userid={6}&do=showpm&pmid={4}" style="font-weight: bold;">{5}</a>',

    // ── Announcements ─────────────────────────────────────────────────────────
    'anntitle'                   => 'ANNOUCEMENT TITLE:',
    'anncreated'                 => 'CREATED ON:',
    'annby'                      => 'BY:',
    'annmsg'                     => 'MESSAGE:',
    'annclose'                   => 'Click {1} here</a> clear this announcement.',

    // ── Auth ──────────────────────────────────────────────────────────────────
    'login'                      => 'Login',
    'register'                   => 'Register',
    'recoverpassword'            => 'Recover Password:',
    'viaemail'                   => 'via Email',
    'viaquestion'                => 'via Question',

    // ── Misc ──────────────────────────────────────────────────────────────────
    'donate'                     => 'click here to donate us',
    'thanks'                     => 'thanks',
    'extramembers'               => 'MemberList',
    'extrafriends'               => 'FriendList',
    'extrarssfeed'               => 'RSS Feeds',

    // ── Logout Modal ──────────────────────────────────────────────────────────
    'logout_confirm_text'        => 'Are you sure you want to log out?',
    'btn_cancel'                 => 'Cancel',
    'btn_logout'                 => 'Log Out',
    'btn_go_back'                => 'Go Back',
    'btn_home_page'              => 'Home Page',

    // ── Avatar ────────────────────────────────────────────────────────────────
    'no_avatar'                  => 'No Avatar',

    // ── Ban Page ──────────────────────────────────────────────────────────────
    'ban_important'              => 'Important:',
    'ban_contact_admin'          => 'If you believe this is a mistake, please contact the administrator for assistance.',
    'ban_need_help'              => 'Need help? Contact',
    'ban_support_team'           => 'Support Team',

    // ── Select2 ───────────────────────────────────────────────────────────────
    'select2_match'              => 'One result is available, press enter to select it.',
    'select2_matches'            => '{1} results are available, use up and down arrow keys to navigate.',
    'select2_nomatches'          => 'No matches found',
    'select2_inputtooshort'      => 'Please enter one or more character',
    'select2_inputtooshort_plural' => 'Please enter {1} or more characters',
    'select2_inputtoolong'       => 'Please delete one character',
    'select2_inputtoolong_plural' => 'Please delete {1} characters',
    'select2_toobig'             => 'You can only select one item',
    'select2_toobig_plural'      => 'You can only select {1} items',
    'select2_loadmore'           => 'Loading more results...',
    'select2_searching'          => 'Searching...',

    // ── Navigation: Main ──────────────────────────────────────────────────────
    'nav_browse'                 => 'Browse',
    'nav_browse_torrents'        => 'Browse Torrents',
    'nav_forums'                 => 'Forums',
    'nav_forums_home'            => 'Forums Home',
    'nav_search'                 => 'Search',
    'nav_search_placeholder'     => 'Search torrents...',

    // ── Navigation: Browse Dropdown ───────────────────────────────────────────
    'nav_my_bookmarks'           => 'My Bookmarks',
    'nav_my_reseeds'             => 'My Reseeds',
    'nav_weak_torrents'          => 'Weak Torrents',

    // ── Navigation: User CP Dropdown ──────────────────────────────────────────
    'nav_usercp_home'            => 'User CP Home',
    'nav_private_messages'       => 'Private Messages',
    'nav_your_torrents'          => 'Your Torrents',

    // ── Navigation: Top 10 Dropdown ───────────────────────────────────────────
    'nav_top_users'              => 'Users',
    'nav_top_torrents'           => 'Torrents',
    'nav_top_countries'          => 'Countries',
    'nav_top_peers'              => 'Peers',

    // ── Navigation: Extra Dropdown ────────────────────────────────────────────
    'nav_your_profile'           => 'Your Profile',
    'nav_members_list'           => 'Members List',
    'nav_rss_feeds'              => 'RSS Feeds',
    'nav_invite_friend'          => 'Invite Friend',
    'nav_bonus_points'           => 'Bonus Points',
    'nav_requests'               => 'Requests',
    'nav_offers'                 => 'Offers',

    // ── Navigation: Staff Dropdown ────────────────────────────────────────────
    'nav_staff_team'             => 'Staff Team',
    'nav_contact_staff'          => 'Contact Staff',

    // ── Navigation: Guest ─────────────────────────────────────────────────────
    'nav_faq'                    => 'FAQ',
    'nav_contact'                => 'Contact',

    // ── Navigation: Auth ──────────────────────────────────────────────────────
    'nav_login'                  => 'Login',
    'nav_register'               => 'Register',
    'nav_confirm_logout'         => 'Confirm Logout',
	
	
	// ── Navigation: Help Dropdown ─────────────────────────────────────────────────
    'nav_help'           => 'Help',
    'nav_video_formats'  => 'Video Formats',
    'nav_torrent_links'  => 'Torrent Links',
    'nav_faq'            => 'FAQ',
    'nav_rules'          => 'Rules'
	

);
?>