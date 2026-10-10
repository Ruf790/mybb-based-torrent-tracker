<?php

class trackerlanguage
{
    var $path = null;
    var $language = null;

    // Предзаданные свойства для всех языковых ключей
    public $adduser;
    public $announcements;
    public $browse;
    public $checkuser;
    public $clear_ann;
    public $comment;
    public $confirm;
    public $confirmemail;
    public $contact;
    public $contactstaff;
    public $contactus;
    public $cronjobs;
	public $cron;
	public $datahandler_login;
    public $datahandler_pm;
    public $datahandler_post;
    public $datahandler_user;	
    public $delete;
    public $details;
    public $download;
    public $editor;
    public $editpost;
    public $faq;
    public $ff;
    public $findnotconnectable;
    public $finduser;
    public $formats;
    public $forumdisplay;
	public $forum_management;
    public $getrss;
    public $global = [];
    public $header;
    public $index;
    public $invite;
    public $links;
    public $login;
    public $member;
    public $memberlist;
    public $messages;
    public $misc;
    public $modcp;
    public $moderation;
    public $modrules;
    public $modtask;
    public $mybonus;
    public $newreply;
    public $newthread;
    public $ok;
    public $online;
    public $polls;
    public $port_check;
    public $printthread;
    public $private;
    public $quick_editor;
    public $ratethread;
    public $recover;
    public $referrals;
    public $report;
    public $search;
    public $showteam;
    public $showthread;
    public $signup;
    public $stats;
    public $stats2;
    public $syndication;
    public $takeflush;
    public $takewhatever;
    public $timezone;
    public $top_stats;
    public $topten;
    public $transfer;
    public $tsf_forums;
    public $unbaniprequest;
    public $upload;
    public $uploaderform;
    public $user_awaiting_activation;
    public $usercp;
    public $usercpnav;
    public $userdetails;
    public $usersearch;
    public $viewsnatches;
    public $watch_list;
    public $xmlhttp;
	public $claim;
	public $exams;
	public $manage_torrents;
	public $managesettings;
	public $groups;
	public $manage_screenshots;
	public $manage_uploads;
	public $seedbonus_settings;
	public $reports;
	public $edituser;
	public $bonuspoints;
	public $attachments;
	public $manage_polls;
	public $batch_upload;
	public $langcheck;
	public $log;
	public $changemail;
	public $changeusername;
	public $cache2;
	public $maxlogin;
	public $snatched_torrents;
	public $ratio;
	public $cheat_attempts;
	public $inactiveusers;
	public $doubleupload;
	public $staffpanel;
	public $manage_invites;
	public $passkeysearch;
	public $indexmain;
	public $hit_and_run;
	public $manage_avatars;	
    public $mass_reseed;
    public $sendmail;
    public $staffbox;
    public $staffmess;
    public $torrentstats;
    public $traceroute;
    public $usersregstats;
    public $view_error_logs;
    public $viewunbaniprequest;
    public $warned;
    public $viewpeers;
    public $useractivity;
    public $smilies;
    public $recount_rebuild;
    public $massmail;
    public $manage_vip;
	public $downloadadd;
    public $convert_innodb;
    public $cleartable;
    public $country;
    public $fixhash;
    public $ipsearch;
    public $freeleech;
    public $logmails;
    public $logmailserror;
    public $massinvite;
	public $requests_offers;
	public $announcements_forum;
	public $spam;
	
	public $bonuslog;
	
	public $backupdb;
	
	public $execute_sql_query;
	
	public $faqmanage;
	
	public $latest_comments;

    public $takereport;
	
	public $exam_header;
	
	public $task;
	
	public $functions_upload;
	
	
	public $post3;
	
	public $settings_history;
	
	
	


    function set_path($path)
    {
        $this->path = $path;
    }

    function set_language($language = 'english')
    {
        $language = str_replace(array('/', '\\', '..'), '', trim($language));
        if ($language == '') {
            $language = 'english';
        }

        $this->language = $language;
    }

    function load($section)
    {
        global $rootpath;
        $lfile = $this->path . '/' . $this->language . '/' . $section . '.lang.php';
        if (file_exists($lfile)) {
            require_once $lfile;
        } else {
            define('errorid', 3);
            include_once TSDIR . '/error.php';
            exit();
        }

        if ((isset($language) AND is_array($language))) {
            foreach ($language as $key => $val) {
                if ((!isset($this->$key) OR $this->$key != $val)) {
                    $val = preg_replace('#\\{([0-9]+)\\}#', '%$1\\$s', $val);
                    $this->$key = $val;
                }
            }
        }
    }
}
